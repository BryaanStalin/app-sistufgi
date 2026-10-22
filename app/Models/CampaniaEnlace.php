<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaniaEnlace extends OfertaFlexibleModel
{
    protected $table = 'TB_CAMPANIA_ENLACES';

    protected $fillable = [
        'campania_id',
        'medico_usuario_id',
        'admision_usuario_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'campania_id' => 'integer',
        'medico_usuario_id' => 'integer',
        'admision_usuario_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class, 'campania_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_usuario_id');
    }

    public function admision(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admision_usuario_id');
    }
}
