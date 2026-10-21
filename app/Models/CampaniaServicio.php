<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaniaServicio extends OfertaFlexibleModel
{
    protected $table = 'TB_CAMPANIA_SERVICIOS';

    protected $fillable = ['campania_id', 'ups_id', 'fecha_servicio', 'cupos', 'cupo_base', 'cupo_personalizado', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['campania_id' => 'integer', 'ups_id' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'deleted_by' => 'integer', 'fecha_servicio' => 'immutable_datetime', 'cupos' => 'integer', 'cupo_base' => 'integer', 'cupo_personalizado' => 'boolean'];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class, 'campania_id');
    }

    public function servicioUps(): BelongsTo
    {
        return $this->belongsTo(ServicioUps::class, 'ups_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(HojaRutaDetalle::class, 'campania_servicio_id');
    }
}
