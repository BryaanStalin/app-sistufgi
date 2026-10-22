@extends('layouts.app')
@section('title', 'Gestión de Usuarios para Campañas')
@section('script', 'oferta_flexible/usuarios/index')
@section('content')
    <div class="container-fluid oferta-admin" data-campania-id="{{ $campania?->id }}">
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-people',
            'titulo' => 'Gestión de Usuarios para Campañas',
            'subtitulo' => 'Primero asigne el equipo de la campaña y luego enlace a sus integrantes.',
        ])
        @include('oferta_flexible._mensajes')
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end" data-admin-submit>
                    <div class="col-md-7">
                        <label for="campania_id" id="campania_id-label" class="form-label">Campaña</label>
                        <select
                            id="campania_id"
                            name="campania_id"
                            class="form-select"
                            required
                            data-admin-select
                        >
                            <option value="">Seleccione una campaña</option>
                            @foreach ($campanias as $opcion)
                                <option
                                    value="{{ $opcion->id }}"
                                    @selected($campania?->id === $opcion->id)
                                >
                                    {{ $opcion->nombre }} — {{ $opcion->estado }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mb-2">
                            <input
                                type="checkbox"
                                name="solo_activas"
                                id="solo_activas"
                                value="1"
                                class="form-check-input"
                                @checked(request()->boolean('solo_activas'))
                            >
                            <label for="solo_activas" class="form-check-label">Solo campañas activas</label>
                        </div>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            Consultar
                        </button>
                    </div>
                </form>
                @if ($campania)
                    <div
                        class="border-top mt-4 pt-3 d-flex flex-wrap align-items-center justify-content-between gap-3"
                    >
                        <div>
                            <h2 class="h5 mb-1">{{ $campania->nombre }}</h2>
                            <p class="small text-body-secondary mb-0">
                                <i class="bi bi-calendar3" aria-hidden="true"></i>
                                {{ $campania->fecha_inicio->format('d/m/Y') }} –
                                {{ $campania->fecha_fin->format('d/m/Y') }}
                            </p>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            @include('oferta_flexible.partials.badge', ['etiqueta' => $campania->estado])
                            <span class="small text-body-secondary">Asociar Triaje y Admisión:</span>
                            @include('oferta_flexible.partials.badge', [
                                'etiqueta' => $campania->asociar_triaje_admision ? 'SÍ' : 'NO',
                            ])
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @if ($campania)
            @php
                $tieneMedicos = $campania->asignaciones->contains('tipo', 'M');
                $tieneAdmisiones = $campania->asignaciones->contains('tipo', 'A');
                $puedeEnlazar = $campania->asociar_triaje_admision
                    && $campania->estado === 'Activa'
                    && $tieneMedicos
                    && $tieneAdmisiones;
            @endphp
            @if ($campania->cerrada)
                <div class="alert alert-info d-flex gap-2" role="status">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    Campaña cerrada: asignaciones y enlaces de solo lectura.
                </div>
            @endif
            <div class="card mb-4">
                <div class="card-body">
                    <div
                        class="nav nav-pills gap-2"
                        role="tablist"
                        aria-label="Configuración del equipo"
                    >
                        <button
                            class="nav-link active"
                            id="asignar-tab"
                            type="button"
                            role="tab"
                            data-bs-toggle="pill"
                            data-bs-target="#asignar-panel"
                            aria-controls="asignar-panel"
                            aria-selected="true"
                        >
                            <span class="oferta-step-number">1</span>
                            <i class="bi bi-person-plus me-1" aria-hidden="true"></i>
                            Asignar usuarios
                        </button>
                        <button
                            class="nav-link"
                            id="enlazar-tab"
                            type="button"
                            role="tab"
                            data-bs-toggle="pill"
                            data-bs-target="#enlazar-panel"
                            aria-controls="enlazar-panel"
                            aria-selected="false"
                            @disabled(!$puedeEnlazar && $campania->enlaces->isEmpty())
                        >
                            <span class="oferta-step-number">2</span>
                            <i class="bi bi-link-45deg me-1" aria-hidden="true"></i>
                            Enlazar usuarios
                        </button>
                    </div>
                    @unless ($puedeEnlazar)
                        <p class="small text-body-secondary mt-3 mb-0" role="status">
                            @if (!$campania->asociar_triaje_admision)
                                Esta campaña no usa enlaces entre Triaje y Admisión.
                            @elseif ($campania->estado !== 'Activa')
                                Para crear enlaces, seleccione una campaña Activa.
                            @elseif (!$tieneMedicos || !$tieneAdmisiones)
                                Para continuar al paso 2, asigne al menos un Médico Triaje y un usuario de Admisión.
                            @endif
                        </p>
                    @endunless
                </div>
            </div>
            <div class="tab-content">
                <section
                    class="tab-pane show active"
                    id="asignar-panel"
                    role="tabpanel"
                    aria-labelledby="asignar-tab"
                >
                    @include('oferta_flexible.usuarios._asignaciones')
                </section>
                <section
                    class="tab-pane"
                    id="enlazar-panel"
                    role="tabpanel"
                    aria-labelledby="enlazar-tab"
                >
                    @include('oferta_flexible.usuarios._enlaces')
                </section>
            </div>
        @else
            <div class="card">
                @include('oferta_flexible.partials.vacio', [
                    'mensajeVacio' => 'Seleccione una campaña para organizar su equipo.',
                    'ayudaVacio' => 'Podrá asignar usuarios existentes y enlazar médicos con Admisión.',
                    'iconoVacio' => 'bi-people',
                ])
            </div>
        @endif
    </div>
@endsection
