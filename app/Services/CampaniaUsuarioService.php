<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\CampaniaEnlace;
use App\Models\CampaniaUsuario;
use App\Models\HojaRutaImpresion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaniaUsuarioService
{
    /** @param array{usuarios: list<int|string>} $data */
    public function assign(Campania $campania, array $data, int $actorId): void
    {
        DB::transaction(function () use ($campania, $data, $actorId): void {
            $campania = $this->editable($campania);
            foreach (array_unique($data['usuarios']) as $usuarioId) {
                $medico = OfertaFlexibleAccess::usuariosConRol('Médico Triaje')
                    ->lockForUpdate()
                    ->find($usuarioId);
                $admision = OfertaFlexibleAccess::usuariosConRol('Admisión')
                    ->lockForUpdate()
                    ->find($usuarioId);
                if (($medico === null) === ($admision === null)) {
                    $this->fail(
                        'Cada usuario debe estar activo y tener un único tipo de rol de campaña activo.',
                    );
                }
                $asignacion = $campania
                    ->asignaciones()
                    ->where('usuario_id', $usuarioId)
                    ->first();
                $tipo = $medico ? 'M' : 'A';
                if ($asignacion !== null && $asignacion->tipo !== $tipo) {
                    $this->fail(
                        'El usuario cambió de rol. Retire su asignación anterior antes de reasignarlo.',
                    );
                }
                if ($asignacion === null) {
                    CampaniaUsuario::create([
                        'campania_id' => $campania->id,
                        'usuario_id' => $usuarioId,
                        'tipo' => $tipo,
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                }
            }
        });
    }

    public function unassign(Campania $campania, CampaniaUsuario $asignacion, int $actorId): void
    {
        DB::transaction(function () use ($campania, $asignacion, $actorId): void {
            $campania = $this->editable($campania);
            $asignacion = $campania
                ->asignaciones()
                ->lockForUpdate()
                ->findOrFail($asignacion->id);
            $usuarioId = $asignacion->usuario_id;
            if (
                $campania
                    ->enlaces()
                    ->where(function ($query) use ($usuarioId): void {
                        $query
                            ->where('medico_usuario_id', $usuarioId)
                            ->orWhere('admision_usuario_id', $usuarioId);
                    })
                    ->exists()
            ) {
                $this->fail('Quite primero los enlaces del usuario en esta campaña.');
            }
            if (
                $campania
                    ->hojas()
                    ->withTrashed()
                    ->where('medico_usuario_id', $usuarioId)
                    ->exists() ||
                HojaRutaImpresion::query()
                    ->where('impreso_por', $usuarioId)
                    ->whereHas(
                        'hoja',
                        fn ($query) => $query->withTrashed()->where('campania_id', $campania->id),
                    )
                    ->exists()
            ) {
                $this->fail(
                    'No se puede quitar un usuario con historial de hojas o impresiones en esta campaña.',
                );
            }
            $asignacion->deleted_by = $actorId;
            $asignacion->delete();
        });
    }

    /** @param array{medico_usuario_id: int|string, admision_usuario_id: int|string} $data */
    public function link(Campania $campania, array $data, int $actorId): void
    {
        DB::transaction(function () use ($campania, $data, $actorId): void {
            $campania = $this->editable($campania);
            if (! $campania->asociar_triaje_admision || $campania->estado !== 'Activa') {
                $this->fail(
                    'Solo se pueden enlazar usuarios en campañas activas con asociación de Triaje y Admisión.',
                );
            }
            foreach (
                [
                    'medico_usuario_id' => ['M', 'Médico Triaje'],
                    'admision_usuario_id' => ['A', 'Admisión'],
                ] as $campo => [$tipo, $rol]
            ) {
                if (
                    ! $campania
                        ->asignaciones()
                        ->where('usuario_id', $data[$campo])
                        ->where('tipo', $tipo)
                        ->exists() ||
                    ! OfertaFlexibleAccess::usuariosConRol($rol)
                        ->lockForUpdate()
                        ->whereKey($data[$campo])
                        ->exists()
                ) {
                    $this->fail(
                        'Los usuarios deben estar activos, tener el rol correcto y estar asignados a esta campaña.',
                    );
                }
            }
            if (
                $campania
                    ->enlaces()
                    ->where('medico_usuario_id', $data['medico_usuario_id'])
                    ->exists()
            ) {
                $this->fail(
                    'El médico ya está enlazado a un admisionista. Quite primero el enlace existente.',
                );
            }
            CampaniaEnlace::create(
                $data + ['campania_id' => $campania->id, 'created_by' => $actorId, 'updated_by' => $actorId],
            );
        });
    }

    public function unlink(Campania $campania, CampaniaEnlace $enlace, int $actorId): void
    {
        DB::transaction(function () use ($campania, $enlace, $actorId): void {
            $campania = $this->editable($campania);
            $enlace = $campania
                ->enlaces()
                ->lockForUpdate()
                ->findOrFail($enlace->id);
            $enlace->deleted_by = $actorId;
            $enlace->delete();
        });
    }

    private function editable(Campania $campania): Campania
    {
        $campania = Campania::query()
            ->lockForUpdate()
            ->findOrFail($campania->id);
        $campania->assertEditable();

        return $campania;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['usuarios' => $message]);
    }
}
