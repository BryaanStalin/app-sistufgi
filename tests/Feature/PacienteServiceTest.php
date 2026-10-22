<?php

use App\Models\Campania;
use App\Models\CampaniaUsuario;
use App\Models\Menu;
use App\Models\PacienteMock;
use App\Models\User;
use App\Services\PacienteService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
    ofertaConfigurarDniApi();
});

afterEach(function (): void {
    $this->travelBack();
});

/** @return array{medico: User, campania: Campania} */
function pacienteDniEscenario(): array
{
    $medico = ofertaActor('Médico Triaje');
    $menu = Menu::create([
        'link' => '/oferta-flexible/triaje',
        'titulo' => 'Campaña de Salud de Especialidad',
        'status' => 1,
    ]);
    $medico->roles
        ->first()
        ->menus()
        ->attach($menu->id, ['status' => 1]);
    $campania = Campania::factory()->create();
    CampaniaUsuario::factory()->create([
        'campania_id' => $campania->id,
        'usuario_id' => $medico->id,
        'tipo' => 'M',
    ]);

    return compact('medico', 'campania');
}

/** @return array<string, mixed> */
function pacienteDniBusqueda(Campania $campania, string $tipo = 'DNI'): array
{
    return [
        'accion' => 'buscar',
        'campania_id' => $campania->id,
        'tipo_doc' => $tipo,
        'dni' => '73050308',
    ];
}

test('DNI lookup reuses the local patient without requesting or updating API data', function (): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    $paciente = PacienteMock::factory()->create(['dni' => '73050308']);
    $original = $paciente->fresh()->getAttributes();
    Http::fake(['https://dni.example.test/*' => Http::response(ofertaRespuestaDni())]);

    $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertOk()
        ->assertJsonPath('paciente.id', $paciente->id)
        ->assertJsonPath('paciente.nombre_completo', $paciente->nombre_completo);

    expect($paciente->fresh()->getAttributes())->toEqual($original);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
    Http::assertNothingSent();
});

test('successful DNI lookup persists normalized patient data outside the HTTP waiting transaction', function (): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    Http::fake(['https://dni.example.test/consulta?numeroDocumento=73050308' => function () {
        expect(DB::transactionLevel())->toBe(0);

        return Http::response(ofertaRespuestaDni());
    }]);

    $response = $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertOk()
        ->assertJsonPath('paciente.nombre_completo', 'JUAN CARLOS PÉREZ GARCÍA')
        ->assertJsonPath('paciente.edad', 46)
        ->assertJsonPath('paciente.sexo', 'M')
        ->assertJsonPath('consulta_dni.estado', 'ENCONTRADO');

    $this->assertDatabaseHas('TB_PACIENTES_MOCK', [
        'dni' => '73050308',
        'nombres' => 'JUAN CARLOS',
        'apellidos' => 'PÉREZ GARCÍA',
        'telefono' => null,
    ]);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
    expect(PacienteMock::first()->fecha_nacimiento->toDateString())->toBe('1980-08-27');
    expect($response->getContent())->not->toContain('credencial-ficticia-privada', 'dni.example.test');
});

test('DNI lookup reuses a patient registered while the external request was pending', function (): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    $ganador = null;
    Http::fake(['https://dni.example.test/consulta?numeroDocumento=73050308' => function () use (&$ganador) {
        $ganador = PacienteMock::factory()->create(['dni' => '73050308', 'nombres' => 'REGISTRO LOCAL']);

        return Http::response(ofertaRespuestaDni());
    }]);

    $response = $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertOk();

    $response->assertJsonPath('paciente.id', $ganador->id)
        ->assertJsonPath('paciente.nombres', 'REGISTRO LOCAL');
    expect($ganador->fresh()->nombres)->toBe('REGISTRO LOCAL');
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
});

test('incomplete DNI data remains unsaved and can be completed through manual registration', function (
    array $datos,
    string $faltante,
): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    Http::fake([
        'https://dni.example.test/consulta?numeroDocumento=73050308' => Http::response(ofertaRespuestaDni($datos)),
    ]);

    $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertOk()
        ->assertJsonPath('paciente', null)
        ->assertJsonPath('consulta_dni.datos.nombres', 'JUAN CARLOS')
        ->assertJsonPath('consulta_dni.datos.apellidos', 'PÉREZ GARCÍA')
        ->assertJsonPath('consulta_dni.faltantes', [$faltante]);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);

    $this->postJson(route('oferta-flexible.triaje.store'), [
        ...pacienteDniBusqueda($campania),
        'accion' => 'registrar',
        'nombres' => "  juan\t Carlos ",
        'apellidos' => '  pérez   garcía ',
        'fecha_nacimiento' => '1980-08-27',
        'sexo' => 'M',
    ])->assertOk()->assertJsonPath('paciente.nombre_completo', 'JUAN CARLOS PÉREZ GARCÍA');
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
    Http::assertSentCount(1);
})->with([
    [['fechaNacimiento' => '31/02/1980'], 'fecha_nacimiento'],
    [['idSexo' => null, 'sexo' => null], 'sexo'],
]);

test('failed and missing DNI responses allow manual registration without exposing credentials', function (
    int $status,
    string $mensaje,
): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    Http::fake(['https://dni.example.test/consulta?numeroDocumento=73050308' => Http::response([
        'data' => [],
        'respuesta' => 'credencial-ficticia-privada https://dni.example.test',
    ], $status)]);

    $response = $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertOk()
        ->assertJsonPath('paciente', null)
        ->assertJsonPath('consulta_dni.mensaje', $mensaje);

    expect($response->getContent())->not->toContain('credencial-ficticia-privada', 'dni.example.test');
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);
})->with([
    [200, 'No se encontró el DNI en la consulta externa; puede registrar al paciente manualmente'],
    [401, 'La consulta de DNI no está disponible en este momento; puede continuar con el registro manual'],
    [500, 'La consulta de DNI no está disponible en este momento; puede continuar con el registro manual'],
]);

test('other document types never call the DNI API', function (string $tipo): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    Http::fake(['https://dni.example.test/*' => Http::response(ofertaRespuestaDni())]);

    $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania, $tipo))
        ->assertOk()
        ->assertJsonPath('paciente', null)
        ->assertJsonPath('consulta_dni', null);

    Http::assertNothingSent();
})->with(['CNV', 'CE', 'S/ DOCUMENTO', 'DIE', 'PASS']);

test('disabled DNI lookup falls back to manual registration with no external requests', function (): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    config(['services.dni_api.enabled' => false]);

    $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertOk()
        ->assertJsonPath('paciente', null)
        ->assertJsonPath('consulta_dni', null);

    Http::assertNothingSent();
});

test('a concurrent unique document violation returns the existing patient unchanged', function (?int $codigo): void {
    $paciente = PacienteMock::factory()->create(['dni' => '73050308']);
    $original = $paciente->fresh()->getAttributes();
    $pdo = new PDOException('Duplicado');
    $pdo->errorInfo = ['23000', $codigo, 'Duplicado'];
    $error = $codigo
        ? new QueryException('sqlsrv', 'insert', [], $pdo)
        : new UniqueConstraintViolationException('sqlite', 'insert', [], $pdo);
    $database = DB::getFacadeRoot();
    DB::partialMock()->shouldReceive('transaction')->once()->andThrow($error);

    try {
        $resultado = app(PacienteService::class)->registrar([
            'tipo_doc' => 'DNI',
            'dni' => '73050308',
            'nombres' => 'NOMBRE DIFERENTE',
            'apellidos' => 'APELLIDO DIFERENTE',
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'F',
        ]);
    } finally {
        DB::swap($database);
    }

    expect($resultado['id'])->toBe($paciente->id);
    expect($paciente->fresh()->getAttributes())->toEqual($original);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
})->with([null, 2601, 2627]);

test('patient registration does not hide database errors unrelated to duplicate documents', function (): void {
    $paciente = PacienteMock::factory()->create(['dni' => '73050308']);
    $pdo = new PDOException('Restricción de datos');
    $pdo->errorInfo = ['23000', 547, 'Restricción de datos'];
    $error = new QueryException('sqlsrv', 'insert', [], $pdo);
    $database = DB::getFacadeRoot();
    DB::partialMock()->shouldReceive('transaction')->once()->andThrow($error);

    try {
        expect(fn () => app(PacienteService::class)->registrar([
            'tipo_doc' => 'DNI',
            'dni' => $paciente->dni,
        ]))->toThrow(QueryException::class);
    } finally {
        DB::swap($database);
    }
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
});

test('the filtered unique index prevents a second patient with the same document', function (): void {
    PacienteMock::factory()->create(['dni' => '73050308']);

    expect(fn () => PacienteMock::factory()->create(['dni' => '73050308']))
        ->toThrow(UniqueConstraintViolationException::class);
    PacienteMock::factory()->create(['dni' => '73050308', 'tipo_doc' => 'CE']);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 2);
});

test('DNI lookup rechecks campaign access after the external request', function (string $cambio): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    Http::fake(['https://dni.example.test/consulta?numeroDocumento=73050308' => function () use ($campania, $cambio) {
        if ($cambio === 'cerrada') {
            $campania->update(['cerrada' => true]);
        } else {
            $campania->asignaciones()->delete();
        }

        return Http::response(ofertaRespuestaDni());
    }]);

    $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertStatus($cambio === 'cerrada' ? 422 : 404);

    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);
})->with(['cerrada', 'sin_asignacion']);

test('DNI lookup rejects invalid input and future campaigns before making HTTP requests', function (): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    Http::fake(['https://dni.example.test/*' => Http::response(ofertaRespuestaDni())]);
    $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), [
            ...pacienteDniBusqueda($campania),
            'dni' => '7305030A',
        ])->assertUnprocessable()->assertJsonValidationErrors('dni');
    $campania->update(['fecha_inicio' => '2026-10-21', 'fecha_fin' => '2026-10-22']);

    $this->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertUnprocessable()->assertJsonValidationErrors('campania_id');

    Http::assertNothingSent();
});

test('manual registration requires separate names and surnames', function (string $campo): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();

    $this->actingAs($medico)
        ->postJson(route('oferta-flexible.triaje.store'), [
            ...pacienteDniBusqueda($campania),
            'accion' => 'registrar',
            'nombres' => 'JUAN',
            'apellidos' => 'PEREZ',
            'fecha_nacimiento' => '1980-08-27',
            'sexo' => 'M',
            $campo => '   ',
        ])->assertUnprocessable()->assertJsonValidationErrors($campo);

    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);
    Http::assertNothingSent();
})->with(['nombres', 'apellidos']);

test('DNI searches are rate limited per user without limiting other document types or actions', function (): void {
    ['medico' => $medico, 'campania' => $campania] = pacienteDniEscenario();
    config(['services.dni_api.enabled' => false]);
    $this->actingAs($medico);
    for ($indice = 0; $indice < 20; $indice++) {
        $this->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))->assertOk();
    }
    $this->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
    $this->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania, 'CE'))->assertOk();
    $this->postJson(route('oferta-flexible.triaje.store'), [
        'accion' => 'consultar',
        'campania_id' => $campania->id,
    ])->assertOk();
    $otro = ofertaActor('Médico Triaje');
    CampaniaUsuario::factory()->create([
        'campania_id' => $campania->id,
        'usuario_id' => $otro->id,
        'tipo' => 'M',
    ]);
    $this->actingAs($otro)
        ->postJson(route('oferta-flexible.triaje.store'), pacienteDniBusqueda($campania))
        ->assertOk();

    Http::assertNothingSent();
});
