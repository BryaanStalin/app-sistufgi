<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MenuService
{
    public function list(): Collection
    {
        return Menu::with('padre')->orderBy('orden')->get();
    }

    public function create(array $data, int $actorId): Menu
    {
        $menu = new Menu;
        $menu->titulo = $data['titulo'];
        $menu->link = $data['link'];
        $menu->descripcion = $data['descripcion'] ?? null;
        $menu->orden = $data['orden'];
        $menu->padre_id = $data['padre_id'] ?? null;
        $menu->status = $data['status'];
        $menu->created_by = $actorId;
        $menu->updated_by = $actorId;
        $menu->save();

        return $menu;
    }

    public function update(Menu $menu, array $data, int $actorId): Menu
    {
        $menu->titulo = $data['titulo'];
        $menu->link = $data['link'];
        $menu->descripcion = $data['descripcion'] ?? null;
        $menu->orden = $data['orden'];
        $menu->padre_id = $data['padre_id'] ?? null;
        $menu->status = $data['status'];
        $menu->updated_by = $actorId;
        $menu->save();

        return $menu;
    }

    public function delete(Menu $menu, int $actorId): void
    {
        DB::transaction(function () use ($menu, $actorId): void {
            DB::table('TB_ACCESO_MENU_X_ROL')
                ->where('menu_principal_id', $menu->id)
                ->update([
                    'status' => 0,
                    'deleted_by' => $actorId,
                    'updated_by' => $actorId,
                    'updated_at' => now(),
                ]);

            $menu->status = 0;
            $menu->updated_by = $actorId;
            $menu->deleted_by = $actorId;
            $menu->save();
        });
    }
}
