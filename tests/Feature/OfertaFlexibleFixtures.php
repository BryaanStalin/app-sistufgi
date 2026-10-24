<?php

use App\Models\CampaniaServicio;
use App\Models\HojaRuta;
use App\Models\HojaRutaDetalle;
use App\Models\Rol;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\OfertaFlexibleUserFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

function ofertaFlexibleFixtures(): void
{
    if (
        app()->configurationIsCached() ||
        ! app()->environment('testing') ||
        config('database.default') !== 'sqlite' ||
        config('database.connections.sqlite.database') !== ':memory:' ||
        ! empty(config('database.connections.sqlite.url'))
    ) {
        throw new LogicException('Oferta Flexible exige SQLite :memory: sin caché. No se permite otra base.');
    }
    config([
        'database.connections' => ['sqlite' => config('database.connections.sqlite')],
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        'session.driver' => 'array',
        'cache.default' => 'array',
        'services.dni_api.enabled' => false,
    ]);
    Http::preventStrayRequests();
    test()->withoutVite();
    test()->travelTo(Carbon::parse('2026-10-20 12:00:00', 'America/Lima'));
    app()->setLocale('es');
    Schema::create('TB_ACCESO_USUARIO', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('nombre');
        $table->string('user');
        $table->string('email');
        $table->string('password');
        $table->boolean('status')->default(true);
        $table->rememberToken();
        $table->string('DNI')->nullable();
        $table->timestamps();
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            $table->integer($column)->nullable();
        }
    });
    Schema::create('TB_ACCESO_ROL', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('titulo');
        $table->boolean('status')->default(true);
        $table->string('descripcion')->nullable();
        $table->timestamps();
    });
    Schema::create('TB_ACCESO_USUARIO_X_ROL', function (Blueprint $table): void {
        $table->integer('usuario_id');
        $table->integer('rol_id');
        $table->boolean('status')->default(true);
        $table->timestamps();
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            $table->integer($column)->nullable();
        }
        $table->primary(['usuario_id', 'rol_id']);
    });
    Schema::create('TB_ACCESO_MENU', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('titulo');
        $table->string('link')->nullable();
        $table->string('icono')->nullable();
        $table->integer('padre_id')->nullable();
        $table->integer('orden')->default(1);
        $table->boolean('status')->default(true);
        $table->timestamps();
    });
    Schema::create('TB_ACCESO_MENU_X_ROL', function (Blueprint $table): void {
        $table->integer('menu_principal_id');
        $table->integer('rol_id');
        $table->boolean('status')->default(true);
        $table->timestamps();
        $table->primary(['menu_principal_id', 'rol_id']);
    });
    Schema::create('TB_UPSS_CAMPAÑAS', function (Blueprint $table): void {
        $table->increments('id');
        $table->integer('ID_UPS');
        $table->string('DESCRIPCION_UPS');
        $table->char('TIPO_UPS', 1);
        $table->time('HORA_LIMITE')->nullable();
        $table->boolean('ENVIA_EESS')->default(true);
        $table->boolean('ACTIVO')->default(true);
        $table->timestamps();
        foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
            $table->integer($column)->nullable();
        }
    });
    Schema::create('TB_PACIENTES_MOCK', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('dni')->nullable();
        $table->string('tipo_doc')->nullable();
        $table->string('nombres', 150);
        $table->string('apellidos', 150);
        $table->date('fecha_nacimiento');
        $table->string('telefono')->nullable();
        $table->char('sexo', 1)->nullable();
    });
    DB::statement(
        'CREATE UNIQUE INDEX UQ_PACIENTES_MOCK_DOCUMENTO '.
        'ON TB_PACIENTES_MOCK (tipo_doc, dni) WHERE dni IS NOT NULL',
    );
    $definitions = [
        'TB_CAMPANIAS' => function (Blueprint $table): void {
            $table->string('nombre');
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->boolean('asociar_triaje_admision');
            $table->boolean('mismos_servicios');
            $table->boolean('incluye_laboratorio')->default(false);
            $table->integer('lab_ultimo_tm')->default(0);
            $table->boolean('cerrada')->default(false);
            $table->dateTime('cerrada_at')->nullable();
            $table->integer('cerrada_by')->nullable();
        },
        'TB_CAMPANIA_SERVICIOS' => function (Blueprint $table): void {
            $table->integer('campania_id');
            $table->integer('ups_id');
            $table->dateTime('fecha_servicio');
            $table->integer('cupos');
            $table->integer('cupo_base')->nullable();
            $table->boolean('cupo_personalizado')->default(false);
        },
        'TB_CAMPANIA_USUARIOS' => function (Blueprint $table): void {
            $table->integer('campania_id');
            $table->integer('usuario_id');
            $table->string('tipo', 2);
        },
        'TB_CAMPANIA_ENLACES' => function (Blueprint $table): void {
            $table->integer('campania_id');
            $table->integer('medico_usuario_id');
            $table->integer('admision_usuario_id');
        },
        'TB_CAMPANIA_ENLACES_LAB' => function (Blueprint $table): void {
            $table->integer('campania_id');
            $table->integer('admision_lab_usuario_id');
            $table->integer('laboratorio_usuario_id');
        },
        'TB_LAB_PACIENTES' => function (Blueprint $table): void {
            $table->integer('campania_id');
            $table->integer('paciente_id');
            $table->integer('tm_numero');
            $table->string('eess_renipress', 10)->nullable();
            $table->dateTime('eess_asignado_at')->nullable();
            $table->integer('eess_asignado_by')->nullable();
        },
        'TB_LAB_ATENCIONES' => function (Blueprint $table): void {
            $table->integer('lab_paciente_id');
            $table->integer('campania_id');
            $table->dateTime('fecha_atencion');
            $table->string('fua', 20);
            $table->integer('ups_id_solicita');
            $table->integer('hoja_ruta_id')->nullable();
        },
        'TB_LAB_ATENCION_EXAMENES' => function (Blueprint $table): void {
            $table->integer('atencion_id');
            $table->integer('lab_paciente_id');
            $table->integer('campania_servicio_id');
            $table->integer('ups_id');
            $table->char('estado', 1)->default('P');
            $table->dateTime('tomado_at')->nullable();
            $table->integer('tomado_by')->nullable();
        },
        'TB_HOJAS_RUTA' => function (Blueprint $table): void {
            $table->integer('campania_id');
            $table->integer('paciente_id');
            $table->dateTime('fecha_atencion');
            $table->integer('medico_usuario_id');
        },
        'TB_HOJA_RUTA_DETALLE' => function (Blueprint $table): void {
            $table->integer('hoja_ruta_id');
            $table->integer('campania_servicio_id');
        },
        'TB_HOJA_RUTA_IMPRESIONES' => function (Blueprint $table): void {
            $table->integer('hoja_ruta_id');
            $table->integer('impreso_por');
            $table->dateTime('impreso_at');
        },
    ];
    foreach ($definitions as $name => $definition) {
        Schema::create($name, function (Blueprint $table) use ($definition): void {
            $table->increments('id');
            $definition($table);
            $table->timestamps();
            $table->softDeletes();
            foreach (['created_by', 'updated_by', 'deleted_by'] as $column) {
                $table->integer($column)->nullable();
            }
        });
    }
    foreach (
        [
            ['TB_CAMPANIA_SERVICIOS', 'campania_id, ups_id, fecha_servicio'],
            ['TB_CAMPANIA_USUARIOS', 'campania_id, usuario_id'],
            ['TB_CAMPANIA_ENLACES', 'campania_id, medico_usuario_id'],
            ['TB_CAMPANIA_ENLACES_LAB', 'campania_id, laboratorio_usuario_id'],
            ['TB_LAB_PACIENTES', 'campania_id, paciente_id'],
            ['TB_LAB_PACIENTES', 'campania_id, tm_numero'],
            ['TB_LAB_ATENCIONES', 'lab_paciente_id, fecha_atencion'],
            ['TB_HOJAS_RUTA', 'campania_id, paciente_id, fecha_atencion'],
            ['TB_HOJA_RUTA_DETALLE', 'hoja_ruta_id, campania_servicio_id'],
        ] as [$name, $columns]
    ) {
        DB::statement(
            'CREATE UNIQUE INDEX UQ_TEST_'.
                $name.str_replace(', ', '_', $columns).
                ' ON '.
                $name.
                ' ('.
                $columns.
                ') WHERE deleted_at IS NULL',
        );
    }
    DB::statement(
        'CREATE UNIQUE INDEX UQ_TEST_LAB_EXAMEN ON TB_LAB_ATENCION_EXAMENES '.
        "(lab_paciente_id, ups_id) WHERE deleted_at IS NULL AND estado <> 'N'",
    );
    Schema::create('TB_EESS', function (Blueprint $table): void {
        $table->increments('ID_EESS');
        $table->string('RENIPRESS');
        $table->string('NOM_EESS');
        $table->boolean('ACTIVO')->default(true);
    });

}

function ofertaActor(
    string $titulo = 'Administrador',
    bool $roleActive = true,
    bool $pivotActive = true,
): User {
    $actor = OfertaFlexibleUserFactory::new()->create();
    $rol = Rol::query()->firstOrCreate(['titulo' => $titulo], ['status' => $roleActive]);
    $actor->roles()->attach($rol->id, ['status' => $pivotActive]);

    return $actor;
}

/** @return array<string, mixed> */
function ofertaCampaniaData(int $upsId): array
{
    return [
        'nombre' => 'Campaña Pequeña de Prueba',
        'fecha_inicio' => '2026-10-20',
        'fecha_fin' => '2026-10-21',
        'asociar_triaje_admision' => 1,
        'mismos_servicios' => 1,
        'servicios_generales' => [['ups_id' => $upsId, 'cupos' => 10]],
    ];
}

function ofertaHoja(CampaniaServicio $servicio, ?User $medico = null): HojaRuta
{
    $hoja = HojaRuta::factory()->create([
        'campania_id' => $servicio->campania_id,
        'medico_usuario_id' => $medico?->id ?? ofertaActor('Médico Triaje')->id,
        'fecha_atencion' => $servicio->fecha_servicio,
    ]);
    HojaRutaDetalle::factory()->create([
        'hoja_ruta_id' => $hoja->id,
        'campania_servicio_id' => $servicio->id,
    ]);

    return $hoja;
}

/** @param array<string, mixed> $datos
 * @return array<string, mixed>
 */
function ofertaRespuestaDni(array $datos = []): array
{
    return [
        'respuesta' => 'Solicitud exitosa',
        'codigo' => '200',
        'data' => array_replace([
            'numeroDocumento' => '73050308',
            'nombres' => '  Juan   Carlos ',
            'apellidoPaterno' => ' Pérez ',
            'apellidoMaterno' => ' García ',
            'fechaNacimiento' => '27/08/1980',
            'idSexo' => '1',
            'sexo' => 'MASCULINO',
            'edad' => '999 Años',
        ], $datos),
    ];
}

function ofertaConfigurarDniApi(): void
{
    config([
        'services.dni_api.enabled' => true,
        'services.dni_api.url' => 'https://dni.example.test/consulta',
        'services.dni_api.token' => 'credencial-ficticia-privada',
        'services.dni_api.timeout' => 8,
    ]);
    Http::preventStrayRequests();
}
