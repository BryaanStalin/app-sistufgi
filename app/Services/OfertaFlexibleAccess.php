<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OfertaFlexibleAccess
{
    public static function hasRole(?User $usuario, string $titulo): bool
    {
        return $usuario !== null && (bool) $usuario->status
            && $usuario->roles()->where('TB_ACCESO_ROL.status', 1)
                ->where('TB_ACCESO_ROL.titulo', $titulo)->exists();
    }

    public static function usuariosConRol(string $titulo): Builder
    {
        return User::query()->where('status', 1)->whereHas('roles', function (Builder $query) use ($titulo): void {
            $query->where('TB_ACCESO_ROL.status', 1)->where('TB_ACCESO_ROL.titulo', $titulo);
        });
    }
}
