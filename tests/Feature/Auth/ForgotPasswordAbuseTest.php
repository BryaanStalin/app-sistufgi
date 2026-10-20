<?php

use App\Mail\PasswordResetLink;
use App\Services\PasswordResetService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Tests\Feature\Auth\InteractsWithAuthFixtures;

uses(InteractsWithAuthFixtures::class);

beforeEach(function (): void {
    $this->setUpAuthFixtures();
});

afterEach(function (): void {
    $this->tearDownAuthFixtures();
});

test('Auth forgot password returns the same response for existing and missing emails', function (): void {
    $user = $this->createAuthUser();
    Mail::fake();

    $existing = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
    $missing = $this->postJson('/forgot-password', ['email' => 'missing@example.test'])->assertOk();

    expect($existing->json())->toBe($missing->json());
    Mail::assertSent(PasswordResetLink::class, fn (PasswordResetLink $mail): bool => $mail->hasTo($user->email));
    Mail::assertSentCount(1);
});

test('Auth forgot password respects broker throttle after email normalization', function (): void {
    $this->freezeTime();
    $user = $this->createAuthUser(['email' => 'fixture@example.test']);
    Mail::fake();
    $first = $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->json();
    $originalTokenHash = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

    $second = $this->postJson('/forgot-password', ['email' => '  FIXTURE@EXAMPLE.TEST  '])->assertOk();

    expect($second->json())->toBe($first);
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->value('token'))->toBe($originalTokenHash);
    Mail::assertSentCount(1);

    $this->travel(61)->seconds();
    $this->postJson('/forgot-password', ['email' => $user->email])->assertOk();
    expect(DB::table('password_reset_tokens')->where('email', $user->email)->value('token'))->not->toBe($originalTokenHash);
    Mail::assertSentCount(2);
});

test('Auth forgot password does not send a link for an inactive account', function (): void {
    $user = $this->createAuthUser(['status' => 0]);
    Mail::fake();

    $this->postJson('/forgot-password', ['email' => $user->email])
        ->assertOk()->assertJsonPath('success', true);

    Mail::assertNothingOutgoing();
    $this->assertDatabaseCount('password_reset_tokens', 0);
});

test('Auth forgot password blocks the fourth normalized email and IP attempt with 429', function (): void {
    $user = $this->createAuthUser(['email' => 'fixture@example.test']);
    Mail::fake();
    foreach ([$user->email, 'FIXTURE@EXAMPLE.TEST', ' fixture@example.test '] as $email) {
        $this->postJson('/forgot-password', ['email' => $email])->assertOk();
    }

    $this->postJson('/forgot-password', ['email' => $user->email])
        ->assertTooManyRequests()->assertHeader('Retry-After');

    Mail::assertSentCount(1);
});

test('Auth forgot password also applies the global IP limit', function (): void {
    Mail::fake();
    for ($attempt = 1; $attempt <= 20; $attempt++) {
        $this->postJson('/forgot-password', ['email' => 'missing'.$attempt.'@example.test'])->assertOk();
    }

    $this->postJson('/forgot-password', ['email' => 'another@example.test'])->assertTooManyRequests();

    Mail::assertNothingOutgoing();
});

test('Auth reset attempts are limited by email across different IP addresses', function (): void {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$attempt])
            ->postJson('/reset-password', [
                'email' => 'missing@example.test', 'token' => 'synthetic-token',
                'password' => 'AcceptedPass1', 'password_confirmation' => 'AcceptedPass1',
            ])->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
        ->postJson('/reset-password', [
            'email' => ' MISSING@EXAMPLE.TEST ', 'token' => 'synthetic-token',
            'password' => 'AcceptedPass1', 'password_confirmation' => 'AcceptedPass1',
        ])->assertTooManyRequests();
});

test('Auth reset attempts also apply the global IP limit', function (): void {
    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $this->postJson('/reset-password', [
            'email' => 'missing'.$attempt.'@example.test', 'token' => 'synthetic-token',
            'password' => 'AcceptedPass1', 'password_confirmation' => 'AcceptedPass1',
        ])->assertUnprocessable();
    }

    $this->postJson('/reset-password', [
        'email' => 'another@example.test', 'token' => 'synthetic-token',
        'password' => 'AcceptedPass1', 'password_confirmation' => 'AcceptedPass1',
    ])->assertTooManyRequests();
});

test('Auth reset mail uses the canonical URL even with a spoofed request host and writes no sensitive log', function (): void {
    config(['app.url' => 'https://canonical.example.test/base']);
    $user = $this->createAuthUser();
    Mail::fake();
    Log::spy();

    $this->postJson('http://audit.invalid/forgot-password', ['email' => $user->email])->assertOk();

    Mail::assertSent(PasswordResetLink::class, function (PasswordResetLink $mail) use ($user): bool {
        $parts = parse_url($mail->resetUrl);
        parse_str($parts['query'], $query);
        $mail->assertSeeInHtml('Restablecer contraseña');

        return $parts['scheme'] === 'https'
            && $parts['host'] === 'canonical.example.test'
            && str_starts_with($parts['path'], '/base/reset-password/')
            && $query['email'] === $user->email;
    });
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('log');
});

test('Auth hides delivery exception details and returns the generic response without logging them', function (): void {
    Mail::fake();
    Log::spy();
    $this->mock(PasswordResetService::class)
        ->shouldReceive('sendResetLink')->once()
        ->andThrow(new RuntimeException('Synthetic exception with a sensitive reset URL and payload'));

    $this->postJson('/forgot-password', ['email' => 'fixture@example.test'])->assertOk()->assertExactJson([
        'success' => true,
        'message' => 'Si el correo se encuentra registrado, recibirás un enlace para restablecer tu contraseña.',
    ]);

    Log::shouldHaveReceived('error')->once()->with('Password reset link delivery failed.');
    Mail::assertNothingOutgoing();
});

test('Auth trusted hosts rejects a spoofed host outside local and testing environments', function (): void {
    config(['app.url' => 'https://canonical.example.test']);
    expect(app(Kernel::class)->getGlobalMiddleware())->toContain(TrustHosts::class);
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('environment')->with('local')->andReturn(false);
    $application->shouldReceive('runningUnitTests')->andReturn(false);
    $middleware = new TrustHosts($application);

    expect(fn () => $middleware->handle(
        Request::create('https://audit.invalid/forgot-password'),
        fn (Request $request): string => $request->getHost()
    ))->toThrow(SuspiciousOperationException::class);
});

test('Auth trusted hosts accepts the configured Laragon hostname without trusting subdomains', function (): void {
    config(['app.url' => 'http://laragon-fixture.test']);
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('environment')->with('local')->andReturn(false);
    $application->shouldReceive('runningUnitTests')->andReturn(false);
    $middleware = new TrustHosts($application);

    expect($middleware->handle(
        Request::create('http://laragon-fixture.test/login'),
        fn (Request $request): string => $request->getHost()
    ))->toBe('laragon-fixture.test');

    expect(fn () => $middleware->handle(
        Request::create('http://sub.laragon-fixture.test/login'),
        fn (Request $request): string => $request->getHost()
    ))->toThrow(SuspiciousOperationException::class);
});
