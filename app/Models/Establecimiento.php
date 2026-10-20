<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperEstablecimiento
 */
class Establecimiento extends Model
{
    protected $table = 'TB_EESS';
    protected $primaryKey = 'ID_EESS';
    public $timestamps = false;

    protected $fillable = [
        'ID_RIS_DIST',
        'RENIPRESS',
        'NOM_EESS',
        'ACTIVO',
        'CATEGORIA',
        'ID_AMBITO'
    ];

    protected $casts = [
        'ACTIVO' => 'boolean',
    ];

    public function risDistrito()
    {
        return $this->belongsTo(RisDistrito::class, 'ID_RIS_DIST', 'ID_RIS_DIST');
    }

    public function ambito()
    {
        return $this->belongsTo(Ambito::class, 'ID_AMBITO', 'ID_AMBITO');
    }

    // Acceso directo al distrito a través de RisDistrito
    public function distrito()
    {
        return $this->hasOneThrough(
            Distrito::class,
            RisDistrito::class,
            'ID_RIS_DIST',  // FK en TB_RIS_X_DISTRITO que apunta a TB_EESS
            'ID_DIST',      // FK en TB_DISTRITO
            'ID_RIS_DIST',  // llave local en TB_EESS
            'ID_DIST'       // llave local en TB_RIS_X_DISTRITO
        );
    }
}
