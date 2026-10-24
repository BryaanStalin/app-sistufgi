<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabAtencion extends OfertaFlexibleModel
{
    protected $table = 'TB_LAB_ATENCIONES';

    protected $fillable = [
        'lab_paciente_id',
        'campania_id',
        'fecha_atencion',
        'fua',
        'ups_id_solicita',
        'hoja_ruta_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'lab_paciente_id' => 'integer',
        'campania_id' => 'integer',
        'fecha_atencion' => 'immutable_datetime',
        'ups_id_solicita' => 'integer',
        'hoja_ruta_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    public function labPaciente(): BelongsTo
    {
        return $this->belongsTo(LabPaciente::class, 'lab_paciente_id');
    }

    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class, 'campania_id');
    }

    public function servicioSolicitante(): BelongsTo
    {
        return $this->belongsTo(ServicioUps::class, 'ups_id_solicita');
    }

    public function hojaRuta(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class, 'hoja_ruta_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function examenes(): HasMany
    {
        return $this->hasMany(LabAtencionExamen::class, 'atencion_id');
    }

    #[Scope]
    protected function deCampania(Builder $query, int $campaniaId): void
    {
        $query->where($this->qualifyColumn('campania_id'), $campaniaId);
    }
}
