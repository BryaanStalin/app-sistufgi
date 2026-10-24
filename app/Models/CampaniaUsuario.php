<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaniaUsuario extends OfertaFlexibleModel
{
    public const ROLES = [
        'M' => 'Médico Triaje',
        'A' => 'Admisión',
        'AL' => 'Admisión Laboratorio',
        'L' => 'Laboratorio',
    ];

    protected $table = 'TB_CAMPANIA_USUARIOS';

    protected $fillable = ['campania_id', 'usuario_id', 'tipo', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'campania_id' => 'integer',
        'usuario_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class, 'campania_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
