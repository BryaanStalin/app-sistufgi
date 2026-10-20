<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperDistrito
 */
class Distrito extends Model
{
    protected $table = 'TB_EESS_DISTRITO';
    protected $primaryKey = 'ID_DIST';
    public $timestamps = false;

    protected $fillable = [
        'NOM_DIST',
        'ACTIVO',
    ];

    protected $casts = [
        'ACTIVO' => 'boolean',
    ];

    public function eess()
    {
        return $this->hasMany(Establecimiento::class, 'ID_DIST', 'ID_DIST');
    }

    public function ris()
    {
        return $this->belongsToMany(
            Ris::class,
            'RisDistrito',
            'ID_DIST',
            'ID_RIS',
            'ID_DIST',
            'ID_RIS'
        );
    }
}
