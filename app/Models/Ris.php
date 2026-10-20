<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRis
 */
class Ris extends Model
{
    protected $table = 'TB_EESS_RIS';
    protected $primaryKey = 'ID_RIS';
    public $timestamps = false;

    protected $fillable = [
        'NOM_RIS',
        'ORDEN_RIS',
        'ACTIVO',
    ];

    protected $casts = [
        'ACTIVO' => 'boolean',
    ];

    public function risDistritos()
    {
        return $this->hasMany(RisDistrito::class, 'ID_RIS', 'ID_RIS');
    }

    public function distritos()
    {
        return $this->hasManyThrough(
            Distrito::class,
            RisDistrito::class,
            'ID_RIS',
            'ID_DIST',
            'ID_RIS',
            'ID_DIST'
        );
    }
}
