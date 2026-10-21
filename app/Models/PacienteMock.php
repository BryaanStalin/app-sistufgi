<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PacienteMock extends Model
{
    use HasFactory;

    protected $table = 'TB_PACIENTES_MOCK';

    public $timestamps = false;

    protected $fillable = ['dni', 'nombres_apellidos', 'fecha_nacimiento', 'telefono', 'tipo_doc', 'sexo'];

    protected $casts = ['fecha_nacimiento' => 'immutable_date'];
}
