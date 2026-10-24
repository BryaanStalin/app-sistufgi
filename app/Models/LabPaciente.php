<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LabPaciente extends OfertaFlexibleModel
{
    protected $table = 'TB_LAB_PACIENTES';

    protected $fillable = [
        'campania_id',
        'paciente_id',
        'tm_numero',
        'eess_renipress',
        'eess_asignado_at',
        'eess_asignado_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'campania_id' => 'integer',
        'paciente_id' => 'integer',
        'tm_numero' => 'integer',
        'eess_asignado_at' => 'immutable_datetime',
        'eess_asignado_by' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(Campania::class, 'campania_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(PacienteMock::class, 'paciente_id');
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eess_asignado_by');
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(LabAtencion::class, 'lab_paciente_id');
    }

    public function examenes(): HasMany
    {
        return $this->hasMany(LabAtencionExamen::class, 'lab_paciente_id');
    }

    #[Scope]
    protected function deCampania(Builder $query, int $campaniaId): void
    {
        $query->where($this->qualifyColumn('campania_id'), $campaniaId);
    }
}
