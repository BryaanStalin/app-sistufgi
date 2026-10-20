<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogNavegacion extends Model
{
    protected $table = 'TB_LOG_NAVEGACION';
    
    protected $fillable = [
        'usuario_id',
        'menu_id',
        'ruta',
        'titulo_menu',
        'metodo',
        'ip_address',
        'user_agent',
        'parametros',
        'created_at'
    ];
    
    public $timestamps = false;
    
    protected $dateFormat = 'Y-m-d H:i:s';
    
    protected $casts = [
        'parametros' => 'array',
        'created_at' => 'datetime',
    ];
    
    /**
     * Relación con usuario
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
    
    /**
     * Relación con menú
     */
    public function menu()
    {
        return $this->belongsTo(Menu::class, 'menu_id', 'id');
    }
    
    /**
     * Scope para filtrar por usuario
     */
    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }
    
    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopePorRangoFechas($query, $fechaInicio, $fechaFin = null)
    {
        if ($fechaFin) {
            return $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
        }
        
        return $query->whereDate('created_at', $fechaInicio);
    }
    
    /**
     * Scope para filtrar por menú específico
     */
    public function scopePorMenu($query, $menuId)
    {
        return $query->where('menu_id', $menuId);
    }
    
    /**
     * Scope para obtener solo accesos a menús (excluir dashboard y otras rutas)
     */
    public function scopeSoloMenus($query)
    {
        return $query->whereNotNull('menu_id');
    }
}