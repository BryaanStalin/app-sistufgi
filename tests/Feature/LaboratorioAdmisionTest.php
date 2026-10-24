<?php

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\LabAtencion;
use App\Models\LabAtencionExamen;
use App\Models\LabPaciente;
use App\Models\Menu;
use App\Models\PacienteMock;
use App\Models\ServicioUps;
use App\Models\User;
use App\Services\LaboratorioAdmisionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
    $this->travelTo(Carbon::parse('2026-10-20 09:00:00', 'America/Lima'));
});

afterEach(function (): void {
    $this->travelBack();
});

function labAdmUrl(): string
{
    return route('oferta-flexible.laboratorio.admision.store');
}

function labAdmActor(string $role = 'Admisión Laboratorio'): User
{
    $actor = ofertaActor($role);
    $menu = Menu::firstOrCreate(['link' => '/oferta-flexible/laboratorio/admision'], [
        'titulo' => 'Admisión Laboratorio', 'orden' => 6, 'status' => true,
    ]);
    $actor->roles->first()
        ->menus()
        ->syncWithoutDetaching([$menu->id => ['status' => true]]);

    return $actor;
}

function labAdmHoja(CampaniaServicio $servicio, int $pacienteId): HojaRuta
{
    $hoja = HojaRuta::factory()->create([
        'campania_id' => $servicio->campania_id, 'paciente_id' => $pacienteId,
        'fecha_atencion' => $servicio->fecha_servicio,
    ]);
    HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $hoja->id, 'campania_servicio_id' => $servicio->id]);

    return $hoja;
}

/** @return array<string, mixed> */
function labAdmEscenario(): array
{
    $actor = labAdmActor();
    $campaign = Campania::factory()->create(['incluye_laboratorio' => true, 'fecha_fin' => '2026-10-21']);
    CampaniaUsuario::factory()->create(['campania_id' => $campaign->id, 'usuario_id' => $actor->id, 'tipo' => 'AL']);
    $clinical = CampaniaServicio::factory()->create(['campania_id' => $campaign->id]);
    $exam = CampaniaServicio::factory()->create([
        'campania_id' => $campaign->id,
        'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L', 'DESCRIPCION_UPS' => 'Perfil Hepático']),
    ]);
    $patient = PacienteMock::factory()->create(['tipo_doc' => 'DNI', 'dni' => '73050308']);
    labAdmHoja($clinical, $patient->id);
    $data = [
        'accion' => 'grabar', 'campania_id' => $campaign->id, 'paciente_id' => $patient->id,
        'fua' => '001-234-567', 'ups_id_solicita' => $clinical->ups_id, 'telefono' => '999999999',
        'examenes' => [$exam->id], 'eliminar' => [],
    ];

    return compact('actor', 'campaign', 'clinical', 'exam', 'patient', 'data');
}

test('lab admission requires login and its own active role even with a granted menu', function (string $role): void {
    $this->get(labAdmUrl())->assertRedirect(route('login'));
    $this->actingAs(labAdmActor($role))
        ->get(labAdmUrl())
        ->assertForbidden();
})->with(['Médico Triaje', 'Admisión', 'Laboratorio']);

test('lab admission shows only assigned campaigns in todays open range', function (): void {
    $s = labAdmEscenario();
    Campania::factory()->create(['nombre' => 'Campaña ajena', 'incluye_laboratorio' => true]);
    $this->actingAs($s['actor'])
        ->get(labAdmUrl())
        ->assertOk()
        ->assertSee($s['campaign']->nombre)->assertDontSee('Campaña ajena')
        ->assertSee('CNV')->assertSee('S/ DOCUMENTO')->assertDontSee('lab-registro-modal');
    $s['campaign']->update(['fecha_inicio' => '2026-10-21']);
    $this->get(labAdmUrl())
        ->assertOk()
        ->assertSee('No hay una campaña de laboratorio activa hoy');
});

test('administrator can use an active laboratory campaign without assignments', function (): void {
    $s = labAdmEscenario();
    $admin = ofertaActor();
    $this->actingAs($admin)
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk()
        ->assertJsonPath('tm', 'TM-001');
    expect(LabAtencion::sole()
        ->created_by)
        ->toBe($admin->id);
    expect(LabAtencionExamen::sole()
        ->created_by)
        ->toBe($admin->id);
});

test('lab admission rejects foreign disabled closed and non current campaigns', function (string $condition): void {
    $s = labAdmEscenario();
    match ($condition) {
        'foreign' => $s['campaign']->asignaciones()->delete(),
        'disabled' => $s['campaign']->update(['incluye_laboratorio' => false]),
        'closed' => $s['campaign']->update(['cerrada' => true]),
        'future' => $s['campaign']->update(['fecha_inicio' => '2026-10-21']),
        'past' => $s['campaign']->update(['fecha_fin' => '2026-10-19']),
    };
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertNotFound();
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
})->with(['foreign', 'disabled', 'closed', 'future', 'past']);

test('patient search accepts all six documents without reserving TM or stock', function (string $type): void {
    $s = labAdmEscenario();
    $s['patient']->update(['tipo_doc' => $type]);
    ofertaConfigurarDniApi();
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), [
        'accion' => 'buscar', 'campania_id' => $s['campaign']->id, 'tipo_doc' => $type, 'dni' => '73050308',
    ])
        ->assertOk()
        ->assertJsonPath('paciente.id', $s['patient']->id)
        ->assertJsonPath('tm', null);
    Http::assertNothingSent();
    expect($s['campaign']->fresh()
        ->lab_ultimo_tm)
        ->toBe(0);
    $this->assertDatabaseCount('TB_LAB_ATENCION_EXAMENES', 0);
})->with(['DNI', 'CE', 'PASS', 'DIE', 'CNV', 'S/ DOCUMENTO']);

test('a missing local DNI never triggers the external API', function (): void {
    $s = labAdmEscenario();
    ofertaConfigurarDniApi();
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), [
        'accion' => 'buscar', 'campania_id' => $s['campaign']->id, 'tipo_doc' => 'DNI', 'dni' => '00000001',
    ])->assertUnprocessable()->assertJsonPath('errors.dni.0', LaboratorioAdmisionService::PACIENTE_NO_ENCONTRADO)
        ->assertJsonMissingPath('paciente');
    Http::assertNothingSent();
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
});

test('lab admission refuses the retired patient registration action', function (): void {
    $s = labAdmEscenario();
    $name = $s['patient']->nombre_completo;
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), [
        'accion' => 'registrar', 'campania_id' => $s['campaign']->id,
        'tipo_doc' => 'DNI', 'dni' => '73050308', 'nombres' => 'OTRO', 'apellidos' => 'NOMBRE',
        'fecha_nacimiento' => '1980-01-01', 'sexo' => 'F',
    ])
        ->assertUnprocessable()->assertJsonValidationErrors('accion');
    expect($s['patient']->fresh()->nombre_completo)->toBe($name);
});

test('invalid document types and malformed DNI never call the API', function (string $type, string $document): void {
    $s = labAdmEscenario();
    ofertaConfigurarDniApi();
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), [
        'accion' => 'buscar', 'campania_id' => $s['campaign']->id, 'tipo_doc' => $type, 'dni' => $document,
    ])->assertUnprocessable();
    Http::assertNothingSent();
})->with([['OTRO', '12345678'], ['DNI', '1234'], ['CE', '123456']]);

test('FUA validation rejects letters slashes missing values and excess length', function (mixed $fua): void {
    $s = labAdmEscenario();
    $s['data']['fua'] = $fua;
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), $s['data'])
        ->assertUnprocessable()->assertJsonValidationErrors('fua');
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
})->with(['ABC', '123/456', '', '123456789012345678901', 123]);

test('cellphone saves one or two normalized numbers without changing identity data', function (
    string $input, string $expected,
): void {
    $s = labAdmEscenario();
    $name = $s['patient']->nombre_completo;
    $s['data']['telefono'] = $input;
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk();
    expect($s['patient']->fresh()
        ->telefono)
        ->toBe($expected);
    expect($s['patient']->fresh()
        ->nombre_completo)
        ->toBe($name);
    expect(LabAtencion::sole()
        ->fua)
        ->toBe('001-234-567');
})->with([
    ['999999999', '999999999'], ['999999999/988888888', '999999999 / 988888888'],
    ['999999999, 988888888', '999999999 / 988888888'], ['999999999 988888888', '999999999 / 988888888'],
]);

test('cellphone validation rejects invalid and third numbers', function (string $phone): void {
    $s = labAdmEscenario();
    $s['data']['telefono'] = $phone;
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), $s['data'])
        ->assertUnprocessable()->assertJsonValidationErrors('telefono');
})->with(['123', '+51999999999', '999999999 / 988888888 / 977777777', '999999999;988888888']);

test('add validates without writing and cutoff is rechecked at actual save time', function (): void {
    $s = labAdmEscenario();
    $s['exam']->servicioUps->update(['HORA_LIMITE' => '10:30:00']);
    $this->actingAs($s['actor']);
    $this->travelTo(Carbon::parse('2026-10-20 10:29:59', 'America/Lima'));
    $this->postJson(labAdmUrl(), array_replace($s['data'], ['accion' => 'validar']))->assertOk();
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
    $this->travelTo(Carbon::parse('2026-10-20 10:30:00', 'America/Lima'));
    $this->postJson(labAdmUrl(), $s['data'])->assertUnprocessable()
        ->assertJsonPath('errors.examenes.0', LaboratorioAdmisionService::HORA_LIMITE);
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
    expect($s['patient']->fresh()
        ->telefono)
        ->toBeNull();
});

test('two stale stock checks cannot consume the last supply twice or reserve duplicate TM', function (): void {
    $s = labAdmEscenario();
    $s['exam']->update(['cupos' => 1]);
    $second = labAdmActor();
    CampaniaUsuario::factory()->create([
        'campania_id' => $s['campaign']->id, 'usuario_id' => $second->id, 'tipo' => 'AL',
    ]);
    $otherData = array_replace($s['data'], ['paciente_id' => PacienteMock::factory()
        ->create()
        ->id]);
    labAdmHoja($s['clinical'], $otherData['paciente_id']);
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), array_replace($s['data'], ['accion' => 'validar']))
        ->assertOk();
    $this->actingAs($second)
        ->postJson(labAdmUrl(), array_replace($otherData, ['accion' => 'validar']))
        ->assertOk();
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk();
    $this->actingAs($second)
        ->postJson(labAdmUrl(), $otherData)
        ->assertUnprocessable()
        ->assertJsonPath('errors.examenes.0', 'No hay insumos de Perfil Hepático');
    expect($s['campaign']->fresh()
        ->lab_ultimo_tm)
        ->toBe(1);
    $this->assertDatabaseCount('TB_LAB_ATENCION_EXAMENES', 1);
});

test('repeat across dates is blocked while a different exam reuses the TM on a second visit', function (): void {
    $s = labAdmEscenario();
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk()
        ->assertJsonPath('tm', 'TM-001');
    $this->travelTo(Carbon::parse('2026-10-21 09:00', 'America/Lima'));
    $clinical = CampaniaServicio::factory()->create([
        'campania_id' => $s['campaign']->id, 'ups_id' => $s['clinical']->ups_id, 'fecha_servicio' => today(),
    ]);
    labAdmHoja($clinical, $s['patient']->id);
    $repeat = CampaniaServicio::factory()->create([
        'campania_id' => $s['campaign']->id, 'ups_id' => $s['exam']->ups_id, 'fecha_servicio' => today(),
    ]);
    $data = array_replace($s['data'], ['examenes' => [$repeat->id]]);
    $this->postJson(labAdmUrl(), $data)->assertUnprocessable()
        ->assertJsonPath('errors.examenes.0', LaboratorioAdmisionService::REPETIDO);
    $newExam = CampaniaServicio::factory()->create([
        'campania_id' => $s['campaign']->id, 'fecha_servicio' => today(),
        'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L']),
    ]);
    $data['examenes'] = [$newExam->id];
    $this->postJson(labAdmUrl(), $data)
        ->assertOk()
        ->assertJsonPath('tm', 'TM-001')
        ->assertJsonPath('historial.1.editable', false);
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 1);
    $this->assertDatabaseCount('TB_LAB_ATENCIONES', 2);
});

test('a performed exam cannot be removed and failed writes preserve stock and phone', function (): void {
    $s = labAdmEscenario();
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk();
    $exam = LabAtencionExamen::sole();
    $exam->update(['estado' => 'T']);
    $data = array_replace($s['data'], ['examenes' => [], 'eliminar' => [$exam->id], 'telefono' => '988888888']);
    $this->postJson(labAdmUrl(), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('eliminar');
    expect($exam->fresh()
        ->trashed())
        ->toBeFalse();
    expect($s['patient']->fresh()
        ->telefono)
        ->toBe('999999999');
});

test('removing only a pending exam soft deletes it and releases its supply', function (): void {
    $s = labAdmEscenario();
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk()
        ->assertJsonPath('stock.0.quedan', 9);
    $exam = LabAtencionExamen::sole();
    $this->postJson(labAdmUrl(), array_replace($s['data'], [
        'examenes' => [], 'eliminar' => [$exam->id],
    ]))
        ->assertOk()
        ->assertJsonPath('stock.0.quedan', 10);
    expect($exam->fresh()
        ->trashed())
        ->toBeTrue();
    expect($exam->fresh()
        ->deleted_by)
        ->toBe($s['actor']->id);
});

test('requesting an N exam again is allowed and preserves its prior record', function (): void {
    $s = labAdmEscenario();
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk();
    LabAtencionExamen::sole()->update(['estado' => 'N']);
    $this->postJson(labAdmUrl(), $s['data'])
        ->assertOk()
        ->assertJsonCount(2, 'historial');
    expect(LabAtencionExamen::count())->toBe(2);
});

test('requesting service honors todays route sheet instead of all campaign services', function (): void {
    $s = labAdmEscenario();
    $other = CampaniaServicio::factory()->create(['campania_id' => $s['campaign']->id]);
    $sheet = HojaRuta::sole();
    $data = array_replace($s['data'], ['ups_id_solicita' => $other->ups_id]);
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $data)
        ->assertUnprocessable();
    $this->postJson(labAdmUrl(), $s['data'])->assertOk();
    expect(LabAtencion::sole()
        ->hoja_ruta_id)
        ->toBe($sheet->id);
});

test('stock refresh is scoped reports ten and the red threshold and omits patient data', function (): void {
    $s = labAdmEscenario();
    $this->actingAs($s['actor'])->getJson(labAdmUrl().'?stock=1&campania_id='.$s['campaign']->id)
        ->assertOk()
        ->assertJsonPath('stock.0.quedan', 10)
        ->assertJsonPath('stock.0.alerta', true)
        ->assertJsonMissingPath('paciente');
    $s['exam']->update(['cupos' => 1]);
    $this->postJson(labAdmUrl(), $s['data'])
        ->assertOk()
        ->assertJsonPath('stock.0.quedan', 0)
        ->assertJsonPath('stock.0.rojo', true);
});

test('new patients cannot be registered from laboratory admission', function (): void {
    $s = labAdmEscenario();
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), [
        'accion' => 'registrar', 'campania_id' => $s['campaign']->id,
        'tipo_doc' => 'CE', 'dni' => 'CE0000123', 'nombres' => '  María   Elena ', 'apellidos' => ' Muñoz   Peña ',
        'fecha_nacimiento' => '1980-01-01', 'sexo' => 'F',
    ])
        ->assertUnprocessable()->assertJsonValidationErrors('accion');
    $this->assertDatabaseMissing('TB_PACIENTES_MOCK', ['tipo_doc' => 'CE', 'dni' => 'CE0000123']);
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
});

test('foreign dates clinical rows and missing stock cannot be submitted as examinations', function (string $kind): void {
    $s = labAdmEscenario();
    $invalid = match ($kind) {
        'clinical' => $s['clinical'],
        'foreign' => CampaniaServicio::factory()->create([
            'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L']),
        ]),
        'tomorrow' => CampaniaServicio::factory()->create([
            'campania_id' => $s['campaign']->id, 'fecha_servicio' => '2026-10-21',
            'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L']),
        ]),
    };
    $s['data']['examenes'] = [$invalid->id];
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertUnprocessable();
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
})->with(['clinical', 'foreign', 'tomorrow']);

test('two admissions cannot repeat the same patient examination after concurrent preflight checks', function (): void {
    $s = labAdmEscenario();
    $second = labAdmActor();
    CampaniaUsuario::factory()->create([
        'campania_id' => $s['campaign']->id, 'usuario_id' => $second->id, 'tipo' => 'AL',
    ]);
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), array_replace($s['data'], ['accion' => 'validar']))
        ->assertOk();
    $this->actingAs($second)
        ->postJson(labAdmUrl(), array_replace($s['data'], ['accion' => 'validar']))
        ->assertOk();
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk();
    $this->actingAs($second)
        ->postJson(labAdmUrl(), $s['data'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.examenes.0', LaboratorioAdmisionService::REPETIDO);
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 1);
    $this->assertDatabaseCount('TB_LAB_ATENCION_EXAMENES', 1);
    expect($s['campaign']->fresh()
        ->lab_ultimo_tm)
        ->toBe(1);
});

test('missing today configuration is listed disabled and stock refresh does not expose patients', function (): void {
    $s = labAdmEscenario();
    $s['exam']->update(['fecha_servicio' => '2026-10-21']);
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), [
        'accion' => 'buscar', 'campania_id' => $s['campaign']->id,
        'tipo_doc' => 'DNI', 'dni' => '73050308',
    ])
        ->assertOk()
        ->assertJsonPath('examenes.0.sin_configuracion', true)
        ->assertJsonPath('examenes.0.disponible', false);
});

test('a duplicate removal from a stale screen is rejected without deleting other examinations', function (): void {
    $s = labAdmEscenario();
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk();
    $id = LabAtencionExamen::sole()->id;
    $payload = array_replace($s['data'], ['examenes' => [], 'eliminar' => [$id]]);
    $this->postJson(labAdmUrl(), $payload)->assertOk();
    $this->postJson(labAdmUrl(), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('eliminar');
});

test('crossing cutoff during a multi examination save rolls every write back including TM', function (): void {
    $s = labAdmEscenario();
    $other = CampaniaServicio::factory()->create([
        'campania_id' => $s['campaign']->id,
        'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L', 'HORA_LIMITE' => '10:30:00']),
    ]);
    $this->travelTo(Carbon::parse('2026-10-20 10:29:59', 'America/Lima'));
    Event::listen('eloquent.created: '.LabAtencionExamen::class, function (): void {
        test()->travelTo(Carbon::parse('2026-10-20 10:30:01', 'America/Lima'));
    });
    $s['data']['examenes'] = [$s['exam']->id, $other->id];
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.examenes.0', LaboratorioAdmisionService::HORA_LIMITE);
    $this->assertDatabaseCount('TB_LAB_ATENCION_EXAMENES', 0);
    $this->assertDatabaseCount('TB_LAB_ATENCIONES', 0);
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
    expect($s['campaign']->fresh()
        ->lab_ultimo_tm)
        ->toBe(0);
    expect($s['patient']->fresh()
        ->telefono)
        ->toBeNull();
});

test('only the authorized patient phone field changes when extra identity fields are forged', function (): void {
    $s = labAdmEscenario();
    $name = $s['patient']->nombre_completo;
    $s['data']['nombres'] = 'CAMBIO PROHIBIDO';
    $s['data']['sexo'] = 'X';
    $this->actingAs($s['actor'])
        ->postJson(labAdmUrl(), $s['data'])
        ->assertOk();
    expect($s['patient']->fresh()
        ->nombre_completo)
        ->toBe($name);
    expect($s['patient']->fresh()
        ->sexo)
        ->not->toBe('X');
});

test('two successful competing admissions receive distinct consecutive TM codes', function (): void {
    $s = labAdmEscenario();
    $s['exam']->update(['cupos' => 2]);
    $second = labAdmActor();
    CampaniaUsuario::factory()->create([
        'campania_id' => $s['campaign']->id, 'usuario_id' => $second->id, 'tipo' => 'AL',
    ]);
    $other = array_replace($s['data'], ['paciente_id' => PacienteMock::factory()->create()->id]);
    labAdmHoja($s['clinical'], $other['paciente_id']);
    foreach ([[$s['actor'], $s['data']], [$second, $other]] as [$user, $data]) {
        $this->actingAs($user)->postJson(labAdmUrl(), array_replace($data, ['accion' => 'validar']))->assertOk();
    }
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
    $this->actingAs($s['actor'])->postJson(labAdmUrl(), $s['data'])
        ->assertOk()->assertJsonPath('tm', 'TM-001');
    $this->actingAs($second)->postJson(labAdmUrl(), $other)
        ->assertOk()->assertJsonPath('tm', 'TM-002')->assertJsonPath('stock.0.quedan', 0);
    expect(LabPaciente::orderBy('tm_numero')->pluck('tm_numero')->all())->toBe([1, 2]);
});
