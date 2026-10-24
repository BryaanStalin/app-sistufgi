<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabAtencionExamen extends OfertaFlexibleModel
{
    protected $table = 'TB_LAB_ATENCION_EXAMENES';

    protected $fillable = [
        'atencion_id',
        'lab_paciente_id',
        'campania_servicio_id',
        'ups_id',
        'estado',
        'tomado_at',
        'tomado_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'atencion_id' => 'integer',
        'lab_paciente_id' => 'integer',
        'campania_servicio_id' => 'integer',
        'ups_id' => 'integer',
        'tomado_at' => 'immutable_datetime',
        'tomado_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(LabAtencion::class, 'atencion_id');
    }

    public function labPaciente(): BelongsTo
    {
        return $this->belongsTo(LabPaciente::class, 'lab_paciente_id');
    }

    public function campaniaServicio(): BelongsTo
    {
        return $this->belongsTo(CampaniaServicio::class, 'campania_servicio_id');
    }

    public function servicioUps(): BelongsTo
    {
        return $this->belongsTo(ServicioUps::class, 'ups_id');
    }

    public function tomadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tomado_by');
    }

    public const ESTADOS = ['P' => 'Pendiente', 'T' => 'Realizado', 'N' => 'No realizado'];

    #[Scope]
    protected function consumenInsumos(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('estado'), ['P', 'T']);
    }
}
