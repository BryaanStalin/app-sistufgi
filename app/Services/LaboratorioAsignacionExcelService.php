<?php

namespace App\Services;

use App\Models\Campania;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class LaboratorioAsignacionExcelService
{
    public const COLOR_CABECERA = 'F1F5FA';

    public const COLOR_TEXTO_CABECERA = '34455C';

    public function __construct(
        protected LaboratorioAsignacionService $asignaciones,
        protected LaboratorioTmService $tm,
    ) {}

    /** @param array<string, mixed> $filtros
     * @param  list<array{renipress: string, nombre: string}>  $eess
     */
    public function exportar(
        User $usuario,
        Campania $campania,
        array $filtros,
        Collection $columnas,
        array $eess,
    ): BinaryFileResponse {
        $campania = $this->asignaciones->campania($usuario, $campania->id);
        $archivo = tempnam(sys_get_temp_dir(), 'lab-asignacion-');
        if ($archivo === false) {
            throw new RuntimeException('No se pudo preparar la exportación.');
        }
        $excel = new Spreadsheet;
        try {
            $hoja = $excel->getActiveSheet();
            $hoja->setTitle('Asignación Laboratorio');
            $cabeceras = [
                'N°', 'Código', 'Documento', 'Paciente', 'Celular',
                ...$columnas->pluck('DESCRIPCION_UPS')->all(), 'Laboratorio asignado',
            ];
            foreach ($cabeceras as $indice => $cabecera) {
                $hoja->setCellValueExplicit([$indice + 1, 1], $cabecera, DataType::TYPE_STRING);
            }
            $cantidad = $this->escribirFilas($hoja, $campania, $filtros, $columnas, $eess);
            $this->formatear($hoja, count($cabeceras), $columnas->count(), $cantidad);
            $writer = new Xlsx($excel);
            $writer->setPreCalculateFormulas(false);
            $writer->save($archivo);
            $this->registrarExportacion($usuario, $campania, $filtros, $cantidad);
            clearstatcache(true, $archivo);

            return response()->download($archivo, $this->nombreArchivo($campania), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ])->deleteFileAfterSend();
        } catch (Throwable $exception) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
            throw $exception;
        } finally {
            $excel->disconnectWorksheets();
        }
    }

    private function escribirFilas(
        Worksheet $hoja,
        Campania $campania,
        array $filtros,
        Collection $columnas,
        array $eess,
    ): int {
        $destinos = collect($eess)->keyBy('renipress');
        $cantidad = 0;
        foreach ($this->asignaciones->consulta($campania, $filtros, $columnas)->cursor() as $fila) {
            $cantidad++;
            $eessAsignado = $destinos->get($fila->eess_renipress);
            $valores = [
                $this->tm->formatear((int) $fila->tm_numero),
                $fila->tipo_doc.'-'.$fila->dni,
                trim($fila->nombres.' '.$fila->apellidos),
                $fila->telefono ?? '',
            ];
            foreach ($columnas as $columna) {
                $valores[] = (int) $fila->{'examen_'.$columna->id} === 1 ? '✓' : '';
            }
            $valores[] = $eessAsignado
                ? $eessAsignado['nombre'].', RENIPRESS: '.$eessAsignado['renipress'] : 'Sin asignar';
            $hoja->setCellValueExplicit([1, $cantidad + 1], $cantidad, DataType::TYPE_NUMERIC);
            foreach ($valores as $indice => $valor) {
                $hoja->setCellValueExplicit([$indice + 2, $cantidad + 1], $valor, DataType::TYPE_STRING);
            }
        }

        return $cantidad;
    }

    private function formatear(Worksheet $hoja, int $numeroColumnas, int $numeroExamenes, int $cantidad): void
    {
        $ultimaColumna = Coordinate::stringFromColumnIndex($numeroColumnas);
        $ultimaFila = $cantidad + 1;
        $hoja->freezePane('A2');
        $hoja->setAutoFilter('A1:'.$ultimaColumna.$ultimaFila);
        $hoja->getStyle('A1:'.$ultimaColumna.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::COLOR_TEXTO_CABECERA]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR_CABECERA]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $hoja->getRowDimension(1)->setRowHeight(36);
        if ($cantidad > 0) {
            $hoja->getStyle('A2:'.$ultimaColumna.$ultimaFila)
                ->getAlignment()
                ->setWrapText(true);
        }
        foreach ([6, 12, 23, 38, 27, ...array_fill(0, $numeroExamenes, 18), 54] as $indice => $ancho) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($indice + 1))->setWidth($ancho);
        }
    }

    private function registrarExportacion(User $usuario, Campania $campania, array $filtros, int $cantidad): void
    {
        $busqueda = (string) ($filtros['q'] ?? '');
        Log::channel('laboratorio_exportaciones')->info('Exportación de asignación de laboratorio', [
            'usuario_id' => $usuario->id,
            'campania_id' => $campania->id,
            'filtros' => [
                'asignacion' => $filtros['asignacion'] ?? 'todos',
                'busqueda_aplicada' => $busqueda !== '',
                'busqueda_hmac' => $busqueda !== '' ? hash_hmac('sha256', $busqueda, config('app.key')) : null,
            ],
            'filas' => $cantidad,
        ]);
    }

    private function nombreArchivo(Campania $campania): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9_-]+/', '_', Str::ascii($campania->nombre));
        $nombre = trim(substr($nombre ?: 'Campania_'.$campania->id, 0, 80), '_');

        return 'Laboratorio_Asignacion_'.($nombre ?: $campania->id).'_'.now('America/Lima')->format('Ymd').'.xlsx';
    }
}
