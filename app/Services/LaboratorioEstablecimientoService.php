<?php

namespace App\Services;

use App\Models\Establecimiento;

class LaboratorioEstablecimientoService
{
    /** @return list<array{renipress: string, nombre: string}> */
    public function disponibles(): array
    {
        $configurados = config('oferta_flexible.laboratorio.eess', []);
        $oficiales = Establecimiento::query()
            ->whereIn('RENIPRESS', array_column($configurados, 'renipress'))
            ->pluck('NOM_EESS', 'RENIPRESS');

        return array_map(fn (array $eess): array => [
            'renipress' => (string) $eess['renipress'],
            'nombre' => $oficiales->get($eess['renipress'], $eess['nombre']),
        ], $configurados);
    }
}
