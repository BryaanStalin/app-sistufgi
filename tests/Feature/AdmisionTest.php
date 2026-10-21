<?php

use App\Models\Campania;
use App\Models\CampaniaEnlace;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\HojaRutaImpresion;
use App\Models\Menu;
use App\Models\User;
use App\Services\AdmisionService;
use App\Services\CampaniaService;
use App\Services\TriajeService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
});

afterEach(function (): void {
    $this->travelBack();
});

function admisionActor(string $rol = 'Admisión'): User
{
    $usuario = ofertaActor($rol);
    $menu = Menu::firstOrCreate(['link' => '/oferta-flexible/admision'], ['titulo' => 'Listado de pacientes', 'orden' => 1, 'status' => 1]);
    $usuario->roles->first()->menus()->syncWithoutDetaching([$menu->id => ['status' => 1]]);

    return $usuario;
}

/** @return array{usuario: User, medico: User, campania: Campania, servicio: CampaniaServicio, hoja: HojaRuta} */
function admisionEscenario(bool $asociar = true): array
{
    $usuario = admisionActor();
    $medico = ofertaActor('Médico Triaje');
    $campania = Campania::factory()->create(['nombre' => 'Campaña de especialidad', 'asociar_triaje_admision' => $asociar]);
    CampaniaUsuario::factory()->create(['campania_id' => $campania->id, 'usuario_id' => $usuario->id, 'tipo' => 'A']);
    CampaniaUsuario::factory()->create(['campania_id' => $campania->id, 'usuario_id' => $medico->id, 'tipo' => 'M']);
    if ($asociar) {
        CampaniaEnlace::factory()->create(['campania_id' => $campania->id, 'medico_usuario_id' => $medico->id, 'admision_usuario_id' => $usuario->id]);
    }
    $servicio = CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'cupos' => 1]);
    $hoja = ofertaHoja($servicio, $medico);

    return compact('usuario', 'medico', 'campania', 'servicio', 'hoja');
}

test('admission guests must sign in for every operation', function (): void {
    $url = route('oferta-flexible.admision.index');
    $this->get($url)->assertRedirect(route('login'));
    $this->post($url)->assertRedirect(route('login'));
    $this->delete($url)->assertRedirect(route('login'));
});

test('admission refuses other roles even with a menu permission', function (string $rol): void {
    $usuario = admisionActor($rol);
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertForbidden();
    $this->postJson(route('oferta-flexible.admision.imprimir'), ['campania_id' => 1, 'hoja_id' => 1])->assertForbidden();
    $this->deleteJson(route('oferta-flexible.admision.destroy'), ['campania_id' => 1, 'hoja_id' => 1])->assertForbidden();
})->with(['Administrador', 'Médico Triaje']);

test('admission requires the exact menu permission', function (): void {
    $this->actingAs(ofertaActor('Admisión'))->get(route('oferta-flexible.admision.index'))->assertRedirect(route('dashboard'));
});

test('admission refuses inactive user role or pivot', function (string $inactive): void {
    $usuario = admisionActor();
    match ($inactive) {
        'usuario' => $usuario->update(['status' => false]),
        'rol' => $usuario->roles->first()->update(['status' => false]),
        'pivote' => $usuario->roles()->updateExistingPivot($usuario->roles->first()->id, ['status' => false]),
    };
    expect(fn () => app(AdmisionService::class)->imprimir($usuario, 1, 1))
        ->toThrow(HttpException::class);
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertRedirect(route('login'));
})->with(['usuario', 'rol', 'pivote']);

test('admission without campaigns sees a clear empty state', function (): void {
    $this->actingAs(admisionActor())->get(route('oferta-flexible.admision.index'))->assertOk()
        ->assertSee('No tiene campañas disponibles asignadas a Admisión.');
});

test('admission sees only linked doctors and sheets in the selected campaign', function (): void {
    ['usuario' => $usuario, 'medico' => $medico, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $ajena = ofertaHoja($servicio);
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertOk()
        ->assertSee($campania->nombre)->assertSee($medico->nombre)->assertSee($hoja->paciente->nombres_apellidos)
        ->assertDontSee($ajena->paciente->nombres_apellidos)->assertDontSee($ajena->medico->nombre)
        ->assertSee('data-script="oferta_flexible/admision"', false);
});

test('unassociated campaigns show all sheets to assigned admission users', function (): void {
    ['usuario' => $usuario, 'servicio' => $servicio] = admisionEscenario(false);
    $otra = ofertaHoja($servicio);
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertOk()->assertSee($otra->paciente->nombres_apellidos);
});

test('associated campaigns with no links return no sheets', function (): void {
    ['usuario' => $usuario, 'hoja' => $hoja] = admisionEscenario();
    CampaniaEnlace::firstOrFail()->delete();
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertOk()
        ->assertSee('No tiene médicos enlazados en esta campaña.')->assertDontSee($hoja->paciente->nombres_apellidos);
});

test('campaign selection prefers active campaigns and supports closed history', function (): void {
    ['usuario' => $usuario, 'campania' => $activa] = admisionEscenario();
    $cerrada = Campania::factory()->create(['nombre' => 'Historial cerrado', 'cerrada' => true]);
    CampaniaUsuario::factory()->create(['campania_id' => $cerrada->id, 'usuario_id' => $usuario->id, 'tipo' => 'A']);
    $ajena = Campania::factory()->create(['nombre' => 'Campaña ajena invisible']);
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertOk()
        ->assertViewHas('campania', fn ($row): bool => $row->id === $activa->id)->assertSee('id="admision-campania"', false)->assertDontSee($ajena->nombre);
    $this->get(route('oferta-flexible.admision.index', ['campania_id' => $cerrada->id]))->assertOk()->assertSee('Campaña cerrada');
    $this->get(route('oferta-flexible.admision.index', ['campania_id' => $ajena->id]))->assertNotFound();
});

test('date doctor and search filters are retained in ajax pagination', function (): void {
    ['usuario' => $usuario, 'medico' => $medico, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $hoja->paciente->update(['nombres_apellidos' => 'Paciente Muñoz 00']);
    for ($i = 1; $i <= 15; $i++) {
        $otra = ofertaHoja($servicio, $medico);
        $otra->paciente->update(['nombres_apellidos' => 'Paciente Muñoz '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
    }
    $filters = ['campania_id' => $campania->id, 'fecha' => '2026-10-20', 'medico_id' => $medico->id, 'q' => 'Muñoz'];
    $respuesta = $this->actingAs($usuario)->getJson(route('oferta-flexible.admision.index', $filters))->assertOk();
    $html = html_entity_decode($respuesta->json('html'));
    expect($html)->toContain('16 pacientes encontrados', 'fecha=2026-10-20', 'medico_id='.$medico->id, 'q=Mu%C3%B1oz', 'page=2');
    $this->getJson(route('oferta-flexible.admision.index', [...$filters, 'page' => 2]))->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Paciente Muñoz 00'));
    $this->getJson(route('oferta-flexible.admision.index', [...$filters, 'fecha' => '2026-10-19']))->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, '0 pacientes encontrados'));
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
});

test('document search and manipulated doctor filters cannot expand admission scope', function (): void {
    ['usuario' => $usuario, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $ajena = ofertaHoja($servicio);
    $this->actingAs($usuario)->getJson(route('oferta-flexible.admision.index', ['q' => $hoja->paciente->dni]))->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, e($hoja->paciente->nombres_apellidos)));
    foreach ([['q' => "' OR 1=1 --"], ['medico_id' => $ajena->medico_usuario_id], ['q' => $ajena->paciente->nombres_apellidos]] as $filter) {
        $this->getJson(route('oferta-flexible.admision.index', $filter))->assertOk()
            ->assertJsonPath('html', fn (string $html): bool => str_contains($html, '0 pacientes encontrados'));
    }
});

test('admission validates filters with Spanish messages', function (array $data, string $field): void {
    $this->actingAs(admisionActor())->getJson(route('oferta-flexible.admision.index', $data))->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['fecha' => '2026-02-30'], 'fecha'], [['campania_id' => -1], 'campania_id'], [['medico_id' => 'x'], 'medico_id'],
    [['q' => str_repeat('a', 201)], 'q'], [['page' => 0], 'page'],
]);

test('foreign sheet ids cannot be printed or deleted', function (string $scope): void {
    ['usuario' => $usuario, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $data = ['campania_id' => $campania->id, 'hoja_id' => $hoja->id];
    match ($scope) {
        'medico' => $data['hoja_id'] = ofertaHoja($servicio)->id,
        'campania' => $data['hoja_id'] = ofertaHoja(CampaniaServicio::factory()->create())->id,
        'campania_ajena' => $data['campania_id'] = Campania::factory()->create()->id,
        'sin_asignacion' => CampaniaUsuario::where('usuario_id', $usuario->id)->firstOrFail()->delete(),
        'enlace_eliminado' => CampaniaEnlace::firstOrFail()->delete(),
        'hoja_eliminada' => $hoja->delete(),
        'campania_eliminada' => $campania->delete(),
    };
    $this->actingAs($usuario)->postJson(route('oferta-flexible.admision.imprimir'), $data)->assertNotFound();
    $this->deleteJson(route('oferta-flexible.admision.destroy'), $data)->assertNotFound();
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
})->with(['medico', 'campania', 'campania_ajena', 'sin_asignacion', 'enlace_eliminado', 'hoja_eliminada', 'campania_eliminada']);

test('print and deletion require validated ids', function (): void {
    $this->actingAs(admisionActor())->postJson(route('oferta-flexible.admision.imprimir'), [])->assertUnprocessable()
        ->assertJsonValidationErrors(['campania_id', 'hoja_id'])->assertJsonPath('errors.hoja_id.0', 'El campo hoja de ruta es obligatorio.');
    $this->deleteJson(route('oferta-flexible.admision.destroy'), ['campania_id' => 1, 'hoja_id' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('hoja_id');
});

test('each generated PDF appends an audited impression even when the campaign is closed', function (bool $closed): void {
    ['usuario' => $usuario, 'medico' => $medico, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $campania->update(['cerrada' => $closed]);
    $hoja->paciente->update(['nombres_apellidos' => 'María Muñoz Peña', 'fecha_nacimiento' => '1980-10-20', 'sexo' => 'F']);
    $updatedAt = $hoja->updated_at->toDateTimeString();
    $data = ['campania_id' => $campania->id, 'hoja_id' => $hoja->id, 'impreso_por' => $medico->id];
    $respuesta = $this->actingAs($usuario)->post(route('oferta-flexible.admision.imprimir'), $data)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($respuesta->getContent())->toStartWith('%PDF-');
    $this->assertDatabaseHas('TB_HOJA_RUTA_IMPRESIONES', ['hoja_ruta_id' => $hoja->id, 'impreso_por' => $usuario->id,
        'created_by' => $usuario->id, 'impreso_at' => '2026-10-20 12:00:00']);
    $this->post(route('oferta-flexible.admision.imprimir'), $data)->assertOk();
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 2);
    expect($hoja->refresh()->updated_at->toDateTimeString())->toBe($updatedAt);
    $this->get(route('oferta-flexible.admision.index'))->assertOk()->assertDontSee('Modificada');
    if ($closed) {
        $this->get(route('oferta-flexible.admision.index'))->assertDontSee('admision-eliminar', false);
    }
    if (! $closed && ($preview = getenv('ADMISION_PREVIEW_DIR'))) {
        if (! is_dir($preview)) {
            mkdir($preview, recursive: true);
        }
        file_put_contents($preview.'/hoja-de-ruta.pdf', $respuesta->getContent());
        file_put_contents($preview.'/index.html', $this->get(route('oferta-flexible.admision.index'))->getContent());
    }
})->with([false, true]);

test('failed impression persistence returns no PDF and leaves no print record', function (): void {
    ['usuario' => $usuario, 'campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    DB::statement("CREATE TRIGGER reject_print BEFORE INSERT ON TB_HOJA_RUTA_IMPRESIONES BEGIN SELECT RAISE(ABORT, 'print rejected'); END");
    $this->actingAs($usuario)->postJson(route('oferta-flexible.admision.imprimir'), ['campania_id' => $campania->id, 'hoja_id' => $hoja->id])->assertStatus(500);
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
    $this->assertNotSoftDeleted($hoja);
});

test('deleting a sheet audits details releases quota and retains patient and print history', function (): void {
    ['usuario' => $usuario, 'medico' => $medico, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $impresion = HojaRutaImpresion::factory()->create(['hoja_ruta_id' => $hoja->id, 'impreso_por' => $usuario->id]);
    $this->actingAs($usuario)->deleteJson(route('oferta-flexible.admision.destroy'), ['campania_id' => $campania->id, 'hoja_id' => $hoja->id])->assertOk()->assertJsonPath('success', true);
    $this->assertSoftDeleted($hoja);
    $this->assertDatabaseHas('TB_HOJAS_RUTA', ['id' => $hoja->id, 'deleted_by' => $usuario->id, 'updated_by' => $usuario->id]);
    $this->assertDatabaseHas('TB_HOJA_RUTA_DETALLE', ['hoja_ruta_id' => $hoja->id, 'deleted_by' => $usuario->id]);
    expect(HojaRutaDetalle::where('hoja_ruta_id', $hoja->id)->count())->toBe(0);
    expect(app(CampaniaService::class)->usage([$servicio->id]))->toBe([]);
    $this->assertDatabaseHas('TB_PACIENTES_MOCK', ['id' => $hoja->paciente_id]);
    $this->assertDatabaseHas('TB_HOJA_RUTA_IMPRESIONES', ['id' => $impresion->id]);
    $this->actingAs($medico);
    $resultado = app(TriajeService::class)->operar($medico, ['accion' => 'grabar', 'campania_id' => $campania->id,
        'paciente_id' => $hoja->paciente_id, 'servicios' => [$servicio->id]]);
    expect($resultado['hoja']['id'])->not->toBe($hoja->id);
});

test('closed campaigns cannot lose sheets or quota through admission', function (): void {
    ['usuario' => $usuario, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $campania->update(['cerrada' => true]);
    $this->actingAs($usuario)->deleteJson(route('oferta-flexible.admision.destroy'), ['campania_id' => $campania->id, 'hoja_id' => $hoja->id])
        ->assertUnprocessable()->assertJsonValidationErrors('campania');
    $this->assertNotSoftDeleted($hoja);
    expect(app(CampaniaService::class)->usage([$servicio->id]))->toBe([$servicio->id => 1]);
});

test('a later service edit by the same doctor marks the printed sheet modified until reprinted', function (): void {
    ['usuario' => $usuario, 'medico' => $medico, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $hoja->update(['updated_by' => $medico->id]);
    $this->actingAs($usuario)->post(route('oferta-flexible.admision.imprimir'), ['campania_id' => $campania->id, 'hoja_id' => $hoja->id])->assertOk();
    $this->travel(1)->minutes();
    $otro = CampaniaServicio::factory()->create(['campania_id' => $campania->id]);
    $this->actingAs($medico);
    app(TriajeService::class)->operar($medico, ['accion' => 'grabar', 'campania_id' => $campania->id,
        'paciente_id' => $hoja->paciente_id, 'servicios' => [$servicio->id, $otro->id]]);
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertOk()->assertSee('Modificada');
    $this->post(route('oferta-flexible.admision.imprimir'), ['campania_id' => $campania->id, 'hoja_id' => $hoja->id])->assertOk();
    $this->get(route('oferta-flexible.admision.index'))->assertOk()->assertDontSee('Modificada');
    expect($hoja->refresh()->medico_usuario_id)->toBe($medico->id);
});

test('patient doctor campaign and service names are escaped in list and PDF templates', function (): void {
    ['usuario' => $usuario, 'medico' => $medico, 'campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $payload = '<script>alert("x")</script>';
    $hoja->paciente->update(['nombres_apellidos' => $payload]);
    $medico->update(['nombre' => $payload]);
    $campania->update(['nombre' => $payload]);
    $servicio->servicioUps->update(['DESCRIPCION_UPS' => $payload]);
    $this->actingAs($usuario)->get(route('oferta-flexible.admision.index'))->assertOk()->assertSee($payload)->assertDontSee($payload, false);
    $hoja->load(['campania', 'paciente', 'medico', 'detalles.campaniaServicio.servicioUps']);
    $html = view('oferta_flexible.admision.pdf', ['hoja' => $hoja, 'emitidoAt' => now(), 'edad' => 40])->render();
    expect($html)->toContain('&lt;script&gt;')->not->toContain($payload);
});
