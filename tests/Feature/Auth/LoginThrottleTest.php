<?php

use App\Services\AuthService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Auth\InteractsWithAuthFixtures;

uses(InteractsWithAuthFixtures::class);

beforeEach(function (): void {
    $this->setUpAuthFixtures();
});

afterEach(function (): void {
    $this->tearDownAuthFixtures();
});

test('Auth fixture refuses an unsafe connection before executing any query', function (): void {
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    config(['database.default' => 'forbidden-connection']);

    try {
        expect(fn () => $this->setUpAuthFixtures())->toThrow(LogicException::class);
    } finally {
        config(['database.default' => 'sqlite']);
    }

    expect($queries)->toBeEmpty();
});

test('Auth login limits normalized accounts independently behind the same IP', function (): void {
    $this->mock(AuthService::class)->shouldReceive('authenticate')->andReturnNull();
    foreach (['AccountA', ' accounta ', 'ACCOUNTA'] as $identifier) {
        $this->postJson('/login', ['user' => $identifier, 'password' => 'WrongPassword1'])
            ->assertUnprocessable()->assertJsonPath('message', 'Usuario o contraseña inválidos');
    }

    $this->postJson('/login', ['user' => 'accounta', 'password' => 'WrongPassword1'])
        ->assertTooManyRequests()->assertHeader('Retry-After');
    $this->postJson('/login', ['user' => 'accountb', 'password' => 'WrongPassword1'])
        ->assertUnprocessable()->assertJsonPath('message', 'Usuario o contraseña inválidos');
});

test('Auth login applies the IP limit even when identifiers change', function (): void {
    $this->mock(AuthService::class)->shouldReceive('authenticate')->andReturnNull();
    for ($attempt = 1; $attempt <= 30; $attempt++) {
        $this->postJson('/login', ['user' => 'account'.$attempt, 'password' => 'WrongPassword1'])
            ->assertUnprocessable();
    }

    $this->postJson('/login', ['user' => 'another-account', 'password' => 'WrongPassword1'])
        ->assertTooManyRequests();
});
