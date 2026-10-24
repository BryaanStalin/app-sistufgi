<?php

namespace App\Services;

use App\Models\PacienteMock;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PacienteService
{
    public const TIPOS_DOCUMENTO = ['CNV', 'DNI', 'CE', 'S/ DOCUMENTO', 'DIE', 'PASS'];

    public function __construct(protected ConsultaDniApiService $api) {}

    /** @return array<string, int|string|null>|null */
    public function buscar(string $tipo, ?string $numero): ?array
    {
        if ($tipo === 'S/ DOCUMENTO') {
            return null;
        }
        $paciente = $this->pacienteLocal($tipo, $numero);

        return $paciente ? $this->datos($paciente) : null;
    }

    /** @return array<string, int|string|null>|null */
    public function buscarDocumentoLocal(string $tipo, string $numero): ?array
    {
        $paciente = $this->pacienteLocal($tipo, $numero);

        return $paciente ? $this->datos($paciente) : null;
    }

    protected function generarCodigoSinDocumento(): string
    {
        return 'SD-'.substr(strtoupper(bin2hex(random_bytes(12))), 0, 12);
    }

    private function codigoSinDocumento(): string
    {
        do {
            $codigo = $this->generarCodigoSinDocumento();
        } while ($this->pacienteLocal('S/ DOCUMENTO', $codigo, true) !== null);

        return $codigo;
    }

    public function prepararBusqueda(string $tipo, ?string $numero): ?ResultadoConsultaDni
    {
        if ($tipo !== 'DNI' || $this->pacienteLocal($tipo, $numero) || ! config('services.dni_api.enabled')) {
            return null;
        }

        return $this->api->consultar($numero ?? '');
    }

    /** @return array{paciente: ?array, consulta_dni: ?array} */
    public function resolverBusqueda(string $tipo, ?string $numero, ?ResultadoConsultaDni $consulta): array
    {
        $paciente = $this->buscar($tipo, $numero);
        if ($paciente || ! $consulta) {
            return ['paciente' => $paciente, 'consulta_dni' => null];
        }
        $faltantes = $consulta->camposPendientes();
        if ($consulta->estado === ResultadoConsultaDni::ENCONTRADO && $faltantes === []) {
            $paciente = $this->registrar($consulta->datos);
        }
        $mensaje = match ($consulta->estado) {
            ResultadoConsultaDni::ERROR => 'La consulta de DNI no está disponible en este momento; puede continuar con el registro manual',
            ResultadoConsultaDni::NO_ENCONTRADO => 'No se encontró el DNI en la consulta externa; puede registrar al paciente manualmente',
            default => $paciente
                ? 'Paciente encontrado.'
                : 'La consulta devolvió datos incompletos. Complete los campos indicados.',
        };

        return [
            'paciente' => $paciente,
            'consulta_dni' => [
                'estado' => $consulta->estado,
                'datos' => $paciente ? [] : $consulta->datos,
                'faltantes' => $consulta->estado === ResultadoConsultaDni::ENCONTRADO ? $faltantes : [],
                'mensaje' => $mensaje,
            ],
        ];
    }

    /** @return array<string, int|string|null> */
    public function buscarId(int $id): array
    {
        return $this->datos(PacienteMock::query()->findOrFail($id));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|string|null>
     */
    public function registrar(array $data): array
    {
        $sinDocumento = $data['tipo_doc'] === 'S/ DOCUMENTO';
        try {
            return DB::transaction(function () use ($data, $sinDocumento): array {
                if (! $sinDocumento) {
                    $existente = $this->pacienteLocal($data['tipo_doc'], $data['dni'], true);
                    if ($existente) {
                        return $this->datos($existente);
                    }
                }
                $numero = $sinDocumento ? $this->codigoSinDocumento() : $data['dni'];
                $paciente = PacienteMock::create([
                    'tipo_doc' => $data['tipo_doc'],
                    'dni' => $numero,
                    'nombres' => self::normalizarNombre($data['nombres']),
                    'apellidos' => self::normalizarNombre($data['apellidos']),
                    'fecha_nacimiento' => $data['fecha_nacimiento'],
                    'sexo' => $data['sexo'],
                    'telefono' => $data['telefono'] ?? null,
                ]);

                return $this->datos($paciente);
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->esClaveDuplicada($exception)) {
                throw $exception;
            }
            $existente = ! $sinDocumento ? $this->pacienteLocal($data['tipo_doc'], $data['dni']) : null;
            if (! $existente) {
                throw $exception;
            }

            return $this->datos($existente);
        }
    }

    private function esClaveDuplicada(QueryException $exception): bool
    {
        return $exception instanceof UniqueConstraintViolationException ||
            in_array((int) ($exception->errorInfo[1] ?? 0), [2601, 2627], true);
    }

    public static function normalizarNombre(mixed $valor): string
    {
        if (! is_string($valor)) {
            return '';
        }

        return mb_strtoupper(trim(preg_replace('/[\s\p{Z}]+/u', ' ', $valor) ?? ''), 'UTF-8');
    }

    public function calcularEdad(string $fechaNacimiento): int
    {
        return max(
            0,
            (int) CarbonImmutable::parse($fechaNacimiento, 'America/Lima')->diffInYears(
                CarbonImmutable::today('America/Lima'),
            ),
        );
    }

    private function pacienteLocal(string $tipo, ?string $numero, bool $bloquear = false): ?PacienteMock
    {
        return PacienteMock::query()
            ->where('tipo_doc', $tipo)
            ->where('dni', $numero)
            ->orderBy('id')
            ->when($bloquear, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /** @return array<string, int|string|null> */
    private function datos(PacienteMock $paciente): array
    {
        return [
            'id' => $paciente->id,
            'tipo_doc' => $paciente->tipo_doc,
            'dni' => $paciente->dni,
            'nombres' => $paciente->nombres,
            'apellidos' => $paciente->apellidos,
            'nombre_completo' => $paciente->nombre_completo,
            'edad' => $this->calcularEdad($paciente->fecha_nacimiento->toDateString()),
            'sexo' => $paciente->sexo,
        ];
    }
}
