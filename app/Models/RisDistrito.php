<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRisDistrito
 */
class RisDistrito extends Model
{
    protected $table = 'TB_EESS_RIS_X_DISTRITO';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ID_RIS',
        'ID_DIST',
        'ACTIVO',
    ];

    protected $casts = [
        'ACTIVO' => 'boolean',
    ];

    public function ris()
    {
        return $this->belongsTo(Ris::class, 'ID_RIS', 'ID_RIS');
    }

    public function distrito()
    {
        return $this->belongsTo(Distrito::class, 'ID_DIST', 'ID_DIST');
    }
}
