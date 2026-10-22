<?php

use App\Models\Campania;
use App\Models\CampaniaEnlace;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRutaImpresion;

require_once __DIR__.'/OfertaFlexibleFixtures.php';
beforeEach(function (): void {
    ofertaFlexibleFixtures();
});
afterEach(function (): void {
    $this->travelBack();
});

test('assignment consults existing users and never creates accounts', function (): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $admision = ofertaActor('Admisión');
    $campania = Campania::factory()->create();

    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.usuarios.store', $campania), [
            'usuarios' => [$medico->id, $admision->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $this->assertDatabaseCount('TB_ACCESO_USUARIO', 3);
    $this->assertDatabaseHas('TB_CAMPANIA_USUARIOS', [
        'campania_id' => $campania->id,
        'usuario_id' => $medico->id,
        'tipo' => 'M',
    ]);
    $this->assertDatabaseHas('TB_CAMPANIA_USUARIOS', [
        'campania_id' => $campania->id,
        'usuario_id' => $admision->id,
        'tipo' => 'A',
    ]);
    $this
        ->get(route('oferta-flexible.usuarios.index', ['campania_id' => $campania->id]))
        ->assertOk()
        ->assertSee($medico->nombre);
});

test('assignment rejects inactive user role or pivot and rolls back the whole batch', function (
    string $inactive,
): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $admision = ofertaActor('Admisión');
    $campania = Campania::factory()->create();
    if ($inactive === 'user') {
        $admision->update(['status' => 0]);
    }
    if ($inactive === 'role') {
        $admision->roles->first()->update(['status' => 0]);
    }
    if ($inactive === 'pivot') {
        $admision->roles()->updateExistingPivot($admision->roles->first()->id, ['status' => 0]);
    }

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.usuarios.store', $campania), [
            'usuarios' => [$medico->id, $admision->id],
        ])
        ->assertUnprocessable();
    $this->assertDatabaseCount('TB_CAMPANIA_USUARIOS', 0);
})->with(['user', 'role', 'pivot']);

test('one admission user can link several doctors but a doctor has only one link', function (): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $otro = ofertaActor('Médico Triaje');
    $admision = ofertaActor('Admisión');
    $campania = Campania::factory()->create();
    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.usuarios.store', $campania), [
            'usuarios' => [$medico->id, $otro->id, $admision->id],
        ])
        ->assertRedirect();

    foreach ([$medico, $otro] as $usuario) {
        $this
            ->post(route('oferta-flexible.enlaces.store', $campania), [
                'medico_usuario_id' => $usuario->id,
                'admision_usuario_id' => $admision->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
    $this->postJson(route('oferta-flexible.enlaces.store', $campania), [
        'medico_usuario_id' => $medico->id,
        'admision_usuario_id' => $admision->id,
    ])->assertUnprocessable();
    $this->assertDatabaseCount('TB_CAMPANIA_ENLACES', 2);
});

test('link rejects users assigned to a different campaign', function (): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $admision = ofertaActor('Admisión');
    $campania = Campania::factory()->create();
    $other = Campania::factory()->create();
    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.usuarios.store', $other), ['usuarios' => [$medico->id, $admision->id]])
        ->assertRedirect();

    $this->postJson(route('oferta-flexible.enlaces.store', $campania), [
        'medico_usuario_id' => $medico->id,
        'admision_usuario_id' => $admision->id,
    ])->assertUnprocessable();
    $this->assertDatabaseCount('TB_CAMPANIA_ENLACES', 0);
});

test('link requires a nonclosed active campaign with association enabled', function (
    string $restriction,
): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $admision = ofertaActor('Admisión');
    $campania = Campania::factory()->create([
        'asociar_triaje_admision' => $restriction !== 'sin_asociacion',
        'fecha_inicio' => '2026-10-18',
        'fecha_fin' => $restriction === 'finalizada' ? '2026-10-19' : '2026-10-22',
    ]);
    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.usuarios.store', $campania), [
            'usuarios' => [$medico->id, $admision->id],
        ])
        ->assertRedirect();

    if ($restriction === 'cerrada') {
        $campania->update(['cerrada' => true]);
    }
    $this->postJson(route('oferta-flexible.enlaces.store', $campania), [
        'medico_usuario_id' => $medico->id,
        'admision_usuario_id' => $admision->id,
    ])->assertUnprocessable();
    $this->assertDatabaseCount('TB_CAMPANIA_ENLACES', 0);
})->with(['sin_asociacion', 'finalizada', 'cerrada']);

test(
    'a future active campaign accepts assignments and links and appears in the active selector',
    function (): void {
        $actor = ofertaActor();
        $medico = ofertaActor('Médico Triaje');
        $admision = ofertaActor('Admisión');
        $campania = Campania::factory()->create([
            'nombre' => 'Atención futura habilitada',
            'fecha_inicio' => '2026-10-21',
            'fecha_fin' => '2026-10-22',
        ]);

        $this
            ->actingAs($actor)
            ->post(route('oferta-flexible.usuarios.store', $campania), [
                'usuarios' => [$medico->id, $admision->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this
            ->post(route('oferta-flexible.enlaces.store', $campania), [
                'medico_usuario_id' => $medico->id,
                'admision_usuario_id' => $admision->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this
            ->get(route('oferta-flexible.usuarios.index', ['solo_activas' => 1]))
            ->assertOk()
            ->assertSee($campania->nombre);
        $this->assertDatabaseHas('TB_CAMPANIA_ENLACES', [
            'campania_id' => $campania->id,
            'medico_usuario_id' => $medico->id,
            'admision_usuario_id' => $admision->id,
            'created_by' => $actor->id,
        ]);
    },
);

test('assignment cannot be removed until its link is removed', function (): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $admision = ofertaActor('Admisión');
    $campania = Campania::factory()->create();
    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.usuarios.store', $campania), [
            'usuarios' => [$medico->id, $admision->id],
        ])
        ->assertRedirect();
    $this->post(route('oferta-flexible.enlaces.store', $campania), [
        'medico_usuario_id' => $medico->id,
        'admision_usuario_id' => $admision->id,
    ])->assertRedirect();
    $assignment = $campania
        ->asignaciones()
        ->where('usuario_id', $medico->id)
        ->firstOrFail();
    $link = $campania->enlaces()->firstOrFail();

    $this->deleteJson(
        route('oferta-flexible.usuarios.destroy', [$campania, $assignment]),
    )->assertUnprocessable();
    $this->deleteJson(route('oferta-flexible.enlaces.destroy', [$campania, $link]))->assertOk();
    $this->deleteJson(route('oferta-flexible.usuarios.destroy', [$campania, $assignment]))->assertOk();
    $this->assertSoftDeleted($assignment);
    $this->assertSoftDeleted($link);
    $this->assertModelExists($medico);
});

test('doctor with route sheet history cannot be unassigned', function (): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $daily = CampaniaServicio::factory()->create();
    $campania = $daily->campania;
    $assignment = CampaniaUsuario::factory()->create([
        'campania_id' => $campania->id,
        'usuario_id' => $medico->id,
    ]);
    ofertaHoja($daily, $medico)->delete();

    $this
        ->actingAs($actor)
        ->deleteJson(route('oferta-flexible.usuarios.destroy', [$campania, $assignment]))
        ->assertUnprocessable();
    $this->assertNotSoftDeleted($assignment);
});

test('admission with print history cannot be unassigned', function (): void {
    $actor = ofertaActor();
    $admision = ofertaActor('Admisión');
    $daily = CampaniaServicio::factory()->create();
    $campania = $daily->campania;
    $assignment = CampaniaUsuario::factory()->create([
        'campania_id' => $campania->id,
        'usuario_id' => $admision->id,
        'tipo' => 'A',
    ]);
    $hoja = ofertaHoja($daily);
    HojaRutaImpresion::factory()->create(['hoja_ruta_id' => $hoja->id, 'impreso_por' => $admision->id]);

    $this
        ->actingAs($actor)
        ->deleteJson(route('oferta-flexible.usuarios.destroy', [$campania, $assignment]))
        ->assertUnprocessable();
    $this->assertNotSoftDeleted($assignment);
});

test('nested deletions cannot target another campaign', function (string $type): void {
    $actor = ofertaActor();
    $campania = Campania::factory()->create();
    $record =
        $type === 'usuarios' ? CampaniaUsuario::factory()->create() : CampaniaEnlace::factory()->create();

    $this
        ->actingAs($actor)
        ->deleteJson(route('oferta-flexible.'.$type.'.destroy', [$campania, $record]))
        ->assertNotFound();
    $this->assertNotSoftDeleted($record);
})->with(['usuarios', 'enlaces']);

test('closed campaign rejects assignment and link mutations', function (): void {
    $actor = ofertaActor();
    $medico = ofertaActor('Médico Triaje');
    $admision = ofertaActor('Admisión');
    $campania = Campania::factory()->create(['cerrada' => true]);
    $assignment = CampaniaUsuario::factory()->create([
        'campania_id' => $campania->id,
        'usuario_id' => $medico->id,
    ]);
    $link = CampaniaEnlace::factory()->create([
        'campania_id' => $campania->id,
        'medico_usuario_id' => $medico->id,
        'admision_usuario_id' => $admision->id,
    ]);

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.usuarios.store', $campania), ['usuarios' => [$admision->id]])
        ->assertUnprocessable();
    $this->deleteJson(
        route('oferta-flexible.usuarios.destroy', [$campania, $assignment]),
    )->assertUnprocessable();
    $this->postJson(route('oferta-flexible.enlaces.store', $campania), [
        'medico_usuario_id' => $medico->id,
        'admision_usuario_id' => $admision->id,
    ])->assertUnprocessable();
    $this->deleteJson(route('oferta-flexible.enlaces.destroy', [$campania, $link]))->assertUnprocessable();
    $this
        ->get(route('oferta-flexible.usuarios.index', ['campania_id' => $campania->id]))
        ->assertOk()
        ->assertSee('solo lectura');
    $this->assertNotSoftDeleted($assignment);
    $this->assertNotSoftDeleted($link);
});
