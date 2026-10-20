<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ambito extends Model
{
    protected $table = 'TB_EESS_AMBITO';
    protected $primaryKey = 'ID_AMBITO';
    public $timestamps = false;

    const CREATED_AT = 'CREATED_AT';
    const UPDATED_AT = 'UPDATED_AT';

    protected $fillable = [
        'NOM_AMBITO',
        'ACTIVO'
    ];

    protected $casts = [
        'ACTIVO' => 'boolean',
    ];

    // Relación con establecimientos (uno a muchos)
    public function establecimientos()
    {
        return $this->hasMany(Establecimiento::class, 'ID_AMBITO', 'ID_AMBITO');
    }
}
