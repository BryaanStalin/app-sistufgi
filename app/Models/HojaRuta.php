<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HojaRuta extends OfertaFlexibleModel
{
    protected $table = 'TB_HOJAS_RUTA';

    protected $fillable = [
        'campania_id',
        'paciente_id',
        'fecha_atencion',
        'medico_usuario_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'campania_id' => 'integer',
        'paciente_id' => 'integer',
        'medico_usuario_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
        'fecha_atencion' => 'immutable_datetime',
    ];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class, 'campania_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(PacienteMock::class, 'paciente_id');
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_usuario_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(HojaRutaDetalle::class, 'hoja_ruta_id');
    }

    public function impresiones(): HasMany
    {
        return $this->hasMany(HojaRutaImpresion::class, 'hoja_ruta_id');
    }
}
