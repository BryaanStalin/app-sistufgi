<?php

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\ServicioUps;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/OfertaFlexibleFixtures.php';
beforeEach(function (): void {
    ofertaFlexibleFixtures();
});
afterEach(function (): void {
    $this->travelBack();
});

test('campaign listing counts distinct services excluding deleted daily rows', function (): void {
    $actor = ofertaActor();
    $campaign = Campania::factory()->create();
    $service = ServicioUps::factory()->create();
    foreach (['2026-10-20', '2026-10-21'] as $date) {
        CampaniaServicio::factory()->create([
            'campania_id' => $campaign->id,
            'ups_id' => $service->id,
            'fecha_servicio' => $date,
        ]);
    }
    CampaniaServicio::factory()
        ->create(['campania_id' => $campaign->id])
        ->delete();
    $empty = Campania::factory()->create();

    $response = $this
        ->actingAs($actor)
        ->get(route('oferta-flexible.campanias.index'))
        ->assertOk();

    expect((int) $response['campanias']->firstWhere('id', $campaign->id)->servicios_count)->toBe(1);
    expect((int) $response['campanias']->firstWhere('id', $empty->id)->servicios_count)->toBe(0);
});

test('same services are replicated per day with audit and midnight dates', function (): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();

    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.campanias.store'), ofertaCampaniaData($ups->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $campania = Campania::firstOrFail();
    expect($campania->servicios->pluck('cupos')->all())->toBe([10, 10]);
    expect($campania->servicios
        ->pluck('fecha_servicio')->map
        ->format('Y-m-d H:i:s')
        ->all())->toBe([
            '2026-10-20 00:00:00',
            '2026-10-21 00:00:00',
        ]);
    expect($campania->created_by)->toBe($actor->id);
    $this
        ->get(route('oferta-flexible.campanias.edit', $campania))
        ->assertOk()
        ->assertSee('Actualizar campaña');
    $this
        ->get(route('oferta-flexible.campanias.show', $campania))
        ->assertOk()
        ->assertSee('CERRAR CAMPAÑA');
});

test('campaign can start and finish on the same day', function (): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $data['fecha_fin'] = $data['fecha_inicio'];

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.campanias.store'), $data)
        ->assertRedirect();
    $this->assertDatabaseCount('TB_CAMPANIA_SERVICIOS', 1);
});

test('the browser JSON matrix is validated before any write', function (bool $valid): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $data['servicios_config'] = json_encode([
        'generales' => [['ups_id' => $ups->id, 'cupos' => $valid ? 12 : 0]],
        'diarios' => [],
    ]);
    unset($data['servicios_generales']);

    $response = $this->actingAs($actor)->postJson(route('oferta-flexible.campanias.store'), $data);
    if ($valid) {
        $response->assertRedirect();
        expect(CampaniaServicio::pluck('cupos')->all())->toBe([12, 12]);
    } else {
        $response->assertUnprocessable()->assertJsonValidationErrors('servicios_generales.0.cupos');
        $this->assertDatabaseCount('TB_CAMPANIAS', 0);
    }
})->with([true, false]);

test('campaign rejects a backwards range', function (): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $data['fecha_fin'] = '2026-10-19';

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.campanias.store'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fecha_fin');
    $this->assertDatabaseCount('TB_CAMPANIAS', 0);
});

test('state filters agree with the calculated state', function (string $state): void {
    $actor = ofertaActor();
    Campania::factory()->create([
        'nombre' => 'Campaña futura única',
        'fecha_inicio' => '2026-10-21',
        'fecha_fin' => '2026-10-22',
    ]);
    Campania::factory()->create(['nombre' => 'Estado activo único']);
    Campania::factory()->create([
        'nombre' => 'Estado finalizado único',
        'fecha_inicio' => '2026-10-18',
        'fecha_fin' => '2026-10-19',
    ]);
    Campania::factory()->create(['nombre' => 'Estado cerrado único', 'cerrada' => true]);

    $this
        ->actingAs($actor)
        ->get(route('oferta-flexible.campanias.index', ['estado' => $state]))
        ->assertOk()
        ->assertViewHas(
            'campanias',
            fn ($rows): bool => $rows->count() === ($state === 'Activa' ? 2 : 1) &&
                $rows->every(fn ($row): bool => $row->estado === $state),
        );
})->with(['Activa', 'Finalizada', 'Cerrada']);

test('daily mode accepts separate services for each date', function (): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $data['mismos_servicios'] = 0;
    unset($data['servicios_generales']);
    $data['servicios_dia'] = [
        ['fecha' => '2026-10-20', 'ups_id' => $ups->id, 'cupos' => 3],
        ['fecha' => '2026-10-21', 'ups_id' => $ups->id, 'cupos' => 8],
    ];

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.campanias.store'), $data)
        ->assertRedirect();
    expect(CampaniaServicio::orderBy('fecha_servicio')
        ->pluck('cupos')
        ->all())->toBe([3, 8]);
});

test('campaign rejects invalid quotas even without frontend checks', function (mixed $quota): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $data['servicios_generales'][0]['cupos'] = $quota;

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.campanias.store'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('servicios_generales.0.cupos');
    $this->assertDatabaseCount('TB_CAMPANIAS', 0);
})->with([null, 0, -1, 1.5]);

test('campaign rejects repeated or out of range daily rows without writing', function (
    bool $duplicate,
): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $row = ['fecha' => $duplicate ? '2026-10-20' : '2026-10-22', 'ups_id' => $ups->id, 'cupos' => 5];
    $data['servicios_dia'] = $duplicate ? [$row, $row] : [$row];

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.campanias.store'), $data)
        ->assertUnprocessable();
    $this->assertDatabaseCount('TB_CAMPANIAS', 0);
})->with([true, false]);

test('a new campaign cannot select inactive services', function (): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create(['ACTIVO' => false]);

    $this
        ->actingAs($actor)
        ->postJson(route('oferta-flexible.campanias.store'), ofertaCampaniaData($ups->id))
        ->assertUnprocessable();
    $this->assertDatabaseCount('TB_CAMPANIAS', 0);
});

test('personal daily quota survives later general changes unless administrator confirms reset', function (
    bool $overwrite,
): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.campanias.store'), $data)
        ->assertRedirect();
    $campania = Campania::firstOrFail();
    $data['servicios_dia'] = [['fecha' => '2026-10-20', 'ups_id' => $ups->id, 'cupos' => 7]];
    $this->putJson(route('oferta-flexible.campanias.update', $campania), $data)->assertRedirect();
    $data['servicios_generales'][0]['cupos'] = 15;
    $data['sobrescribir_personalizados'] = $overwrite;
    unset($data['servicios_dia']);

    $this->putJson(route('oferta-flexible.campanias.update', $campania), $data)->assertRedirect();

    expect($campania
        ->servicios()
        ->orderBy('fecha_servicio')
        ->pluck('cupos')
        ->all())->toBe(
            $overwrite ? [15, 15] : [7, 15],
        );
    expect($campania
        ->servicios()
        ->orderBy('fecha_servicio')
        ->first()->cupo_personalizado)->toBe(! $overwrite);
})->with([false, true]);

test('campaign status uses whole Lima days with closed taking precedence', function (
    string $start,
    string $end,
    bool $closed,
    string $expected,
): void {
    $campania = Campania::factory()->create([
        'fecha_inicio' => $start,
        'fecha_fin' => $end,
        'cerrada' => $closed,
    ]);

    expect($campania->estado)->toBe($expected);
})->with([
    ['2026-10-21', '2026-10-22', false, 'Activa'],
    ['2026-10-20', '2026-10-20', false, 'Activa'],
    ['2026-10-18', '2026-10-19', false, 'Finalizada'],
    ['2026-10-21', '2026-10-22', true, 'Cerrada'],
    ['2026-10-18', '2026-10-19', true, 'Cerrada'],
]);

test(
    'future active campaigns allow editing daily services and quotas before the start date',
    function (): void {
        $actor = ofertaActor();
        $ups = ServicioUps::factory()->create();
        $data = ofertaCampaniaData($ups->id);
        $data['fecha_inicio'] = '2026-10-21';
        $data['fecha_fin'] = '2026-10-22';

        $this
            ->actingAs($actor)
            ->post(route('oferta-flexible.campanias.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $campania = Campania::firstOrFail();
        $data['servicios_generales'][] = [
            'ups_id' => ServicioUps::factory()->create()->id,
            'cupos' => 4,
        ];
        $data['servicios_generales'][0]['cupos'] = 7;
        $this->get(route('oferta-flexible.campanias.edit', $campania))->assertOk();
        $this
            ->put(route('oferta-flexible.campanias.update', $campania), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($campania->refresh()->estado)->toBe('Activa');
        expect($campania
            ->servicios()
            ->orderBy('fecha_servicio')
            ->orderBy('id')
            ->pluck('cupos')
            ->all())->toBe(
                [7, 4, 7, 4],
            );
    },
);

test('used days services and campaign mode cannot be removed and capacity cannot fall below usage', function (
    string $change,
): void {
    $actor = ofertaActor();
    $ups = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($ups->id);
    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.campanias.store'), $data)
        ->assertRedirect();
    $campania = Campania::firstOrFail();
    $daily = $campania->servicios()->first();
    ofertaHoja($daily);
    ofertaHoja($daily);
    match ($change) {
        'quota' => ($data['servicios_generales'][0]['cupos'] = 1),
        'date' => ($data['fecha_inicio'] = '2026-10-21'),
        'mode' => ($data['mismos_servicios'] = 0),
        'service' => ($data['servicios_generales'][0]['ups_id'] = ServicioUps::factory()->create()->id),
    };

    $this->putJson(route('oferta-flexible.campanias.update', $campania), $data)->assertUnprocessable();
    expect($daily->refresh()->cupos)->toBe(10);
    expect($campania->refresh()->mismos_servicios)->toBeTrue();
    $this->assertNotSoftDeleted($daily);
})->with(['quota', 'date', 'mode', 'service']);

test('closed campaign is readonly and can only be changed after confirmed reopening', function (): void {
    $actor = ofertaActor();
    $campania = Campania::factory()->create();
    $ups = ServicioUps::factory()->create();
    $this
        ->actingAs($actor)
        ->post(route('oferta-flexible.campanias.cerrar', $campania), ['confirmar' => 1])
        ->assertRedirect();

    $this->putJson(
        route('oferta-flexible.campanias.update', $campania),
        ofertaCampaniaData($ups->id),
    )->assertUnprocessable();
    $this->deleteJson(route('oferta-flexible.campanias.destroy', $campania))->assertUnprocessable();
    $this
        ->get(route('oferta-flexible.campanias.show', $campania))
        ->assertOk()
        ->assertSee('solo lectura');
    expect($campania->refresh()->cerrada_by)->toBe($actor->id);
    $this->postJson(route('oferta-flexible.campanias.reabrir', $campania), [])->assertUnprocessable();
    $this->post(route('oferta-flexible.campanias.reabrir', $campania), ['confirmar' => 1])->assertRedirect();
    expect($campania->refresh()->cerrada)->toBeFalse();
});

test('used services remain protected when PDO returns numeric strings', function (): void {
    $actor = ofertaActor();
    $daily = CampaniaServicio::factory()->create();
    ofertaHoja($daily);
    $replacement = ServicioUps::factory()->create();
    $data = ofertaCampaniaData($replacement->id);
    $data['fecha_fin'] = $data['fecha_inicio'];
    DB::connection()
        ->getPdo()
        ->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

    $this
        ->actingAs($actor)
        ->putJson(route('oferta-flexible.campanias.update', $daily->campania_id), $data)
        ->assertUnprocessable();
    $this->assertNotSoftDeleted($daily);
});

test('campaign deletion is soft and blocked by route sheet history', function (bool $hasHistory): void {
    $actor = ofertaActor();
    $daily = CampaniaServicio::factory()->create();
    $campania = $daily->campania;
    if ($hasHistory) {
        ofertaHoja($daily)->delete();
    }

    $response = $this->actingAs($actor)->deleteJson(route('oferta-flexible.campanias.destroy', $campania));
    if ($hasHistory) {
        $response->assertUnprocessable();
        $this->assertNotSoftDeleted($campania);
    } else {
        $response->assertOk();
        $this->assertSoftDeleted($campania);
        $this->assertSoftDeleted($daily);
        expect($campania->refresh()->deleted_by)->toBe($actor->id);
    }
})->with([true, false]);
