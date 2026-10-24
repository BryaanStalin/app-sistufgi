<?php

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\PacienteMock;
use App\Models\ServicioUps;
use App\Services\HojaRutaPdfService;
use Database\Factories\OfertaFlexibleUserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
});

afterEach(function (): void {
    $this->travelBack();
});

function hojaRutaPdfEjemplo(int $cantidad, bool $nombresLargos = false): HojaRuta
{
    $hoja = HojaRuta::factory()->make([
        'campania_id' => 1,
        'paciente_id' => 1,
        'medico_usuario_id' => 1,
    ]);
    $hoja->setRelation('campania', Campania::factory()->make([
        'nombre' => 'Campaña de Salud de Especialidad - Atención Integral',
    ]));
    $hoja->setRelation('paciente', PacienteMock::factory()->make([
        'nombres' => 'María',
        'apellidos' => 'Muñoz Peña',
        'dni' => '12345678',
        'fecha_nacimiento' => '1980-10-20',
    ]));
    $hoja->setRelation('medico', OfertaFlexibleUserFactory::new()->make(['nombre' => 'Dr. José Núñez']));
    $nombres = [
        'Medicina interna',
        'Cardiología',
        'Oftalmología',
        'Otorrinolaringología',
        'Ginecología y obstetricia',
        'Pediatría',
        'Nutrición',
        'Odontología',
        'Psicología',
        'Dermatología',
    ];
    $detalles = new Collection;

    for ($indice = 0; $indice < $cantidad; $indice++) {
        $nombre = $nombres[$indice % count($nombres)];
        if ($nombresLargos) {
            $nombre .= ' - Evaluación especializada, orientación y seguimiento integral del paciente';
        }
        $servicio = new ServicioUps(['DESCRIPCION_UPS' => $nombre, 'TIPO_UPS' => 'E']);
        $campaniaServicio = new CampaniaServicio;
        $campaniaServicio->setRelation('servicioUps', $servicio);
        $detalle = new HojaRutaDetalle;
        $detalle->setRelation('campaniaServicio', $campaniaServicio);
        $detalles->push($detalle);
    }

    return $hoja->setRelation('detalles', $detalles);
}

test('route PDF keeps the requested services on one portrait A4 page', function (int $cantidad): void {
    $hoja = hojaRutaPdfEjemplo($cantidad);
    $contenido = app(HojaRutaPdfService::class)->generar($hoja, now('America/Lima'));

    expect($contenido)->toStartWith('%PDF-');
    expect(preg_match_all('~/Type\s*/Page\b~', $contenido))->toBe(1);
    preg_match('~/MediaBox\s*\[\s*0\.000\s+0\.000\s+([\d.]+)\s+([\d.]+)\s*\]~', $contenido, $papel);
    expect((float) $papel[1])->toEqualWithDelta(595.28, 0.01);
    expect((float) $papel[2])->toEqualWithDelta(841.89, 0.01);
    $this->assertDatabaseCount('TB_HOJAS_RUTA', 0);

    if ($directorio = getenv('HOJA_RUTA_PDF_PREVIEW_DIR')) {
        file_put_contents($directorio.'/hoja-'.$cantidad.'.pdf', $contenido);
    }
})->with([8, 9, 12, 16, 20, 22]);

test('route PDF fits sixteen services with long Spanish descriptions on one page', function (): void {
    $contenido = app(HojaRutaPdfService::class)->generar(hojaRutaPdfEjemplo(16, true), now());

    expect(preg_match_all('~/Type\s*/Page\b~', $contenido))->toBe(1);

    if ($directorio = getenv('HOJA_RUTA_PDF_PREVIEW_DIR')) {
        file_put_contents($directorio.'/descripcion-larga.pdf', $contenido);
    }
});

test('route PDF refuses overflow instead of splitting stamps across multiple pages', function (int $cantidad): void {
    expect(fn () => app(HojaRutaPdfService::class)->generar(hojaRutaPdfEjemplo($cantidad), now()))
        ->toThrow(ValidationException::class);
})->with([23, 24, 25, 60]);

test('route PDF leaves the unmatched second half borderless and preserves vital sign labels', function (): void {
    $html = view('oferta_flexible.admision.pdf', [
        'hoja' => hojaRutaPdfEjemplo(9),
        'emitidoAt' => now(),
        'edad' => 46,
    ])->render();
    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($documento);

    expect($xpath->query('//table[@class="servicios"]/tbody/tr'))->toHaveCount(5);
    expect($xpath->query('//td[@class="mitad-vacia" and @colspan="2"]'))->toHaveCount(1);
    expect($xpath->query('//table[@class="signos"]/tr/td'))->toHaveCount(6);
    expect($html)->toContain('PESO:', 'TALLA:', 'PA:', 'Sat O2:', 'T°:', 'C°', 'GLUCOSA', 'María Muñoz Peña');
});

test('route PDF excludes laboratory exams before measuring its single page capacity', function (): void {
    $hoja = hojaRutaPdfEjemplo(23);
    $hoja->detalles->first()->campaniaServicio->servicioUps->TIPO_UPS = 'L';
    $contenido = app(HojaRutaPdfService::class)->generar($hoja, now());
    expect(preg_match_all('~/Type\s*/Page\b~', $contenido))->toBe(1);
    expect($hoja->detalles)->toHaveCount(22);
});
