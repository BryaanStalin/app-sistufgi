<?php

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRutaDetalle;
use App\Models\LabAtencion;
use App\Models\LabAtencionExamen;
use App\Models\LabPaciente;
use App\Models\Menu;
use App\Models\PacienteMock;
use App\Models\ServicioUps;
use App\Services\LaboratorioEstablecimientoService;
use App\Services\LaboratorioStockService;
use App\Services\LaboratorioTmService;
use App\Services\PowerBiResumenService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
});

afterEach(function (): void {
    $this->travelBack();
});

/** @return array{campania: Campania, triaje: CampaniaServicio, examen: CampaniaServicio} */
function laboratorioEjemplo(): array
{
    $campania = Campania::factory()->create(['incluye_laboratorio' => true]);
    $triaje = CampaniaServicio::factory()->create(['campania_id' => $campania->id]);
    $examen = CampaniaServicio::factory()->create([
        'campania_id' => $campania->id,
        'ups_id' => ServicioUps::factory()->state(['TIPO_UPS' => 'L', 'DESCRIPCION_UPS' => 'Perfil Hepático']),
        'cupos' => 20,
        'cupo_base' => 20,
    ]);

    return compact('campania', 'triaje', 'examen');
}

function laboratorioRegistro(CampaniaServicio $fila, string $estado = 'P'): LabAtencionExamen
{
    $paciente = LabPaciente::factory()->create([
        'campania_id' => $fila->campania_id,
        'tm_numero' => LabPaciente::withTrashed()
            ->where('campania_id', $fila->campania_id)
            ->count() + 1,
    ]);
    $atencion = LabAtencion::factory()->create([
        'lab_paciente_id' => $paciente->id,
        'campania_id' => $fila->campania_id,
        'fecha_atencion' => $fila->fecha_servicio,
    ]);

    return LabAtencionExamen::factory()->create([
        'atencion_id' => $atencion->id,
        'lab_paciente_id' => $paciente->id,
        'campania_servicio_id' => $fila->id,
        'estado' => $estado,
    ]);
}

/** @return array<string, mixed> */
function laboratorioDatos(array $ejemplo): array
{
    return array_replace(ofertaCampaniaData($ejemplo['triaje']->ups_id), [
        'fecha_fin' => '2026-10-20',
        'incluye_laboratorio' => 1,
        'laboratorio_generales' => [['ups_id' => $ejemplo['examen']->ups_id, 'cupos' => 20]],
    ]);
}

test('administrator creates a laboratory exam with optional cutoff and EESS processing', function (): void {
    $actor = ofertaActor();
    $this->actingAs($actor)->post(route('oferta-flexible.servicios.store'), [
        'ID_UPS' => 9001,
        'DESCRIPCION_UPS' => 'Perfil Lipídico',
        'TIPO_UPS' => 'L',
        'ACTIVO' => 1,
        'HORA_LIMITE' => '10:30',
        'ENVIA_EESS' => 0,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $examen = ServicioUps::sole();
    expect($examen->TIPO_UPS)->toBe('L');
    expect($examen->HORA_LIMITE)->toStartWith('10:30');
    expect($examen->ENVIA_EESS)->toBeFalse();
    $this->get(route('oferta-flexible.servicios.index'))->assertDontSee('Perfil Lipídico');
    $this->get(route('oferta-flexible.servicios.index', ['tipo' => 'L']))
        ->assertSee('Perfil Lipídico')->assertSee('Laboratorio (examen)');
});

test('catalog validates laboratory cutoff and keeps default EESS processing', function (): void {
    $this->actingAs(ofertaActor());
    $data = ['ID_UPS' => 9001, 'DESCRIPCION_UPS' => 'Urea', 'TIPO_UPS' => 'L', 'ACTIVO' => 1];
    $this->postJson(route('oferta-flexible.servicios.store'), $data + ['HORA_LIMITE' => '25:00'])
        ->assertUnprocessable()->assertJsonValidationErrors('HORA_LIMITE');
    $this->post(route('oferta-flexible.servicios.store'), $data)->assertSessionHasNoErrors();
    expect(ServicioUps::sole()
        ->ENVIA_EESS)
        ->toBeTrue();
});

test('campaign saves laboratory supplies per date and preserves independently customized days', function (): void {
    $this->actingAs(ofertaActor());
    $ups = ServicioUps::factory()->create();
    $examen = ServicioUps::factory()->create(['TIPO_UPS' => 'L']);
    $data = ofertaCampaniaData($ups->id) + [
        'incluye_laboratorio' => 1,
        'laboratorio_generales' => [['ups_id' => $examen->id, 'cupos' => 30]],
    ];
    $this->post(route('oferta-flexible.campanias.store'), $data)->assertSessionHasNoErrors();
    $campania = Campania::sole();
    expect($campania->servicios()
        ->triaje()
        ->count())
        ->toBe(2);
    expect($campania->servicios()
        ->laboratorio()
        ->count())
        ->toBe(2);
    $data['laboratorio_dia'] = [['ups_id' => $examen->id, 'fecha' => '2026-10-21', 'cupos' => 25]];
    $this->put(route('oferta-flexible.campanias.update', $campania), $data)->assertSessionHasNoErrors();
    $data['laboratorio_generales'][0]['cupos'] = 40;
    $this->put(route('oferta-flexible.campanias.update', $campania), $data)->assertSessionHasNoErrors();
    expect($campania->servicios()
        ->laboratorio()
        ->orderBy('fecha_servicio')
        ->pluck('cupos')
        ->all())
        ->toBe([40, 25]);
});

test('daily laboratory mode supports different exams for each date', function (): void {
    $actor = ofertaActor();
    $triaje = ServicioUps::factory()->create();
    $primero = ServicioUps::factory()->create(['TIPO_UPS' => 'L']);
    $segundo = ServicioUps::factory()->create(['TIPO_UPS' => 'L']);
    $data = ofertaCampaniaData($triaje->id);
    $data['mismos_servicios'] = 0;
    $data['servicios_dia'] = [
        ['fecha' => '2026-10-20', 'ups_id' => $triaje->id, 'cupos' => 10],
        ['fecha' => '2026-10-21', 'ups_id' => $triaje->id, 'cupos' => 10],
    ];
    $data['incluye_laboratorio'] = 1;
    $data['laboratorio_dia'] = [
        ['fecha' => '2026-10-20', 'ups_id' => $primero->id, 'cupos' => 30],
        ['fecha' => '2026-10-21', 'ups_id' => $segundo->id, 'cupos' => 40],
    ];
    $this->actingAs($actor)
        ->post(route('oferta-flexible.campanias.store'), $data)
        ->assertSessionHasNoErrors();
    expect(Campania::sole()
        ->servicios()
        ->laboratorio()
        ->pluck('cupos')
        ->all())
        ->toBe([30, 40]);
});

test('campaign rejects mixed categories and missing laboratory supplies', function (string $error): void {
    $this->actingAs(ofertaActor());
    $example = laboratorioEjemplo();
    $data = laboratorioDatos($example);
    if ($error === 'triaje') {
        $data['servicios_generales'][0]['ups_id'] = $example['examen']->ups_id;
    } elseif ($error === 'laboratorio') {
        $data['laboratorio_generales'][0]['ups_id'] = $example['triaje']->ups_id;
    } else {
        $data['laboratorio_generales'] = [];
    }
    $this->putJson(route('oferta-flexible.campanias.update', $example['campania']), $data)->assertUnprocessable();
})->with(['triaje', 'laboratorio', 'faltan']);

test('stock counts active P T N by original day and ignores deleted records and parents', function (): void {
    $example = laboratorioEjemplo();
    laboratorioRegistro($example['examen'], 'P');
    laboratorioRegistro($example['examen'], 'T');
    laboratorioRegistro($example['examen'], 'N');
    laboratorioRegistro($example['examen'])->delete();
    laboratorioRegistro($example['examen'])->atencion->delete();
    laboratorioRegistro($example['examen'])->labPaciente->delete();
    $otraFecha = CampaniaServicio::factory()->create([
        'campania_id' => $example['campania']->id,
        'ups_id' => $example['examen']->ups_id,
        'fecha_servicio' => '2026-10-21',
    ]);
    laboratorioRegistro($otraFecha, 'T');
    $stock = app(LaboratorioStockService::class)->delDia($example['campania']);
    expect($stock)->toHaveCount(1);
    expect($stock[0])->toMatchArray([
        'insumos' => 20, 'registrados' => 2, 'tomadas' => 1, 'no_realizadas' => 1, 'quedan' => 18,
    ]);
    expect(app(LaboratorioStockService::class)->examen($example['campania'], $otraFecha->ups_id, '2026-10-22'))
        ->toBeNull();
});

test('stock rounds the red threshold upwards and alerts at ten or fewer', function (): void {
    $service = app(LaboratorioStockService::class);
    expect($service->calcular(21, 17, 0, 0, 'Urea'))->toMatchArray(['quedan' => 4, 'rojo' => true, 'alerta' => true]);
    expect($service->calcular(21, 16, 0, 0, 'Urea')['rojo'])->toBeFalse();
    expect($service->calcular(100, 89, 0, 0, 'Urea')['alerta'])->toBeFalse();
    expect($service->calcular(100, 90, 0, 0, 'Urea')['mensaje_alerta'])->toBe(
        'NOS ESTAMOS QUEDANDO SIN INSUMOS DE “Urea” ABASTEZCA A TRAVÉS DEL ADMINISTRADOR '.
        'O INDIQUE A LOS PACIENTES QUE ESTA PRUEBA ESTARÁ RESTRINGIDA DENTRO DE POCO',
    );
});

test('stock blocks an exhausted exam with its name', function (): void {
    $example = laboratorioEjemplo();
    $example['examen']->update(['cupos' => 1]);
    laboratorioRegistro($example['examen']);
    expect(fn () => app(LaboratorioStockService::class)->assertDisponible(
        $example['campania'], $example['examen']->ups_id, '2026-10-20',
    ))->toThrow(ValidationException::class, 'No hay insumos de Perfil Hepático');
});

test('laboratory supplies cannot fall below consumption or remove recorded exams including N', function (
    string $operation,
): void {
    $this->actingAs(ofertaActor());
    $example = laboratorioEjemplo();
    laboratorioRegistro($example['examen'], $operation === 'remove' ? 'N' : 'P');
    laboratorioRegistro($example['examen'], 'T');
    $data = laboratorioDatos($example);
    if ($operation === 'quota') {
        $data['laboratorio_generales'][0]['cupos'] = 1;
    } elseif ($operation === 'remove') {
        $otro = ServicioUps::factory()->create(['TIPO_UPS' => 'L']);
        $data['laboratorio_generales'][0]['ups_id'] = $otro->id;
    } else {
        $data['incluye_laboratorio'] = 0;
    }
    $this->putJson(route('oferta-flexible.campanias.update', $example['campania']), $data)->assertUnprocessable();
    expect($example['examen']->fresh()
        ->cupos)
        ->toBe(20);
    expect($example['campania']->fresh()
        ->incluye_laboratorio)
        ->toBeTrue();
})->with(['quota', 'remove', 'disable']);

test('administrator sees a separate stock table total and an authenticated refresh', function (): void {
    $example = laboratorioEjemplo();
    laboratorioRegistro($example['examen'], 'T');
    $this->actingAs(ofertaActor())->get(route('oferta-flexible.campanias.show', $example['campania']))
        ->assertOk()
        ->assertSee('TOTAL GENERAL')
        ->assertSee('Perfil Hepático')
        ->assertSee('No realizadas');
    $this->getJson(route('oferta-flexible.laboratorio.stock', $example['campania']))
        ->assertOk()
        ->assertJsonStructure(['html', 'generado_en'])
        ->assertSee('TOTAL GENERAL');
    $this->get(route('oferta-flexible.campanias.edit', $example['campania']))
        ->assertOk()->assertSee('Exámenes de laboratorio e insumos');
});

test('TM reuses the patient code grows past 999 and rolls the counter back with its transaction', function (): void {
    $actor = ofertaActor();
    $campania = Campania::factory()->create(['incluye_laboratorio' => true]);
    $service = app(LaboratorioTmService::class);
    $patient = PacienteMock::factory()->create();
    $first = $service->obtenerOGenerar($campania, $patient->id, $actor->id);
    expect($service->obtenerOGenerar($campania, $patient->id, $actor->id)
        ->id)
        ->toBe($first->id);
    expect($service->formatear($first->tm_numero))->toBe('TM-001');
    $first->delete();
    expect($service->obtenerOGenerar($campania, $patient->id, $actor->id)
        ->tm_numero)
        ->toBe(1);
    $campania->lab_ultimo_tm = 999;
    $campania->save();
    $second = $service->obtenerOGenerar($campania, PacienteMock::factory()
        ->create()
        ->id, $actor->id);
    expect($service->formatear($second->tm_numero))->toBe('TM-1000');
    try {
        DB::transaction(function () use ($service, $campania, $actor): void {
            $service->obtenerOGenerar($campania, PacienteMock::factory()
                ->create()
                ->id, $actor->id);
            throw new RuntimeException('Revertir grabado incompleto');
        });
    } catch (RuntimeException) {
    }
    expect($campania->fresh()
        ->lab_ultimo_tm)
        ->toBe(1000);
    expect(LabPaciente::count())->toBe(2);
});

test('TM cannot be generated in a closed campaign or before its first day', function (bool $closed): void {
    $actor = ofertaActor();
    $campaign = Campania::factory()->create([
        'incluye_laboratorio' => true,
        'cerrada' => $closed,
        'fecha_inicio' => $closed ? '2026-10-20' : '2026-10-21',
        'fecha_fin' => '2026-10-22',
    ]);
    expect(fn () => app(LaboratorioTmService::class)->obtenerOGenerar(
        $campaign, PacienteMock::factory()
            ->create()
            ->id, $actor->id,
    ))->toThrow(ValidationException::class);
    expect($campaign->fresh()
        ->lab_ultimo_tm)
        ->toBe(0);
})->with([true, false]);

test('lab users are assigned before campaign start without a laboratory linking interface', function (): void {
    $actor = ofertaActor();
    $campaign = Campania::factory()->create([
        'incluye_laboratorio' => true, 'asociar_triaje_admision' => false,
        'fecha_inicio' => '2026-10-21', 'fecha_fin' => '2026-10-22',
    ]);
    $admission = ofertaActor('Admisión Laboratorio');
    $lab = ofertaActor('Laboratorio');
    $this->actingAs($actor)->post(route('oferta-flexible.usuarios.store', $campaign), [
        'usuarios' => [$admission->id, $lab->id],
    ])->assertSessionHasNoErrors();
    expect($campaign->asignaciones()->count())->toBe(2);
    $this->get(route('oferta-flexible.usuarios.index', ['campania_id' => $campaign->id]))
        ->assertOk()->assertSee('Admisión Laboratorio')->assertSee('Laboratorio')
        ->assertDontSee('enlaces-laboratorio', false);
    $this->deleteJson(route('oferta-flexible.usuarios.destroy', [
        $campaign, $campaign->asignaciones()->where('usuario_id', $lab->id)->first(),
    ]))->assertOk();
});

test('retired laboratory link routes no longer exist', function (): void {
    $this->actingAs(ofertaActor());
    $this->postJson('/oferta-flexible/campanias/1/enlaces-laboratorio', [])->assertNotFound();
    $this->deleteJson('/oferta-flexible/campanias/1/enlaces-laboratorio/1')->assertNotFound();
});

test('laboratory assignments require the feature and active unambiguous roles', function (): void {
    $this->actingAs(ofertaActor());
    $campaign = Campania::factory()->create();
    $lab = ofertaActor('Laboratorio');
    $this->postJson(route('oferta-flexible.usuarios.store', $campaign), ['usuarios' => [$lab->id]])
        ->assertUnprocessable();
    expect($campaign->asignaciones()
        ->count())
        ->toBe(0);
});

test('an N exam can be requested again but two consuming requests cannot coexist', function (): void {
    $example = laboratorioEjemplo();
    $old = laboratorioRegistro($example['examen'], 'N');
    $new = $old->replicate();
    $new->estado = 'P';
    $new->save();
    expect(LabAtencionExamen::count())->toBe(2);
    expect(fn () => $new->replicate()
        ->save())
        ->toThrow(UniqueConstraintViolationException::class);
});

test('EESS configuration keeps codes as strings and resolves the official catalog name', function (): void {
    DB::table('TB_EESS')->insert(['RENIPRESS' => '5772', 'NOM_EESS' => 'NOMBRE OFICIAL', 'ACTIVO' => 1]);
    $list = app(LaboratorioEstablecimientoService::class)->disponibles();
    expect($list)->toHaveCount(9);
    expect($list[0])->toBe(['renipress' => '5772', 'nombre' => 'NOMBRE OFICIAL']);
});

test('laboratory rows do not alter Power BI aggregates or configured clinical services', function (): void {
    $example = laboratorioEjemplo();
    $hoja = ofertaHoja($example['triaje']);
    laboratorioRegistro($example['examen'], 'T');
    HojaRutaDetalle::factory()->create([
        'hoja_ruta_id' => $hoja->id, 'campania_servicio_id' => $example['examen']->id,
    ]);
    $service = app(PowerBiResumenService::class);
    expect($service->resumen((string) $example['campania']->id))->toMatchArray(['atendidos' => 1, 'atenciones' => 1]);
    expect($service->servicios((string) $example['campania']->id))->toHaveCount(1);
    expect($service->servicios((string) $example['campania']->id)[0]['ups_id'])->toBe($example['triaje']->ups_id);
});

test('catalog refuses deactivation and category conversion for a recorded laboratory exam', function (): void {
    $example = laboratorioEjemplo();
    laboratorioRegistro($example['examen'], 'N')->delete();
    $service = $example['examen']->servicioUps;
    $this->actingAs(ofertaActor())->deleteJson(route('oferta-flexible.servicios.destroy', $service))
        ->assertUnprocessable();
    $this->putJson(route('oferta-flexible.servicios.update', $service), [
        'ID_UPS' => $service->ID_UPS, 'DESCRIPCION_UPS' => $service->DESCRIPCION_UPS,
        'TIPO_UPS' => 'P', 'ACTIVO' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('TIPO_UPS');
    expect($service->fresh()
        ->ACTIVO)
        ->toBeTrue();
});

test('campaign closure blocks laboratory edits links and supply changes', function (): void {
    $this->actingAs(ofertaActor());
    $example = laboratorioEjemplo();
    $example['campania']->update(['cerrada' => true]);
    $this->putJson(route('oferta-flexible.campanias.update', $example['campania']), laboratorioDatos($example))
        ->assertUnprocessable();
    $lab = ofertaActor('Laboratorio');
    $this->postJson(route('oferta-flexible.usuarios.store', $example['campania']), ['usuarios' => [$lab->id]])
        ->assertUnprocessable();
});

test('future active campaigns allow laboratory supplies while finalized ones retain clinical editing', function (): void {
    $this->actingAs(ofertaActor());
    $example = laboratorioEjemplo();
    $data = laboratorioDatos($example);
    $data['fecha_inicio'] = '2026-10-21';
    $data['fecha_fin'] = '2026-10-22';
    $data['laboratorio_generales'][0]['cupos'] = 30;
    $this->put(route('oferta-flexible.campanias.update', $example['campania']), $data)->assertSessionHasNoErrors();
    expect($example['campania']->fresh()
        ->estado)
        ->toBe('Activa');
    expect($example['campania']->servicios()
        ->laboratorio()
        ->pluck('cupos')
        ->all())
        ->toBe([30, 30]);
    $this->travelTo(Carbon::parse('2026-10-23 12:00', 'America/Lima'));
    $data['servicios_generales'][0]['cupos'] = 50;
    $this->put(route('oferta-flexible.campanias.update', $example['campania']), $data)->assertSessionHasNoErrors();
    $data['laboratorio_generales'][0]['cupos'] = 35;
    $this->putJson(route('oferta-flexible.campanias.update', $example['campania']), $data)->assertUnprocessable();
});

test('removing a user with laboratory history is blocked even after deleting that history', function (): void {
    $example = laboratorioEjemplo();
    $lab = ofertaActor('Laboratorio');
    $assignment = CampaniaUsuario::factory()->create([
        'campania_id' => $example['campania']->id, 'usuario_id' => $lab->id, 'tipo' => 'L',
    ]);
    $record = laboratorioRegistro($example['examen'], 'T');
    $record->tomado_by = $lab->id;
    $record->save();
    $record->delete();
    $this->actingAs(ofertaActor())->deleteJson(route('oferta-flexible.usuarios.destroy', [
        $example['campania'], $assignment,
    ]))->assertUnprocessable();
    expect($assignment->fresh()
        ->trashed())
        ->toBeFalse();
});

test('laboratory maintenance remains restricted to administrators despite a granted menu', function (): void {
    $example = laboratorioEjemplo();
    $this->getJson(route('oferta-flexible.laboratorio.stock', $example['campania']))
        ->assertRedirect(route('login'));
    $user = ofertaActor('Laboratorio');
    $menu = Menu::create([
        'titulo' => 'Permiso de prueba',
        'link' => '/oferta-flexible/campanias/'.$example['campania']->id.'/laboratorio/stock',
        'orden' => 1, 'status' => 1,
    ]);
    $user->roles->first()
        ->menus()
        ->attach($menu->id, ['status' => 1]);
    $this->actingAs($user)->getJson(route('oferta-flexible.laboratorio.stock', $example['campania']))
        ->assertForbidden();
});

test('laboratory exam history blocks campaign removal and excluding a recorded day', function (): void {
    $this->actingAs(ofertaActor());
    $example = laboratorioEjemplo();
    laboratorioRegistro($example['examen'], 'N')->delete();
    $this->deleteJson(route('oferta-flexible.campanias.destroy', $example['campania']))->assertUnprocessable();
    $data = laboratorioDatos($example);
    $data['fecha_inicio'] = '2026-10-21';
    $data['fecha_fin'] = '2026-10-21';
    $this->putJson(route('oferta-flexible.campanias.update', $example['campania']), $data)->assertUnprocessable();
});

test('laboratory stock ignores deleted campaigns and disabled laboratory features', function (): void {
    $example = laboratorioEjemplo();
    $stock = app(LaboratorioStockService::class);
    expect($stock->delDia($example['campania']))->toHaveCount(1);
    $example['campania']->delete();
    expect($stock->delDia($example['campania']))->toBe([]);
    expect($stock->delDia(Campania::factory()
        ->create()))
        ->toBe([]);
});

test('campaign editors serialize mixed service categories as lists after adding more clinical rows', function (): void {
    $example = laboratorioEjemplo();
    CampaniaServicio::factory()->create(['campania_id' => $example['campania']->id]);
    $response = $this->actingAs(ofertaActor())->get(route('oferta-flexible.campanias.edit', $example['campania']))
        ->assertOk();
    expect(array_is_list($response['diarios']))->toBeTrue();
    expect(array_is_list($response['labDiarios']))->toBeTrue();
    expect($response['diarios'])->toHaveCount(2);
    expect($response['labDiarios'])->toHaveCount(1);
});
