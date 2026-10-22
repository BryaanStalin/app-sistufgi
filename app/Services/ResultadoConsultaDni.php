<?php

namespace App\Services;

final readonly class ResultadoConsultaDni
{
    public const ENCONTRADO = 'ENCONTRADO';

    public const NO_ENCONTRADO = 'NO_ENCONTRADO';

    public const ERROR = 'ERROR';

    /** @param array<string, string|null> $datos */
    private function __construct(public string $estado, public array $datos = []) {}

    /** @param array<string, string|null> $datos */
    public static function encontrado(array $datos): self
    {
        return new self(self::ENCONTRADO, $datos);
    }

    public static function noEncontrado(): self
    {
        return new self(self::NO_ENCONTRADO);
    }

    public static function error(): self
    {
        return new self(self::ERROR);
    }

    /** @return list<string> */
    public function camposPendientes(): array
    {
        $pendientes = [];
        foreach (['nombres', 'apellidos', 'fecha_nacimiento', 'sexo'] as $campo) {
            $valor = $this->datos[$campo] ?? '';
            if ($valor === '' || (in_array($campo, ['nombres', 'apellidos'], true) && mb_strlen($valor) > 150)) {
                $pendientes[] = $campo;
            }
        }

        return $pendientes;
    }
}
