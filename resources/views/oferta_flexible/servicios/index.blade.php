@extends('layouts.app')
@section('title', 'Gestión de Servicios UPSS')
@section('script', 'oferta_flexible/servicios/index')
@section('content')
    <div class="container-fluid oferta-admin">
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-hospital',
            'titulo' => 'Gestión de Servicios UPSS',
            'subtitulo' => 'Administre el catálogo de servicios de triaje y exámenes de laboratorio.',
            'accionUrl' => route('oferta-flexible.servicios.create'),
            'accionTexto' => 'Nuevo servicio UPSS',
        ])
        @include('oferta_flexible._mensajes')
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end" data-admin-submit>
                    <div class="col-md-6">
                        <label for="q" class="form-label">Buscar por código o descripción</label>
                        <input
                            id="q"
                            name="q"
                            class="form-control"
                            value="{{ request('q') }}"
                            maxlength="255"
                            placeholder="Código o descripción del servicio"
                        >
                    </div>
                    <div class="col-md-4">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select">
                            <option value="">Triaje: Preventivo y Especialidad</option>
                            @foreach (\App\Models\ServicioUps::TIPOS as $value => $label)
                                <option value="{{ $value }}" @selected(request('tipo') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="activo" value="{{ request('activo') }}">
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            Filtrar
                        </button>
                    </div>
                </form>
                <nav class="d-flex flex-wrap gap-2 mt-3" aria-label="Filtrar servicios por estado">
                    @foreach ([
                        ['', 'Todos', $conteos->todos],
                        ['1', 'Activos', $conteos->activos],
                        ['0', 'Inactivos', $conteos->inactivos],
                    ] as [$valor, $texto, $cantidad])
                        <a
                            @class([
                                'btn',
                                'btn-primary' => (string) request('activo', '') === $valor,
                                'btn-secondary' => (string) request('activo', '') !== $valor,
                            ])
                            href="{{ route('oferta-flexible.servicios.index', array_merge(request()->only('q', 'tipo'), ['activo' => $valor])) }}"
                            @if ((string) request('activo', '') === $valor)
                            aria-current="page"
                            @endif
                        >
                            <i
                                class="bi {{ $valor === '0' ? 'bi-pause-circle' : 'bi-check2-circle' }}"
                                aria-hidden="true"
                            >
                            </i>
                            {{ $texto }}
                            <span class="badge bg-white text-dark">{{ $cantidad }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h6 mb-0">Catálogo de servicios</h2>
                <span class="small text-body-secondary">{{ $servicios->total() }} resultados</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th class="text-center">Código</th>
                                <th>Descripción</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($servicios as $servicio)
                                <tr @class(['oferta-inactive' => !$servicio->ACTIVO])>
                                    <td class="text-center">{{ $servicio->ID_UPS }}</td>
                                    <td class="fw-semibold">{{ $servicio->DESCRIPCION_UPS }}</td>
                                    <td class="text-center">
                                        @include('oferta_flexible.partials.badge', [
                                            'etiqueta' => $servicio->tipo_etiqueta,
                                        ])
                                    </td>
                                    <td class="text-center">
                                        @include('oferta_flexible.partials.badge', [
                                            'etiqueta' => $servicio->ACTIVO ? 'Activo' : 'Inactivo',
                                        ])
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a
                                                class="btn btn-primary oferta-icon-button"
                                                href="{{ route('oferta-flexible.servicios.edit', $servicio) }}"
                                                data-bs-toggle="tooltip"
                                                title="Editar servicio"
                                                aria-label="Editar servicio"
                                            >
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                            </a>
                                            @if ($servicio->ACTIVO)
                                                <button
                                                    type="button"
                                                    class="btn btn-danger oferta-icon-button"
                                                    data-delete-url="{{ route('oferta-flexible.servicios.destroy', $servicio) }}"
                                                    data-confirm="¿Desactivar este servicio UPSS?"
                                                    data-bs-toggle="tooltip"
                                                    title="Desactivar servicio"
                                                    aria-label="Desactivar servicio"
                                                >
                                                    <i
                                                        class="bi bi-pause-circle"
                                                        aria-hidden="true"
                                                    >
                                                        </i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        @include('oferta_flexible.partials.vacio', [
                                            'mensajeVacio' => 'No hay servicios disponibles.',
                                            'ayudaVacio' => 'Cambie los filtros o registre un nuevo servicio UPSS.',
                                            'iconoVacio' => 'bi-hospital',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $servicios->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>
    </div>
@endsection
