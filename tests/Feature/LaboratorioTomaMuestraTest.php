<?php

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\LabAtencion;
use App\Models\LabAtencionExamen;
use App\Models\LabPaciente;
use App\Models\Menu;
use App\Models\PacienteMock;
use App\Models\ServicioUps;
use App\Models\User;
use App\Services\LaboratorioAdmisionService;
use App\Services\LaboratorioStockService;
use App\Services\LaboratorioTomaMuestraService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
    $this->travelTo(Carbon::parse('2026-10-20 09:00:00', 'America/Lima'));
});

afterEach(function (): void {
    $this->travelBack();
});

function labTomaUrl(): string
{
    return route('oferta-flexible.laboratorio.toma-muestra.store');
}

function labTomaActor(string $rol = 'Laboratorio'): User
{
    $actor = ofertaActor($rol);
    $menu = Menu::firstOrCreate(['link' => '/oferta-flexible/laboratorio/toma-muestra'], [
        'titulo' => 'Toma de Muestra', 'orden' => 7, 'status' => true,
    ]);
    $actor->roles->first()
        ->menus()
        ->syncWithoutDetaching([$menu->id => ['status' => true]]);

    return $actor;
}

/** @return array<string, mixed> */
function labTomaEscenario(): array
{
    $actor = labTomaActor();
    $admision = ofertaActor('Admisión Laboratorio');
    $campaign = Campania::factory()->create([
        'incluye_laboratorio' => true, 'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-10-21',
    ]);
    foreach ([$actor->id => 'L', $admision->id => 'AL'] as $id => $tipo) {
        CampaniaUsuario::factory()->create([
            'campania_id' => $campaign->id, 'usuario_id' => $id, 'tipo' => $tipo,
        ]);
    }
    $patient = PacienteMock::factory()->create([
        'tipo_doc' => 'DNI', 'dni' => '73050308', 'telefono' => '999999999 / 988888888',
    ]);
    $lab = LabPaciente::factory()->create([
        'campania_id' => $campaign->id, 'paciente_id' => $patient->id, 'created_by' => $admision->id,
    ]);
    $attention = LabAtencion::factory()->create([
        'campania_id' => $campaign->id, 'lab_paciente_id' => $lab->id, 'created_by' => $admision->id,
    ]);
    $service = CampaniaServicio::factory()->create([
        'campania_id' => $campaign->id, 'cupos' => 10,
        'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L', 'DESCRIPCION_UPS' => 'Perfil Hepático']),
    ]);
    $exam = LabAtencionExamen::factory()->create([
        'atencion_id' => $attention->id, 'lab_paciente_id' => $lab->id,
        'campania_servicio_id' => $service->id, 'ups_id' => $service->ups_id, 'created_by' => $admision->id,
    ]);
    $search = ['accion' => 'buscar', 'campania_id' => $campaign->id, 'tipo_doc' => 'DNI', 'dni' => '73050308'];
    $data = [
        'accion' => 'grabar', 'campania_id' => $campaign->id, 'lab_paciente_id' => $lab->id,
        'estados' => [['id' => $exam->id, 'estado' => 'T', 'estado_original' => 'P']],
    ];

    return compact('actor', 'admision', 'campaign', 'patient', 'lab', 'attention', 'service', 'exam',
        'search', 'data');
}

test('sample collection requires login and its own active role despite a menu grant', function (string $rol): void {
    $this->get(labTomaUrl())->assertRedirect(route('login'));
    $actor = labTomaActor($rol);
    $this->actingAs($actor)->get(labTomaUrl())->assertForbidden();
})->with(['Admisión Laboratorio', 'Admisión', 'Médico Triaje']);

test('sample collection renders assigned campaigns today and all six document types', function (): void {
    $s = labTomaEscenario();
    Campania::factory()->create(['incluye_laboratorio' => true, 'nombre' => 'Campaña ajena']);
    $this->actingAs($s['actor'])->get(labTomaUrl())
        ->assertOk()
        ->assertSee($s['campaign']->nombre)
        ->assertDontSee('Campaña ajena')
        ->assertSee('Exámenes del paciente')->assertSee('Asignar Laboratorio')
        ->assertSee('CNV')->assertSee('S/ DOCUMENTO');
    $s['campaign']->update(['fecha_inicio' => '2026-10-21']);
    $this->get(labTomaUrl())->assertOk()->assertSee('No hay una campaña de laboratorio activa hoy');
});

test('sample collection searches only registered patients without calling the DNI API', function (string $tipo): void {
    $s = labTomaEscenario();
    $s['patient']->update(['tipo_doc' => $tipo]);
    $s['search']['tipo_doc'] = $tipo;
    config(['services.dni_api.enabled' => true]);
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])
        ->assertOk()
        ->assertJsonPath('paciente.id', $s['patient']->id)
        ->assertJsonPath('paciente.telefono', '999999999 / 988888888')
        ->assertJsonPath('tm', 'TM-001')
        ->assertJsonPath('examenes.0.editable', true)
        ->assertJsonPath('examenes.0.estado', 'P');
    Http::assertNothingSent();
})->with(['DNI', 'CE', 'PASS', 'DIE', 'CNV', 'S/ DOCUMENTO']);

test('sample lookup rejects unsupported or invalid documents', function (string $tipo, string $dni): void {
    $s = labTomaEscenario();
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), array_replace($s['search'], [
        'tipo_doc' => $tipo, 'dni' => $dni,
    ]))->assertUnprocessable();
    Http::assertNothingSent();
})->with([['OTRO', '12345678'], ['DNI', '1234567'], ['CE', '123456']]);

test('patients registered by any admission are visible without laboratory links', function (): void {
    $s = labTomaEscenario();
    $other = ofertaActor('Admisión Laboratorio');
    $s['attention']->update(['created_by' => $other->id]);
    $s['lab']->update(['created_by' => $other->id]);
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])
        ->assertOk();
    $this->postJson(labTomaUrl(), $s['data'])->assertOk();
    expect($s['exam']->fresh()->estado)->toBe('T');
});

test('another admission creator does not restrict patient visibility', function (): void {
    $s = labTomaEscenario();
    $s['lab']->update(['created_by' => ofertaActor('Admisión Laboratorio')->id]);
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])->assertOk();
});

test('removing the last active examination hides a laboratory patient', function (): void {
    $s = labTomaEscenario();
    $s['exam']->delete();
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])->assertNotFound();
    $this->postJson(labTomaUrl(), $s['data'])->assertNotFound();
});

test('administrator manages all patients without assignments', function (): void {
    $s = labTomaEscenario();
    $admin = ofertaActor();
    $this->actingAs($admin)->postJson(labTomaUrl(), $s['search'])->assertOk();
    $this->postJson(labTomaUrl(), $s['data'])->assertOk();
    expect($s['exam']->fresh()->tomado_by)->toBe($admin->id);
    expect($s['exam']->fresh()->updated_by)->toBe($admin->id);
});

test('state corrections update audit and shared stock', function (string $estado, int $quedan, int $tomadas): void {
    $s = labTomaEscenario();
    $s['exam']->update(['estado' => $estado === 'P' ? 'T' : 'P']);
    $s['data']['estados'][0]['estado_original'] = $s['exam']->estado;
    $s['data']['estados'][0]['estado'] = $estado;
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])
        ->assertOk()->assertJsonPath('examenes.0.estado', $estado)
        ->assertJsonPath('stock.0.quedan', $quedan)->assertJsonPath('stock.0.tomadas', $tomadas);
    $exam = $s['exam']->fresh();
    expect($exam->tomado_by)->toBe($s['actor']->id);
    expect($exam->tomado_at->format('Y-m-d H:i:s'))->toBe('2026-10-20 09:00:00');
    expect($exam->created_by)->toBe($s['admision']->id);
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 1);
    expect($s['patient']->fresh()->telefono)->toBe('999999999 / 988888888');
})->with([['T', 9, 1], ['N', 10, 0], ['P', 9, 0]]);

test('no realizado returns stock and cannot consume it again when exhausted', function (string $estado): void {
    $s = labTomaEscenario();
    $s['service']->update(['cupos' => 1]);
    $s['data']['estados'][0]['estado'] = 'N';
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])
        ->assertOk()->assertJsonPath('stock.0.quedan', 1);
    $otherLab = LabPaciente::factory()->create(['campania_id' => $s['campaign']->id, 'tm_numero' => 2]);
    $other = LabAtencion::factory()->create([
        'campania_id' => $s['campaign']->id, 'lab_paciente_id' => $otherLab->id,
    ]);
    LabAtencionExamen::factory()->create([
        'atencion_id' => $other->id, 'lab_paciente_id' => $other->lab_paciente_id,
        'campania_servicio_id' => $s['service']->id, 'ups_id' => $s['service']->ups_id,
    ]);
    $s['data']['estados'][0] = ['id' => $s['exam']->id, 'estado' => $estado, 'estado_original' => 'N'];
    $this->postJson(labTomaUrl(), $s['data'])->assertUnprocessable()
        ->assertJsonPath('errors.estados.0', 'No hay insumos de Perfil Hepático');
    expect($s['exam']->fresh()->estado)->toBe('N');
})->with(['T', 'P']);

test('pending to realizado is allowed when its already reserved supply leaves zero available', function (): void {
    $s = labTomaEscenario();
    $s['service']->update(['cupos' => 1]);
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])
        ->assertOk()->assertJsonPath('stock.0.quedan', 0)->assertJsonPath('stock.0.tomadas', 1);
});

test('cutoff time warns without blocking a realizado state', function (string $hora, bool $aviso): void {
    $s = labTomaEscenario();
    $s['service']->servicioUps->update(['HORA_LIMITE' => '10:30:00']);
    $this->travelTo(Carbon::parse('2026-10-20 '.$hora, 'America/Lima'));
    $response = $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])->assertOk();
    if ($aviso) {
        $response->assertJsonPath('advertencias.0.mensaje', LaboratorioAdmisionService::HORA_LIMITE);
    } else {
        $response->assertJsonPath('advertencias', []);
    }
    expect($s['exam']->fresh()->estado)->toBe('T');
})->with([['10:29:59', false], ['10:30:00', true], ['11:00:00', true]]);

test('previous visit examinations are visible but readonly and cannot be changed by ID', function (): void {
    $s = labTomaEscenario();
    $s['attention']->update(['fecha_atencion' => '2026-10-19']);
    $s['service']->update(['fecha_servicio' => '2026-10-19']);
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])
        ->assertOk()->assertJsonPath('examenes.0.editable', false);
    $this->postJson(labTomaUrl(), $s['data'])->assertUnprocessable();
    expect($s['exam']->fresh()->estado)->toBe('P');
});

test('sample collection rejects unavailable campaigns', function (string $condition): void {
    $s = labTomaEscenario();
    match ($condition) {
        'closed' => $s['campaign']->update(['cerrada' => true]),
        'future' => $s['campaign']->update(['fecha_inicio' => '2026-10-21']),
        'past' => $s['campaign']->update(['fecha_fin' => '2026-10-19']),
        'deleted' => $s['campaign']->delete(),
        'disabled' => $s['campaign']->update(['incluye_laboratorio' => false]),
        'unassigned' => $s['campaign']->asignaciones()->where('usuario_id', $s['actor']->id)->delete(),
    };
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])->assertNotFound();
    $this->postJson(labTomaUrl(), $s['data'])->assertNotFound();
    expect($s['exam']->fresh()->estado)->toBe('P');
})->with(['closed', 'future', 'past', 'deleted', 'disabled', 'unassigned']);

test('stale concurrent updates cannot overwrite an already changed examination', function (): void {
    $s = labTomaEscenario();
    $second = labTomaActor();
    CampaniaUsuario::factory()->create([
        'campania_id' => $s['campaign']->id, 'usuario_id' => $second->id, 'tipo' => 'L',
    ]);
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])->assertOk();
    $this->actingAs($second)->postJson(labTomaUrl(), $s['search'])->assertOk();
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])->assertOk();
    $s['data']['estados'][0]['estado'] = 'N';
    $this->actingAs($second)->postJson(labTomaUrl(), $s['data'])->assertUnprocessable()
        ->assertJsonPath(
            'errors.estados.0',
            'Otro usuario modificó un examen. Busque nuevamente al paciente antes de guardar.',
        );
    expect($s['exam']->fresh()->estado)->toBe('T');
    expect($s['exam']->fresh()->tomado_by)->toBe($s['actor']->id);
});

test('shared stock counts all users and omits deleted exams', function (): void {
    $s = labTomaEscenario();
    $s['exam']->update(['estado' => 'T', 'tomado_by' => $s['actor']->id]);
    $otherLab = LabPaciente::factory()->create(['campania_id' => $s['campaign']->id, 'tm_numero' => 2]);
    $other = LabAtencion::factory()->create([
        'campania_id' => $s['campaign']->id, 'lab_paciente_id' => $otherLab->id,
    ]);
    $taken = LabAtencionExamen::factory()->create([
        'atencion_id' => $other->id, 'lab_paciente_id' => $other->lab_paciente_id,
        'campania_servicio_id' => $s['service']->id, 'ups_id' => $s['service']->ups_id,
        'estado' => 'T', 'tomado_by' => ofertaActor('Laboratorio')->id,
    ]);
    $this->actingAs($s['actor'])->getJson(labTomaUrl().'?stock=1&campania_id='.$s['campaign']->id)
        ->assertOk()->assertJsonPath('stock.0.tomadas', 2)->assertJsonPath('stock.0.quedan', 8)
        ->assertJsonMissingPath('paciente');
    $taken->delete();
    $stock = app(LaboratorioStockService::class)->delDia($s['campaign']);
    expect($stock[0]['tomadas'])->toBe(1);
    expect($stock[0]['quedan'])->toBe(9);
});

test('a patient without laboratory registration is not created or looked up externally', function (): void {
    $s = labTomaEscenario();
    $s['lab']->delete();
    config(['services.dni_api.enabled' => true]);
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])
        ->assertNotFound()->assertJsonPath('message', LaboratorioTomaMuestraService::NO_ENCONTRADO);
    Http::assertNothingSent();
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 1);
});

test('deleted admission visits do not grant visibility or consume supplies', function (): void {
    $s = labTomaEscenario();
    $s['attention']->delete();
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['search'])->assertNotFound();
    $this->getJson(labTomaUrl().'?stock=1&campania_id='.$s['campaign']->id)
        ->assertOk()->assertJsonPath('stock.0.quedan', 10)->assertJsonPath('stock.0.tomadas', 0);
});

test('reactivation cannot repeat an exam requested again after a no realizado state', function (): void {
    $s = labTomaEscenario();
    $s['exam']->update(['estado' => 'N']);
    $replacement = LabAtencionExamen::factory()->create([
        'atencion_id' => $s['attention']->id, 'lab_paciente_id' => $s['lab']->id,
        'campania_servicio_id' => $s['service']->id, 'ups_id' => $s['service']->ups_id,
    ]);
    $s['data']['estados'][0]['estado_original'] = 'N';
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])->assertUnprocessable()
        ->assertJsonPath('errors.estados.0', LaboratorioAdmisionService::REPETIDO);
    expect($s['exam']->fresh()->estado)->toBe('N');
    expect($replacement->fresh()->estado)->toBe('P');
});

test('releasing and reactivating the same exam in a batch honors final uniqueness and stock', function (): void {
    $s = labTomaEscenario();
    $s['service']->update(['cupos' => 1]);
    $s['exam']->update(['estado' => 'N']);
    $replacement = LabAtencionExamen::factory()->create([
        'atencion_id' => $s['attention']->id, 'lab_paciente_id' => $s['lab']->id,
        'campania_servicio_id' => $s['service']->id, 'ups_id' => $s['service']->ups_id,
    ]);
    $s['data']['estados'] = [
        ['id' => $s['exam']->id, 'estado' => 'T', 'estado_original' => 'N'],
        ['id' => $replacement->id, 'estado' => 'N', 'estado_original' => 'P'],
    ];
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])->assertOk()
        ->assertJsonPath('stock.0.quedan', 0)->assertJsonPath('stock.0.tomadas', 1);
    expect($s['exam']->fresh()->estado)->toBe('T');
    expect($replacement->fresh()->estado)->toBe('N');
});

test('a batch with an invalid exam rolls back all changes and audit data', function (): void {
    $s = labTomaEscenario();
    $previous = LabAtencion::factory()->create([
        'lab_paciente_id' => $s['lab']->id, 'campania_id' => $s['campaign']->id,
        'fecha_atencion' => '2026-10-19',
    ]);
    $oldExam = LabAtencionExamen::factory()->create([
        'atencion_id' => $previous->id, 'lab_paciente_id' => $s['lab']->id,
    ]);
    $s['data']['estados'][] = ['id' => $oldExam->id, 'estado' => 'N', 'estado_original' => 'P'];
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])->assertUnprocessable();
    expect($s['exam']->fresh()->estado)->toBe('P');
    expect($s['exam']->fresh()->tomado_by)->toBeNull();
    expect($oldExam->fresh()->estado)->toBe('P');
});

test('competing reactivations from separate patients cannot consume the same final supply', function (): void {
    $s = labTomaEscenario();
    $s['service']->update(['cupos' => 1]);
    $s['exam']->update(['estado' => 'N']);
    $secondLab = LabPaciente::factory()->create(['campania_id' => $s['campaign']->id, 'tm_numero' => 2]);
    $attention = LabAtencion::factory()->create([
        'lab_paciente_id' => $secondLab->id, 'campania_id' => $s['campaign']->id,
        'created_by' => $s['admision']->id,
    ]);
    $secondExam = LabAtencionExamen::factory()->create([
        'atencion_id' => $attention->id, 'lab_paciente_id' => $secondLab->id,
        'campania_servicio_id' => $s['service']->id, 'ups_id' => $s['service']->ups_id, 'estado' => 'N',
    ]);
    $this->actingAs($s['actor'])->getJson(labTomaUrl().'?stock=1&campania_id='.$s['campaign']->id)
        ->assertOk()->assertJsonPath('stock.0.quedan', 1);
    $s['data']['estados'][0]['estado_original'] = 'N';
    $this->postJson(labTomaUrl(), $s['data'])->assertOk();
    $s['data']['lab_paciente_id'] = $secondLab->id;
    $s['data']['estados'] = [['id' => $secondExam->id, 'estado' => 'T', 'estado_original' => 'N']];
    $this->postJson(labTomaUrl(), $s['data'])->assertUnprocessable()
        ->assertJsonPath('errors.estados.0', 'No hay insumos de Perfil Hepático');
    expect($s['exam']->fresh()->estado)->toBe('T');
    expect($secondExam->fresh()->estado)->toBe('N');
});

test('stock and taken totals preserve the exam registration day across visits', function (): void {
    $s = labTomaEscenario();
    $s['attention']->update(['fecha_atencion' => '2026-10-19']);
    $s['service']->update(['fecha_servicio' => '2026-10-19']);
    $s['exam']->update(['estado' => 'T']);
    CampaniaServicio::factory()->create([
        'campania_id' => $s['campaign']->id, 'ups_id' => $s['service']->ups_id, 'cupos' => 20,
    ]);
    $this->actingAs($s['actor'])->getJson(labTomaUrl().'?stock=1&campania_id='.$s['campaign']->id)
        ->assertOk()->assertJsonPath('stock.0.quedan', 20)->assertJsonPath('stock.0.tomadas', 0);
    $previousStock = app(LaboratorioStockService::class)->tabla($s['campaign'], '2026-10-19');
    expect($previousStock[0]['tomadas'])->toBe(1);
    expect($previousStock[0]['quedan'])->toBe(9);
});

test('manipulated exam identifiers cannot modify another patient even within the same campaign', function (): void {
    $s = labTomaEscenario();
    $otherLab = LabPaciente::factory()->create(['campania_id' => $s['campaign']->id, 'tm_numero' => 2]);
    $otherAttention = LabAtencion::factory()->create([
        'campania_id' => $s['campaign']->id, 'lab_paciente_id' => $otherLab->id,
    ]);
    $otherExam = LabAtencionExamen::factory()->create([
        'atencion_id' => $otherAttention->id, 'lab_paciente_id' => $otherLab->id,
        'campania_servicio_id' => $s['service']->id, 'ups_id' => $s['service']->ups_id,
    ]);
    $s['data']['estados'][0]['id'] = $otherExam->id;
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])->assertUnprocessable();
    expect($otherExam->fresh()->estado)->toBe('P');
});

test('sample collection rejects invalid states and browser audit fields', function (string $field): void {
    $s = labTomaEscenario();
    if ($field === 'estado') {
        $s['data']['estados'][0]['estado'] = 'X';
    } elseif ($field === 'empty') {
        $s['data']['estados'] = [];
    } elseif ($field === 'duplicate') {
        $s['data']['estados'][] = $s['data']['estados'][0];
    } else {
        $s['data'][$field] = $field === 'tomado_by' ? 999 : '2026-01-01';
    }
    $this->actingAs($s['actor'])->postJson(labTomaUrl(), $s['data'])->assertUnprocessable();
    expect($s['exam']->fresh()->estado)->toBe('P');
})->with(['estado', 'empty', 'duplicate', 'tomado_by', 'tomado_at', 'fecha_atencion']);

test('stock endpoint refuses campaigns outside the laboratory users assignments', function (): void {
    $s = labTomaEscenario();
    $other = Campania::factory()->create(['incluye_laboratorio' => true]);
    $this->actingAs($s['actor'])->getJson(labTomaUrl().'?stock=1&campania_id='.$other->id)->assertNotFound();
});
