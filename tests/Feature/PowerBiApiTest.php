<?php

use App\Models\Campania;
use App\Models\CampaniaServicio;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\PacienteMock;
use App\Models\ServicioUps;
use App\Services\PowerBiResumenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
    config([
        'services.powerbi_api' => [
            'enabled' => true,
            'user' => 'lector-pruebas',
            'password' => 'password-ficticio:privado',
            'key' => 'clave-ficticia-privada',
            'require_https' => true,
        ],
        'logging.channels.powerbi' => [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
        ],
    ]);
    Log::forgetChannel('powerbi');
});

afterEach(function (): void {
    $this->travelBack();
});

function powerBiUrl(string $endpoint = '', int|string $campania = 1): string
{
    return 'https://localhost/api/powerbi'.($endpoint ? '/campanias/'.$campania.'/'.$endpoint : '');
}

/** @return array<string, string> */
function powerBiCredenciales(string $tipo = 'key'): array
{
    return $tipo === 'basic'
        ? ['Authorization' => 'Basic '.base64_encode('lector-pruebas:password-ficticio:privado')]
        : ['X-Api-Key' => 'clave-ficticia-privada'];
}

function powerBiLog(): TestHandler
{
    return Log::channel('powerbi')->getLogger()->getHandlers()[0];
}

/**
 * @return array{
 *     campania: Campania, principal: ServicioUps, preventivo: ServicioUps,
 *     inactivo: ServicioUps, vacio: ServicioUps
 * }
 */
function powerBiEscenario(): array
{
    $campania = Campania::factory()->create([
        'nombre' => 'Campaña de atención a niños',
        'fecha_inicio' => '2026-10-20',
        'fecha_fin' => '2026-10-21',
    ]);
    $principal = ServicioUps::factory()->create(['DESCRIPCION_UPS' => 'Cardiología y niños', 'TIPO_UPS' => 'E']);
    $preventivo = ServicioUps::factory()->create(['DESCRIPCION_UPS' => 'Prevención', 'TIPO_UPS' => 'P']);
    $vacio = ServicioUps::factory()->create(['DESCRIPCION_UPS' => 'Sin pacientes']);
    $inactivo = ServicioUps::factory()->create(['DESCRIPCION_UPS' => 'Servicio inactivo', 'ACTIVO' => false]);
    $primero = CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'ups_id' => $principal->id]);
    $segundo = CampaniaServicio::factory()->create([
        'campania_id' => $campania->id,
        'ups_id' => $principal->id,
        'fecha_servicio' => '2026-10-21',
    ]);
    $prevencion = CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'ups_id' => $preventivo->id]);
    foreach ([$vacio, $inactivo] as $ups) {
        CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'ups_id' => $ups->id]);
    }
    $paciente = PacienteMock::factory()->create([
        'nombres' => 'NOMBRE PERSONAL RESERVADO',
        'apellidos' => 'APELLIDO PERSONAL RESERVADO',
        'dni' => '11112222',
        'telefono' => '999888777',
    ]);
    $hoja = HojaRuta::factory()->create(['campania_id' => $campania->id, 'paciente_id' => $paciente->id]);
    foreach ([$primero, $prevencion] as $servicio) {
        HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $hoja->id, 'campania_servicio_id' => $servicio->id]);
    }
    $otroDia = HojaRuta::factory()->create([
        'campania_id' => $campania->id,
        'paciente_id' => $paciente->id,
        'fecha_atencion' => '2026-10-21',
    ]);
    HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $otroDia->id, 'campania_servicio_id' => $segundo->id]);
    $otroPaciente = HojaRuta::factory()->create(['campania_id' => $campania->id]);
    HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $otroPaciente->id, 'campania_servicio_id' => $primero->id]);
    HojaRutaDetalle::factory()->create([
        'hoja_ruta_id' => $otroPaciente->id,
        'campania_servicio_id' => $prevencion->id,
        'deleted_at' => now(),
    ]);
    $eliminada = HojaRuta::factory()->create(['campania_id' => $campania->id, 'deleted_at' => now()]);
    HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $eliminada->id, 'campania_servicio_id' => $primero->id]);
    $retirado = CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'deleted_at' => now()]);
    $sinDetalleVigente = HojaRuta::factory()->create(['campania_id' => $campania->id]);
    HojaRutaDetalle::factory()->create([
        'hoja_ruta_id' => $sinDetalleVigente->id,
        'campania_servicio_id' => $retirado->id,
    ]);
    $ajeno = CampaniaServicio::factory()->create(['ups_id' => $principal->id]);
    $hojaAjena = HojaRuta::factory()->create(['campania_id' => $ajeno->campania_id]);
    HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $hojaAjena->id, 'campania_servicio_id' => $ajeno->id]);

    return compact('campania', 'principal', 'preventivo', 'inactivo', 'vacio');
}

test('Power BI health authenticates either mechanism without a web session', function (string $tipo): void {
    config(['session.driver' => 'database']);
    DB::enableQueryLog();

    $response = $this->withUnencryptedCookies(['laravel_session' => 'cookie-inutil'])
        ->get(powerBiUrl(), powerBiCredenciales($tipo))
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('Content-Type', 'application/json');

    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    expect($response->headers->getCookies())->toBe([]);
    expect(app('request')->hasSession())->toBeFalse();
    expect(DB::getQueryLog())->toBe([]);
})->with(['key', 'basic']);

test('Power BI rejects invalid credentials before querying data', function (array $headers): void {
    DB::enableQueryLog();

    $response = $this->get(powerBiUrl('resumen', 999), $headers)
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Basic realm="powerbi"')
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'No autorizado']);

    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    expect(DB::getQueryLog())->toBe([]);
})->with([
    [[]],
    [['X-Api-Key' => 'incorrecta']],
    [['Authorization' => 'Bearer incorrecto']],
    [['Authorization' => 'Basic !!!']],
    [['Authorization' => 'Basic '.base64_encode('sin-separador')]],
    [['Authorization' => 'Basic '.base64_encode('otro:password-ficticio:privado')]],
    [['Authorization' => 'Basic '.base64_encode('lector-pruebas:incorrecto')]],
]);

test('either valid mechanism suffices when the other supplied credential is invalid', function (string $tipo): void {
    $headers = $tipo === 'key'
        ? [...powerBiCredenciales(), 'Authorization' => 'Basic !!!']
        : [...powerBiCredenciales('basic'), 'X-Api-Key' => 'incorrecta'];

    $this->get(powerBiUrl(), $headers)->assertOk()->assertExactJson(['status' => 'ok']);
})->with(['key', 'basic']);

test('Power BI is hidden when disabled or no complete mechanism is configured', function (array $overrides): void {
    foreach ($overrides as $key => $value) {
        config(['services.powerbi_api.'.$key => $value]);
    }
    DB::enableQueryLog();

    $this->get(powerBiUrl(), powerBiCredenciales())
        ->assertNotFound()
        ->assertExactJson(['message' => 'No encontrado'])
        ->assertHeaderMissing('WWW-Authenticate');
    $this->post(powerBiUrl())->assertNotFound();

    expect(DB::getQueryLog())->toBe([]);
})->with([
    [['enabled' => false]],
    [['user' => '', 'password' => '', 'key' => '']],
    [['user' => 'lector', 'password' => '', 'key' => '']],
    [['user' => '', 'password' => 'secreto', 'key' => '']],
]);

test('only the configured authentication mechanism can grant access', function (string $tipo): void {
    config($tipo === 'basic' ? ['services.powerbi_api.key' => ''] : [
        'services.powerbi_api.user' => '',
        'services.powerbi_api.password' => '',
    ]);

    $this->get(powerBiUrl(), powerBiCredenciales($tipo))->assertOk();
    $this->get(powerBiUrl(), powerBiCredenciales($tipo === 'key' ? 'basic' : 'key'))->assertUnauthorized();
})->with(['key', 'basic']);

test('HTTP and forged forwarding headers cannot bypass required HTTPS', function (array $headers): void {
    $this->get(str_replace('https://', 'http://', powerBiUrl()), [...powerBiCredenciales(), ...$headers])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Se requiere HTTPS']);
})->with([
    [[]],
    [['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '192.0.2.90']],
    [['Forwarded' => 'for=192.0.2.90;proto=https']],
]);

test('HTTP can be enabled explicitly for local testing', function (): void {
    config(['services.powerbi_api.require_https' => false]);

    $this->get(str_replace('https://', 'http://', powerBiUrl()), powerBiCredenciales())
        ->assertOk()->assertExactJson(['status' => 'ok']);
});

test('Power BI allows only GET and HEAD even without a CSRF token', function (string $method, string $endpoint): void {
    $response = $this->call($method, powerBiUrl($endpoint));

    $response->assertStatus(405)
        ->assertHeader('Allow', 'GET, HEAD')
        ->assertExactJson(['message' => 'Método no permitido']);
    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
})->with(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])->with(['', 'resumen', 'servicios']);

test('CORS preflight cannot bypass the API method restriction', function (): void {
    config([
        'cors.paths' => ['api/*'],
        'cors.allowed_origins' => ['https://example.test'],
        'cors.allowed_methods' => ['*'],
        'cors.allowed_headers' => ['*'],
    ]);

    $this->options(powerBiUrl(), [], [
        'Origin' => 'https://example.test',
        'Access-Control-Request-Method' => 'POST',
    ])->assertStatus(405)->assertExactJson(['message' => 'Método no permitido']);
});

test('method overrides cannot turn an actual POST into an allowed API request', function (string $tipo): void {
    $headers = powerBiCredenciales();
    $data = $tipo === 'formulario' ? ['_method' => 'GET'] : [];
    if ($tipo === 'cabecera') {
        $headers['X-HTTP-Method-Override'] = 'GET';
    }

    $this->post(powerBiUrl(), $data, $headers)
        ->assertStatus(405)->assertExactJson(['message' => 'Método no permitido']);
})->with(['formulario', 'cabecera']);

test('credentials supplied only in the query string never authenticate the API', function (): void {
    $this->get(powerBiUrl().'?'.http_build_query([
        'api_key' => 'clave-ficticia-privada',
        'user' => 'lector-pruebas',
        'password' => 'password-ficticio:privado',
    ]))->assertUnauthorized()->assertExactJson(['message' => 'No autorizado']);
});

test('Power BI HEAD returns no body for successful and unauthorized responses', function (string $endpoint): void {
    $campania = Campania::factory()->create();
    $this->head(powerBiUrl($endpoint, $campania->id), powerBiCredenciales())
        ->assertOk()->assertContent('')->assertHeader('Content-Type', 'application/json');
    $this->head(powerBiUrl($endpoint, $campania->id))
        ->assertUnauthorized()->assertContent('')->assertHeader('WWW-Authenticate', 'Basic realm="powerbi"');
})->with(['', 'resumen', 'servicios']);

test('Power BI returns the same JSON 404 for missing deleted and invalid campaign identifiers', function (
    string $caso,
    string $endpoint,
): void {
    $id = match ($caso) {
        'eliminada' => Campania::factory()->create(['deleted_at' => now()])->id,
        'cero' => 0,
        'texto' => 'abc',
        'desbordamiento' => '9999999999999999999999999999',
        default => 999,
    };

    $response = $this->get(powerBiUrl($endpoint, $id), [...powerBiCredenciales(), 'Accept' => 'text/html'])
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson(['message' => 'Campaña no encontrada']);

    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
})->with(['eliminada', 'inexistente', 'cero', 'texto', 'desbordamiento'])->with(['resumen', 'servicios']);

test('summary counts distinct patients and active details using the exact public contract', function (): void {
    ['campania' => $campania] = powerBiEscenario();
    DB::enableQueryLog();

    $response = $this->get(powerBiUrl('resumen', $campania->id), powerBiCredenciales())
        ->assertOk()
        ->assertExactJson(['data' => [
            'campania_id' => $campania->id,
            'campania' => 'Campaña de atención a niños',
            'fecha_inicio' => '2026-10-20',
            'fecha_fin' => '2026-10-21',
            'estado' => 'Activa',
            'atendidos' => 3,
            'atenciones' => 4,
            'generado_en' => '2026-10-20T12:00:00-05:00',
        ]]);

    expect($response->json('data.atendidos'))->toBeInt();
    expect($response->json('data.atenciones'))->toBeInt();
    expect($response->getContent())->toContain('Campaña', 'niños')->not->toContain('\\u00');
    expect($response->getContent())->not->toContain('11112222', '999888777', 'PERSONAL RESERVADO');
    $queries = DB::getQueryLog();
    expect($queries)->toHaveCount(3);
    foreach ($queries as $query) {
        expect(strtolower($query['query']))->toStartWith('select');
        expect($query['query'])->not->toContain('TB_PACIENTES_MOCK', 'TB_ACCESO_USUARIO');
    }
});

test('services aggregate dates and patients and retain configured zero counts', function (): void {
    $escenario = powerBiEscenario();
    DB::enableQueryLog();

    $response = $this->get(powerBiUrl('servicios', $escenario['campania']->id), powerBiCredenciales())
        ->assertOk()
        ->assertExactJson(['data' => [
            [
                'ups_id' => $escenario['principal']->id,
                'servicio' => 'Cardiología y niños',
                'tipo_servicio' => 'E',
                'pacientes' => 2,
            ],
            [
                'ups_id' => $escenario['preventivo']->id,
                'servicio' => 'Prevención',
                'tipo_servicio' => 'P',
                'pacientes' => 1,
            ],
            [
                'ups_id' => $escenario['inactivo']->id,
                'servicio' => 'Servicio inactivo',
                'tipo_servicio' => 'P',
                'pacientes' => 0,
            ],
            [
                'ups_id' => $escenario['vacio']->id,
                'servicio' => 'Sin pacientes',
                'tipo_servicio' => 'P',
                'pacientes' => 0,
            ],
        ]]);

    foreach ($response->json('data') as $fila) {
        expect($fila['ups_id'])->toBeInt();
        expect($fila['pacientes'])->toBeInt();
        expect(array_keys($fila))->toBe(['ups_id', 'servicio', 'tipo_servicio', 'pacientes']);
    }
    expect(DB::getQueryLog())->toHaveCount(2);
});

test('an empty campaign returns integer zero totals and an empty service list', function (): void {
    $campania = Campania::factory()->create();
    $this->get(powerBiUrl('resumen', $campania->id), powerBiCredenciales())
        ->assertOk()->assertJsonPath('data.atendidos', 0)->assertJsonPath('data.atenciones', 0);
    $this->get(powerBiUrl('servicios', $campania->id), powerBiCredenciales())
        ->assertOk()->assertExactJson(['data' => []]);
});

test('Power BI reuses the current campaign state in every period including closed campaigns', function (
    array $fechas,
    string $estado,
): void {
    $campania = Campania::factory()->create($fechas);
    $this->get(powerBiUrl('resumen', $campania->id), powerBiCredenciales())
        ->assertOk()->assertJsonPath('data.estado', $estado);
    $this->get(powerBiUrl('servicios', $campania->id), powerBiCredenciales())->assertOk();
})->with([
    [['fecha_inicio' => '2026-11-03', 'fecha_fin' => '2026-11-04'], 'Activa'],
    [['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-19'], 'Finalizada'],
    [['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-19', 'cerrada' => true], 'Cerrada'],
]);

test('Power BI limits all endpoints together to sixty requests per IP per minute', function (): void {
    $campania = Campania::factory()->create();
    for ($indice = 0; $indice < 60; $indice++) {
        $this->get(powerBiUrl(), powerBiCredenciales())->assertOk();
    }
    $this->get(powerBiUrl('resumen', $campania->id), powerBiCredenciales())
        ->assertTooManyRequests()->assertHeader('Retry-After', '60');
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])
        ->get(powerBiUrl(), powerBiCredenciales())->assertOk();
    $this->travel(61)->seconds();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get(powerBiUrl(), powerBiCredenciales())->assertOk();
});

test('ten authentication failures trigger a separate temporary IP block even for valid credentials', function (): void {
    for ($indice = 0; $indice < 10; $indice++) {
        $this->get(powerBiUrl())->assertUnauthorized();
    }
    $this->get(powerBiUrl(), powerBiCredenciales())
        ->assertTooManyRequests()->assertHeader('Retry-After', '60');
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.3'])
        ->get(powerBiUrl(), powerBiCredenciales())->assertOk();
    $this->travel(61)->seconds();
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get(powerBiUrl(), powerBiCredenciales())->assertOk();
});

test('audit logs exclude credentials query strings and response data', function (): void {
    $campania = Campania::factory()->create(['nombre' => 'Nombre reservado de campaña']);
    $this->get(powerBiUrl('resumen', $campania->id).'?password=password-ficticio:privado', [
        ...powerBiCredenciales('basic'),
        ...powerBiCredenciales(),
    ])->assertOk();
    $this->get(powerBiUrl(), ['Authorization' => 'Basic cabecera-rechazada'])->assertUnauthorized();
    $this->post(powerBiUrl())->assertStatus(405);
    $this->get(powerBiUrl('resumen', 999), powerBiCredenciales())->assertNotFound();
    $records = powerBiLog()->getRecords();

    expect($records)->toHaveCount(4);
    expect(array_map(fn (LogRecord $record): int => $record->context['codigo_http'], $records))
        ->toBe([200, 401, 405, 404]);
    foreach ($records as $record) {
        expect(array_keys($record->context))->toBe(['fecha', 'ip', 'ruta', 'campania', 'codigo_http', 'duracion_ms']);
        expect($record->context['duracion_ms'])->toBeGreaterThanOrEqual(0);
    }
    expect($records[0]->context['campania'])->toBe($campania->id);
    expect($records[0]->context['ruta'])->toBe('/api/powerbi/campanias/{campania}/resumen');
    $contenido = json_encode(array_map(fn (LogRecord $record): array => $record->toArray(), $records));
    expect($contenido)->not->toContain(
        'password-ficticio:privado',
        'clave-ficticia-privada',
        'cabecera-rechazada',
        base64_encode('lector-pruebas:password-ficticio:privado'),
        'Nombre reservado de',
        'Authorization',
        'X-Api-Key',
    );
});

test('Power BI server errors remain generic JSON even with application debug enabled', function (): void {
    config(['app.debug' => true]);
    $this->mock(PowerBiResumenService::class, function ($mock): void {
        $mock->shouldReceive('resumen')->once()->andThrow(new RuntimeException('Detalle interno de prueba'));
    });

    $response = $this->get(powerBiUrl('resumen'), [...powerBiCredenciales(), 'Accept' => 'text/html'])
        ->assertStatus(500)->assertExactJson(['message' => 'No se pudo completar la solicitud']);

    expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue();
    expect(powerBiLog()->getRecords()[0]->context['codigo_http'])->toBe(500);
});
