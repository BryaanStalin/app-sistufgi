<?php

namespace App\Services;

use App\Models\HojaRuta;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HojaRutaPdfService
{
    private const TEMPORAL_MINUTOS = 30;

    public function __construct(protected PacienteService $pacientes) {}

    public function generar(HojaRuta $hoja, CarbonInterface $emitidoAt): string
    {
        $filas = max(1, (int) ceil($hoja->detalles->count() / 2));
        $alto = min(56, max(28.35, 470 / $filas - 7));

        while (true) {
            $fuente = max(8, min(10, 10 * $alto / 56));
            $pdf = Pdf::loadView('oferta_flexible.admision.pdf', [
                'hoja' => $hoja,
                'emitidoAt' => $emitidoAt,
                'edad' => $this->pacientes->calcularEdad($hoja->paciente->fecha_nacimiento->toDateString()),
                'logo' => 'data:image/svg+xml;base64,'.base64_encode(
                    file_get_contents(public_path('img/logo-diris-ln.svg')),
                ),
                'altoServicio' => $alto,
                'fuenteServicio' => $fuente,
            ])
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'DejaVu Sans',
                    'isRemoteEnabled' => false,
                    'isPhpEnabled' => false,
                    'isJavascriptEnabled' => false,
                ]);
            $contenido = $pdf->output();

            if ($pdf->getDomPDF()->getCanvas()->get_page_count() === 1) {
                return $contenido;
            }

            if ($alto <= 28.35) {
                throw ValidationException::withMessages([
                    'hoja_id' => 'Los servicios no caben en una hoja A4 con letra de 8 pt y sello de 1 cm.',
                ]);
            }

            $alto = max(28.35, $alto - 4);
        }
    }

    public function guardarTemporal(string $token, User $usuario, HojaRuta $hoja, string $contenido): void
    {
        $documento = Str::ascii($hoja->paciente->dni ?: 'SIN_DOCUMENTO');
        $documento = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', $documento), '_-');
        $documento = substr($documento ?: 'SIN_DOCUMENTO', 0, 60);
        $nombre = 'Hoja_de_Ruta_'.$documento.'_'.$hoja->fecha_atencion->format('Ymd').'.pdf';
        $guardado = Cache::store('file')->put('oferta-flexible:pdf:'.$token, [
            'usuario_id' => (int) $usuario->id,
            'campania_id' => (int) $hoja->campania_id,
            'hoja_id' => (int) $hoja->id,
            'nombre' => $nombre,
            'contenido' => $contenido,
        ], now()->addMinutes(self::TEMPORAL_MINUTOS));
        abort_unless($guardado, 503, 'No se pudo preparar el PDF. Vuelva a intentarlo.');
    }

    /** @return array{usuario_id: int, campania_id: int, hoja_id: int, nombre: string, contenido: string} */
    public function recuperarTemporal(User $usuario, string $token): array
    {
        $documento = Cache::store('file')->get('oferta-flexible:pdf:'.$token);
        abort_unless($documento, 410, 'El PDF venció. Vuelva al listado y pulse Imprimir.');
        abort_unless($documento['usuario_id'] === (int) $usuario->id, 404);

        return $documento;
    }

    public function olvidarTemporal(string $token): void
    {
        Cache::store('file')->forget('oferta-flexible:pdf:'.$token);
    }
}
