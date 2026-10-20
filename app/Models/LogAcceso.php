<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAcceso extends Model
{
    protected $table = 'TB_LOG_ACCESOS';
    
    protected $fillable = [
        'usuario_id',
        'tipo_evento',
        'ip_address',
        'user_agent',
        'detalle',
        'created_at'
    ];
    
    public $timestamps = false;
    
    protected $dateFormat = 'Y-m-d H:i:s';
    
    // Constantes para tipos de evento
    const EVENTO_LOGIN = 'LOGIN';
    const EVENTO_LOGOUT = 'LOGOUT';
    const EVENTO_LOGIN_FAILED = 'LOGIN_FAILED';
    const EVENTO_LOCKED = 'LOCKED';
    const EVENTO_ROL_DESACTIVADO = 'ROL_DESACTIVADO';
    const EVENTO_USUARIO_DESACTIVADO = 'USUARIO_DESACTIVADO';
    
    /**
     * Relación con usuario
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }
}
