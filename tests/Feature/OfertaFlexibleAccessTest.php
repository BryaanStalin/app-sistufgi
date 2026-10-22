<?php

use App\Models\Menu;
use App\Models\Rol;

require_once __DIR__.'/OfertaFlexibleFixtures.php';
beforeEach(function (): void {
    ofertaFlexibleFixtures();
});
afterEach(function (): void {
    $this->travelBack();
});

test('oferta flexible redirects guests to login', function (string $name): void {
    $this->get(route('oferta-flexible.'.$name))->assertRedirect(route('login'));
})->with(['campanias.index', 'servicios.index', 'usuarios.index']);

test('menu permissions cannot grant administrative access to other roles', function (string $title): void {
    $actor = ofertaActor($title);
    foreach (
        ['/oferta-flexible/campanias', '/oferta-flexible/servicios', '/oferta-flexible/usuarios-campanias'] as $path
    ) {
        $menu = Menu::create([
            'titulo' => 'Acceso indebidamente concedido',
            'link' => $path,
            'orden' => 1,
            'status' => 1,
        ]);
        $actor->roles
            ->first()
            ->menus()
            ->attach($menu->id, ['status' => 1]);
    }

    $this
        ->actingAs($actor)
        ->get(route('oferta-flexible.campanias.index'))
        ->assertForbidden();
    $this->postJson(route('oferta-flexible.campanias.store'), [])->assertForbidden();
    $this->get(route('oferta-flexible.servicios.index'))->assertForbidden();
    $this->get(route('oferta-flexible.usuarios.index'))->assertForbidden();
    $this->assertDatabaseCount('TB_CAMPANIAS', 0);
})->with(['Médico Triaje', 'Admisión']);

test('inactive administrator role cannot use the administrator menu bypass', function (): void {
    $actor = ofertaActor('Administrador', false);
    $role = Rol::create(['titulo' => 'Admisión', 'status' => 1]);
    $actor->roles()->attach($role->id, ['status' => 1]);

    $this
        ->actingAs($actor)
        ->get(route('oferta-flexible.campanias.index'))
        ->assertForbidden();
});

test('inactive administrator assignment is rejected by authentication', function (): void {
    $actor = ofertaActor('Administrador', true, false);

    $this
        ->actingAs($actor)
        ->get(route('oferta-flexible.campanias.index'))
        ->assertRedirect(route('login'));
});

test('administrator pages render the requested script selectors', function (
    string $name,
    string $selector,
): void {
    $actor = ofertaActor();

    $this
        ->actingAs($actor)
        ->get(route('oferta-flexible.'.$name))
        ->assertOk()
        ->assertSee('data-script="oferta_flexible/'.$selector.'"', false);
})->with([
    ['campanias.index', 'campanias/index'],
    ['campanias.create', 'campanias/form'],
    ['servicios.index', 'servicios/index'],
    ['servicios.create', 'servicios/form'],
    ['usuarios.index', 'usuarios/index'],
]);
