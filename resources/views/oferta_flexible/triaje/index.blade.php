@extends('layouts.app')

@section('title', 'Campaña de Salud de Especialidad')
@section('script', 'oferta_flexible/triaje')

@section('content')
    <div class="container-fluid">
        <main
            class="oferta-triaje oferta-admin mx-auto"
            id="triaje"
            data-endpoint="{{ route('oferta-flexible.triaje.store') }}"
            data-campania="{{ $seleccionada?->id }}"
            data-fecha-inicio="{{ $seleccionada?->fecha_inicio?->toDateString() }}"
            data-hoy="{{ today('America/Lima')->toDateString() }}"
        >
            @if ($campanias->isEmpty())
                @include('oferta_flexible.partials.encabezado', [
                    'icono' => 'bi-heart-pulse',
                    'titulo' => 'Campaña de Salud de Especialidad',
                    'subtitulo' => 'Identifique al paciente y prepare su hoja de ruta.',
                ])
                <div class="alert alert-info" role="status">
                    No tiene campañas activas asignadas. Consulte con el Administrador.
                </div>
            @else
                @if ($campanias->count() > 1)
                    @include('oferta_flexible.partials.encabezado', [
                        'icono' => 'bi-heart-pulse',
                        'titulo' => $seleccionada->nombre,
                        'subtitulo' => 'Identifique al paciente y prepare su hoja de ruta.',
                    ])
                    <label for="triaje-campania" class="form-label fw-semibold">Campaña</label>
                    <select id="triaje-campania" class="form-select mb-3">
                        @foreach ($campanias as $campania)
                            <option
                                value="{{ $campania->id }}"
                                data-fecha-inicio="{{ $campania->fecha_inicio->toDateString() }}"
                                @selected($seleccionada->id === $campania->id)
                            >
                                {{ $campania->nombre }}
                            </option>
                        @endforeach
                    </select>
                @else
                    @include('oferta_flexible.partials.encabezado', [
                        'icono' => 'bi-heart-pulse',
                        'titulo' => $seleccionada->nombre,
                        'subtitulo' => 'Identifique al paciente y prepare su hoja de ruta.',
                    ])
                @endif

                <div
                    id="triaje-mensaje"
                    class="alert alert-info"
                    role="status"
                    aria-live="polite"
                    tabindex="-1"
                    @if ($seleccionada->estaEnPeriodoAtencion())
                    hidden
                    @endif
                >
                    @if (!$seleccionada->estaEnPeriodoAtencion())
                        La campaña inicia el {{ $seleccionada->fecha_inicio->format('d/m/Y') }}.
                    @endif
                </div>

                <section
                    id="triaje-identificacion"
                    class="card shadow-sm mb-3"
                    aria-labelledby="buscar-titulo"
                    @if (!$seleccionada->estaEnPeriodoAtencion())
                    hidden
                    @endif
                >
                    <div class="card-body">
                        <h2 class="h5" id="buscar-titulo">1. Identificar al paciente</h2>
                        <form
                            id="triaje-buscar"
                            method="POST"
                            action="{{ route('oferta-flexible.triaje.store') }}"
                        >
                            @csrf
                            <div class="mb-3">
                                <label for="triaje-tipo" class="form-label">Tipo de documento</label>
                                <select
                                    id="triaje-tipo"
                                    class="form-select"
                                    name="tipo_doc"
                                    required
                                >
                                    @foreach ($tiposDocumento as $tipo)
                                        <option value="{{ $tipo }}" @selected($tipo === 'DNI')>
                                            {{ $tipo }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3" id="triaje-numero-grupo">
                                <label for="triaje-numero" class="form-label">Número de documento</label>
                                <input
                                    id="triaje-numero"
                                    class="form-control"
                                    name="dni"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    autocapitalize="none"
                                    spellcheck="false"
                                    minlength="8"
                                    maxlength="8"
                                    pattern="[0-9]{8}"
                                    aria-describedby="triaje-documento-ayuda triaje-numero-error"
                                    required
                                >
                                <div class="invalid-feedback" id="triaje-numero-error">
                                    Revise el número según el tipo de documento seleccionado.
                                </div>
                                <div id="triaje-documento-ayuda" class="form-text">
                                    Ingrese exactamente 8 dígitos.
                                </div>
                            </div>
                            <button
                                type="submit"
                                id="triaje-buscar-boton"
                                class="btn btn-primary w-100"
                            >
                                <i class="bi bi-search" aria-hidden="true"></i>
                                BUSCAR
                            </button>
                        </form>
                    </div>
                </section>

                <section
                    id="triaje-registro"
                    class="card shadow-sm mb-3"
                    aria-labelledby="registro-titulo"
                    hidden
                >
                    <div class="card-body">
                        <h2 class="h5" id="registro-titulo">Registrar paciente</h2>
                        <p class="text-muted" id="triaje-registro-aviso">
                            No se encontró el documento. Complete los datos del paciente.
                        </p>
                        <form
                            id="triaje-registrar"
                            method="POST"
                            action="{{ route('oferta-flexible.triaje.store') }}"
                        >
                            @csrf
                            <div class="mb-3">
                                <label for="triaje-nombres" class="form-label">Nombres</label>
                                <input
                                    id="triaje-nombres"
                                    aria-describedby="triaje-nombres-error"
                                    class="form-control"
                                    name="nombres"
                                    maxlength="150"
                                    autocomplete="given-name"
                                    required
                                >
                                <div class="invalid-feedback" id="triaje-nombres-error">
                                    Ingrese los nombres del paciente.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="triaje-apellidos" class="form-label">Apellidos</label>
                                <input
                                    id="triaje-apellidos"
                                    aria-describedby="triaje-apellidos-error"
                                    class="form-control"
                                    name="apellidos"
                                    maxlength="150"
                                    autocomplete="family-name"
                                    required
                                >
                                <div class="invalid-feedback" id="triaje-apellidos-error">
                                    Ingrese los apellidos del paciente.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="triaje-nacimiento" class="form-label">Fecha de nacimiento</label>
                                <input
                                    id="triaje-nacimiento"
                                    aria-describedby="triaje-nacimiento-error"
                                    class="form-control"
                                    name="fecha_nacimiento"
                                    type="date"
                                    max="{{ today('America/Lima')->toDateString() }}"
                                    required
                                >
                                <div class="invalid-feedback" id="triaje-nacimiento-error">
                                    Ingrese una fecha de nacimiento válida.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="triaje-sexo" class="form-label">Sexo</label>
                                <select
                                    id="triaje-sexo"
                                    aria-describedby="triaje-sexo-error"
                                    class="form-select"
                                    name="sexo"
                                    required
                                >
                                    <option value="">Seleccione</option>
                                    <option value="F">Femenino</option>
                                    <option value="M">Masculino</option>
                                </select>
                                <div class="invalid-feedback" id="triaje-sexo-error">
                                    Seleccione el sexo del paciente.
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="triaje-telefono" class="form-label">Teléfono (opcional)</label>
                                <input
                                    id="triaje-telefono"
                                    aria-describedby="triaje-telefono-error"
                                    class="form-control"
                                    name="telefono"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    maxlength="150"
                                >
                                <div class="invalid-feedback" id="triaje-telefono-error">
                                    Revise el teléfono ingresado.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-person-plus" aria-hidden="true"></i>
                                Registrar paciente
                            </button>
                        </form>
                    </div>
                </section>

                <section
                    id="triaje-paciente"
                    class="card shadow-sm mb-3"
                    aria-labelledby="paciente-titulo"
                    hidden
                >
                    <div class="card-body">
                        <h2 class="h5" id="paciente-titulo">Paciente</h2>
                        <p class="fw-semibold fs-5 mb-2" id="triaje-paciente-nombre"></p>
                        <dl class="triaje-paciente-datos mb-0">
                            <dt>Documento</dt>
                            <dd id="triaje-paciente-documento"></dd>
                            <dt>Edad</dt>
                            <dd id="triaje-paciente-edad"></dd>
                            <dt>Sexo</dt>
                            <dd id="triaje-paciente-sexo"></dd>
                        </dl>
                        <p id="triaje-hoja-aviso" class="text-muted small mb-0"></p>
                        <button type="button" id="triaje-otro" class="btn btn-secondary w-100 mt-3">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            Buscar otro paciente
                        </button>
                    </div>
                </section>

                <section
                    id="triaje-servicios"
                    class="card shadow-sm"
                    aria-labelledby="servicios-titulo"
                    hidden
                >
                    <div class="card-body">
                        <div
                            class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"
                        >
                            <h2 class="h5 mb-0" id="servicios-titulo">2. Servicios de hoy</h2>
                            <button type="button" id="triaje-refrescar" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                                Actualizar cupos
                            </button>
                        </div>
                        <p class="text-muted small">El cupo se confirma al grabar la hoja de ruta.</p>
                        <label for="triaje-servicio" class="form-label">Servicio y cupos disponibles</label>
                        <select
                            id="triaje-servicio"
                            class="form-select mb-2"
                            aria-describedby="triaje-servicios-ayuda"
                        >
                        </select>
                        <button
                            type="button"
                            id="triaje-agregar"
                            class="btn btn-primary w-100 mb-3"
                            disabled
                        >
                            <i class="bi bi-plus-circle" aria-hidden="true"></i>
                            Agregar servicio
                        </button>
                        <p class="small text-muted" id="triaje-servicios-ayuda"></p>
                        <h3 class="h6">Servicios agregados</h3>
                        <ul id="triaje-lista" class="list-unstyled d-grid gap-2" aria-live="polite"></ul>
                        <div id="triaje-lista-vacia">
                            @include('oferta_flexible.partials.vacio', [
                                'mensajeVacio' => 'Agregue al menos un servicio.',
                                'iconoVacio' => 'bi-list-check',
                            ])
                        </div>
                        <div class="triaje-grabar-panel">
                            <button
                                type="button"
                                class="btn btn-primary w-100 fw-semibold"
                                id="triaje-grabar"
                                disabled
                            >
                                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                GRABAR HOJA DE RUTA
                            </button>
                        </div>
                    </div>
                </section>
                <noscript>
                    <div class="alert alert-warning mt-3">
                        Active JavaScript para buscar pacientes y registrar la hoja de ruta.
                    </div>
                </noscript>
            @endif
        </main>
    </div>
@endsection
