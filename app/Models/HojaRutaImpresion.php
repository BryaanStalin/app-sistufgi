<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class HojaRutaImpresion extends Model
{
    use HasFactory;

    protected $table = 'TB_HOJA_RUTA_IMPRESIONES';

    protected $dateFormat = 'Y-m-d H:i:s';

    public $timestamps = false;

    protected $fillable = ['hoja_ruta_id', 'impreso_por', 'impreso_at', 'created_at', 'created_by'];

    protected $casts = [
        'hoja_ruta_id' => 'integer',
        'impreso_por' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
        'impreso_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $impresion): void {
            $impresion->impreso_at ??= now();
            $impresion->created_at ??= now();
            $impresion->created_by ??= auth()->id();
        });
        static::updating(function (): never {
            throw new LogicException('Las impresiones solo admiten inserciones.');
        });
        static::deleting(function (): never {
            throw new LogicException('Las impresiones solo admiten inserciones.');
        });
    }

    public function hoja(): BelongsTo
    {
        return $this->belongsTo(HojaRuta::class, 'hoja_ruta_id');
    }
}
