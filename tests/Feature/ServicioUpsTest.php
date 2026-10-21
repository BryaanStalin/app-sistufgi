<?php

use App\Models\CampaniaServicio;
use App\Models\ServicioUps;

require_once __DIR__.'/OfertaFlexibleFixtures.php';
beforeEach(function (): void {
    ofertaFlexibleFixtures();
});
afterEach(function (): void {
    $this->travelBack();
});

test('administrator creates a service with Spanish text and audit', function (): void {
    $actor = ofertaActor();

    $this->actingAs($actor)->post(route('oferta-flexible.servicios.store'), [
        'ID_UPS' => 101, 'DESCRIPCION_UPS' => 'Atención del niño y la niña', 'TIPO_UPS' => 'P', 'ACTIVO' => 1,
    ])->assertRedirect(route('oferta-flexible.servicios.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('TB_UPSS_CAMPAÑAS', ['ID_UPS' => 101, 'DESCRIPCION_UPS' => 'Atención del niño y la niña', 'created_by' => $actor->id]);
    $this->get(route('oferta-flexible.servicios.index', ['tipo' => 'P', 'q' => 'niña']))->assertOk()->assertSee('PREVENTIVO')->assertSee('niña');
});

test('service rejects any type outside P and E', function (string $type): void {
    $actor = ofertaActor();

    $this->actingAs($actor)->postJson(route('oferta-flexible.servicios.store'), [
        'ID_UPS' => 102, 'DESCRIPCION_UPS' => 'Consulta', 'TIPO_UPS' => $type, 'ACTIVO' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('TIPO_UPS');
    $this->assertDatabaseCount('TB_UPSS_CAMPAÑAS', 0);
})->with(['X', 'PREVENTIVO', 'p']);

test('active service codes cannot be duplicated but inactive codes can be reused', function (bool $active): void {
    $actor = ofertaActor();
    ServicioUps::factory()->create(['ID_UPS' => 103, 'ACTIVO' => $active]);

    $response = $this->actingAs($actor)->postJson(route('oferta-flexible.servicios.store'), [
        'ID_UPS' => 103, 'DESCRIPCION_UPS' => 'Especialidad', 'TIPO_UPS' => 'E', 'ACTIVO' => 1,
    ]);
    if ($active) {
        $response->assertUnprocessable()->assertJsonValidationErrors('ID_UPS');
        $this->assertDatabaseCount('TB_UPSS_CAMPAÑAS', 1);
    } else {
        $response->assertRedirect();
        $this->assertDatabaseCount('TB_UPSS_CAMPAÑAS', 2);
    }
})->with([true, false]);

test('service deactivation preserves the record and audit', function (): void {
    $actor = ofertaActor();
    $service = ServicioUps::factory()->create();

    $this->actingAs($actor)->deleteJson(route('oferta-flexible.servicios.destroy', $service))->assertOk()->assertJsonPath('success', true);
    $this->assertDatabaseHas('TB_UPSS_CAMPAÑAS', ['id' => $service->id, 'ACTIVO' => 0, 'deleted_by' => $actor->id]);
});

test('service with route sheets cannot be deactivated through either endpoint', function (string $operation): void {
    $actor = ofertaActor();
    $daily = CampaniaServicio::factory()->create();
    ofertaHoja($daily);
    $service = $daily->servicioUps;

    $response = $operation === 'delete'
        ? $this->actingAs($actor)->deleteJson(route('oferta-flexible.servicios.destroy', $service))
        : $this->actingAs($actor)->putJson(route('oferta-flexible.servicios.update', $service), [
            'ID_UPS' => $service->ID_UPS, 'DESCRIPCION_UPS' => $service->DESCRIPCION_UPS, 'TIPO_UPS' => 'P', 'ACTIVO' => 0,
        ]);
    $response->assertUnprocessable();
    expect($service->refresh()->ACTIVO)->toBeTrue();
})->with(['delete', 'update']);
