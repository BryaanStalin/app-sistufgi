<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

trait InteractsWithAuthFixtures
{
    protected function setUpAuthFixtures(): void
    {
        if ($this->app->configurationIsCached()
            || ! $this->app->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || ! empty(config('database.connections.sqlite.url'))) {
            throw new LogicException('Auth tests require uncached SQLite :memory: configuration. No other database is allowed.');
        }

        config([
            'database.connections' => ['sqlite' => config('database.connections.sqlite')],
            'app.url' => 'http://localhost',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'cache.default' => 'array',
            'mail.default' => 'array',
            'session.driver' => 'array',
            'session.serialization' => 'json',
            'session.connection' => 'sqlite',
        ]);

        $this->withoutVite();

        Schema::create('TB_ACCESO_USUARIO', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nombre');
            $table->string('user');
            $table->string('email');
            $table->string('password');
            $table->boolean('status')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('TB_ACCESO_ROL', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('titulo');
            $table->boolean('status')->default(true);
        });

        Schema::create('TB_ACCESO_USUARIO_X_ROL', function (Blueprint $table): void {
            $table->integer('usuario_id');
            $table->integer('rol_id');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->bigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('payload');
            $table->integer('last_activity');
        });
    }

    protected function tearDownAuthFixtures(): void
    {
        Request::setTrustedHosts([]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createAuthUser(array $attributes = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'nombre' => 'Auth Fixture',
            'user' => 'fixture-'.Str::random(8),
            'email' => Str::lower(Str::random(8)).'@example.test',
            'password' => 'PreviousPassword1',
            'status' => 1,
            'remember_token' => Str::random(60),
        ], $attributes));
        $user->save();

        $roleId = DB::table('TB_ACCESO_ROL')->insertGetId(['titulo' => 'Fixture Role', 'status' => 1]);
        DB::table('TB_ACCESO_USUARIO_X_ROL')->insert([
            'usuario_id' => $user->id,
            'rol_id' => $roleId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function createPreviousAuthSession(User $user): string
    {
        $sessionId = Str::random(40);
        DB::table('sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'payload' => base64_encode(json_encode([
                Auth::guard()->getName() => $user->id,
                '_token' => Str::random(40),
            ], JSON_THROW_ON_ERROR)),
            'last_activity' => now()->timestamp,
        ]);

        return $sessionId;
    }

    protected function readPreviousAuthSession(string $sessionId): Store
    {
        $session = new Store('auth-fixture', new DatabaseSessionHandler(DB::connection(), 'sessions', 120), $sessionId, 'json');
        $session->start();

        return $session;
    }
}
