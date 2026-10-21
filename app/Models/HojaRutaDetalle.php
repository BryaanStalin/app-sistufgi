<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HojaRutaDetalle extends OfertaFlexibleModel
{
    protected $table = 'TB_HOJA_RUTA_DETALLE';

    protected $fillable = ['hoja_ruta_id', 'campania_servicio_id', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['hoja_ruta_id' => 'integer', 'campania_servicio_id' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'deleted_by' => 'integer'];

    public function hoja(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class, 'hoja_ruta_id');
    }

    public function campaniaServicio(): BelongsTo
    {
        return $this->belongsTo(CampaniaServicio::class, 'campania_servicio_id');
    }
}
