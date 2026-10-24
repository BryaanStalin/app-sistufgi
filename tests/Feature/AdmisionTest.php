<?php

use App\Models\Campania;
use App\Models\CampaniaEnlace;
use App\Models\CampaniaServicio;
use App\Models\CampaniaUsuario;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\HojaRutaImpresion;
use App\Models\Menu;
use App\Models\ServicioUps;
use App\Models\User;
use App\Services\AdmisionService;
use App\Services\CampaniaService;
use App\Services\TriajeService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/OfertaFlexibleFixtures.php';

beforeEach(function (): void {
    ofertaFlexibleFixtures();
    config(['cache.stores.file' => ['driver' => 'array', 'serialize' => false]]);
});

afterEach(function (): void {
    $this->travelBack();
});

test('PDF downloads and reloads reuse one audited emission with a safe filename', function (): void {
    ['usuario' => $usuario, 'campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    $hoja->paciente->update(['tipo_doc' => 'CE', 'dni' => 'AB/Ñ 1234567']);
    $respuesta = $this
        ->actingAs($usuario)
        ->post(route('oferta-flexible.admision.imprimir'), [
            'campania_id' => $campania->id,
            'hoja_id' => $hoja->id,
        ])
        ->assertStatus(303);
    $url = $respuesta->headers->get('Location');
    expect(parse_url($url, PHP_URL_PATH))->toEndWith('.pdf');
    $pdf = $this
        ->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="Hoja_de_Ruta_AB_N_1234567_20261020.pdf"');
    expect($pdf->getContent())->toStartWith('%PDF-');
    $this->travel(1)->minutes();
    $recarga = $this->get($url)->assertOk();
    expect(hash('sha256', $recarga->getContent()))->toBe(hash('sha256', $pdf->getContent()));
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 1);

    if ($directorio = getenv('HOJA_RUTA_PDF_PREVIEW_DIR')) {
        file_put_contents($directorio.'/respuesta.pdf', $pdf->getContent());
        file_put_contents($directorio.'/respuesta.json', json_encode([
            'path' => parse_url($url, PHP_URL_PATH),
            'headers' => array_intersect_key($pdf->headers->all(), array_flip([
                'content-type',
                'content-disposition',
                'content-length',
                'cache-control',
                'x-content-type-options',
            ])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
});

test('temporary PDF refuses other users and rechecks the original users current scope', function (
    string $cambio,
): void {
    ['usuario' => $usuario, 'campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    $respuesta = $this
        ->actingAs($usuario)
        ->post(route('oferta-flexible.admision.imprimir'), [
            'campania_id' => $campania->id,
            'hoja_id' => $hoja->id,
        ])
        ->assertStatus(303);
    $url = $respuesta->headers->get('Location');

    match ($cambio) {
        'otro_usuario' => $this->actingAs(admisionActor()),
        'administrador_ajeno' => $this->actingAs(admisionActor('Administrador')),
        'sin_enlace' => CampaniaEnlace::query()
            ->where('campania_id', $campania->id)
            ->delete(),
        'sin_asignacion' => CampaniaUsuario::query()
            ->where('usuario_id', $usuario->id)
            ->delete(),
        'hoja_eliminada' => $hoja->delete(),
    };

    $this->get($url)->assertNotFound();
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 1);
})->with(['otro_usuario', 'administrador_ajeno', 'sin_enlace', 'sin_asignacion', 'hoja_eliminada']);

test('temporary PDF still requires login role and the original menu permission', function (string $caso): void {
    $url = route('oferta-flexible.admision.pdf', ['token' => str_repeat('a', 64)]);
    if ($caso === 'invitado') {
        $this->get($url)->assertRedirect(route('login'));
    } elseif ($caso === 'sin_menu') {
        $this
            ->actingAs(ofertaActor('Admisión'))
            ->get($url)
            ->assertRedirect(route('dashboard'));
    } else {
        $this
            ->actingAs(admisionActor('Médico Triaje'))
            ->get($url)
            ->assertForbidden();
    }
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
})->with(['invitado', 'sin_menu', 'otro_rol']);

test('temporary PDF expires without adding an impression', function (): void {
    ['usuario' => $usuario, 'campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    $respuesta = $this
        ->actingAs($usuario)
        ->post(route('oferta-flexible.admision.imprimir'), [
            'campania_id' => $campania->id,
            'hoja_id' => $hoja->id,
        ])
        ->assertStatus(303);
    $this->travel(31)->minutes();

    $this->get($respuesta->headers->get('Location'))->assertStatus(410);
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 1);
});

test('failed temporary PDF storage leaves no impression record', function (): void {
    ['usuario' => $usuario, 'campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    $cache = Mockery::mock(Repository::class);
    $cache
        ->shouldReceive('put')
        ->once()
        ->andReturn(false);
    $cache
        ->shouldReceive('forget')
        ->once()
        ->andReturn(true);
    Cache::shouldReceive('store')
        ->with('file')
        ->twice()
        ->andReturn($cache);

    $this
        ->actingAs($usuario)
        ->postJson(route('oferta-flexible.admision.imprimir'), [
            'campania_id' => $campania->id,
            'hoja_id' => $hoja->id,
        ])
        ->assertStatus(503);
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
});

function admisionActor(string $rol = 'Admisión'): User
{
    $usuario = ofertaActor($rol);
    $menu = Menu::firstOrCreate(
        ['link' => '/oferta-flexible/admision'],
        ['titulo' => 'Listado de pacientes', 'orden' => 1, 'status' => 1],
    );
    $usuario->roles
        ->first()
        ->menus()
        ->syncWithoutDetaching([$menu->id => ['status' => 1]]);

    return $usuario;
}

/** @return array{usuario: User, medico: User, campania: Campania, servicio: CampaniaServicio, hoja: HojaRuta} */
function admisionEscenario(bool $asociar = true): array
{
    $usuario = admisionActor();
    $medico = ofertaActor('Médico Triaje');
    $campania = Campania::factory()->create([
        'nombre' => 'Campaña de especialidad',
        'asociar_triaje_admision' => $asociar,
    ]);
    CampaniaUsuario::factory()->create([
        'campania_id' => $campania->id,
        'usuario_id' => $usuario->id,
        'tipo' => 'A',
    ]);
    CampaniaUsuario::factory()->create([
        'campania_id' => $campania->id,
        'usuario_id' => $medico->id,
        'tipo' => 'M',
    ]);
    if ($asociar) {
        CampaniaEnlace::factory()->create([
            'campania_id' => $campania->id,
            'medico_usuario_id' => $medico->id,
            'admision_usuario_id' => $usuario->id,
        ]);
    }
    $servicio = CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'cupos' => 1]);
    $hoja = ofertaHoja($servicio, $medico);

    return compact('usuario', 'medico', 'campania', 'servicio', 'hoja');
}

test('admission name search matches every word across names and surnames in any order', function (
    string $busqueda,
    bool $encontrado,
): void {
    ['usuario' => $usuario, 'campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    $hoja->paciente->update([
        'nombres' => 'JUAN CARLOS',
        'apellidos' => 'PEREZ GARCIA',
        'dni' => '73050308',
    ]);

    $response = $this->actingAs($usuario)
        ->getJson(route('oferta-flexible.admision.index', [
            'campania_id' => $campania->id,
            'q' => $busqueda,
        ]))
        ->assertOk();

    $documento = new DOMDocument;
    @$documento->loadHTML('<?xml encoding="UTF-8">'.$response->json('html'));
    $xpath = new DOMXPath($documento);
    expect(trim($xpath->query('//*[@data-resumen="total"]')
        ->item(0)
        ->textContent))
        ->toBe($encontrado ? '1' : '0');

    if ($encontrado) {
        expect($response->json('html'))->toContain('JUAN CARLOS PEREZ GARCIA');
    } else {
        expect($response->json('html'))->not->toContain('JUAN CARLOS PEREZ GARCIA');
    }
})->with([
    ['PEREZ JUAN', true],
    ['garcia carlos juan', true],
    [" JUAN\tGARCIA  CARLOS ", true],
    ['CAR GAR', true],
    ['73050308', true],
    ['JUAN DESCONOCIDO', false],
]);

test('admission guests must sign in for every operation', function (): void {
    $url = route('oferta-flexible.admision.index');
    $this->get($url)->assertRedirect(route('login'));
    $this->post($url)->assertRedirect(route('login'));
    $this->delete($url)->assertRedirect(route('login'));
});

test('admission refuses other roles even with a menu permission', function (string $rol): void {
    $usuario = admisionActor($rol);
    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertForbidden();
    $this->postJson(route('oferta-flexible.admision.imprimir'), [
        'campania_id' => 1,
        'hoja_id' => 1,
    ])->assertForbidden();
    $this->deleteJson(route('oferta-flexible.admision.destroy'), [
        'campania_id' => 1,
        'hoja_id' => 1,
    ])->assertForbidden();
})->with(['Médico Triaje']);

test('admission requires the exact menu permission', function (): void {
    $this
        ->actingAs(ofertaActor('Admisión'))
        ->get(route('oferta-flexible.admision.index'))
        ->assertRedirect(route('dashboard'));
});

test('admission refuses inactive user role or pivot', function (string $inactive): void {
    $usuario = admisionActor();
    match ($inactive) {
        'usuario' => $usuario->update(['status' => false]),
        'rol' => $usuario->roles->first()->update(['status' => false]),
        'pivote' => $usuario
            ->roles()
            ->updateExistingPivot($usuario->roles->first()->id, ['status' => false]),
    };
    expect(fn () => app(AdmisionService::class)
        ->imprimir($usuario, 1, 1))
        ->toThrow(HttpException::class);
    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertRedirect(route('login'));
})->with(['usuario', 'rol', 'pivote']);

test('admission without campaigns sees a clear empty state', function (): void {
    $this
        ->actingAs(admisionActor())
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertSee('No tiene campañas disponibles asignadas a Admisión.');
});

test('admission sees only linked doctors and sheets in the selected campaign', function (): void {
    [
        'usuario' => $usuario,
        'medico' => $medico,
        'campania' => $campania,
        'servicio' => $servicio,
        'hoja' => $hoja,
    ] = admisionEscenario();
    $ajena = ofertaHoja($servicio);
    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertSee($campania->nombre)
        ->assertSee($medico->nombre)
        ->assertSee($hoja->paciente->nombre_completo)
        ->assertDontSee($ajena->paciente->nombre_completo)
        ->assertDontSee($ajena->medico->nombre)
        ->assertSee('data-script="oferta_flexible/admision"', false);
});

test('unassociated campaigns show all sheets to assigned admission users', function (): void {
    ['usuario' => $usuario, 'servicio' => $servicio] = admisionEscenario(false);
    $otra = ofertaHoja($servicio);
    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertSee($otra->paciente->nombre_completo);
});

test('associated campaigns with no links return no sheets', function (): void {
    ['usuario' => $usuario, 'hoja' => $hoja] = admisionEscenario();
    CampaniaEnlace::firstOrFail()->delete();
    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertSee('No tiene médicos enlazados en esta campaña.')
        ->assertDontSee($hoja->paciente->nombre_completo);
});

test('campaign selection prefers active campaigns and supports closed history', function (): void {
    ['usuario' => $usuario, 'campania' => $activa] = admisionEscenario();
    $cerrada = Campania::factory()->create(['nombre' => 'Historial cerrado', 'cerrada' => true]);
    CampaniaUsuario::factory()->create([
        'campania_id' => $cerrada->id,
        'usuario_id' => $usuario->id,
        'tipo' => 'A',
    ]);
    $ajena = Campania::factory()->create(['nombre' => 'Campaña ajena invisible']);
    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertViewHas('campania', fn ($row): bool => $row->id === $activa->id)
        ->assertSee('id="admision-campania"', false)
        ->assertDontSee($ajena->nombre);
    $this
        ->get(route('oferta-flexible.admision.index', ['campania_id' => $cerrada->id]))
        ->assertOk()
        ->assertSee('Campaña cerrada');
    $this->get(route('oferta-flexible.admision.index', ['campania_id' => $ajena->id]))->assertNotFound();
});

test('date doctor and search filters are retained in ajax pagination', function (): void {
    [
        'usuario' => $usuario,
        'medico' => $medico,
        'campania' => $campania,
        'servicio' => $servicio,
        'hoja' => $hoja,
    ] = admisionEscenario();
    $hoja->paciente->update(['nombres' => 'Paciente Muñoz 00']);
    for ($i = 1; $i <= 15; $i++) {
        $otra = ofertaHoja($servicio, $medico);
        $otra->paciente->update([
            'nombres' => 'Paciente Muñoz '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        ]);
    }
    $filters = [
        'campania_id' => $campania->id,
        'fecha' => '2026-10-20',
        'medico_id' => $medico->id,
        'q' => 'Muñoz',
    ];
    $respuesta = $this
        ->actingAs($usuario)
        ->getJson(route('oferta-flexible.admision.index', $filters))
        ->assertOk();
    $html = html_entity_decode($respuesta->json('html'));
    expect($html)->toContain(
        '16 pacientes encontrados',
        'fecha=2026-10-20',
        'medico_id='.$medico->id,
        'q=Mu%C3%B1oz',
        'page=2',
    );
    $this
        ->getJson(route('oferta-flexible.admision.index', [...$filters, 'page' => 2]))
        ->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, 'Paciente Muñoz 00'));
    $this
        ->getJson(route('oferta-flexible.admision.index', [...$filters, 'fecha' => '2026-10-19']))
        ->assertOk()
        ->assertJsonPath('html', fn (string $html): bool => str_contains($html, '0 pacientes encontrados'));
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
});

test('sheet summaries cover all filtered pages and preserve each role scope', function (string $role): void {
    [
        'usuario' => $admission,
        'medico' => $doctor,
        'campania' => $campaign,
        'servicio' => $service,
        'hoja' => $first,
    ] = admisionEscenario();
    $actor = $role === 'Administrador' ? admisionActor($role) : $admission;
    for ($index = 0; $index < 18; $index++) {
        $sheet = $index === 0 ? $first : ofertaHoja($service, $doctor);
        $sheet->paciente->update(['nombres' => 'Resumen filtro '.$index]);
        $sheet
            ->forceFill([
                'updated_at' => $index < 3 ? '2026-10-20 12:00:00' : '2026-10-20 10:00:00',
            ])
            ->save();
        if ($index < 10) {
            foreach (['2026-10-20 11:00:00', '2026-10-20 11:30:00'] as $printedAt) {
                HojaRutaImpresion::factory()->create([
                    'hoja_ruta_id' => $sheet->id,
                    'impreso_por' => $admission->id,
                    'impreso_at' => $printedAt,
                ]);
            }
        }
    }
    $foreign = ofertaHoja($service);
    $foreign->paciente->update(['nombres' => 'Resumen de otro médico']);
    $outsideSearch = ofertaHoja($service, $doctor);
    $outsideSearch->paciente->update(['nombres' => 'Paciente fuera de búsqueda']);
    $outsideDate = ofertaHoja($service, $doctor);
    $outsideDate->paciente->update(['nombres' => 'Resumen de ayer']);
    $outsideDate->update(['fecha_atencion' => '2026-10-19']);
    $deleted = ofertaHoja($service, $doctor);
    $deleted->paciente->update(['nombres' => 'Resumen eliminado']);
    $deleted->delete();
    $filters = [
        'campania_id' => $campaign->id,
        'fecha' => '2026-10-20',
        'medico_id' => $doctor->id,
        'q' => 'Resumen',
    ];

    $response = $this
        ->actingAs($actor)
        ->get(route('oferta-flexible.admision.index', $filters))
        ->assertOk();

    expect($response['resumen'])->toBe([
        'total' => 18,
        'impresas' => 10,
        'pendientes' => 8,
        'modificadas' => 3,
    ]);
    expect($response['hojas']->count())->toBe(15);
    $ajax = $this->getJson(route('oferta-flexible.admision.index', [...$filters, 'page' => 2]))->assertOk();
    expect(array_keys($ajax->json()))->toBe(['html']);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$ajax->json('html'));
    $xpath = new DOMXPath($document);
    foreach (['total' => 18, 'impresas' => 10, 'pendientes' => 8, 'modificadas' => 3] as $key => $count) {
        expect(trim($xpath->query('//*[@data-resumen="'.$key.'"]')
            ->item(0)
            ->textContent))
            ->toBe(
                (string) $count,
            );
    }
    unset($filters['medico_id']);
    $all = $this->get(route('oferta-flexible.admision.index', $filters))->assertOk();
    expect($all['resumen']['total'])->toBe($role === 'Administrador' ? 19 : 18);
    $empty = $this->get(
        route('oferta-flexible.admision.index', [...$filters, 'q' => 'Sin coincidencias']),
    )->assertOk();
    expect($empty['resumen'])->toBe(['total' => 0, 'impresas' => 0, 'pendientes' => 0, 'modificadas' => 0]);
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 20);
})->with(['Admisión', 'Administrador']);

test('document search and manipulated doctor filters cannot expand admission scope', function (): void {
    ['usuario' => $usuario, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $ajena = ofertaHoja($servicio);
    $this
        ->actingAs($usuario)
        ->getJson(route('oferta-flexible.admision.index', ['q' => $hoja->paciente->dni]))
        ->assertOk()
        ->assertJsonPath(
            'html',
            fn (string $html): bool => str_contains($html, e($hoja->paciente->nombre_completo)),
        );
    foreach (
        [
            ['q' => "' OR 1=1 --"],
            ['medico_id' => $ajena->medico_usuario_id],
            ['q' => $ajena->paciente->nombre_completo],
        ] as $filter
    ) {
        $this
            ->getJson(route('oferta-flexible.admision.index', $filter))
            ->assertOk()
            ->assertJsonPath(
                'html',
                fn (string $html): bool => str_contains($html, '0 pacientes encontrados'),
            );
    }
});

test('admission validates filters with Spanish messages', function (array $data, string $field): void {
    $this
        ->actingAs(admisionActor())
        ->getJson(route('oferta-flexible.admision.index', $data))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    [['fecha' => '2026-02-30'], 'fecha'],
    [['campania_id' => -1], 'campania_id'],
    [['medico_id' => 'x'], 'medico_id'],
    [['q' => str_repeat('a', 201)], 'q'],
    [['page' => 0], 'page'],
]);

test('foreign sheet ids cannot be printed or deleted', function (string $scope): void {
    [
        'usuario' => $usuario,
        'campania' => $campania,
        'servicio' => $servicio,
        'hoja' => $hoja,
    ] = admisionEscenario();
    $data = ['campania_id' => $campania->id, 'hoja_id' => $hoja->id];
    match ($scope) {
        'medico' => ($data['hoja_id'] = ofertaHoja($servicio)->id),
        'campania' => ($data['hoja_id'] = ofertaHoja(CampaniaServicio::factory()
            ->create())
            ->id),
        'campania_ajena' => ($data['campania_id'] = Campania::factory()
            ->create()
            ->id),
        'sin_asignacion' => CampaniaUsuario::where('usuario_id', $usuario->id)
            ->firstOrFail()
            ->delete(),
        'enlace_eliminado' => CampaniaEnlace::firstOrFail()->delete(),
        'hoja_eliminada' => $hoja->delete(),
        'campania_eliminada' => $campania->delete(),
    };
    $this
        ->actingAs($usuario)
        ->postJson(route('oferta-flexible.admision.imprimir'), $data)
        ->assertNotFound();
    $this->deleteJson(route('oferta-flexible.admision.destroy'), $data)->assertNotFound();
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
})->with([
    'medico',
    'campania',
    'campania_ajena',
    'sin_asignacion',
    'enlace_eliminado',
    'hoja_eliminada',
    'campania_eliminada',
]);

test('print and deletion require validated ids', function (): void {
    $this
        ->actingAs(admisionActor())
        ->postJson(route('oferta-flexible.admision.imprimir'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['campania_id', 'hoja_id'])
        ->assertJsonPath('errors.hoja_id.0', 'El campo hoja de ruta es obligatorio.');
    $this
        ->deleteJson(route('oferta-flexible.admision.destroy'), ['campania_id' => 1, 'hoja_id' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hoja_id');
});

test('each generated PDF appends an audited impression even when the campaign is closed', function (
    bool $closed,
): void {
    [
        'usuario' => $usuario,
        'medico' => $medico,
        'campania' => $campania,
        'servicio' => $servicio,
        'hoja' => $hoja,
    ] = admisionEscenario();
    $campania->update(['cerrada' => $closed]);
    $hoja->paciente->update([
        'nombres' => 'María Muñoz Peña',
        'fecha_nacimiento' => '1980-10-20',
        'sexo' => 'F',
    ]);
    $updatedAt = $hoja->updated_at->toDateTimeString();
    $data = ['campania_id' => $campania->id, 'hoja_id' => $hoja->id, 'impreso_por' => $medico->id];
    $respuesta = $this
        ->actingAs($usuario)
        ->post(route('oferta-flexible.admision.imprimir'), $data)
        ->assertStatus(303);
    $respuesta = $this
        ->get($respuesta->headers->get('Location'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($respuesta->getContent())->toStartWith('%PDF-');
    $this->assertDatabaseHas('TB_HOJA_RUTA_IMPRESIONES', [
        'hoja_ruta_id' => $hoja->id,
        'impreso_por' => $usuario->id,
        'created_by' => $usuario->id,
        'impreso_at' => '2026-10-20 12:00:00',
    ]);
    $this->post(route('oferta-flexible.admision.imprimir'), $data)->assertStatus(303);
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 2);
    expect($hoja->refresh()
        ->updated_at->toDateTimeString())
        ->toBe($updatedAt);
    $list = $this->get(route('oferta-flexible.admision.index'))->assertOk();
    expect(preg_match('/\bModificada\b/u', strip_tags($list->getContent())))->toBe(0);
    if ($closed) {
        $this->get(route('oferta-flexible.admision.index'))->assertDontSee('admision-eliminar', false);
    }
    if (! $closed && ($preview = getenv('ADMISION_PREVIEW_DIR'))) {
        if (! is_dir($preview)) {
            mkdir($preview, recursive: true);
        }
        file_put_contents($preview.'/hoja-de-ruta.pdf', $respuesta->getContent());
        file_put_contents(
            $preview.'/index.html',
            $this->get(route('oferta-flexible.admision.index'))->getContent(),
        );
    }
})->with([false, true]);

test('failed impression persistence returns no PDF and leaves no print record', function (): void {
    ['usuario' => $usuario, 'campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    DB::statement(
        "CREATE TRIGGER reject_print BEFORE INSERT ON TB_HOJA_RUTA_IMPRESIONES BEGIN SELECT RAISE(ABORT, 'print rejected'); END",
    );
    $this
        ->actingAs($usuario)
        ->postJson(route('oferta-flexible.admision.imprimir'), [
            'campania_id' => $campania->id,
            'hoja_id' => $hoja->id,
        ])
        ->assertStatus(500);
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
    $this->assertNotSoftDeleted($hoja);
});

test(
    'deleting a sheet audits details releases quota and retains patient and print history',
    function (): void {
        [
            'usuario' => $usuario,
            'medico' => $medico,
            'campania' => $campania,
            'servicio' => $servicio,
            'hoja' => $hoja,
        ] = admisionEscenario();
        $impresion = HojaRutaImpresion::factory()->create([
            'hoja_ruta_id' => $hoja->id,
            'impreso_por' => $usuario->id,
        ]);
        $this
            ->actingAs($usuario)
            ->deleteJson(route('oferta-flexible.admision.destroy'), [
                'campania_id' => $campania->id,
                'hoja_id' => $hoja->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertSoftDeleted($hoja);
        $this->assertDatabaseHas('TB_HOJAS_RUTA', [
            'id' => $hoja->id,
            'deleted_by' => $usuario->id,
            'updated_by' => $usuario->id,
        ]);
        $this->assertDatabaseHas('TB_HOJA_RUTA_DETALLE', [
            'hoja_ruta_id' => $hoja->id,
            'deleted_by' => $usuario->id,
        ]);
        expect(HojaRutaDetalle::where('hoja_ruta_id', $hoja->id)
            ->count())
            ->toBe(0);
        expect(app(CampaniaService::class)
            ->usage([$servicio->id]))
            ->toBe([]);
        $this->assertDatabaseHas('TB_PACIENTES_MOCK', ['id' => $hoja->paciente_id]);
        $this->assertDatabaseHas('TB_HOJA_RUTA_IMPRESIONES', ['id' => $impresion->id]);
        $this->actingAs($medico);
        $resultado = app(TriajeService::class)->operar($medico, [
            'accion' => 'grabar',
            'campania_id' => $campania->id,
            'paciente_id' => $hoja->paciente_id,
            'servicios' => [$servicio->id],
        ]);
        expect($resultado['hoja']['id'])->not->toBe($hoja->id);
    },
);

test('closed campaigns cannot lose sheets or quota through admission', function (): void {
    [
        'usuario' => $usuario,
        'campania' => $campania,
        'servicio' => $servicio,
        'hoja' => $hoja,
    ] = admisionEscenario();
    $campania->update(['cerrada' => true]);
    $this
        ->actingAs($usuario)
        ->deleteJson(route('oferta-flexible.admision.destroy'), [
            'campania_id' => $campania->id,
            'hoja_id' => $hoja->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('campania');
    $this->assertNotSoftDeleted($hoja);
    expect(app(CampaniaService::class)
        ->usage([$servicio->id]))
        ->toBe([$servicio->id => 1]);
});

test(
    'a later service edit by the same doctor marks the printed sheet modified until reprinted',
    function (): void {
        [
            'usuario' => $usuario,
            'medico' => $medico,
            'campania' => $campania,
            'servicio' => $servicio,
            'hoja' => $hoja,
        ] = admisionEscenario();
        $hoja->update(['updated_by' => $medico->id]);
        $this
            ->actingAs($usuario)
            ->post(route('oferta-flexible.admision.imprimir'), [
                'campania_id' => $campania->id,
                'hoja_id' => $hoja->id,
            ])
            ->assertStatus(303);
        $this->travel(1)->minutes();
        $otro = CampaniaServicio::factory()->create(['campania_id' => $campania->id]);
        $this->actingAs($medico);
        app(TriajeService::class)->operar($medico, [
            'accion' => 'grabar',
            'campania_id' => $campania->id,
            'paciente_id' => $hoja->paciente_id,
            'servicios' => [$servicio->id, $otro->id],
        ]);
        $list = $this
            ->actingAs($usuario)
            ->get(route('oferta-flexible.admision.index'))
            ->assertOk();
        expect(preg_match('/\bModificada\b/u', strip_tags($list->getContent())))->toBe(1);
        $this->post(route('oferta-flexible.admision.imprimir'), [
            'campania_id' => $campania->id,
            'hoja_id' => $hoja->id,
        ])->assertStatus(303);
        $list = $this->get(route('oferta-flexible.admision.index'))->assertOk();
        expect(preg_match('/\bModificada\b/u', strip_tags($list->getContent())))->toBe(0);
        expect($hoja->refresh()
            ->medico_usuario_id)
            ->toBe($medico->id);
    },
);

test('patient doctor campaign and service names are escaped in list and PDF templates', function (): void {
    [
        'usuario' => $usuario,
        'medico' => $medico,
        'campania' => $campania,
        'servicio' => $servicio,
        'hoja' => $hoja,
    ] = admisionEscenario();
    $payload = '<script>alert("x")</script>';
    $hoja->paciente->update(['nombres' => $payload]);
    $medico->update(['nombre' => $payload]);
    $campania->update(['nombre' => $payload]);
    $servicio->servicioUps->update(['DESCRIPCION_UPS' => $payload]);
    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertSee($payload)
        ->assertDontSee($payload, false);
    $hoja->load(['campania', 'paciente', 'medico', 'detalles.campaniaServicio.servicioUps']);
    $html = view('oferta_flexible.admision.pdf', [
        'hoja' => $hoja,
        'emitidoAt' => now(),
        'edad' => 40,
    ])->render();
    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain($payload);
});

test('administrator sees every sheet creator without assignments or links', function (
    bool $association,
): void {
    [
        'usuario' => $admision,
        'medico' => $medico,
        'campania' => $campania,
        'servicio' => $servicio,
        'hoja' => $hoja,
    ] = admisionEscenario($association);
    $administrador = admisionActor('Administrador');
    $sinEnlace = ofertaHoja($servicio);
    $ajena = ofertaHoja(CampaniaServicio::factory()->create());

    $this
        ->actingAs($administrador)
        ->get(route('oferta-flexible.admision.index', ['campania_id' => $campania->id]))
        ->assertOk()
        ->assertSee($hoja->paciente->nombre_completo)
        ->assertSee($sinEnlace->paciente->nombre_completo)
        ->assertDontSee($ajena->paciente->nombre_completo)
        ->assertDontSee('Médicos enlazados:')
        ->assertViewHas(
            'medicos',
            fn ($rows): bool => $rows->count() === 2 &&
                $rows->contains('id', $medico->id) &&
                $rows->contains('id', $sinEnlace->medico_usuario_id),
        );
    $this->assertDatabaseMissing('TB_CAMPANIA_USUARIOS', ['usuario_id' => $administrador->id]);
    $this->assertDatabaseMissing('TB_CAMPANIA_ENLACES', ['admision_usuario_id' => $administrador->id]);

    if ($association) {
        $this
            ->actingAs($admision)
            ->get(route('oferta-flexible.admision.index', ['campania_id' => $campania->id]))
            ->assertOk()
            ->assertSee($hoja->paciente->nombre_completo)
            ->assertDontSee($sinEnlace->paciente->nombre_completo);
    }
})->with([true, false]);

test(
    'administrator campaign selector includes future active campaigns and prefers current attention dates',
    function (): void {
        $administrador = admisionActor('Administrador');
        $futura = Campania::factory()->create([
            'fecha_inicio' => '2026-10-21',
            'fecha_fin' => '2026-10-22',
        ]);
        $actual = Campania::factory()->create();
        $finalizada = Campania::factory()->create([
            'fecha_inicio' => '2026-10-18',
            'fecha_fin' => '2026-10-19',
        ]);
        $cerrada = Campania::factory()->create(['cerrada' => true]);

        $this
            ->actingAs($administrador)
            ->get(route('oferta-flexible.admision.index'))
            ->assertOk()
            ->assertViewHas('campania', fn ($row): bool => $row->id === $actual->id)
            ->assertViewHas(
                'campanias',
                fn ($rows): bool => $rows->count() === 2 && $rows->contains('id', $futura->id),
            );
        $this->get(route('oferta-flexible.admision.index', ['campania_id' => $futura->id]))->assertOk();
        $this->get(
            route('oferta-flexible.admision.index', ['campania_id' => $finalizada->id]),
        )->assertNotFound();
        $this->get(
            route('oferta-flexible.admision.index', ['campania_id' => $cerrada->id]),
        )->assertNotFound();
    },
);

test('administrator without active campaigns has an accurate empty message', function (): void {
    $administrador = admisionActor('Administrador');
    Campania::factory()->create(['cerrada' => true]);

    $this
        ->actingAs($administrador)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertSee('No hay campañas activas disponibles.')
        ->assertDontSee('asignadas a Admisión');
});

test(
    'administrator uses date doctor and document filters through the existing ajax contract',
    function (): void {
        ['campania' => $campania, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
        $administrador = admisionActor('Administrador');
        $otra = ofertaHoja($servicio);
        $data = [
            'campania_id' => $campania->id,
            'fecha' => '2026-10-20',
            'medico_id' => $otra->medico_usuario_id,
            'q' => $otra->paciente->dni,
        ];

        $respuesta = $this
            ->actingAs($administrador)
            ->getJson(route('oferta-flexible.admision.index', $data))
            ->assertOk();
        expect(array_keys($respuesta->json()))->toBe(['html']);
        expect($respuesta->json('html'))
            ->toContain(e($otra->paciente->nombre_completo))
            ->not->toContain(e($hoja->paciente->nombre_completo));
        $this
            ->getJson(route('oferta-flexible.admision.index', [...$data, 'fecha' => '2026-10-19']))
            ->assertOk()
            ->assertJsonPath(
                'html',
                fn (string $html): bool => str_contains($html, '0 pacientes encontrados'),
            );
        $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
    },
);

test('administrator prints and deletes unlinked sheets with its own audit identity', function (): void {
    ['campania' => $campania, 'hoja' => $conEnlace] = admisionEscenario();
    $administrador = admisionActor('Administrador');
    $servicio = CampaniaServicio::factory()->create(['campania_id' => $campania->id, 'cupos' => 1]);
    $hoja = ofertaHoja($servicio);
    $data = [
        'campania_id' => $campania->id,
        'hoja_id' => $hoja->id,
        'impreso_por' => $hoja->medico_usuario_id,
        'deleted_by' => $hoja->medico_usuario_id,
    ];

    $respuesta = $this
        ->actingAs($administrador)
        ->post(route('oferta-flexible.admision.imprimir'), $data)
        ->assertStatus(303);
    $respuesta = $this
        ->get($respuesta->headers->get('Location'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($respuesta->getContent())->toStartWith('%PDF-');
    $this->assertDatabaseHas('TB_HOJA_RUTA_IMPRESIONES', [
        'hoja_ruta_id' => $hoja->id,
        'impreso_por' => $administrador->id,
        'created_by' => $administrador->id,
    ]);
    $this
        ->deleteJson(route('oferta-flexible.admision.destroy'), $data)
        ->assertOk()
        ->assertJsonPath('success', true);
    $this->assertSoftDeleted($hoja);
    $this->assertNotSoftDeleted($conEnlace);
    $this->assertDatabaseHas('TB_HOJAS_RUTA', ['id' => $hoja->id, 'deleted_by' => $administrador->id]);
    $this->assertDatabaseHas('TB_HOJA_RUTA_DETALLE', [
        'hoja_ruta_id' => $hoja->id,
        'deleted_by' => $administrador->id,
    ]);
    $this->assertDatabaseHas('TB_HOJA_RUTA_IMPRESIONES', ['hoja_ruta_id' => $hoja->id]);
    expect(app(CampaniaService::class)
        ->usage([$servicio->id]))
        ->toBe([]);
});

test('administrator can reprint after campaign closure but cannot delete the sheet', function (): void {
    ['campania' => $campania, 'hoja' => $hoja, 'servicio' => $servicio] = admisionEscenario();
    $administrador = admisionActor('Administrador');
    $this
        ->actingAs($administrador)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk();
    $campania->update(['cerrada' => true]);
    $data = ['campania_id' => $campania->id, 'hoja_id' => $hoja->id];

    $this->post(route('oferta-flexible.admision.imprimir'), $data)->assertStatus(303);
    $this
        ->deleteJson(route('oferta-flexible.admision.destroy'), $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('campania');
    $this->assertNotSoftDeleted($hoja);
    $this->assertDatabaseHas('TB_HOJA_RUTA_IMPRESIONES', [
        'hoja_ruta_id' => $hoja->id,
        'impreso_por' => $administrador->id,
    ]);
    expect(app(CampaniaService::class)
        ->usage([$servicio->id]))
        ->toBe([$servicio->id => 1]);
});

test('administrator cannot operate on mismatched deleted or finished campaign sheets', function (
    string $scope,
): void {
    ['campania' => $campania, 'hoja' => $hoja] = admisionEscenario();
    $administrador = admisionActor('Administrador');
    $data = ['campania_id' => $campania->id, 'hoja_id' => $hoja->id];
    match ($scope) {
        'otra_campania' => ($data['hoja_id'] = ofertaHoja(CampaniaServicio::factory()
            ->create())
            ->id),
        'campania_eliminada' => $campania->delete(),
        'hoja_eliminada' => $hoja->delete(),
        'finalizada' => $campania->update(['fecha_inicio' => '2026-10-18', 'fecha_fin' => '2026-10-19']),
    };

    $this
        ->actingAs($administrador)
        ->postJson(route('oferta-flexible.admision.imprimir'), $data)
        ->assertNotFound();
    $this->deleteJson(route('oferta-flexible.admision.destroy'), $data)->assertNotFound();
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
})->with(['otra_campania', 'campania_eliminada', 'hoja_eliminada', 'finalizada']);

test('inactive administrator permissions cannot expand admission scope', function (string $inactive): void {
    ['usuario' => $usuario, 'servicio' => $servicio, 'hoja' => $hoja] = admisionEscenario();
    $adminRole = admisionActor('Administrador')->roles->first();
    $usuario->roles()->attach($adminRole->id, ['status' => $inactive !== 'pivote']);
    if ($inactive === 'rol') {
        $adminRole->update(['status' => false]);
    }
    $sinEnlace = ofertaHoja($servicio);

    $this
        ->actingAs($usuario)
        ->get(route('oferta-flexible.admision.index'))
        ->assertOk()
        ->assertSee($hoja->paciente->nombre_completo)
        ->assertDontSee($sinEnlace->paciente->nombre_completo);
    $this->postJson(route('oferta-flexible.admision.imprimir'), [
        'campania_id' => $hoja->campania_id,
        'hoja_id' => $sinEnlace->id,
    ])->assertNotFound();
    $this->assertDatabaseCount('TB_HOJA_RUTA_IMPRESIONES', 0);
})->with(['rol', 'pivote']);

test(
    'admission sees assigned future active campaigns while preserving linked doctor scope',
    function (): void {
        ['usuario' => $usuario, 'campania' => $campania] = admisionEscenario();
        $campania->update(['fecha_inicio' => '2026-10-21', 'fecha_fin' => '2026-10-22']);

        $this
            ->actingAs($usuario)
            ->get(route('oferta-flexible.admision.index', ['campania_id' => $campania->id]))
            ->assertOk()
            ->assertSee($campania->nombre)
            ->assertSee('Médicos enlazados:');
    },
);

test('admission service badges exclude laboratory even if a foreign detail was inserted', function (): void {
    ['usuario' => $user, 'campania' => $campaign, 'hoja' => $sheet] = admisionEscenario();
    $lab = CampaniaServicio::factory()->create([
        'campania_id' => $campaign->id,
        'ups_id' => ServicioUps::factory()->state([
            'TIPO_UPS' => 'L', 'DESCRIPCION_UPS' => 'Examen solo de laboratorio',
        ]),
    ]);
    HojaRutaDetalle::factory()->create(['hoja_ruta_id' => $sheet->id, 'campania_servicio_id' => $lab->id]);
    $this->actingAs($user)->get(route('oferta-flexible.admision.index', ['campania_id' => $campaign->id]))
        ->assertOk()->assertDontSee('Examen solo de laboratorio');
});
