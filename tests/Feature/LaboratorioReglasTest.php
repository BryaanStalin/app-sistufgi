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
use App\Services\AdmisionService;
use App\Services\CampaniaService;
use App\Services\ConsultaDniApiService;
use App\Services\LaboratorioAdmisionService;
use App\Services\LaboratorioRegistroService;
use App\Services\PacienteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
    $this->travelTo(Carbon::parse('2026-10-20 09:00:00', 'America/Lima'));
});

afterEach(function (): void {
    $this->travelBack();
});

function labQUrl(string $pantalla = 'admision', array $query = []): string
{
    return route('oferta-flexible.laboratorio.'.$pantalla.'.index', $query);
}

function labQActor(string $role = 'Admisión Laboratorio'): User
{
    $actor = ofertaActor($role);
    $screens = match ($role) {
        'Administrador' => ['admision', 'toma-muestra', 'asignar'],
        'Admisión Laboratorio' => ['admision'],
        'Laboratorio' => ['toma-muestra', 'asignar'],
        default => [],
    };
    foreach ($screens as $screen) {
        $menu = Menu::firstOrCreate(['link' => '/oferta-flexible/laboratorio/'.$screen], [
            'titulo' => $screen, 'status' => true,
        ]);
        $actor->roles->first()->menus()->syncWithoutDetaching([$menu->id => ['status' => true]]);
    }

    return $actor;
}

/** @return array<string, mixed> */
function labQEscenario(): array
{
    $actor = labQActor();
    $campaign = Campania::factory()->create(['incluye_laboratorio' => true]);
    CampaniaUsuario::factory()->create([
        'campania_id' => $campaign->id, 'usuario_id' => $actor->id, 'tipo' => 'AL',
    ]);
    $patient = PacienteMock::factory()->create(['tipo_doc' => 'DNI', 'dni' => '73050308']);
    $clinical = CampaniaServicio::factory()->create(['campania_id' => $campaign->id]);
    $sheet = HojaRuta::factory()->create(['campania_id' => $campaign->id, 'paciente_id' => $patient->id]);
    HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $sheet->id, 'campania_servicio_id' => $clinical->id]);
    $exam = CampaniaServicio::factory()->create([
        'campania_id' => $campaign->id,
        'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L']),
    ]);
    $search = ['accion' => 'buscar', 'campania_id' => $campaign->id, 'tipo_doc' => 'DNI', 'dni' => '73050308'];
    $save = [
        'accion' => 'grabar', 'campania_id' => $campaign->id, 'paciente_id' => $patient->id,
        'fua' => '001-123', 'ups_id_solicita' => $clinical->ups_id, 'telefono' => '999999999',
        'examenes' => [$exam->id], 'eliminar' => [],
    ];

    return compact('actor', 'campaign', 'patient', 'clinical', 'sheet', 'exam', 'search', 'save');
}

/** @return array<string, mixed> */
function labQRegistro(array $s, string $estado = 'P', bool $vinculada = true): array
{
    $lab = LabPaciente::factory()->create([
        'campania_id' => $s['campaign']->id, 'paciente_id' => $s['patient']->id, 'tm_numero' => 1,
    ]);
    $attention = LabAtencion::factory()->create([
        'lab_paciente_id' => $lab->id, 'campania_id' => $s['campaign']->id,
        'hoja_ruta_id' => $vinculada ? $s['sheet']->id : null, 'created_by' => $s['actor']->id,
    ]);
    $record = LabAtencionExamen::factory()->create([
        'atencion_id' => $attention->id, 'lab_paciente_id' => $lab->id,
        'campania_servicio_id' => $s['exam']->id, 'ups_id' => $s['exam']->ups_id, 'estado' => $estado,
    ]);
    $delete = [
        'campania_id' => $s['campaign']->id, 'paciente_id' => $s['patient']->id, 'atencion_id' => $attention->id,
    ];

    return compact('lab', 'attention', 'record', 'delete');
}

test('local lookup requires todays sheet without exposing patient details', function (string $case): void {
    $s = labQEscenario();
    if ($case === 'missing') {
        $s['search']['dni'] = '00000001';
    } elseif ($case === 'yesterday') {
        $s['sheet']->update(['fecha_atencion' => '2026-10-19']);
    } elseif ($case === 'deleted') {
        $s['sheet']->delete();
    } else {
        $s['sheet']->update(['campania_id' => Campania::factory()->create()->id]);
    }
    ofertaConfigurarDniApi();
    $message = $case === 'missing'
        ? LaboratorioAdmisionService::PACIENTE_NO_ENCONTRADO : LaboratorioAdmisionService::SIN_HOJA;
    $field = $case === 'missing' ? 'dni' : 'paciente_id';
    $this->actingAs($s['actor'])->postJson(labQUrl(), $s['search'])->assertUnprocessable()
        ->assertJsonPath('errors.'.$field.'.0', $message)->assertJsonMissingPath('paciente');
    Http::assertNothingSent();
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
})->with(['missing', 'yesterday', 'deleted', 'foreign']);

test('laboratory admission accepts all six documents with an existing sheet', function (string $tipo): void {
    $s = labQEscenario();
    $number = $tipo === 'S/ DOCUMENTO' ? 'SD-A1B2C3D4E5F6' : '73050308';
    $s['patient']->update(['tipo_doc' => $tipo, 'dni' => $number]);
    $s['search']['tipo_doc'] = $tipo;
    $s['search']['dni'] = $number;
    $this->actingAs($s['actor'])->postJson(labQUrl(), $s['search'])->assertOk()
        ->assertJsonPath('paciente.dni', $number);
    $this->postJson(labQUrl(), $s['save'])->assertOk();
    expect(LabAtencion::sole()->hoja_ruta_id)->toBe($s['sheet']->id);
    Http::assertNothingSent();
})->with(['DNI', 'CE', 'PASS', 'DIE', 'CNV', 'S/ DOCUMENTO']);

test('old internal patient codes remain searchable in laboratory', function (): void {
    $s = labQEscenario();
    $code = 'SD-'.str_repeat('A', 24);
    $s['patient']->update(['tipo_doc' => 'S/ DOCUMENTO', 'dni' => $code]);
    $s['search']['tipo_doc'] = 'S/ DOCUMENTO';
    $s['search']['dni'] = $code;
    $this->actingAs($s['actor'])->postJson(labQUrl(), $s['search'])->assertOk()
        ->assertJsonPath('paciente.dni', $code);
});

test('requesting service options contain only todays route sheet services', function (): void {
    $s = labQEscenario();
    $extra = CampaniaServicio::factory()->create(['campania_id' => $s['campaign']->id]);
    $this->actingAs($s['actor'])->postJson(labQUrl(), $s['search'])->assertOk()
        ->assertJsonCount(1, 'servicios')->assertJsonPath('servicios.0.id', $s['clinical']->ups_id);
    $s['save']['ups_id_solicita'] = $extra->ups_id;
    $this->postJson(labQUrl(), $s['save'])->assertUnprocessable()->assertJsonValidationErrors('ups_id_solicita');
});

test('a sheet removed after lookup prevents every laboratory write', function (): void {
    $s = labQEscenario();
    $this->actingAs($s['actor'])->postJson(labQUrl(), $s['search'])->assertOk();
    $s['sheet']->delete();
    $this->postJson(labQUrl(), $s['save'])->assertUnprocessable()
        ->assertJsonPath('errors.paciente_id.0', LaboratorioAdmisionService::SIN_HOJA);
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 0);
    $this->assertDatabaseCount('TB_LAB_ATENCIONES', 0);
    expect($s['patient']->fresh()->telefono)->toBeNull();
});

test('lab registry deletion audits all examinations releases stock and retains TM', function (string $role): void {
    $s = labQEscenario();
    $r = labQRegistro($s);
    $notDone = $r['record']->replicate();
    $notDone->estado = 'N';
    $notDone->save();
    $actor = $role === 'Administrador' ? labQActor($role) : $s['actor'];
    $this->actingAs($actor)->deleteJson(labQUrl(), $r['delete'])->assertOk()
        ->assertJsonPath('stock.0.quedan', 10)->assertJsonPath('paciente', null);
    expect($r['attention']->fresh()->trashed())->toBeTrue();
    expect($r['attention']->fresh()->deleted_by)->toBe($actor->id);
    foreach ([$r['record'], $notDone] as $record) {
        expect($record->fresh()->trashed())->toBeTrue();
        expect($record->fresh()->deleted_by)->toBe($actor->id);
    }
    expect($r['lab']->fresh()->trashed())->toBeFalse();
    expect($r['lab']->fresh()->tm_numero)->toBe(1);
    $this->actingAs($s['actor'])->postJson(labQUrl(), $s['save'])->assertOk()->assertJsonPath('tm', 'TM-001');
    $this->assertDatabaseCount('TB_LAB_PACIENTES', 1);
    expect(LabAtencion::query()->sole()->id)->not->toBe($r['attention']->id);
})->with(['Admisión Laboratorio', 'Administrador']);

test('lab registry deletion fails atomically when any examination is realized', function (): void {
    $s = labQEscenario();
    $r = labQRegistro($s, 'T');
    $this->actingAs($s['actor'])->deleteJson(labQUrl(), $r['delete'])->assertUnprocessable()
        ->assertJsonPath('errors.atencion_id.0', 'No se puede eliminar: ya hay muestras tomadas para este paciente.');
    expect($r['attention']->fresh()->trashed())->toBeFalse();
    expect($r['record']->fresh()->trashed())->toBeFalse();
});

test('lab registry deletion rejects unavailable campaigns and yesterday attentions', function (string $case): void {
    $s = labQEscenario();
    $r = labQRegistro($s);
    match ($case) {
        'closed' => $s['campaign']->update(['cerrada' => true]),
        'future' => $s['campaign']->update(['fecha_inicio' => '2026-10-21', 'fecha_fin' => '2026-10-22']),
        'past' => $s['campaign']->update(['fecha_inicio' => '2026-10-18', 'fecha_fin' => '2026-10-19']),
        'yesterday' => $r['attention']->update(['fecha_atencion' => '2026-10-19']),
    };
    $this->actingAs($s['actor'])->deleteJson(labQUrl(), $r['delete'])->assertNotFound();
    expect($r['record']->fresh()->trashed())->toBeFalse();
})->with(['closed', 'future', 'past', 'yesterday']);

test('all active examination states block linked or legacy sheet deletion', function (bool $linked, string $state): void {
    $s = labQEscenario();
    $r = labQRegistro($s, $state, $linked);
    $this->actingAs(labQActor('Administrador'))->deleteJson(route('oferta-flexible.admision.destroy'), [
        'campania_id' => $s['campaign']->id, 'hoja_id' => $s['sheet']->id,
    ])->assertUnprocessable()->assertJsonPath('errors.hoja_id.0', LaboratorioRegistroService::HOJA_BLOQUEADA);
    expect($s['sheet']->fresh()->trashed())->toBeFalse();
    expect($r['record']->fresh()->trashed())->toBeFalse();
})->with([[true, 'P'], [true, 'T'], [true, 'N'], [false, 'P'], [false, 'T'], [false, 'N']]);

test('deleting a laboratory attention permits deleting its route sheet', function (): void {
    $s = labQEscenario();
    $r = labQRegistro($s, 'P', false);
    $this->actingAs($s['actor'])->deleteJson(labQUrl(), $r['delete'])->assertOk();
    $this->actingAs(labQActor('Administrador'))->deleteJson(route('oferta-flexible.admision.destroy'), [
        'campania_id' => $s['campaign']->id, 'hoja_id' => $s['sheet']->id,
    ])->assertOk();
    expect($s['sheet']->fresh()->trashed())->toBeTrue();
});

test('an attention without active examinations does not block sheet deletion', function (): void {
    $s = labQEscenario();
    $r = labQRegistro($s);
    $r['record']->delete();
    $this->actingAs(labQActor('Administrador'))->deleteJson(route('oferta-flexible.admision.destroy'), [
        'campania_id' => $s['campaign']->id, 'hoja_id' => $s['sheet']->id,
    ])->assertOk();
});

test('listing marks laboratory and disables deletion with one lookup per page', function (): void {
    $s = labQEscenario();
    labQRegistro($s, 'N', false);
    $actor = labQActor('Administrador');
    $this->actingAs($actor);
    DB::enableQueryLog();
    $page = app(AdmisionService::class)->listado($s['campaign'], $actor, ['fecha' => '2026-10-20']);
    $queries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'TB_LAB_ATENCIONES'));
    DB::disableQueryLog();
    expect($page->first()->laboratorio)->toBeTrue();
    expect($queries->count())->toBe(1);
    $response = $this->get(route('oferta-flexible.admision.index'))->assertOk()->assertSee('Laboratorio');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//button[contains(@class,"admision-eliminar") and @disabled]')->length)->toBe(2);
});

test('laboratory visibility includes active patients and excludes empty registries', function (string $state): void {
    $s = labQEscenario();
    $r = labQRegistro($s, $state);
    $actor = labQActor('Laboratorio');
    CampaniaUsuario::factory()->create([
        'campania_id' => $s['campaign']->id, 'usuario_id' => $actor->id, 'tipo' => 'L',
    ]);
    $this->actingAs($actor)->postJson(labQUrl('toma-muestra'), $s['search'])->assertOk();
    $page = $this->get(labQUrl('asignar'))->assertOk();
    expect($page->viewData('pacientes')->total())->toBe(1);
    $r['record']->delete();
    $this->postJson(labQUrl('toma-muestra'), $s['search'])->assertNotFound();
    $page = $this->get(labQUrl('asignar'))->assertOk();
    expect($page->viewData('pacientes')->total())->toBe(0);
    expect($r['lab']->fresh()->tm_numero)->toBe(1);
})->with(['P', 'T', 'N']);

test('laboratory screens and AJAX actions enforce roles with 403', function (string $role, string $screen, bool $allowed): void {
    $actor = labQActor($role);
    $this->actingAs($actor);
    $this->get(labQUrl($screen))->assertStatus($allowed ? 200 : 403);
    $this->postJson(labQUrl($screen), [])->assertStatus($allowed ? 422 : 403);
    if ($screen === 'admision') {
        $this->deleteJson(labQUrl($screen), [])->assertStatus($allowed ? 422 : 403);
    } elseif ($screen === 'asignar') {
        $this->get(labQUrl($screen, ['exportar' => 1]))->assertStatus($allowed ? 404 : 403);
    } else {
        $this->getJson(labQUrl($screen, ['stock' => 1]))->assertStatus($allowed ? 404 : 403);
    }
})->with([
    ['Administrador', 'admision', true], ['Administrador', 'toma-muestra', true], ['Administrador', 'asignar', true],
    ['Admisión Laboratorio', 'admision', true], ['Admisión Laboratorio', 'toma-muestra', false],
    ['Admisión Laboratorio', 'asignar', false], ['Laboratorio', 'admision', false],
    ['Laboratorio', 'toma-muestra', true], ['Laboratorio', 'asignar', true],
    ['Médico Triaje', 'admision', false], ['Médico Triaje', 'toma-muestra', false], ['Médico Triaje', 'asignar', false],
    ['Admisión', 'admision', false], ['Admisión', 'toma-muestra', false], ['Admisión', 'asignar', false],
]);

test('new internal codes have twelve hexadecimal characters and retry existing collisions', function (): void {
    $existing = PacienteMock::factory()->create(['tipo_doc' => 'S/ DOCUMENTO', 'dni' => 'SD-AAAAAAAAAAAA']);
    $service = Mockery::mock(PacienteService::class, [app(ConsultaDniApiService::class)])
        ->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('generarCodigoSinDocumento')->twice()->andReturn('SD-AAAAAAAAAAAA', 'SD-BBBBBBBBBBBB');
    $patient = $service->registrar([
        'tipo_doc' => 'S/ DOCUMENTO', 'nombres' => 'ANA', 'apellidos' => 'PRUEBA',
        'fecha_nacimiento' => '1980-01-01', 'sexo' => 'F',
    ]);
    expect($patient['dni'])->toBe('SD-BBBBBBBBBBBB')->toMatch('/^SD-[A-F0-9]{12}$/');
    expect($existing->fresh()->dni)->toBe('SD-AAAAAAAAAAAA');
    $this->assertDatabaseCount('TB_PACIENTES_MOCK', 2);
});

test('new generated internal codes use the shortened format', function (): void {
    $patient = app(PacienteService::class)->registrar([
        'tipo_doc' => 'S/ DOCUMENTO', 'nombres' => 'ANA', 'apellidos' => 'PRUEBA',
        'fecha_nacimiento' => '1980-01-01', 'sexo' => 'F',
    ]);
    expect($patient['dni'])->toMatch('/^SD-[A-F0-9]{12}$/');
});

test('retired link data remains untouched when deleting an otherwise empty campaign', function (): void {
    $actor = labQActor('Administrador');
    $campaign = Campania::factory()->create(['incluye_laboratorio' => true]);
    $admission = ofertaActor('Admisión Laboratorio');
    $lab = ofertaActor('Laboratorio');
    DB::table('TB_CAMPANIA_ENLACES_LAB')->insert([
        'campania_id' => $campaign->id, 'admision_lab_usuario_id' => $admission->id,
        'laboratorio_usuario_id' => $lab->id,
    ]);
    $this->actingAs($actor);
    app(CampaniaService::class)->delete($campaign, $actor->id);
    $this->assertDatabaseHas('TB_CAMPANIA_ENLACES_LAB', ['campania_id' => $campaign->id, 'deleted_at' => null]);
});

test('all six document types remain local in collection and assignment', function (string $type): void {
    $s = labQEscenario();
    $number = $type === 'S/ DOCUMENTO' ? 'SD-A1B2C3D4E5F6' : '73050308';
    $s['patient']->update(['tipo_doc' => $type, 'dni' => $number]);
    labQRegistro($s);
    $actor = labQActor('Laboratorio');
    CampaniaUsuario::factory()->create([
        'campania_id' => $s['campaign']->id, 'usuario_id' => $actor->id, 'tipo' => 'L',
    ]);
    $this->actingAs($actor)->postJson(labQUrl('toma-muestra'), array_replace($s['search'], [
        'tipo_doc' => $type, 'dni' => $number,
    ]))->assertOk()->assertJsonPath('paciente.dni', $number);
    $response = $this->get(labQUrl('asignar', ['q' => $number]))->assertOk();
    expect($response->viewData('pacientes')->total())->toBe(1);
    Http::assertNothingSent();
})->with(['DNI', 'CE', 'PASS', 'DIE', 'CNV', 'S/ DOCUMENTO']);

test('revoked laboratory menu grants return 403', function (string $role, string $screen): void {
    $actor = labQActor($role);
    $actor->roles->first()->menus()->updateExistingPivot(
        Menu::where('link', '/oferta-flexible/laboratorio/'.$screen)->value('id'), ['status' => false],
    );
    $this->actingAs($actor)->get(labQUrl($screen))->assertForbidden();
    $this->postJson(labQUrl($screen), [])->assertForbidden();
})->with([
    ['Admisión Laboratorio', 'admision'], ['Laboratorio', 'toma-muestra'], ['Laboratorio', 'asignar'],
]);

test('forged patient or attention IDs cannot delete another laboratory record', function (string $field): void {
    $s = labQEscenario();
    $r = labQRegistro($s);
    $r['delete'][$field] = 999999;
    $this->actingAs($s['actor'])->deleteJson(labQUrl(), $r['delete'])->assertNotFound();
    expect($r['record']->fresh()->trashed())->toBeFalse();
    expect($r['attention']->fresh()->trashed())->toBeFalse();
})->with(['paciente_id', 'atencion_id']);

test('stale sample collection updates fail after the attention is deleted', function (): void {
    $s = labQEscenario();
    $r = labQRegistro($s);
    $actor = labQActor('Laboratorio');
    CampaniaUsuario::factory()->create([
        'campania_id' => $s['campaign']->id, 'usuario_id' => $actor->id, 'tipo' => 'L',
    ]);
    $this->actingAs($actor)->postJson(labQUrl('toma-muestra'), $s['search'])->assertOk();
    $this->actingAs($s['actor'])->deleteJson(labQUrl(), $r['delete'])->assertOk();
    $this->actingAs($actor)->postJson(labQUrl('toma-muestra'), [
        'accion' => 'grabar', 'campania_id' => $s['campaign']->id, 'lab_paciente_id' => $r['lab']->id,
        'estados' => [['id' => $r['record']->id, 'estado' => 'T', 'estado_original' => 'P']],
    ])->assertNotFound();
    expect($r['record']->fresh()->estado)->toBe('P');
});

test('a sample taken after search prevents a stale registry deletion', function (): void {
    $s = labQEscenario();
    $r = labQRegistro($s);
    $this->actingAs($s['actor'])->postJson(labQUrl(), $s['search'])->assertOk();
    $r['record']->update(['estado' => 'T']);
    $this->deleteJson(labQUrl(), $r['delete'])->assertUnprocessable()
        ->assertJsonPath('errors.atencion_id.0', 'No se puede eliminar: ya hay muestras tomadas para este paciente.');
    expect($r['attention']->fresh()->trashed())->toBeFalse();
});

test('an unmatched legacy attention does not block a different days sheet', function (): void {
    $s = labQEscenario();
    $r = labQRegistro($s, 'P', false);
    $r['attention']->update(['fecha_atencion' => '2026-10-19']);
    $this->actingAs(labQActor('Administrador'))->deleteJson(route('oferta-flexible.admision.destroy'), [
        'campania_id' => $s['campaign']->id, 'hoja_id' => $s['sheet']->id,
    ])->assertOk();
});
