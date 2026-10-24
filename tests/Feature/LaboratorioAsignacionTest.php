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
use App\Services\LaboratorioAsignacionExcelService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
    $this->travelTo(Carbon::parse('2026-10-20 11:00:00', 'America/Lima'));
});

afterEach(function (): void {
    $this->travelBack();
});

function labAsignacionUrl(array $query = []): string
{
    return route('oferta-flexible.laboratorio.asignar.index', $query);
}

function labAsignacionActor(string $rol = 'Laboratorio'): User
{
    $actor = ofertaActor($rol);
    $menu = Menu::firstOrCreate(['link' => '/oferta-flexible/laboratorio/asignar'], [
        'titulo' => 'Asignar Laboratorio', 'orden' => 8, 'status' => true,
    ]);
    $actor->roles->first()->menus()->syncWithoutDetaching([$menu->id => ['status' => true]]);

    return $actor;
}

/** @return array<string, mixed> */
function labAsignacionEscenario(): array
{
    $actor = labAsignacionActor();
    $campaign = Campania::factory()->create(['incluye_laboratorio' => true, 'nombre' => 'Campaña Ñ Salud']);
    CampaniaUsuario::factory()->create([
        'campania_id' => $campaign->id, 'usuario_id' => $actor->id, 'tipo' => 'L',
    ]);
    $patient = PacienteMock::factory()->create([
        'tipo_doc' => 'DNI', 'dni' => '73050308', 'nombres' => 'ANA', 'apellidos' => 'MUÑOZ PEREZ',
        'telefono' => '999999999 / 988888888',
    ]);
    $lab = LabPaciente::factory()->create(['campania_id' => $campaign->id, 'paciente_id' => $patient->id]);
    $attention = LabAtencion::factory()->create([
        'campania_id' => $campaign->id, 'lab_paciente_id' => $lab->id,
        'created_by' => ofertaActor('Admisión Laboratorio')->id,
    ]);
    $service = CampaniaServicio::factory()->create([
        'campania_id' => $campaign->id,
        'ups_id' => ServicioUps::factory()->state([
            'TIPO_UPS' => 'L', 'ENVIA_EESS' => true, 'DESCRIPCION_UPS' => 'Hemograma Completo',
        ]),
    ]);
    $exam = LabAtencionExamen::factory()->create([
        'atencion_id' => $attention->id, 'lab_paciente_id' => $lab->id,
        'campania_servicio_id' => $service->id, 'ups_id' => $service->ups_id, 'estado' => 'T',
    ]);
    $data = ['campania_id' => $campaign->id, 'pacientes' => [$lab->id], 'renipress' => '5772'];

    return compact('actor', 'campaign', 'patient', 'lab', 'attention', 'service', 'exam', 'data');
}

function labAsignacionActivar(LabPaciente $lab, CampaniaServicio $service): void
{
    $attention = LabAtencion::factory()->create([
        'campania_id' => $lab->campania_id, 'lab_paciente_id' => $lab->id,
        'fecha_atencion' => $service->fecha_servicio,
    ]);
    LabAtencionExamen::factory()->create([
        'atencion_id' => $attention->id, 'lab_paciente_id' => $lab->id,
        'campania_servicio_id' => $service->id, 'ups_id' => $service->ups_id,
    ]);
}

function labAsignacionLeerExcel(BinaryFileResponse $response): Spreadsheet
{
    $archivo = $response->getFile()->getPathname();
    try {
        return IOFactory::load($archivo);
    } finally {
        if (is_file($archivo)) {
            unlink($archivo);
        }
    }
}

test('assignment and export require login and the laboratory role', function (string $role): void {
    $this->get(labAsignacionUrl())->assertRedirect(route('login'));
    $actor = labAsignacionActor($role);
    $this->actingAs($actor)->get(labAsignacionUrl())->assertForbidden();
    $this->get(labAsignacionUrl(['exportar' => 1]))->assertForbidden();
    $this->postJson(labAsignacionUrl(), [])->assertForbidden();
})->with(['Médico Triaje', 'Admisión', 'Admisión Laboratorio']);

test('assignment shows all registered patients regardless of admission links', function (): void {
    $s = labAsignacionEscenario();
    $this->actingAs($s['actor'])->get(labAsignacionUrl())
        ->assertOk()->assertSee('ANA MUÑOZ PEREZ')->assertSee('TM-001')
        ->assertSee('DNI-73050308')->assertSee('999999999 / 988888888');
});

test('dynamic columns include only configured sendable laboratory exams', function (): void {
    $s = labAsignacionEscenario();
    foreach ([
        ['Orina Tira Reactiva', 'L', false],
        ['Medicina General', 'E', true],
        ['Nuevo Examen', 'L', true],
    ] as [$name, $type, $sends]) {
        CampaniaServicio::factory()->create([
            'campania_id' => $s['campaign']->id,
            'ups_id' => ServicioUps::factory()->state([
                'TIPO_UPS' => $type, 'ENVIA_EESS' => $sends, 'DESCRIPCION_UPS' => $name,
            ]),
        ]);
    }
    $this->actingAs($s['actor'])->get(labAsignacionUrl())
        ->assertOk()->assertSee('Hemograma Completo')->assertSee('Nuevo Examen')
        ->assertDontSee('Orina Tira Reactiva')->assertDontSee('Medicina General');
});

test('checks correspond only to active realized exams', function (string $estado, int $checks): void {
    $s = labAsignacionEscenario();
    $s['exam']->update(['estado' => $estado]);
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk();
    expect(substr_count($response->getContent(), 'bi-check-circle-fill'))->toBe($checks);
})->with([['P', 0], ['N', 0], ['T', 1]]);

test('checks and columns are unique across multiple visit dates', function (): void {
    $s = labAsignacionEscenario();
    $s['campaign']->update(['fecha_inicio' => '2026-10-19']);
    $s['attention']->update(['fecha_atencion' => '2026-10-19']);
    $s['service']->update(['fecha_servicio' => '2026-10-19']);
    CampaniaServicio::factory()->create([
        'campania_id' => $s['campaign']->id, 'ups_id' => $s['service']->ups_id,
    ]);
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk();
    expect($response->viewData('columnas')->count())->toBe(1);
    expect(substr_count($response->getContent(), 'bi-check-circle-fill'))->toBe(1);
});

test('deleted exam parents never produce a realized check', function (string $target): void {
    $s = labAsignacionEscenario();
    $s[$target]->delete();
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk();
    expect(substr_count($response->getContent(), 'bi-check-circle-fill'))->toBe(0);
})->with(['exam', 'attention', 'service']);

test('individual assignment records the server user and time and can be changed', function (): void {
    $s = labAsignacionEscenario();
    $this->actingAs($s['actor'])->postJson(labAsignacionUrl(), $s['data'])
        ->assertOk()->assertJsonPath('cantidad', 1);
    $lab = $s['lab']->fresh();
    expect($lab->eess_renipress)->toBe('5772');
    expect($lab->eess_asignado_by)->toBe($s['actor']->id);
    expect($lab->eess_asignado_at->format('Y-m-d H:i:s'))->toBe('2026-10-20 11:00:00');
    $s['data']['renipress'] = '5755';
    $this->postJson(labAsignacionUrl(), $s['data'])->assertOk();
    expect($s['lab']->fresh()->eess_renipress)->toBe('5755');
    expect($s['exam']->fresh()->estado)->toBe('T');
});

test('bulk assignment updates all selected patients atomically', function (): void {
    $s = labAsignacionEscenario();
    $other = LabPaciente::factory()->create(['campania_id' => $s['campaign']->id, 'tm_numero' => 2]);
    labAsignacionActivar($other, $s['service']);
    $s['data']['pacientes'][] = $other->id;
    $this->actingAs($s['actor'])->postJson(labAsignacionUrl(), $s['data'])
        ->assertOk()->assertJsonPath('cantidad', 2);
    expect($other->fresh()->eess_renipress)->toBe('5772');
    expect($s['lab']->fresh()->eess_renipress)->toBe('5772');
});

test('an invalid bulk member rolls back the entire assignment', function (string $condition): void {
    $s = labAsignacionEscenario();
    $other = LabPaciente::factory()->create();
    if ($condition === 'deleted') {
        $other->update(['campania_id' => $s['campaign']->id, 'tm_numero' => 2]);
        $other->delete();
    }
    $s['data']['pacientes'][] = $other->id;
    $this->actingAs($s['actor'])->postJson(labAsignacionUrl(), $s['data'])
        ->assertUnprocessable()->assertJsonValidationErrors('pacientes');
    expect($s['lab']->fresh()->eess_renipress)->toBeNull();
})->with(['foreign', 'deleted']);

test('only configured RENIPRESS codes are accepted', function (string $codigo): void {
    $s = labAsignacionEscenario();
    $s['data']['renipress'] = $codigo;
    $this->actingAs($s['actor'])->postJson(labAsignacionUrl(), $s['data'])
        ->assertUnprocessable()->assertJsonValidationErrors('renipress');
    expect($s['lab']->fresh()->eess_renipress)->toBeNull();
})->with(['99999', '05772', 'abc', '']);

test('all nine configured destinations are accepted', function (): void {
    $s = labAsignacionEscenario();
    $this->actingAs($s['actor']);
    foreach (['5772', '5755', '5816', '5791', '5821', '5814', '5766', '5820', '5792'] as $codigo) {
        $s['data']['renipress'] = $codigo;
        $this->postJson(labAsignacionUrl(), $s['data'])->assertOk();
        expect($s['lab']->fresh()->eess_renipress)->toBe($codigo);
    }
});

test('assignment remains available before and after campaign dates until closed', function (string $period): void {
    $s = labAsignacionEscenario();
    $s['campaign']->update($period === 'future'
        ? ['fecha_inicio' => '2026-10-21', 'fecha_fin' => '2026-10-22']
        : ['fecha_inicio' => '2026-10-18', 'fecha_fin' => '2026-10-19']);
    $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk()->assertSee('ANA MUÑOZ PEREZ');
    $this->postJson(labAsignacionUrl(), $s['data'])->assertOk();
})->with(['future', 'past']);

test('unavailable campaigns reject listing assignment and export', function (string $condition): void {
    $s = labAsignacionEscenario();
    match ($condition) {
        'closed' => $s['campaign']->update(['cerrada' => true]),
        'disabled' => $s['campaign']->update(['incluye_laboratorio' => false]),
        'deleted' => $s['campaign']->delete(),
        'unassigned' => $s['campaign']->asignaciones()->delete(),
    };
    $this->actingAs($s['actor'])->get(labAsignacionUrl(['campania_id' => $s['campaign']->id]))->assertNotFound();
    $this->postJson(labAsignacionUrl(), $s['data'])->assertNotFound();
    $this->get(labAsignacionUrl(['campania_id' => $s['campaign']->id, 'exportar' => 1]))->assertNotFound();
    expect($s['lab']->fresh()->eess_renipress)->toBeNull();
})->with(['closed', 'disabled', 'deleted', 'unassigned']);

test('administrator can assign without campaign assignments and is the audit author', function (): void {
    $s = labAsignacionEscenario();
    $admin = ofertaActor();
    $s['campaign']->asignaciones()->delete();
    $this->actingAs($admin)->postJson(labAsignacionUrl(), $s['data'])->assertOk();
    expect($s['lab']->fresh()->eess_asignado_by)->toBe($admin->id);
});

test('search matches documents names in any order and TM codes', function (string $query): void {
    $s = labAsignacionEscenario();
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl(['q' => $query]))->assertOk();
    expect($response->viewData('pacientes')->total())->toBe(1);
})->with(['73050308', 'PEREZ ANA', 'MUÑOZ', 'TM-001', '001']);

test('search treats wildcard characters literally', function (): void {
    $s = labAsignacionEscenario();
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl(['q' => '%']))->assertOk();
    expect($response->viewData('pacientes')->total())->toBe(0);
});

test('assignment filters and server pagination limit only the displayed page', function (): void {
    $s = labAsignacionEscenario();
    $s['lab']->update(['eess_renipress' => '5772']);
    foreach (range(2, 28) as $number) {
        $extra = LabPaciente::factory()->create(['campania_id' => $s['campaign']->id, 'tm_numero' => $number]);
        labAsignacionActivar($extra, $s['service']);
    }
    $this->actingAs($s['actor']);
    $assigned = $this->get(labAsignacionUrl(['asignacion' => 'asignados']))->assertOk();
    expect($assigned->viewData('pacientes')->total())->toBe(1);
    $unassigned = $this->get(labAsignacionUrl(['asignacion' => 'sin_asignar', 'page' => 2]))->assertOk();
    expect($unassigned->viewData('pacientes')->total())->toBe(27);
    expect($unassigned->viewData('pacientes')->count())->toBe(2);
    expect($unassigned->viewData('pacientes')->currentPage())->toBe(2);
});

test('AJAX listing preserves filters and falls back from an emptied last page', function (): void {
    $s = labAsignacionEscenario();
    $response = $this->actingAs($s['actor'])->getJson(labAsignacionUrl([
        'campania_id' => $s['campaign']->id, 'tabla' => 1, 'page' => 2,
    ]))->assertOk()->assertJsonPath('pagina', 1);
    expect($response->json('html'))->toContain('ANA MUÑOZ PEREZ');
});

test('Excel exports all filtered rows and keeps layout types checks and safe filename', function (): void {
    $s = labAsignacionEscenario();
    $s['patient']->update(['nombres' => '=1+2', 'telefono' => '099999999']);
    $s['lab']->update(['eess_renipress' => '5772']);
    foreach (range(2, 28) as $number) {
        $extra = LabPaciente::factory()->create([
            'campania_id' => $s['campaign']->id, 'tm_numero' => $number, 'eess_renipress' => '5772',
        ]);
        labAsignacionActivar($extra, $s['service']);
    }
    $unassigned = LabPaciente::factory()->create(['campania_id' => $s['campaign']->id, 'tm_numero' => 29]);
    labAsignacionActivar($unassigned, $s['service']);
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->once()->with('Exportación de asignación de laboratorio', Mockery::on(
        fn (array $context): bool => $context['filas'] === 28 && $context['usuario_id'] === $s['actor']->id,
    ));
    Log::shouldReceive('channel')->with('laboratorio_exportaciones')->once()->andReturn($logger);
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl([
        'exportar' => 1, 'asignacion' => 'asignados', 'page' => 2,
    ]))->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('content-disposition'))
        ->toContain('Laboratorio_Asignacion_Campana_N_Salud_20261020.xlsx');
    $excel = labAsignacionLeerExcel($response->baseResponse);
    try {
        $sheet = $excel->getActiveSheet();
        expect($sheet->getHighestDataRow())->toBe(29);
        expect($sheet->getCell('A1')->getValue())->toBe('N°');
        expect($sheet->getCell('B2')->getValue())->toBe('TM-001');
        expect($sheet->getCell('C2')->getValue())->toBe('DNI-73050308');
        expect($sheet->getCell('D2')->getDataType())->toBe(DataType::TYPE_STRING);
        expect($sheet->getCell('D2')->getValue())->toBe('=1+2 MUÑOZ PEREZ');
        expect($sheet->getCell('E2')->getValue())->toBe('099999999');
        expect($sheet->getCell('F2')->getValue())->toBe('✓');
        expect($sheet->getCell('F3')->getValue())->toBeNull();
        expect($sheet->getCell('G2')->getValue())->toBe('CMI Santa Luzmila II, RENIPRESS: 5772');
        expect($sheet->getFreezePane())->toBe('A2');
        expect($sheet->getStyle('A1')->getFill()->getStartColor()->getRGB())
            ->toBe(LaboratorioAsignacionExcelService::COLOR_CABECERA);
        expect($sheet->getColumnDimension('D')->getWidth())->toBe(38.0);
    } finally {
        $excel->disconnectWorksheets();
    }
});

test('Excel logs redacted search filters without patient content', function (): void {
    $s = labAsignacionEscenario();
    $captured = null;
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->once()->withArgs(function (string $message, array $context) use (&$captured): bool {
        $captured = $context;

        return true;
    });
    Log::shouldReceive('channel')->with('laboratorio_exportaciones')->once()->andReturn($logger);
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl(['exportar' => 1, 'q' => '73050308']))->assertOk();
    labAsignacionLeerExcel($response->baseResponse)->disconnectWorksheets();
    expect($captured['filas'])->toBe(1);
    expect($captured['campania_id'])->toBe($s['campaign']->id);
    expect($captured['filtros']['busqueda_aplicada'])->toBeTrue();
    expect(json_encode($captured))->not->toContain('73050308', 'MUÑOZ', '999999999');
});

test('official EESS names are resolved for the screen and Excel', function (): void {
    $s = labAsignacionEscenario();
    DB::table('TB_EESS')->insert(['RENIPRESS' => '5772', 'NOM_EESS' => 'NOMBRE OFICIAL', 'ACTIVO' => true]);
    $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk()->assertSee('NOMBRE OFICIAL, RENIPRESS: 5772');
});

test('sample collection automatically enables the assignment navigation', function (): void {
    $s = labAsignacionEscenario();
    $menu = Menu::create([
        'titulo' => 'Toma de Muestra', 'link' => '/oferta-flexible/laboratorio/toma-muestra', 'status' => true,
    ]);
    $s['actor']->roles->first()->menus()->attach($menu->id, ['status' => true]);
    $this->actingAs($s['actor'])->get(route('oferta-flexible.laboratorio.toma-muestra.index'))
        ->assertOk()->assertSee('href="'.labAsignacionUrl(['campania_id' => $s['campaign']->id]).'"', false)
        ->assertDontSee('La asignación de laboratorio estará disponible');
});

test('empty export contains only the header and records zero rows', function (): void {
    $s = labAsignacionEscenario();
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['filas'] === 0);
    Log::shouldReceive('channel')->with('laboratorio_exportaciones')->once()->andReturn($logger);
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl(['exportar' => 1, 'q' => 'NO EXISTE']))->assertOk();
    $excel = labAsignacionLeerExcel($response->baseResponse);
    try {
        expect($excel->getActiveSheet()->getHighestDataRow())->toBe(1);
    } finally {
        $excel->disconnectWorksheets();
    }
});

test('administrator can export an unassigned campaign with text safe dynamic headers', function (): void {
    $s = labAsignacionEscenario();
    $s['service']->servicioUps->update(['DESCRIPCION_UPS' => '=1+2']);
    $s['campaign']->asignaciones()->delete();
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->once();
    Log::shouldReceive('channel')->with('laboratorio_exportaciones')->once()->andReturn($logger);
    $response = $this->actingAs(ofertaActor())->get(labAsignacionUrl(['exportar' => 1]))->assertOk();
    $excel = labAsignacionLeerExcel($response->baseResponse);
    try {
        expect($excel->getActiveSheet()->getCell('F1')->getDataType())->toBe(DataType::TYPE_STRING);
        expect($excel->getActiveSheet()->getCell('F1')->getValue())->toBe('=1+2');
    } finally {
        $excel->disconnectWorksheets();
    }
});

test('assignment rejects duplicate patients and client supplied audit values', function (string $condition): void {
    $s = labAsignacionEscenario();
    if ($condition === 'duplicate') {
        $s['data']['pacientes'][] = $s['lab']->id;
    } else {
        $s['data'][$condition] = $condition === 'eess_asignado_by' ? 999 : '2025-01-01';
    }
    $this->actingAs($s['actor'])->postJson(labAsignacionUrl(), $s['data'])->assertUnprocessable();
    expect($s['lab']->fresh()->eess_renipress)->toBeNull();
})->with(['duplicate', 'eess_asignado_by', 'eess_asignado_at']);

test('soft deleted laboratory patients are absent from listing and export', function (): void {
    $s = labAsignacionEscenario();
    $s['lab']->delete();
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk();
    expect($response->viewData('pacientes')->total())->toBe(0);
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['filas'] === 0);
    Log::shouldReceive('channel')->with('laboratorio_exportaciones')->once()->andReturn($logger);
    $file = $this->get(labAsignacionUrl(['exportar' => 1]))->assertOk();
    labAsignacionLeerExcel($file->baseResponse)->disconnectWorksheets();
});

test('an unassigned laboratory user cannot enumerate other campaign patients', function (): void {
    $s = labAsignacionEscenario();
    $actor = labAsignacionActor();
    $this->actingAs($actor)->get(labAsignacionUrl())->assertOk()
        ->assertSee('No hay campañas con laboratorio abiertas para asignar.')
        ->assertDontSee('ANA MUÑOZ PEREZ');
});

test('patient names are escaped in the table and selection attributes', function (): void {
    $s = labAsignacionEscenario();
    $s['patient']->update(['nombres' => '<script>alert(1)</script>']);
    $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('default selection prefers todays campaign while keeping other open campaigns available', function (): void {
    $s = labAsignacionEscenario();
    $previous = Campania::factory()->create([
        'nombre' => 'AAA anterior', 'incluye_laboratorio' => true,
        'fecha_inicio' => '2026-10-18', 'fecha_fin' => '2026-10-19',
    ]);
    CampaniaUsuario::factory()->create([
        'campania_id' => $previous->id, 'usuario_id' => $s['actor']->id, 'tipo' => 'L',
    ]);
    $response = $this->actingAs($s['actor'])->get(labAsignacionUrl())->assertOk();
    expect($response->viewData('seleccionada')->id)->toBe($s['campaign']->id);
    expect($response->viewData('campanias')->count())->toBe(2);
});
