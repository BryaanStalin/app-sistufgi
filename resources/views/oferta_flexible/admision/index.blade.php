@extends('layouts.app')

@section('title', 'Campaña de Salud de Especialidad - Listado de pacientes')
@section('script', 'oferta_flexible/admision')

@section('content')
    <div
        class="container-fluid admision oferta-admin"
        id="admision"
        data-url="{{ route('oferta-flexible.admision.index') }}"
        data-campania="{{ $campania?->id }}"
        data-ultima-actualizacion="{{ now('America/Lima')->format('H:i:s') }}"
        data-poll-interval="{{ config('oferta_flexible.admision_poll_interval_ms') }}"
    >
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-journal-medical',
            'titulo' => 'Listado de pacientes',
            'subtitulo' => 'Consulte las hojas de ruta y gestione su impresión.',
        ])
        @include('oferta_flexible._mensajes')
        <div id="admision-contexto">
            @include('oferta_flexible.partials.contexto_admision')
        </div>
        @if ($campania)
            <form
                id="admision-filtros"
                action="{{ route('oferta-flexible.admision.index') }}"
                method="GET"
                class="card card-body mb-3"
            >
                <div class="row g-3 align-items-end">
                    @if ($campanias->count() > 1)
                        <div class="col-12">
                            <label
                                for="admision-campania"
                                id="admision-campania-label"
                                class="form-label"
                            >
                                {{ $esAdministrador ? 'Campaña activa' : 'Campaña (incluye historial para reimpresión)' }}
                            </label>
                            <select
                                id="admision-campania"
                                data-admin-select
                                name="campania_id"
                                class="form-select"
                            >
                                @foreach ($campanias as $opcion)
                                    <option
                                        value="{{ $opcion->id }}"
                                        @selected($campania->id === $opcion->id)
                                    >
                                        {{ $opcion->nombre }} - {{ $opcion->estado }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="campania_id" value="{{ $campania->id }}">
                    @endif
                    <div class="col-12 col-sm-6 col-xl-3">
                        <label for="admision-fecha" class="form-label">Fecha de atención</label>
                        <input
                            id="admision-fecha"
                            name="fecha"
                            type="date"
                            class="form-control"
                            value="{{ $filters['fecha'] }}"
                            required
                        >
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <label for="admision-medico" id="admision-medico-label" class="form-label">Médico
                        </label>
                        <select
                            id="admision-medico"
                            data-admin-select
                            name="medico_id"
                            class="form-select"
                        >
                            <option value="">Todos los médicos de mi alcance</option>
                            @foreach ($medicos as $medico)
                                <option
                                    value="{{ $medico->id }}"
                                    @selected((int) ($filters['medico_id'] ?? 0) === $medico->id)
                                >
                                    {{ $medico->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-xl-4">
                        <label for="admision-busqueda" class="form-label">Documento o nombres del paciente
                        </label>
                        <input
                            id="admision-busqueda"
                            type="search"
                            name="q"
                            class="form-control"
                            maxlength="200"
                            value="{{ $filters['q'] ?? '' }}"
                            placeholder="Buscar paciente"
                        >
                    </div>
                    <div class="col-12 col-xl-2 d-grid">
                        <button class="btn btn-primary" type="submit" id="admision-filtrar">
                            <i class="bi bi-funnel" aria-hidden="true"></i>
                            Filtrar
                        </button>
                    </div>
                </div>
            </form>
            <div class="card mb-3">
                <div
                    class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"
                >
                    <button id="admision-actualizar" type="button" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                        Actualizar
                    </button>
                    <div class="small text-body-secondary" aria-live="polite">
                        <p id="admision-ultima-actualizacion" class="mb-1">
                            Última actualización: {{ now('America/Lima')->format('H:i:s') }}
                        </p>
                        <p class="mb-0 d-flex align-items-center gap-2">
                            <span
                                class="admision-poll-dot"
                                id="admision-poll-dot"
                                aria-hidden="true"
                            >
                                </span>
                            <span id="admision-poll-estado">
                                Actualización automática cada {{ config('oferta_flexible.admision_poll_interval_ms') / 1000 }} segundos.
                            </span>
                        </p>
                        <p id="admision-estado" class="mb-0 mt-1" role="status"></p>
                    </div>
                </div>
            </div>
        @endif
        <div id="admision-error" class="alert alert-danger d-none" role="alert"></div>
        <div
            id="admision-cargando"
            class="alert alert-light border d-flex align-items-center gap-2"
            hidden
            role="status"
        >
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
            Actualizando listado…
        </div>
        <div id="admision-listado" aria-busy="false">
            @include('oferta_flexible.admision._listado')
        </div>
    </div>
@endsection
