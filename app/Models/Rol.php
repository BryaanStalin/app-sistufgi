<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRol
 */
class Rol extends Model
{
    protected $table = 'TB_ACCESO_ROL';
    protected $fillable = [
        'titulo',
        'descripcion',
        'status',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    public $timestamps = true;

    /**
     * Relación: Un rol tiene muchos usuarios
     */
    public function usuarios()
    {
        return $this->belongsToMany(User::class, 'TB_ACCESO_USUARIO_X_ROL', 'rol_id', 'usuario_id')
            ->withPivot('status', 'created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by')
            ->withTimestamps();
    }

    /**
     * Relación: Un rol tiene muchos menús
     */
    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'TB_ACCESO_MENU_X_ROL', 'rol_id', 'menu_principal_id')
            ->where('TB_ACCESO_MENU.status', 1)
            ->orderBy('TB_ACCESO_MENU.orden')
            ->withPivot('status', 'created_at', 'updated_at', 'created_by', 'updated_by', 'deleted_by')
            ->withTimestamps();
    }
}
