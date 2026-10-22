<?php

use App\Services\ConsultaDniApiService;
use App\Services\ResultadoConsultaDni;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaConfigurarDniApi();
    $this->travelTo(Carbon::parse('2026-10-20 12:00:00', 'America/Lima'));
});

afterEach(function (): void {
    $this->travelBack();
});

test('DNI API normalizes a successful response without using its supplied age', function (): void {
    Http::fake(['https://dni.example.test/*' => Http::response(ofertaRespuestaDni())]);
    Log::spy();

    $resultado = app(ConsultaDniApiService::class)->consultar('73050308');

    expect($resultado->estado)->toBe(ResultadoConsultaDni::ENCONTRADO);
    expect($resultado->datos)->toBe([
        'tipo_doc' => 'DNI',
        'dni' => '73050308',
        'nombres' => 'JUAN CARLOS',
        'apellidos' => 'PÉREZ GARCÍA',
        'fecha_nacimiento' => '1980-08-27',
        'sexo' => 'M',
        'telefono' => null,
    ]);
    expect($resultado->camposPendientes())->toBe([]);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://dni.example.test/consulta?numeroDocumento=73050308'
        && $request->hasHeader('Authorization', 'Bearer credencial-ficticia-privada')
        && $request->hasHeader('Accept', 'application/json')
    );
    Http::assertSentCount(1);
    Log::shouldHaveReceived('info')->once()->with('Consulta DNI.', ['resultado' => 'ENCONTRADO', 'http' => 200]);
});

test('DNI API recognizes empty or missing identity data as not found', function (array $body): void {
    Http::fake(['https://dni.example.test/*' => Http::response($body)]);

    $resultado = app(ConsultaDniApiService::class)->consultar('73050308');

    expect($resultado->estado)->toBe(ResultadoConsultaDni::NO_ENCONTRADO);
    expect($resultado->datos)->toBe([]);
})->with([
    'empty' => [['data' => []]],
    'missing document' => [['data' => ['nombres' => 'OTRA PERSONA']]],
    'empty object' => [['data' => (object) []]],
]);

test('DNI API treats failed HTTP responses as errors without retrying them', function (int $status): void {
    Http::fake(['https://dni.example.test/*' => Http::response(['data' => []], $status)]);

    expect(app(ConsultaDniApiService::class)->consultar('73050308')->estado)->toBe(ResultadoConsultaDni::ERROR);
    Http::assertSentCount(1);
})->with([401, 403, 429, 500, 503, 302]);

test('invalid API credentials are logged without request details', function (): void {
    Http::fake(['https://dni.example.test/*' => Http::response([], 401)]);
    Log::spy();

    expect(app(ConsultaDniApiService::class)->consultar('73050308')->estado)->toBe(ResultadoConsultaDni::ERROR);
    Log::shouldHaveReceived('error')->once()->with(
        'Configuración de consulta DNI inválida.',
        ['resultado' => 'ERROR', 'http' => 401],
    );
    Log::shouldNotHaveReceived('info');
});

test('connection and timeout failures use exactly one retry and hide exception details', function (): void {
    Http::fake(['https://dni.example.test/*' => Http::failedConnection('Timeout con datos privados')]);
    Log::spy();

    expect(app(ConsultaDniApiService::class)->consultar('73050308')->estado)->toBe(ResultadoConsultaDni::ERROR);
    Http::assertSentCount(2);
    Log::shouldHaveReceived('info')->once()->with('Consulta DNI.', ['resultado' => 'ERROR', 'http' => null]);
});

test('DNI API rejects invalid JSON and mismatched identity responses', function (mixed $body): void {
    Http::fake(['https://dni.example.test/*' => Http::response($body)]);

    $resultado = app(ConsultaDniApiService::class)->consultar('73050308');

    expect($resultado->estado)->toBe(ResultadoConsultaDni::ERROR);
    expect($resultado->datos)->toBe([]);
})->with([
    'invalid JSON' => ['{invalid'],
    'JSON scalar' => ['null'],
    'different document' => [ofertaRespuestaDni(['numeroDocumento' => '00000000'])],
    'unrecognized code' => [array_replace(ofertaRespuestaDni(), ['codigo' => '400'])],
]);

test('DNI API maps sex identifiers and fallback text', function (mixed $id, mixed $texto, ?string $expected): void {
    Http::fake(['https://dni.example.test/*' => Http::response(ofertaRespuestaDni([
        'idSexo' => $id,
        'sexo' => $texto,
    ]))]);

    $resultado = app(ConsultaDniApiService::class)->consultar('73050308');

    expect($resultado->datos['sexo'])->toBe($expected);
})->with([
    ['1', 'FEMENINO', 'M'],
    [2, 'MASCULINO', 'F'],
    [null, ' femenino ', 'F'],
    ['', 'Masculino', 'M'],
    [null, null, null],
    ['3', 'Masculino', null],
]);

test('DNI API marks invalid dates and missing sex for manual completion', function (array $data, array $fields): void {
    Http::fake(['https://dni.example.test/*' => Http::response(ofertaRespuestaDni($data))]);

    $resultado = app(ConsultaDniApiService::class)->consultar('73050308');

    expect($resultado->estado)->toBe(ResultadoConsultaDni::ENCONTRADO);
    expect($resultado->camposPendientes())->toBe($fields);
})->with([
    [['fechaNacimiento' => '31/02/1980'], ['fecha_nacimiento']],
    [['fechaNacimiento' => '01/01/0000'], ['fecha_nacimiento']],
    [['fechaNacimiento' => '01/01/2099'], ['fecha_nacimiento']],
    [['fechaNacimiento' => null], ['fecha_nacimiento']],
    [['idSexo' => null, 'sexo' => null], ['sexo']],
    [['nombres' => '   '], ['nombres']],
]);

test('DNI API refuses invalid document strings before sending a request', function (string $dni): void {
    Http::fake(['https://dni.example.test/*' => Http::response(ofertaRespuestaDni())]);

    expect(app(ConsultaDniApiService::class)->consultar($dni)->estado)->toBe(ResultadoConsultaDni::ERROR);
    Http::assertNothingSent();
})->with(['1234567', '123456789', '1234567A', "73050308\n", '73050308&otro=1']);

test('disabled DNI API makes no HTTP requests', function (): void {
    config(['services.dni_api.enabled' => false]);

    expect(app(ConsultaDniApiService::class)->consultar('73050308')->estado)
        ->toBe(ResultadoConsultaDni::NO_ENCONTRADO);
    Http::assertNothingSent();
});

test('DNI API preserves leading zeroes in the document', function (): void {
    Http::fake([
        'https://dni.example.test/consulta?numeroDocumento=00050308' => Http::response(ofertaRespuestaDni([
            'numeroDocumento' => '00050308',
        ])),
    ]);

    $resultado = app(ConsultaDniApiService::class)->consultar('00050308');

    expect($resultado->estado)->toBe(ResultadoConsultaDni::ENCONTRADO);
    expect($resultado->datos['dni'])->toBe('00050308');
    Http::assertSentCount(1);
});
