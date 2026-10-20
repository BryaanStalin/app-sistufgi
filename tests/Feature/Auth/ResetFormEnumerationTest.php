<?php

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

test('Auth reset form does not query users or expose their login identifier', function (): void {
    $user = $this->createAuthUser(['email' => 'fixture@example.test', 'user' => 'private-login-identifier']);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $this->get('/reset-password/random-token?email='.urlencode($user->email))
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee($user->email)
        ->assertDontSee('name="user"', false)
        ->assertDontSee($user->user)
        ->assertDontSee($user->nombre);

    expect(collect($queries)->filter(
        fn (string $sql): bool => str_contains(strtolower($sql), 'tb_acceso_usuario')
    ))->toBeEmpty();
});

test('Auth reset form treats the email query string only as escaped form data', function (): void {
    $this->get('/reset-password/random-token?email='.urlencode('"><script>alert(1)</script>'))
        ->assertOk()->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('name="user"', false);
});
