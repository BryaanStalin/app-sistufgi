<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class ConsultaDniApiService
{
    public function consultar(string $dni): ResultadoConsultaDni
    {
        if (! preg_match('/^[0-9]{8}$/D', $dni)) {
            return $this->resultado(ResultadoConsultaDni::error());
        }
        if (! config('services.dni_api.enabled')) {
            return ResultadoConsultaDni::noEncontrado();
        }
        $url = (string) config('services.dni_api.url');
        $token = (string) config('services.dni_api.token');
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! $token) {
            return $this->resultado(ResultadoConsultaDni::error(), configuracion: true);
        }
        try {
            $respuesta = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(min(3, $this->timeout()))
                ->timeout($this->timeout())
                ->retry(2, 100, fn (Throwable $error): bool => $error instanceof ConnectionException, throw: false)
                ->withOptions(['allow_redirects' => false])
                ->get($url, ['numeroDocumento' => $dni]);
        } catch (Throwable) {
            return $this->resultado(ResultadoConsultaDni::error());
        }
        $codigo = $respuesta->status();
        if (! $respuesta->successful()) {
            return $this->resultado(
                ResultadoConsultaDni::error(),
                $codigo,
                in_array($codigo, [401, 403], true),
            );
        }
        try {
            $json = json_decode($respuesta->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->resultado(ResultadoConsultaDni::error(), $codigo);
        }
        if (! is_array($json)) {
            return $this->resultado(ResultadoConsultaDni::error(), $codigo);
        }
        $datos = $json['data'] ?? [];
        if (empty($datos) || (is_array($datos) && empty($datos['numeroDocumento']))) {
            return $this->resultado(ResultadoConsultaDni::noEncontrado(), $codigo);
        }
        if (
            ! is_array($datos) ||
            ($datos['numeroDocumento'] ?? null) !== $dni ||
            ! in_array($json['codigo'] ?? null, ['200', 200], true)
        ) {
            return $this->resultado(ResultadoConsultaDni::error(), $codigo);
        }

        return $this->resultado(ResultadoConsultaDni::encontrado([
            'tipo_doc' => 'DNI',
            'dni' => $dni,
            'nombres' => PacienteService::normalizarNombre($datos['nombres'] ?? null),
            'apellidos' => trim(
                PacienteService::normalizarNombre($datos['apellidoPaterno'] ?? null).' '.
                PacienteService::normalizarNombre($datos['apellidoMaterno'] ?? null),
            ),
            'fecha_nacimiento' => $this->fechaNacimiento($datos['fechaNacimiento'] ?? null),
            'sexo' => $this->sexo($datos),
            'telefono' => null,
        ]), $codigo);
    }

    private function timeout(): int
    {
        return max(1, min(60, (int) config('services.dni_api.timeout', 8)));
    }

    private function fechaNacimiento(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }
        try {
            $fecha = CarbonImmutable::createFromFormat('!d/m/Y', trim($valor), 'America/Lima');
            if (
                ! $fecha ||
                $fecha->year < 1 ||
                $fecha->format('d/m/Y') !== trim($valor) ||
                $fecha->isFuture()
            ) {
                return null;
            }

            return $fecha->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $datos */
    private function sexo(array $datos): ?string
    {
        $id = $datos['idSexo'] ?? null;
        if ($id !== null && $id !== '') {
            return match ($id) {
                '1', 1 => 'M',
                '2', 2 => 'F',
                default => null,
            };
        }
        $sexo = mb_substr(PacienteService::normalizarNombre($datos['sexo'] ?? null), 0, 1);

        return in_array($sexo, ['M', 'F'], true) ? $sexo : null;
    }

    private function resultado(
        ResultadoConsultaDni $resultado,
        ?int $http = null,
        bool $configuracion = false,
    ): ResultadoConsultaDni {
        $contexto = ['resultado' => $resultado->estado, 'http' => $http];
        if ($configuracion) {
            Log::error('Configuración de consulta DNI inválida.', $contexto);
        } else {
            Log::info('Consulta DNI.', $contexto);
        }

        return $resultado;
    }
}
