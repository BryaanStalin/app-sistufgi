<?php

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\Menu;
use App\Models\PacienteMock;
use App\Models\ServicioUps;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
});

afterEach(function (): void {
    $this->travelBack();
});

function triajeActor(string $rol = 'Médico Triaje'): User
{
    $medico = ofertaActor($rol);
    $menu = Menu::firstOrCreate(['link' => '/oferta-flexible/triaje'], ['titulo' => 'Campaña de Salud de Especialidad', 'orden' => 1, 'status' => 1]);
    $medico->roles->first()->menus()->syncWithoutDetaching([$menu->id => ['status' => 1]]);

    return $medico;
}

function triajeAsignar(User $medico, Campania $campania): void
{
    CampaniaUsuario::factory()->create(['campania_id' => $campania->id, 'usuario_id' => $medico->id, 'tipo' => 'M']);
}

/** @return array<string, mixed> */
function triajeRegistro(string $tipo = 'DNI'): array
{
    return ['accion' => 'registrar', 'tipo_doc' => $tipo, 'dni' => '01234567',
        'nombres_apellidos' => 'José Muñoz Cárdenas', 'fecha_nacimiento' => '1980-10-20', 'sexo' => 'M', 'telefono' => '999123456'];
}

test('triaje guests are redirected and other roles cannot use menu grants', function (): void {
    $this->get(route('oferta-flexible.triaje.index'))->assertRedirect(route('login'));
    foreach (['Administrador', 'Admisión'] as $rol) {
        $actor = triajeActor($rol);
        $response = $this->actingAs($actor)->get(route('oferta-flexible.triaje.index'));
        if ($rol === 'Administrador') {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
        $this->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'consultar', 'campania_id' => 1])->assertForbidden();
    }
});

test('triaje retains exact menu access restrictions', function (): void {
    $medico = ofertaActor('Médico Triaje');

    $this->actingAs($medico)->get(route('oferta-flexible.triaje.index'))->assertRedirect(route('dashboard'));
});

test('triaje has a clear empty state with no active assignments', function (): void {
    $medico = triajeActor();
    Campania::factory()->create();

    $this->actingAs($medico)->get(route('oferta-flexible.triaje.index'))->assertOk()->assertSee('No tiene campañas activas asignadas para hoy.');
});

test('triaje shows only assigned active campaigns and a selector when necessary', function (): void {
    $medico = triajeActor();
    $first = Campania::factory()->create(['nombre' => 'Campaña asignada uno']);
    triajeAsignar($medico, $first);
    $this->actingAs($medico)->get(route('oferta-flexible.triaje.index'))->assertOk()->assertSee($first->nombre)
        ->assertDontSee('id="triaje-campania"', false)->assertSee('data-script="oferta_flexible/triaje"', false);
    $second = Campania::factory()->create(['nombre' => 'Campaña asignada dos']);
    triajeAsignar($medico, $second);
    $foreign = Campania::factory()->create(['nombre' => 'Campaña ajena oculta']);

    $this->get(route('oferta-flexible.triaje.index'))->assertOk()->assertSee('id="triaje-campania"', false)
        ->assertSee($second->nombre)->assertDontSee($foreign->nombre);
    $this->get(route('oferta-flexible.triaje.index', ['campania_id' => $foreign->id]))->assertNotFound();
});

test('triaje rejects unassigned or removed assignments without exposing patient data', function (bool $deleted): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    if ($deleted) {
        $assignment = CampaniaUsuario::factory()->create(['campania_id' => $campania->id, 'usuario_id' => $medico->id, 'tipo' => 'M']);
        $assignment->delete();
    }

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['campania_id' => $campania->id, ...triajeRegistro()])->assertNotFound();
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);
})->with([false, true]);

test('inactive campaigns reject registration and do not appear as active', function (string $state): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create(match ($state) {
        'Futura' => ['fecha_inicio' => '2026-10-21', 'fecha_fin' => '2026-10-22'],
        'Finalizada' => ['fecha_inicio' => '2026-10-18', 'fecha_fin' => '2026-10-19'],
        'Cerrada' => ['cerrada' => true],
    });
    triajeAsignar($medico, $campania);

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['campania_id' => $campania->id, ...triajeRegistro()])
        ->assertUnprocessable()->assertJsonValidationErrors('campania_id');
    $this->get(route('oferta-flexible.triaje.index'))->assertOk()->assertSee('No tiene campañas activas');
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);
})->with(['Futura', 'Finalizada', 'Cerrada']);

test('DNI lookup preserves leading zeros and calculates age', function (): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    triajeAsignar($medico, $campania);
    $paciente = PacienteMock::factory()->create(['tipo_doc' => 'DNI', 'dni' => '01234567', 'nombres_apellidos' => 'Niña Muñoz', 'fecha_nacimiento' => '2016-10-20']);

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'buscar', 'campania_id' => $campania->id, 'tipo_doc' => 'DNI', 'dni' => '01234567'])
        ->assertOk()->assertJsonPath('paciente.id', $paciente->id)->assertJsonPath('paciente.nombres_apellidos', 'Niña Muñoz')->assertJsonPath('paciente.edad', 10);
});

test('document rules are enforced by the backend', function (string $type, string $document, string $message): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    triajeAsignar($medico, $campania);

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'buscar', 'campania_id' => $campania->id, 'tipo_doc' => $type, 'dni' => $document])
        ->assertUnprocessable()->assertJsonPath('errors.dni.0', $message);
})->with([
    ['DNI', '1234567', 'El DNI debe tener exactamente 8 dígitos.'],
    ['DNI', '123456789', 'El DNI debe tener exactamente 8 dígitos.'],
    ['DNI', '1234567A', 'El DNI debe tener exactamente 8 dígitos.'],
    ['CNV', 'ABC123', 'El documento debe tener al menos 7 caracteres.'],
    ['CE', 'ABC123', 'El documento debe tener al menos 7 caracteres.'],
    ['DIE', 'ABC123', 'El documento debe tener al menos 7 caracteres.'],
    ['PASS', 'ABC123', 'El documento debe tener al menos 7 caracteres.'],
]);

test('other document types accept alphanumeric identifiers and unknown documents return no patient', function (string $type): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    triajeAsignar($medico, $campania);

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'buscar', 'campania_id' => $campania->id, 'tipo_doc' => $type, 'dni' => 'AB-12345'])
        ->assertOk()->assertJsonPath('paciente', null);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);
})->with(['CNV', 'CE', 'DIE', 'PASS']);

test('registration only inserts and never overwrites an existing patient', function (): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    triajeAsignar($medico, $campania);
    $payload = ['campania_id' => $campania->id, ...triajeRegistro()];
    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk()->assertJsonPath('paciente.edad', 46);
    $patient = PacienteMock::firstOrFail();
    $payload['nombres_apellidos'] = 'No debe sobrescribir';

    $this->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk()->assertJsonPath('paciente.id', $patient->id);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
    expect($patient->refresh()->nombres_apellidos)->toBe('José Muñoz Cárdenas');
});

test('undocumented registration creates distinct internal identifiers with inserts only', function (): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    triajeAsignar($medico, $campania);
    $updates = [];
    DB::listen(function ($query) use (&$updates): void {
        if (str_contains($query->sql, 'TB_PACIENTES_MOCK') && preg_match('/^\s*update\b/i', $query->sql)) {
            $updates[] = $query->sql;
        }
    });
    $payload = ['campania_id' => $campania->id, ...triajeRegistro('S/ DOCUMENTO')];
    unset($payload['dni']);

    $first = $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk()->json('paciente');
    $second = $this->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk()->json('paciente');
    expect($first['dni'])->toMatch('/^SD-[A-F0-9]{24}$/')->not->toBe($second['dni']);
    expect($updates)->toBe([]);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 2);
});

test('invalid patient details do not insert a record', function (string $field, string $value): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    triajeAsignar($medico, $campania);
    $payload = ['campania_id' => $campania->id, ...triajeRegistro()];
    $payload[$field] = $value;

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 0);
})->with([
    ['fecha_nacimiento', '2026-10-21'], ['sexo', 'X'], ['tipo_doc', 'PASAPORTE'],
    ['nombres_apellidos', ''], ['telefono', str_repeat('9', 151)],
]);

test('context excludes other campaigns dates and inactive UPSS', function (): void {
    $medico = triajeActor();
    $today = CampaniaServicio::factory()->create();
    $campania = $today->campania;
    triajeAsignar($medico, $campania);
    CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'fecha_servicio' => '2026-10-21']);
    CampaniaServicio::factory()->create();
    CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'ups_id' => ServicioUps::factory()->create(['ACTIVO' => false])->id]);

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'consultar', 'campania_id' => $campania->id])->assertOk()
        ->assertJsonCount(1, 'servicios')->assertJsonPath('servicios.0.id', $today->id);
});

test('available quotas count only active details on active route sheets', function (): void {
    $medico = triajeActor();
    $service = CampaniaServicio::factory()->create(['cupos' => 5]);
    $campania = $service->campania;
    triajeAsignar($medico, $campania);
    ofertaHoja($service, $medico);
    ofertaHoja($service, $medico)->delete();
    $withoutDetail = ofertaHoja($service, $medico);
    $withoutDetail->detalles()->first()->delete();

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'consultar', 'campania_id' => $campania->id])
        ->assertOk()->assertJsonPath('servicios.0.disponibles', 4);
});

test('saving creates one daily sheet and repeated saves do not duplicate its details', function (): void {
    $medico = triajeActor();
    $service = CampaniaServicio::factory()->create();
    triajeAsignar($medico, $service->campania);
    $patient = PacienteMock::factory()->create();
    $payload = ['accion' => 'grabar', 'campania_id' => $service->campania_id, 'paciente_id' => $patient->id, 'servicios' => [$service->id]];

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk()->assertJsonPath('hoja.servicios', [$service->id]);
    $this->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk();
    $this->assertDatabaseCount('TB_HOJAS_RUTA', 1);
    $this->assertDatabaseCount('TB_HOJA_RUTA_DETALLE', 1);
    $this->assertDatabaseHas('TB_HOJAS_RUTA', ['paciente_id' => $patient->id, 'medico_usuario_id' => $medico->id, 'created_by' => $medico->id, 'updated_by' => $medico->id, 'fecha_atencion' => '2026-10-20 00:00:00']);
});

test('any assigned physician edits todays sheet and retains its creator while releasing quota', function (): void {
    $creator = triajeActor();
    $editor = triajeActor();
    $first = CampaniaServicio::factory()->create(['cupos' => 1]);
    $campania = $first->campania;
    $second = CampaniaServicio::factory()->create(['campania_id' => $campania->id]);
    triajeAsignar($creator, $campania);
    triajeAsignar($editor, $campania);
    $sheet = ofertaHoja($first, $creator);
    $detail = $sheet->detalles()->firstOrFail();
    $patient = $sheet->paciente;
    $this->actingAs($editor)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'buscar', 'campania_id' => $campania->id, 'tipo_doc' => $patient->tipo_doc, 'dni' => $patient->dni])
        ->assertOk()->assertJsonPath('hoja.id', $sheet->id)->assertJsonPath('hoja.servicios', [$first->id]);

    $this->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'grabar', 'campania_id' => $campania->id, 'paciente_id' => $patient->id, 'servicios' => [$second->id]])
        ->assertOk()->assertJsonPath('hoja.id', $sheet->id)->assertJsonPath('servicios.0.disponibles', 1);
    expect($sheet->refresh()->medico_usuario_id)->toBe($creator->id);
    expect($sheet->updated_by)->toBe($editor->id);
    $this->assertSoftDeleted($detail);
    expect($detail->refresh()->deleted_by)->toBe($editor->id);
});

test('saving rejects a foreign date campaign inactive or repeated service', function (string $invalid): void {
    $medico = triajeActor();
    $service = CampaniaServicio::factory()->create();
    triajeAsignar($medico, $service->campania);
    $patient = PacienteMock::factory()->create();
    $ids = match ($invalid) {
        'other campaign' => [CampaniaServicio::factory()->create()->id],
        'other date' => [CampaniaServicio::factory()->create(['campania_id' => $service->campania_id, 'fecha_servicio' => '2026-10-21'])->id],
        'inactive' => [CampaniaServicio::factory()->create(['campania_id' => $service->campania_id, 'ups_id' => ServicioUps::factory()->create(['ACTIVO' => false])->id])->id],
        'duplicate' => [$service->id, $service->id],
        'empty' => [],
    };

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'grabar', 'campania_id' => $service->campania_id, 'paciente_id' => $patient->id, 'servicios' => $ids])->assertUnprocessable();
    $this->assertDatabaseCount('TB_HOJAS_RUTA', 0);
    $this->assertDatabaseCount('TB_HOJA_RUTA_DETALLE', 0);
})->with(['other campaign', 'other date', 'inactive', 'duplicate', 'empty']);

test('competing physicians cannot reuse the final quota and a failed edit keeps existing reservations', function (): void {
    $firstDoctor = triajeActor();
    $secondDoctor = triajeActor();
    $last = CampaniaServicio::factory()->create(['cupos' => 1]);
    $alternative = CampaniaServicio::factory()->create(['campania_id' => $last->campania_id]);
    triajeAsignar($firstDoctor, $last->campania);
    triajeAsignar($secondDoctor, $last->campania);
    $firstPatient = PacienteMock::factory()->create();
    $secondPatient = PacienteMock::factory()->create();
    $payload = ['accion' => 'grabar', 'campania_id' => $last->campania_id, 'paciente_id' => $firstPatient->id, 'servicios' => [$last->id]];
    $this->actingAs($firstDoctor)->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk();
    $payload['paciente_id'] = $secondPatient->id;
    $payload['servicios'] = [$alternative->id];
    $this->actingAs($secondDoctor)->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk();
    $secondSheet = HojaRuta::where('paciente_id', $secondPatient->id)->firstOrFail();
    $payload['servicios'] = [$last->id];

    $this->postJson(route('oferta-flexible.triaje.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('servicios');
    expect($secondSheet->detalles()->pluck('campania_servicio_id')->all())->toBe([$alternative->id]);
    expect(HojaRutaDetalle::where('campania_servicio_id', $last->id)->count())->toBe(1);
    $this->assertDatabaseCount('TB_HOJAS_RUTA', 2);
});

test('the same patient gets a new sheet on a different campaign day', function (): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create(['fecha_fin' => '2026-10-21']);
    triajeAsignar($medico, $campania);
    $first = CampaniaServicio::factory()->create(['campania_id' => $campania->id]);
    $second = CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'fecha_servicio' => '2026-10-21']);
    $patient = PacienteMock::factory()->create();
    $payload = ['accion' => 'grabar', 'campania_id' => $campania->id, 'paciente_id' => $patient->id, 'servicios' => [$first->id]];
    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk();
    $this->travelTo(Carbon::parse('2026-10-21 00:01:00', 'America/Lima'));
    $payload['servicios'] = [$second->id];

    $this->postJson(route('oferta-flexible.triaje.store'), $payload)->assertOk();
    $this->assertDatabaseCount('TB_HOJAS_RUTA', 2);
});

test('campaign closure after loading prevents a stale browser from saving', function (): void {
    $medico = triajeActor();
    $service = CampaniaServicio::factory()->create();
    triajeAsignar($medico, $service->campania);
    $patient = PacienteMock::factory()->create();
    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'consultar', 'campania_id' => $service->campania_id])->assertOk();
    $service->campania->update(['cerrada' => true]);

    $this->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'grabar', 'campania_id' => $service->campania_id, 'paciente_id' => $patient->id, 'servicios' => [$service->id]])->assertUnprocessable();
    $this->assertDatabaseCount('TB_HOJAS_RUTA', 0);
});

test('the client cannot choose creator sheet id or attendance date', function (string $field): void {
    $medico = triajeActor();
    $campania = Campania::factory()->create();
    triajeAsignar($medico, $campania);

    $this->actingAs($medico)->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'consultar', 'campania_id' => $campania->id, $field => '123'])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with(['medico_usuario_id', 'hoja_ruta_id', 'fecha_atencion']);

test('the mobile page exposes labelled controls and card containers without patient data in its initial HTML', function (): void {
    $medico = triajeActor();
    $daily = CampaniaServicio::factory()->create(['cupos' => 1]);
    $daily->servicioUps->update(['DESCRIPCION_UPS' => 'Atención integral de especialidad para niños y niñas con necesidades de seguimiento y evaluación preventiva']);
    $campania = $daily->campania;
    $campania->update(['nombre' => 'Campaña de Salud de Especialidad de Atención Integral para Niños y Niñas']);
    triajeAsignar($medico, $campania);
    $sheet = ofertaHoja($daily, $medico);
    $sheet->paciente->update(['nombres_apellidos' => 'Paciente Ficticia Muñoz Quiñones con Nombres y Apellidos Extensos para Verificación Móvil', 'dni' => '01234567']);
    CampaniaServicio::factory()->create(['campania_id' => $campania->id]);
    $response = $this->actingAs($medico)->get(route('oferta-flexible.triaje.index'))->assertOk()
        ->assertDontSee($sheet->paciente->nombres_apellidos)->assertDontSee('<table', false)
        ->assertSee('inputmode="numeric"', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['triaje-tipo', 'triaje-numero', 'triaje-nombres', 'triaje-nacimiento', 'triaje-sexo', 'triaje-telefono', 'triaje-servicio'] as $id) {
        expect($xpath->query('//label[@for="'.$id.'"]')->length)->toBe(1);
    }
    if ($directory = getenv('TRIAJE_PREVIEW_DIR')) {
        file_put_contents($directory.'/index.html', $response->getContent());
        $context = $this->postJson(route('oferta-flexible.triaje.store'), ['accion' => 'consultar', 'campania_id' => $campania->id, 'paciente_id' => $sheet->paciente_id])->assertOk()->json();
        file_put_contents($directory.'/contexto.json', json_encode($context, JSON_UNESCAPED_UNICODE));
    }
});
