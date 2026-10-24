<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PowerBiApi
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! self::matches($request)) {
            return $next($request);
        }
        $inicio = hrtime(true);
        try {
            $response = app(TrustProxies::class)->handle(
                $request,
                fn (Request $request): Response => $this->dispatch($request, $next),
            );
        } catch (Throwable $exception) {
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }
        $response->headers->set('Cache-Control', 'no-store');
        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        }
        $this->registrar($request, $response, $inicio);

        return $response->prepare($request);
    }

    public static function matches(Request $request): bool
    {
        return $request->is('api/powerbi', 'api/powerbi/*');
    }

    public static function renderError(Request $request, Throwable $exception): JsonResponse
    {
        $original = $exception instanceof HttpResponseException ? $exception->getResponse() : null;
        $status = $original?->getStatusCode() ??
            ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
        $message = match ($status) {
            404 => $request->is('api/powerbi/campanias/*') ? 'Campaña no encontrada' : 'No encontrado',
            405 => 'Método no permitido',
            429 => 'Demasiadas solicitudes',
            503 => 'Servicio no disponible',
            default => 'No se pudo completar la solicitud',
        };
        $headers = $original?->headers->all() ??
            ($exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []);
        $headers = array_intersect_key(
            array_change_key_case($headers),
            array_flip(['allow', 'retry-after', 'www-authenticate']),
        );

        return response()->json(['message' => $message], $status, [
            ...$headers,
            'Cache-Control' => 'no-store',
        ], JSON_UNESCAPED_UNICODE);
    }

    /** @param Closure(Request): Response $next */
    private function dispatch(Request $request, Closure $next): Response
    {
        if (! config('services.powerbi_api.enabled') || ! $this->configured()) {
            return response()->json(['message' => 'No encontrado'], 404);
        }
        if (! in_array($request->getRealMethod(), ['GET', 'HEAD'], true)) {
            return response()->json(['message' => 'Método no permitido'], 405, ['Allow' => 'GET, HEAD']);
        }
        if (config('services.powerbi_api.require_https', true) && ! $request->isSecure()) {
            return response()->json(['message' => 'Se requiere HTTPS'], 403);
        }

        return app(ThrottleRequests::class)->handle(
            $request,
            fn (Request $request): Response => $this->authenticate($request, $next),
            'powerbi-api',
        );
    }

    /** @param Closure(Request): Response $next */
    private function authenticate(Request $request, Closure $next): Response
    {
        $ip = hash('sha256', (string) $request->ip());
        $bloqueo = 'powerbi:bloqueo:'.$ip;
        $fallos = 'powerbi:fallos:'.$ip;
        if (RateLimiter::tooManyAttempts($bloqueo, 1)) {
            return response()->json(['message' => 'Demasiadas solicitudes'], 429, [
                'Retry-After' => (string) max(1, RateLimiter::availableIn($bloqueo)),
            ]);
        }
        if (! $this->validCredentials($request)) {
            RateLimiter::hit($fallos, 60);
            if (RateLimiter::tooManyAttempts($fallos, 10)) {
                RateLimiter::hit($bloqueo, 60);
            }

            return response()->json(['message' => 'No autorizado'], 401, [
                'WWW-Authenticate' => 'Basic realm="powerbi"',
            ]);
        }

        return $next($request);
    }

    private function configured(): bool
    {
        return (string) config('services.powerbi_api.key') !== '' ||
            ((string) config('services.powerbi_api.user') !== '' &&
                (string) config('services.powerbi_api.password') !== '');
    }

    private function validCredentials(Request $request): bool
    {
        $key = (string) config('services.powerbi_api.key');
        $keyMatches = $key !== '' && hash_equals($key, (string) $request->header('X-Api-Key', ''));
        $basicMatches = $this->validBasic((string) $request->header('Authorization', ''));

        return $keyMatches || $basicMatches;
    }

    private function validBasic(string $header): bool
    {
        $user = (string) config('services.powerbi_api.user');
        $password = (string) config('services.powerbi_api.password');
        if ($user === '' || $password === '' || ! preg_match('/^Basic\s+([A-Za-z0-9+\/=]+)$/iD', $header, $partes)) {
            return false;
        }
        $credentials = base64_decode($partes[1], true);
        if ($credentials === false || ! str_contains($credentials, ':')) {
            return false;
        }
        [$providedUser, $providedPassword] = explode(':', $credentials, 2);
        $userMatches = hash_equals($user, $providedUser);
        $passwordMatches = hash_equals($password, $providedPassword);

        return $userMatches && $passwordMatches;
    }

    private function registrar(Request $request, Response $response, int $inicio): void
    {
        $segmentos = $request->segments();
        $accion = $segmentos[4] ?? '';
        $esCampania = ($segmentos[2] ?? '') === 'campanias' && in_array($accion, ['resumen', 'servicios'], true);
        $campania = $esCampania ? filter_var($segmentos[3] ?? '', FILTER_VALIDATE_INT) : false;
        $ruta = $esCampania ? '/api/powerbi/campanias/{campania}/'.$accion : '/api/powerbi';
        Log::channel('powerbi')->info('Solicitud Power BI.', [
            'fecha' => now('America/Lima')->toIso8601String(),
            'ip' => $request->ip(),
            'ruta' => $ruta,
            'campania' => $campania === false ? null : $campania,
            'codigo_http' => $response->getStatusCode(),
            'duracion_ms' => round((hrtime(true) - $inicio) / 1000000, 3),
        ]);
    }
}
