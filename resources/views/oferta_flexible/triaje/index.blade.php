@extends('layouts.app')

@section('title', 'Campaña de Salud de Especialidad')
@section('script', 'oferta_flexible/triaje')

@section('content')
    <div class="container-fluid">
        <main class="oferta-triaje mx-auto" id="triaje" data-endpoint="{{ route('oferta-flexible.triaje.store') }}" data-campania="{{ $seleccionada?->id }}">
            @if($campanias->isEmpty())
                <h1 class="h4">Campaña de Salud de Especialidad</h1>
                <div class="alert alert-info" role="status">No tiene campañas activas asignadas para hoy. Consulte con el Administrador.</div>
            @else
                @if($campanias->count() > 1)
                    <label for="triaje-campania" class="form-label fw-semibold">Campaña</label>
                    <select id="triaje-campania" class="form-select mb-3">
                        @foreach($campanias as $campania)
                            <option value="{{ $campania->id }}" @selected($seleccionada->id === $campania->id)>{{ $campania->nombre }}</option>
                        @endforeach
                    </select>
                @else
                    <h1 class="h4 mb-3">{{ $seleccionada->nombre }}</h1>
                @endif

                <div id="triaje-mensaje" class="alert" role="status" aria-live="polite" tabindex="-1" hidden></div>

                <section id="triaje-identificacion" class="card shadow-sm mb-3" aria-labelledby="buscar-titulo">
                    <div class="card-body">
                        <h2 class="h5" id="buscar-titulo">1. Identificar al paciente</h2>
                        <form id="triaje-buscar" method="POST" action="{{ route('oferta-flexible.triaje.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="triaje-tipo" class="form-label">Tipo de documento</label>
                                <select id="triaje-tipo" class="form-select" name="tipo_doc" required>
                                    @foreach($tiposDocumento as $tipo)
                                        <option value="{{ $tipo }}" @selected($tipo === 'DNI')>{{ $tipo }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3" id="triaje-numero-grupo">
                                <label for="triaje-numero" class="form-label">Número de documento</label>
                                <input id="triaje-numero" class="form-control" name="dni" type="text" inputmode="numeric" autocomplete="off" autocapitalize="none" spellcheck="false" minlength="8" maxlength="8" pattern="[0-9]{8}" aria-describedby="triaje-documento-ayuda" required>
                                <div id="triaje-documento-ayuda" class="form-text">Ingrese exactamente 8 dígitos.</div>
                            </div>
                            <button type="submit" id="triaje-buscar-boton" class="btn btn-primary w-100">BUSCAR</button>
                        </form>
                    </div>
                </section>

                <section id="triaje-registro" class="card shadow-sm mb-3" aria-labelledby="registro-titulo" hidden>
                    <div class="card-body">
                        <h2 class="h5" id="registro-titulo">Registrar paciente</h2>
                        <p class="text-muted" id="triaje-registro-aviso">No se encontró el documento. Complete los datos del paciente.</p>
                        <form id="triaje-registrar" method="POST" action="{{ route('oferta-flexible.triaje.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="triaje-nombres" class="form-label">Nombres y apellidos</label>
                                <input id="triaje-nombres" class="form-control" name="nombres_apellidos" maxlength="200" autocomplete="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="triaje-nacimiento" class="form-label">Fecha de nacimiento</label>
                                <input id="triaje-nacimiento" class="form-control" name="fecha_nacimiento" type="date" max="{{ today('America/Lima')->toDateString() }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="triaje-sexo" class="form-label">Sexo</label>
                                <select id="triaje-sexo" class="form-select" name="sexo" required>
                                    <option value="">Seleccione</option>
                                    <option value="F">Femenino</option>
                                    <option value="M">Masculino</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="triaje-telefono" class="form-label">Teléfono (opcional)</label>
                                <input id="triaje-telefono" class="form-control" name="telefono" type="tel" inputmode="tel" autocomplete="tel" maxlength="150">
                            </div>
                            <button class="btn btn-primary w-100">Registrar paciente</button>
                        </form>
                    </div>
                </section>

                <section id="triaje-paciente" class="card shadow-sm mb-3" aria-labelledby="paciente-titulo" hidden>
                    <div class="card-body">
                        <h2 class="h5" id="paciente-titulo">Paciente</h2>
                        <p class="fw-semibold fs-5 mb-2" id="triaje-paciente-nombre"></p>
                        <dl class="triaje-paciente-datos mb-0">
                            <dt>Documento</dt><dd id="triaje-paciente-documento"></dd>
                            <dt>Edad</dt><dd id="triaje-paciente-edad"></dd>
                            <dt>Sexo</dt><dd id="triaje-paciente-sexo"></dd>
                        </dl>
                        <p id="triaje-hoja-aviso" class="text-muted small mb-0"></p>
                        <button type="button" id="triaje-otro" class="btn btn-outline-secondary w-100 mt-3">Buscar otro paciente</button>
                    </div>
                </section>

                <section id="triaje-servicios" class="card shadow-sm" aria-labelledby="servicios-titulo" hidden>
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <h2 class="h5 mb-0" id="servicios-titulo">2. Servicios de hoy</h2>
                            <button type="button" id="triaje-refrescar" class="btn btn-outline-secondary">Actualizar cupos</button>
                        </div>
                        <p class="text-muted small">El cupo se confirma al grabar la hoja de ruta.</p>
                        <label for="triaje-servicio" class="form-label">Servicio y cupos disponibles</label>
                        <select id="triaje-servicio" class="form-select mb-2" aria-describedby="triaje-servicios-ayuda"></select>
                        <button type="button" id="triaje-agregar" class="btn btn-outline-primary w-100 mb-3" disabled>Agregar servicio</button>
                        <p class="small text-muted" id="triaje-servicios-ayuda"></p>
                        <h3 class="h6">Servicios agregados</h3>
                        <ul id="triaje-lista" class="list-unstyled d-grid gap-2" aria-live="polite"></ul>
                        <p class="text-muted" id="triaje-lista-vacia">Agregue al menos un servicio.</p>
                        <div class="triaje-grabar-panel">
                            <button type="button" class="btn btn-primary w-100 fw-semibold" id="triaje-grabar" disabled>GRABAR HOJA DE RUTA</button>
                        </div>
                    </div>
                </section>
                <noscript><div class="alert alert-warning mt-3">Active JavaScript para buscar pacientes y registrar la hoja de ruta.</div></noscript>
            @endif
        </main>
    </div>
@endsection
