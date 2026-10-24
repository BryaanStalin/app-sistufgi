<?php

namespace App\Services;

use App\Models\CampaniaServicio;
use App\Models\LabAtencion;
use App\Models\LabAtencionExamen;
use App\Models\ServicioUps;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioUpsService
{
    /** @param array{ID_UPS: int|string, DESCRIPCION_UPS: string, TIPO_UPS: string, ACTIVO: bool|int|string} $data */
    public function save(?ServicioUps $servicio, array $data, int $actorId): ServicioUps
    {
        return DB::transaction(function () use ($servicio, $data, $actorId): ServicioUps {
            /** El bloqueo de rango serializa la unicidad sin alterar la tabla existente. */
            $activos = ServicioUps::query()
                ->where('ACTIVO', 1)
                ->lockForUpdate()
                ->get();
            $servicio = $servicio
                ? ServicioUps::query()
                    ->lockForUpdate()
                    ->findOrFail($servicio->id)
                : new ServicioUps;
            if (
                (bool) $data['ACTIVO'] &&
                $activos->contains(
                    fn (ServicioUps $otro): bool => $otro->id !== $servicio->id &&
                        $otro->ID_UPS === (int) $data['ID_UPS'],
                )
            ) {
                throw ValidationException::withMessages([
                    'ID_UPS' => 'El código UPSS ya existe entre los servicios activos.',
                ]);
            }
            if ($servicio->exists && ! (bool) $data['ACTIVO']) {
                $this->assertCanDeactivate($servicio);
            }
            if (
                $servicio->exists &&
                ($servicio->TIPO_UPS === 'L') !== ($data['TIPO_UPS'] === 'L') &&
                ($servicio->campaniaServicios()
                    ->withTrashed()
                    ->exists() ||
                    LabAtencion::withTrashed()
                        ->where('ups_id_solicita', $servicio->id)
                        ->exists())
            ) {
                throw ValidationException::withMessages([
                    'TIPO_UPS' => 'No se puede cambiar entre triaje y laboratorio un servicio usado en campañas.',
                ]);
            }
            $data['HORA_LIMITE'] = $data['TIPO_UPS'] === 'L' ? ($data['HORA_LIMITE'] ?? null) : null;
            $data['ENVIA_EESS'] = $data['TIPO_UPS'] === 'L' ? (bool) ($data['ENVIA_EESS'] ?? true) : true;
            $servicio->fill($data);
            $servicio->created_by ??= $actorId;
            $servicio->updated_by = $actorId;
            $servicio->deleted_by = $servicio->ACTIVO ? null : $actorId;
            $servicio->save();

            return $servicio;
        });
    }

    public function delete(ServicioUps $servicio, int $actorId): void
    {
        DB::transaction(function () use ($servicio, $actorId): void {
            $servicio = ServicioUps::query()
                ->lockForUpdate()
                ->findOrFail($servicio->id);
            $this->assertCanDeactivate($servicio);
            $servicio->update(['ACTIVO' => false, 'deleted_by' => $actorId, 'updated_by' => $actorId]);
        });
    }

    private function assertCanDeactivate(ServicioUps $servicio): void
    {
        if (
            CampaniaServicio::withTrashed()
                ->where('ups_id', $servicio->id)
                ->whereHas('detalles', fn ($query) => $query->withTrashed())
                ->exists() ||
            LabAtencionExamen::withTrashed()
                ->where('ups_id', $servicio->id)
                ->exists() ||
            LabAtencion::withTrashed()
                ->where('ups_id_solicita', $servicio->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'servicio' => 'No se puede desactivar un servicio con hojas de ruta o registros de laboratorio.',
            ]);
        }
    }
}
