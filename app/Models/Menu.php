<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperMenu
 */
class Menu extends Model
{
    protected $table = 'TB_ACCESO_MENU';

    protected $fillable = [
        'titulo',
        'icono',
        'link',
        'descripcion',
        'orden',
        'padre_id',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public $timestamps = true;

    /**
     * Relación: Un menú puede tener un padre
     */
    public function padre()
    {
        return $this->belongsTo(Menu::class, 'padre_id');
    }

    /**
     * Relación: Un menú puede tener hijos (nivel 2)
     */
    public function hijos()
    {
        return $this->hasMany(Menu::class, 'padre_id')
            ->where('TB_ACCESO_MENU.status', 1)
            ->orderBy('TB_ACCESO_MENU.orden');
    }

    /**
     * Relación: Hijos con sus propios hijos (nivel 3)
     * Para obtener los sub-hijos de manera recursiva
     */
    public function hijosConSubhijos()
    {
        return $this->hasMany(Menu::class, 'padre_id')
            ->where('TB_ACCESO_MENU.status', 1)
            ->orderBy('TB_ACCESO_MENU.orden')
            ->with('hijosConSubhijos');
    }

    /**
     * Relación: Un menú pertenece a muchos roles
     */
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'TB_ACCESO_MENU_X_ROL', 'menu_principal_id', 'rol_id')
            ->wherePivot('status', 1)
            ->where('TB_ACCESO_ROL.status', 1)
            ->withPivot('status', 'created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by')
            ->withTimestamps();
    }

    /**
     * Método para verificar si un menú tiene hijos
     */
    public function tieneHijos()
    {
        return $this->hijos()->exists();
    }
}
