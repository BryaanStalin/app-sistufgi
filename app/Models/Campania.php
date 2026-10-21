<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Campania extends OfertaFlexibleModel
{
    protected $table = 'TB_CAMPANIAS';

    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin', 'asociar_triaje_admision', 'mismos_servicios', 'cerrada', 'cerrada_at', 'cerrada_by', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = ['cerrada_by' => 'integer', 'created_by' => 'integer', 'updated_by' => 'integer', 'deleted_by' => 'integer', 'fecha_inicio' => 'immutable_datetime', 'fecha_fin' => 'immutable_datetime', 'asociar_triaje_admision' => 'boolean', 'mismos_servicios' => 'boolean', 'cerrada' => 'boolean', 'cerrada_at' => 'datetime'];

    public function servicios(): HasMany
    {
        return $this->hasMany(CampaniaServicio::class, 'campania_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(CampaniaUsuario::class, 'campania_id');
    }

    public function enlaces(): HasMany
    {
        return $this->hasMany(CampaniaEnlace::class, 'campania_id');
    }

    public function hojas(): HasMany
    {
        return $this->hasMany(HojaRuta::class, 'campania_id');
    }

    public function getEstadoAttribute(): string
    {
        $hoy = now('America/Lima')->startOfDay();

        return match (true) {
            $this->cerrada => 'Cerrada',
            $this->fecha_fin->lt($hoy) => 'Finalizada',
            default => 'Activa',
        };
    }

    public function assertEditable(): void
    {
        if ($this->cerrada) {
            throw ValidationException::withMessages(['campania' => 'La campaña está cerrada y es de solo lectura.']);
        }
    }
}
