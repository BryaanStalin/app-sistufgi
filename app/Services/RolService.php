<?php

namespace App\Services;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RolService
{
    public function list(): Collection
    {
        return Rol::with(['menus' => fn ($query) => $query->wherePivot('status', 1)])->orderBy('titulo')->get();
    }

    public function create(array $data, int $actorId): Rol
    {
        return DB::transaction(function () use ($data, $actorId): Rol {
            $menus = $data['menus'] ?? [];

            if (! is_array($menus)) {
                $menus = [$menus];
            }

            $rol = new Rol;
            $rol->titulo = $data['titulo'];
            $rol->descripcion = $data['descripcion'] ?? null;
            $rol->status = $data['status'];
            $rol->created_by = $actorId;
            $rol->updated_by = $actorId;
            $rol->save();

            $this->syncMenus($rol, $menus, $actorId);

            return $rol;
        });
    }

    public function update(Rol $rol, array $data, int $actorId): Rol
    {
        return DB::transaction(function () use ($rol, $data, $actorId): Rol {
            $menus = $data['menus'] ?? [];

            if (! is_array($menus)) {
                $menus = [$menus];
            }

            $rol->titulo = $data['titulo'];
            $rol->descripcion = $data['descripcion'] ?? null;
            $rol->status = $data['status'];
            $rol->updated_by = $actorId;
            $rol->save();

            $this->syncMenus($rol, $menus, $actorId);

            return $rol;
        });
    }

    public function delete(Rol $rol, int $actorId): void
    {
        DB::transaction(function () use ($rol, $actorId): void {
            DB::table('TB_ACCESO_MENU_X_ROL')
                ->where('rol_id', $rol->id)
                ->update([
                    'status' => 0,
                    'deleted_by' => $actorId,
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]);

            $rol->status = 0;
            $rol->updated_by = $actorId;
            $rol->deleted_by = $actorId;
            $rol->save();
        });
    }

    protected function syncMenus(Rol $rol, array $menus, int $actorId): void
    {
        $menuIds = array_unique(array_map('intval', $menus));
        DB::table('TB_ACCESO_MENU_X_ROL')->where('rol_id', $rol->id)
            ->whereNotIn('menu_principal_id', $menuIds)->update([
                'status' => 0, 'deleted_by' => $actorId, 'updated_by' => $actorId, 'updated_at' => now(),
            ]);
        foreach ($menuIds as $menuId) {
            $key = ['rol_id' => $rol->id, 'menu_principal_id' => $menuId];
            $existing = DB::table('TB_ACCESO_MENU_X_ROL')->where($key)->first();
            $pivot = ['status' => 1, 'deleted_by' => null, 'updated_by' => $actorId, 'updated_at' => now()];
            if ($existing === null) {
                $pivot += ['created_by' => $actorId, 'created_at' => now()];
            }
            DB::table('TB_ACCESO_MENU_X_ROL')->updateOrInsert($key, $pivot);
        }
    }
}
