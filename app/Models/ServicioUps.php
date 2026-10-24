<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicioUps extends Model
{
    use HasFactory;

    protected $table = 'TB_UPSS_CAMPAÑAS';

    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'ID_UPS',
        'DESCRIPCION_UPS',
        'TIPO_UPS',
        'ACTIVO',
        'HORA_LIMITE',
        'ENVIA_EESS',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = ['ACTIVO' => 'boolean', 'ID_UPS' => 'integer', 'ENVIA_EESS' => 'boolean'];

    public const TIPOS = ['P' => 'PREVENTIVO', 'E' => 'ESPECIALIDAD', 'L' => 'Laboratorio (examen)'];

    public function getTipoEtiquetaAttribute(): string
    {
        return self::TIPOS[$this->TIPO_UPS] ?? 'Sin clasificar';
    }

    public function campaniaServicios(): HasMany
    {
        return $this->hasMany(CampaniaServicio::class, 'ups_id');
    }
}
