<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Auth\InteractsWithAuthFixtures;

uses(InteractsWithAuthFixtures::class);

beforeEach(function (): void {
    $this->setUpAuthFixtures();
});

afterEach(function (): void {
    $this->tearDownAuthFixtures();
});

test('Auth rejects passwords below the reset policy with Spanish messages and 422', function (string $password, array $messages): void {
    app()->setLocale('es');

    $response = $this->postJson('/reset-password', [
        'email' => 'fixture@example.test',
        'token' => 'synthetic-token',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertUnprocessable()->assertJsonValidationErrors('password')
        ->assertJsonPath('errors.password', $messages);

    expect($response->json('message'))->toStartWith($messages[0])
        ->not->toContain('The password', 'and 2 more errors');
})->with([
    'one character' => ['x', [
        'La contraseña debe tener al menos 8 caracteres.',
        'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
        'La contraseña debe contener al menos un número.',
    ]],
    'seven characters' => ['Short1A', ['La contraseña debe tener al menos 8 caracteres.']],
    'missing number' => ['OnlyLetters', ['La contraseña debe contener al menos un número.']],
    'missing uppercase' => ['lowercase1', ['La contraseña debe contener al menos una letra mayúscula y una minúscula.']],
    'missing lowercase' => ['UPPERCASE1', ['La contraseña debe contener al menos una letra mayúscula y una minúscula.']],
]);

test('Auth accepts a policy-compliant confirmed password', function (): void {
    $user = $this->createAuthUser();
    $token = Password::createToken($user);

    $this->postJson('/reset-password', [
        'email' => $user->email, 'token' => $token,
        'password' => 'AcceptedPass1', 'password_confirmation' => 'AcceptedPass1',
    ])->assertOk();

    expect(Hash::check('AcceptedPass1', $user->refresh()->password))->toBeTrue();
});

test('Auth requires matching password confirmation', function (): void {
    $this->postJson('/reset-password', [
        'email' => 'fixture@example.test', 'token' => 'synthetic-token',
        'password' => 'AcceptedPass1', 'password_confirmation' => 'DifferentPass2',
    ])->assertUnprocessable()->assertJsonValidationErrors('password')
        ->assertJsonPath('errors.password.0', 'Las contraseñas no coinciden.');
});
