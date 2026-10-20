<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
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
