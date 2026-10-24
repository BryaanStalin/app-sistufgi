<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => Password::min(8)->mixedCase()->numbers());

        RateLimiter::for('powerbi-api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('powerbi-ip:'.$request->ip())
            ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                'message' => 'Demasiadas solicitudes',
            ], 429, $headers)));

        RateLimiter::for('oferta-flexible-dni', function (Request $request): Limit {
            if ($request->input('accion') !== 'buscar' || $request->input('tipo_doc') !== 'DNI') {
                return Limit::none();
            }

            return Limit::perMinute(20)
                ->by('dni-user:'.$request->user()->id)
                ->response(fn (Request $request, array $headers): JsonResponse => response()->json([
                    'success' => false,
                    'message' => 'Espere un minuto antes de volver a buscar un DNI.',
                ], 429, $headers));
        });

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinutes(5, 3)->by('account-ip:'.$this->accountKey($request, 'user').'|'.$request->ip()),
            Limit::perMinutes(5, 30)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('forgot-password', fn (Request $request): array => [
            Limit::perMinute(3)->by('email-ip:'.$this->accountKey($request, 'email').'|'.$request->ip()),
            Limit::perMinute(20)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('reset-password', fn (Request $request): array => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.$this->accountKey($request, 'email')),
        ]);
    }

    private function accountKey(Request $request, string $field): string
    {
        $identifier = $request->input($field);

        return hash('sha256', Str::lower(trim(is_string($identifier) ? $identifier : '')));
    }
}
