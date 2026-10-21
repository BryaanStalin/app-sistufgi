<?php

namespace App\Services;

use App\Models\HojaRuta;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;

class HojaRutaPdfService
{
    public function __construct(protected PacienteService $pacientes) {}

    public function generar(HojaRuta $hoja, CarbonInterface $emitidoAt): string
    {
        return Pdf::loadView('oferta_flexible.admision.pdf', ['hoja' => $hoja, 'emitidoAt' => $emitidoAt,
            'edad' => $this->pacientes->calcularEdad($hoja->paciente->fecha_nacimiento->toDateString())])
            ->setPaper('a4')->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false,
                'isPhpEnabled' => false, 'isJavascriptEnabled' => false])->output();
    }
}
