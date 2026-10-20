<?php

use App\Models\Menu;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (app()->configurationIsCached() || ! app()->environment('testing')
        || config('database.default') !== 'sqlite'
        || config('database.connections.sqlite.database') !== ':memory:'
        || ! empty(config('database.connections.sqlite.url'))) {
        throw new LogicException('Administrator tests require uncached SQLite :memory:.');
    }

    config([
        'database.connections' => ['sqlite' => config('database.connections.sqlite')],
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'cache.default' => 'array', 'session.driver' => 'array',
    ]);
    $this->withoutVite();
    app()->setLocale('es');

    Schema::create('TB_ACCESO_USUARIO', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('nombre');
        $table->string('user')->unique();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('DNI')->nullable();
        $table->boolean('status')->default(true);
        $table->rememberToken();
        $table->timestamps();
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->integer('deleted_by')->nullable();
    });
    Schema::create('TB_ACCESO_ROL', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('titulo');
        $table->text('descripcion')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->integer('deleted_by')->nullable();
    });
    Schema::create('TB_ACCESO_MENU', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('titulo');
        $table->string('link');
        $table->string('icono')->nullable();
        $table->text('descripcion')->nullable();
        $table->integer('orden')->default(0);
        $table->integer('padre_id')->nullable();
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->integer('deleted_by')->nullable();
    });
    Schema::create('TB_ACCESO_USUARIO_X_ROL', function (Blueprint $table): void {
        $table->integer('usuario_id');
        $table->integer('rol_id');
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->integer('deleted_by')->nullable();
        $table->primary(['usuario_id', 'rol_id']);
    });
    Schema::create('TB_ACCESO_MENU_X_ROL', function (Blueprint $table): void {
        $table->integer('menu_principal_id');
        $table->integer('rol_id');
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->integer('deleted_by')->nullable();
        $table->primary(['menu_principal_id', 'rol_id']);
    });
    Schema::create('TB_EESS', function (Blueprint $table): void {
        $table->increments('ID_EESS');
        $table->string('RENIPRESS')->unique();
        $table->string('NOM_EESS');
        $table->boolean('ACTIVO')->default(true);
    });
    Schema::create('TB_ACCESO_USUARIO_X_EESS', function (Blueprint $table): void {
        $table->increments('id_usuario_x_eess');
        $table->integer('id_usuario');
        $table->string('RENIPRESS');
        $table->boolean('activo')->default(true);
        $table->timestamps();
        $table->integer('created_by')->nullable();
        $table->integer('updated_by')->nullable();
        $table->unique(['id_usuario', 'RENIPRESS']);
    });
});

function administratorActor(string $title = 'Administrador'): User
{
    $actor = User::factory()->make([
        'nombre' => 'Administrador de prueba', 'user' => 'admin-fixture', 'status' => 1,
    ]);
    unset($actor->name, $actor->email_verified_at);
    $actor->save();
    $role = Rol::query()->create(['titulo' => $title, 'status' => 1]);
    $actor->roles()->attach($role->id, ['status' => 1]);

    return $actor;
}

/** @return array{nombre: string, user: string, email: string, DNI: string, password: string, password_confirmation: string, status: int, role: int} */
function administratorUserData(int $roleId): array
{
    return [
        'nombre' => 'Usuario de prueba', 'user' => 'usuario-fixture', 'email' => 'usuario@example.test',
        'DNI' => '12345678', 'password' => 'AcceptedPass1', 'password_confirmation' => 'AcceptedPass1',
        'status' => 1, 'role' => $roleId,
    ];
}

test('administrator routes redirect unauthenticated visitors to login', function (string $module): void {
    $this->get(route($module.'.index'))->assertRedirect(route('login'));
})->with(['usuarios', 'menus', 'roles']);

test('administrator routes retain menu access restrictions', function (string $module): void {
    $actor = administratorActor('Sin permisos');

    $this->actingAs($actor)->get(route($module.'.index'))->assertRedirect(route('dashboard'));
})->with(['usuarios', 'menus', 'roles']);

test('administrator index and form pages render their Vite page selectors', function (string $module): void {
    $actor = administratorActor();

    $this->actingAs($actor)->get(route($module.'.index'))->assertOk()
        ->assertSee('data-page="'.$module.'/index"', false)
        ->assertSee('id="'.$module.'-table"', false)
        ->assertDontSee('cdn.datatables.net', false);
    $this->get(route($module.'.create'))->assertOk()
        ->assertSee('data-page="'.$module.'/form"', false)->assertSee('data-select2', false);
})->with(['usuarios', 'menus', 'roles']);

test('user creation persists one role, a hashed password and the exact establishment code', function (): void {
    $actor = administratorActor();
    $role = Rol::query()->create(['titulo' => 'Profesional', 'status' => 1]);
    DB::table('TB_EESS')->insert(['RENIPRESS' => '00001234', 'NOM_EESS' => 'Centro de salud']);

    $this->actingAs($actor)->post(route('usuarios.store'), administratorUserData($role->id) + ['eess' => '00001234'])
        ->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors()->assertSessionHas('success');

    $user = User::query()->where('user', 'usuario-fixture')->firstOrFail();
    expect(Hash::check('AcceptedPass1', $user->password))->toBeTrue();
    expect($user->roles->pluck('id')->all())->toBe([$role->id]);
    $this->assertDatabaseHas('TB_ACCESO_USUARIO_X_EESS', ['id_usuario' => $user->id, 'RENIPRESS' => '00001234', 'activo' => 1]);
});

test('user requests reject passwords below the shared policy with 422', function (string $operation, string $password, string $message): void {
    $actor = administratorActor();
    $payload = administratorUserData($actor->roles->first()->id);
    $payload['password'] = $password;
    $payload['password_confirmation'] = $password;
    $originalHash = $actor->password;

    $response = $operation === 'store'
        ? $this->actingAs($actor)->postJson(route('usuarios.store'), $payload)
        : $this->actingAs($actor)->putJson(route('usuarios.update', $actor), $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors('password')->assertJsonPath('errors.password.0', $message);
    expect($actor->refresh()->password)->toBe($originalHash);
    $this->assertDatabaseMissing('TB_ACCESO_USUARIO', ['user' => 'usuario-fixture']);
})->with(['store', 'update'])->with([
    'short' => ['Short1A', 'La contraseña debe tener al menos 8 caracteres.'],
    'no number' => ['OnlyLetters', 'La contraseña debe contener al menos un número.'],
    'no uppercase' => ['lowercase1', 'La contraseña debe contener al menos una letra mayúscula y una minúscula.'],
    'no lowercase' => ['UPPERCASE1', 'La contraseña debe contener al menos una letra mayúscula y una minúscula.'],
]);

test('user requests require matching password confirmation with 422', function (string $operation, ?string $confirmation): void {
    $actor = administratorActor();
    $payload = administratorUserData($actor->roles->first()->id);
    $payload['password_confirmation'] = $confirmation;

    $response = $operation === 'store'
        ? $this->actingAs($actor)->postJson(route('usuarios.store'), $payload)
        : $this->actingAs($actor)->putJson(route('usuarios.update', $actor), $payload);

    $response->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->assertDatabaseMissing('TB_ACCESO_USUARIO', ['user' => 'usuario-fixture']);
})->with(['store', 'update'])->with([null, 'DifferentPass2']);

test('user creation requires password and confirmation with 422', function (): void {
    $actor = administratorActor();
    $payload = administratorUserData($actor->roles->first()->id);
    unset($payload['password'], $payload['password_confirmation']);

    $this->actingAs($actor)->postJson(route('usuarios.store'), $payload)->assertUnprocessable()
        ->assertJsonValidationErrors(['password', 'password_confirmation']);
    $this->assertDatabaseMissing('TB_ACCESO_USUARIO', ['user' => 'usuario-fixture']);
});

test('user updates preserve a blank or omitted password and change it only when supplied', function (?string $password): void {
    $actor = administratorActor();
    $role = Rol::query()->create(['titulo' => 'Profesional', 'status' => 1]);
    $payload = administratorUserData($role->id);
    unset($payload['password'], $payload['password_confirmation']);
    if ($password !== null) {
        $payload += ['password' => $password, 'password_confirmation' => $password];
    }
    $originalHash = $actor->password;

    $this->actingAs($actor)->put(route('usuarios.update', $actor), $payload)
        ->assertRedirect(route('usuarios.index'))->assertSessionHasNoErrors()->assertSessionHas('success');

    $actor->refresh();
    if ($password === 'AcceptedPass1') {
        expect(Hash::check($password, $actor->password))->toBeTrue();
    } else {
        expect($actor->password)->toBe($originalHash);
    }
    expect($actor->roles->pluck('id')->all())->toBe([$role->id]);
    $this->assertDatabaseCount('TB_ACCESO_USUARIO_X_ROL', 2);
    $this->assertDatabaseHas('TB_ACCESO_USUARIO_X_ROL', ['usuario_id' => $actor->id, 'rol_id' => $role->id, 'status' => 1]);
})->with([null, '', 'AcceptedPass1']);

test('user deletion retains the user and inactivates its role assignment', function (): void {
    $actor = administratorActor();
    $roleId = $actor->roles->first()->id;

    $this->actingAs($actor)->deleteJson(route('usuarios.destroy', $actor))->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('TB_ACCESO_USUARIO', ['id' => $actor->id, 'status' => 0, 'deleted_by' => $actor->id]);
    $this->assertDatabaseHas('TB_ACCESO_USUARIO_X_ROL', ['usuario_id' => $actor->id, 'rol_id' => $roleId, 'status' => 0]);
});

test('menu CRUD persists edits and uses logical deletion for the menu and its assignments', function (): void {
    $actor = administratorActor();
    $data = ['titulo' => 'Prueba', 'link' => '/prueba', 'orden' => 0, 'status' => 1];
    $this->actingAs($actor)->post(route('menus.store'), $data)->assertRedirect(route('menus.index'))->assertSessionHas('success');
    $menu = Menu::query()->where('link', '/prueba')->firstOrFail();
    $actor->roles->first()->menus()->attach($menu->id, ['status' => 1]);

    $this->put(route('menus.update', $menu), array_replace($data, ['titulo' => 'Actualizado']))
        ->assertRedirect(route('menus.index'))->assertSessionHas('success');
    $this->assertDatabaseHas('TB_ACCESO_MENU', ['id' => $menu->id, 'titulo' => 'Actualizado']);
    $this->get(route('menus.edit', $menu))->assertOk()->assertSee('Actualizado');
    $this->deleteJson(route('menus.destroy', $menu))->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('TB_ACCESO_MENU', ['id' => $menu->id, 'status' => 0, 'deleted_by' => $actor->id]);
    $this->assertDatabaseHas('TB_ACCESO_MENU_X_ROL', ['menu_principal_id' => $menu->id, 'status' => 0]);
});

test('role CRUD retains and reactivates menu assignments without granting removed menus', function (): void {
    $actor = administratorActor();
    $menu = Menu::query()->create(['titulo' => 'Acceso de prueba', 'link' => '/prueba', 'orden' => 1, 'status' => 1]);
    $data = ['titulo' => 'Profesional', 'status' => 1, 'menus' => [$menu->id]];
    $this->actingAs($actor)->post(route('roles.store'), $data)->assertRedirect(route('roles.index'))->assertSessionHas('success');
    $role = Rol::query()->where('titulo', 'Profesional')->firstOrFail();
    $this->post(route('usuarios.store'), administratorUserData($role->id))->assertSessionHas('success');
    $user = User::query()->where('user', 'usuario-fixture')->firstOrFail();
    expect($user->getAllowedMenuPaths())->toBe(['/prueba']);

    $this->put(route('roles.update', $role), ['titulo' => 'Profesional actualizado', 'status' => 1])
        ->assertRedirect(route('roles.index'))->assertSessionHas('success');
    $this->assertDatabaseHas('TB_ACCESO_MENU_X_ROL', ['rol_id' => $role->id, 'menu_principal_id' => $menu->id, 'status' => 0]);
    expect($role->fresh()->menus)->toHaveCount(0);
    expect($menu->fresh()->roles)->toHaveCount(0);
    expect($user->getAllowedMenuPaths())->toBe([]);
    $this->get(route('roles.edit', $role))->assertOk()->assertSee('Profesional actualizado');
    $this->put(route('roles.update', $role), $data)->assertRedirect(route('roles.index'))->assertSessionHas('success');
    $this->assertDatabaseCount('TB_ACCESO_MENU_X_ROL', 1);
    expect($role->fresh()->menus)->toHaveCount(1);
    expect($user->getAllowedMenuPaths())->toBe(['/prueba']);
    $this->deleteJson(route('roles.destroy', $role))->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseHas('TB_ACCESO_ROL', ['id' => $role->id, 'status' => 0, 'deleted_by' => $actor->id]);
    $this->assertDatabaseHas('TB_ACCESO_MENU_X_ROL', ['rol_id' => $role->id, 'status' => 0]);
    expect($user->getAllowedMenuPaths())->toBe([]);
});

test('user creation rolls back when an assignment cannot be persisted and never flashes passwords', function (): void {
    $actor = administratorActor();
    Schema::drop('TB_ACCESO_USUARIO_X_EESS');

    $this->actingAs($actor)->from(route('usuarios.create'))
        ->post(route('usuarios.store'), administratorUserData($actor->roles->first()->id))
        ->assertRedirect(route('usuarios.create'))->assertSessionHas('error')
        ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');

    $this->assertDatabaseMissing('TB_ACCESO_USUARIO', ['user' => 'usuario-fixture']);
    $this->assertDatabaseCount('TB_ACCESO_USUARIO_X_ROL', 1);
});

test('menu updates reject self parenting and descendant cycles with 422', function (bool $useDescendant): void {
    $actor = administratorActor();
    $parent = Menu::query()->create(['titulo' => 'Padre', 'link' => '/padre', 'orden' => 1, 'status' => 1]);
    $child = Menu::query()->create(['titulo' => 'Hijo', 'link' => '/hijo', 'orden' => 1, 'status' => 1, 'padre_id' => $parent->id]);

    $this->actingAs($actor)->putJson(route('menus.update', $parent), [
        'titulo' => 'Padre', 'link' => '/padre', 'orden' => 1, 'status' => 1,
        'padre_id' => $useDescendant ? $child->id : $parent->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('padre_id');

    $this->assertDatabaseHas('TB_ACCESO_MENU', ['id' => $parent->id, 'padre_id' => null]);
})->with([false, true]);

test('user updates reactivate existing role and establishment assignments without duplicates', function (): void {
    $actor = administratorActor();
    $firstRole = Rol::query()->create(['titulo' => 'Profesional', 'status' => 1]);
    $secondRole = Rol::query()->create(['titulo' => 'Coordinador', 'status' => 1]);
    DB::table('TB_EESS')->insert(['RENIPRESS' => '00001234', 'NOM_EESS' => 'Centro de salud']);
    $payload = administratorUserData($firstRole->id) + ['eess' => '00001234'];
    $this->actingAs($actor)->post(route('usuarios.store'), $payload)->assertSessionHas('success');
    $user = User::query()->where('user', 'usuario-fixture')->firstOrFail();

    $this->put(route('usuarios.update', $user), array_replace($payload, [
        'role' => $secondRole->id, 'eess' => '', 'password' => '', 'password_confirmation' => '',
    ]))->assertSessionHas('success');
    $this->assertDatabaseHas('TB_ACCESO_USUARIO_X_EESS', ['id_usuario' => $user->id, 'RENIPRESS' => '00001234', 'activo' => 0]);
    $this->put(route('usuarios.update', $user), $payload)->assertSessionHas('success');

    expect($user->fresh()->roles->pluck('id')->all())->toBe([$firstRole->id]);
    $this->assertDatabaseCount('TB_ACCESO_USUARIO_X_ROL', 3);
    $this->assertDatabaseCount('TB_ACCESO_USUARIO_X_EESS', 1);
    $this->assertDatabaseHas('TB_ACCESO_USUARIO_X_EESS', ['id_usuario' => $user->id, 'RENIPRESS' => '00001234', 'activo' => 1]);
});

test('role editing preserves an empty menu selection after validation failure', function (): void {
    $actor = administratorActor();
    $menu = Menu::query()->create(['titulo' => 'Menú seleccionado', 'link' => '/prueba', 'orden' => 1, 'status' => 1]);
    $role = Rol::query()->create(['titulo' => 'Profesional', 'status' => 1]);
    $role->menus()->attach($menu->id, ['status' => 1]);
    $this->actingAs($actor)->from(route('roles.edit', $role))->put(route('roles.update', $role), ['titulo' => '', 'status' => 1])
        ->assertSessionHasErrors('titulo');

    $response = $this->get(route('roles.edit', $role))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//select[@id="menus"]/optgroup/option[@selected]')->length)->toBe(0);
});
