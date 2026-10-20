<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\LogNavigation;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\ValidateMenuAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->trustHosts(at: fn (): array => [
            '^'.preg_quote((string) parse_url((string) config('app.url'), PHP_URL_HOST)).'$',
        ], subdomains: false);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'menu.access' => ValidateMenuAccess::class,
            'throttle' => ThrottleRequests::class,
            'log.navigation' => LogNavigation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado',
                    'errors' => ['auth' => ['Sesión expirada o no iniciada']],
                ], 401);
            }
        });
    })->create();
