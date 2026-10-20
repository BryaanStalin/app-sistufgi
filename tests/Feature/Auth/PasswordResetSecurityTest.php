<?php

use App\Services\PasswordResetService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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

test('Auth reset changes the password once, rotates remember token, dispatches the event and revokes previous sessions', function (): void {
    config(['session.driver' => 'database']);
    $user = $this->createAuthUser();
    $other = $this->createAuthUser();
    $originalRememberToken = $user->remember_token;
    $previousSession = $this->createPreviousAuthSession($user);
    $otherSession = $this->createPreviousAuthSession($other);
    $guardKey = Auth::guard()->getName();
    expect($this->readPreviousAuthSession($previousSession)->get($guardKey))->toBe($user->id);
    $token = Password::createToken($user);
    Event::fake([PasswordReset::class]);

    $this->postJson('/reset-password', [
        'email' => $user->email,
        'token' => $token,
        'password' => 'NewPassword2',
        'password_confirmation' => 'NewPassword2',
    ])->assertOk()->assertJsonPath('success', true);

    $user->refresh();
    expect(Hash::check('NewPassword2', $user->password))->toBeTrue();
    expect(Hash::check('PreviousPassword1', $user->password))->toBeFalse();
    expect($user->remember_token)->not->toBe($originalRememberToken)->toHaveLength(60);
    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseMissing('sessions', ['id' => $previousSession]);
    expect($this->readPreviousAuthSession($previousSession)->get($guardKey))->toBeNull();
    expect($this->readPreviousAuthSession($otherSession)->get($guardKey))->toBe($other->id);
});

test('Auth returns the same 422 error for invalid reset credentials without changing the account', function (string $failure): void {
    $this->freezeTime();
    $user = $this->createAuthUser();
    $originalHash = $user->password;
    $originalRememberToken = $user->remember_token;
    $token = Password::createToken($user);
    $other = $failure === 'another account' ? $this->createAuthUser() : null;
    $otherHash = $other?->password;
    $email = match ($failure) {
        'another account' => $other->email,
        'unknown email' => 'missing@example.test',
        'invalid email' => 'not-an-email',
        'array email' => ['fixture@example.test'],
        default => $user->email,
    };

    if ($failure === 'expired token') {
        $this->travel(6)->minutes();
    }

    Event::fake([PasswordReset::class]);

    $this->postJson('/reset-password', [
        'email' => $email,
        'token' => match ($failure) {
            'altered token' => str_repeat('0', 64),
            'missing token' => null,
            default => $token,
        },
        'password' => 'NewPassword2',
        'password_confirmation' => 'NewPassword2',
    ])->assertUnprocessable()->assertExactJson([
        'success' => false,
        'status' => 422,
        'message' => 'El enlace de recuperación no es válido o ha expirado. Solicita uno nuevo.',
    ]);

    $user->refresh();
    expect($user->password)->toBe($originalHash);
    expect($user->remember_token)->toBe($originalRememberToken);
    if ($other !== null) {
        expect($other->refresh()->password)->toBe($otherHash);
    }
    Event::assertNotDispatched(PasswordReset::class);
})->with(['another account', 'altered token', 'expired token', 'unknown email', 'invalid email', 'array email', 'missing token']);

test('Auth rejects a reused reset token without changing the successful reset', function (): void {
    $user = $this->createAuthUser();
    $token = Password::createToken($user);
    Event::fake([PasswordReset::class]);
    $payload = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword2', 'password_confirmation' => 'NewPassword2'];
    $this->postJson('/reset-password', $payload)->assertOk();
    $successfulHash = $user->refresh()->password;

    $this->postJson('/reset-password', array_replace($payload, [
        'password' => 'AnotherPassword3',
        'password_confirmation' => 'AnotherPassword3',
    ]))->assertUnprocessable()->assertExactJson([
        'success' => false,
        'status' => 422,
        'message' => 'El enlace de recuperación no es válido o ha expirado. Solicita uno nuevo.',
    ]);

    expect($user->refresh()->password)->toBe($successfulHash);
    Event::assertDispatchedTimes(PasswordReset::class, 1);
});

test('Auth rolls back the password when database session revocation fails', function (): void {
    config(['session.driver' => 'database', 'session.table' => 'missing_auth_sessions']);
    $user = $this->createAuthUser();
    $originalHash = $user->password;
    $originalRememberToken = $user->remember_token;
    $token = Password::createToken($user);
    Event::fake([PasswordReset::class]);

    expect(fn (): array => app(PasswordResetService::class)->resetPassword([
        'email' => $user->email, 'token' => $token, 'password' => 'NewPassword2',
    ]))->toThrow(QueryException::class);

    $user->refresh();
    expect($user->password)->toBe($originalHash);
    expect($user->remember_token)->toBe($originalRememberToken);
    expect(Password::tokenExists($user, $token))->toBeTrue();
    Event::assertNotDispatched(PasswordReset::class);
});
