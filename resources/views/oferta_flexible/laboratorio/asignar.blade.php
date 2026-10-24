@extends('layouts.app')
@section('title', 'Asignar Laboratorio')
@section('script', 'oferta_flexible/laboratorio/asignar')
@section('content')
    <div
        class="container-fluid oferta-admin lab-asignacion"
        data-lab-asignacion
        data-url="{{ route('oferta-flexible.laboratorio.asignar.store') }}"
        data-exportacion="{{ $exportacionUrl }}"
        data-campania="{{ $seleccionada?->id }}"
    >
        @include('oferta_flexible.partials.encabezado', [
            'titulo' => 'Asignar Laboratorio',
            'subtitulo' => $seleccionada?->nombre ?? 'Distribución de muestras para procesamiento en EESS.',
            'icono' => 'bi-building',
        ])
        @if (!$seleccionada)
            <div class="card">
                @include('oferta_flexible.partials.vacio', [
                    'mensajeVacio' => 'No hay campañas con laboratorio abiertas para asignar.',
                    'iconoVacio' => 'bi-calendar-x',
                ])
            </div>
        @else
            <div id="asignacion-error" class="alert alert-danger" role="alert" hidden></div>
            <div id="asignacion-exito" class="alert alert-success" role="status" hidden></div>
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    @foreach ($errors->all() as $error)
                        <p class="mb-1">{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            <div class="card mb-3">
                <div class="card-body">
                    <form
                        id="asignacion-filtros"
                        method="GET"
                        action="{{ route('oferta-flexible.laboratorio.asignar.index') }}"
                        class="row g-3 align-items-end"
                    >
                        <div class="col-md-6 col-xl-3">
                            <label for="asignacion-campania" class="form-label">Campaña de trabajo</label>
                            <select id="asignacion-campania" name="campania_id" class="form-select">
                                @foreach ($campanias as $campania)
                                    <option
                                        value="{{ $campania->id }}"
                                        @selected($campania->id === $seleccionada->id)
                                    >
                                        {{ $campania->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <label for="asignacion-busqueda" class="form-label">Documento, paciente o código TM</label>
                            <input
                                id="asignacion-busqueda"
                                name="q"
                                value="{{ $filtros['q'] }}"
                                class="form-control"
                                maxlength="150"
                                placeholder="Buscar en esta campaña"
                            >
                        </div>
                        <div class="col-md-5 col-xl-2">
                            <label for="asignacion-estado" class="form-label">Asignación</label>
                            <select id="asignacion-estado" name="asignacion" class="form-select">
                                @foreach ([
                                    'todos' => 'Todos',
                                    'asignados' => 'Asignados',
                                    'sin_asignar' => 'Sin asignar',
                                ] as $valor => $texto)
                                    <option value="{{ $valor }}" @selected($filtros['asignacion'] === $valor)>
                                        {{ $texto }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 col-xl-1">
                            <label for="asignacion-pagina" class="form-label">Filas</label>
                            <select id="asignacion-pagina" name="por_pagina" class="form-select">
                                @foreach ([25, 50, 100] as $cantidad)
                                    <option value="{{ $cantidad }}" @selected($filtros['por_pagina'] === $cantidad)>
                                        {{ $cantidad }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 col-xl-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <i class="bi bi-people" aria-hidden="true"></i>
                        Pacientes con registro de laboratorio
                    </div>
                    <button id="asignacion-exportar" type="button" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                        Exportar a Excel
                    </button>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        @include('oferta_flexible.partials.badge', ['etiqueta' => $seleccionada->estado])
                        <span class="small text-body-secondary">
                            La asignación permanece disponible hasta el cierre de la campaña.
                        </span>
                    </div>
                    <form id="asignacion-masiva" method="POST" class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-md-7 col-xl-6">
                            <label for="asignacion-destino" class="form-label">Laboratorio de destino</label>
                            <select id="asignacion-destino" name="renipress" class="form-select" required>
                                <option value="">Seleccione un EESS</option>
                                @foreach ($eess as $destino)
                                    <option value="{{ $destino['renipress'] }}">
                                        {{ $destino['nombre'] }}, RENIPRESS: {{ $destino['renipress'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">Seleccione un laboratorio de destino.</div>
                        </div>
                        <div class="col-md-5 col-xl-6">
                            <button id="asignacion-aplicar" type="submit" class="btn btn-primary" disabled>
                                <i class="bi bi-building-check" aria-hidden="true"></i>
                                Asignar a los seleccionados
                            </button>
                        </div>
                        <p id="asignacion-seleccion" class="small text-body-secondary mb-0" aria-live="polite">
                            Seleccione pacientes de esta página.
                        </p>
                    </form>
                    <div id="asignacion-cargando" class="small mb-2" role="status" hidden>
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        Actualizando listado…
                    </div>
                    <div id="asignacion-listado">
                        @include('oferta_flexible.laboratorio._asignacion_tabla')
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
