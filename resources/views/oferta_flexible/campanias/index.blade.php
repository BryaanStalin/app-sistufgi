@extends('layouts.app')
@section('title', 'Gestión de Campañas')
@section('script', 'oferta_flexible/campanias/index')
@section('content')
    <div class="container-fluid oferta-admin">
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-calendar2-week',
            'titulo' => 'Gestión de Campañas',
            'subtitulo' => 'Organice los periodos, servicios y cupos de cada campaña.',
            'accionUrl' => route('oferta-flexible.campanias.create'),
            'accionTexto' => 'Nueva campaña',
        ])
        @include('oferta_flexible._mensajes')
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end" data-admin-submit>
                    <div class="col-md-6">
                        <label for="q" class="form-label">Buscar campaña</label>
                        <input
                            id="q"
                            name="q"
                            class="form-control"
                            value="{{ request('q') }}"
                            maxlength="200"
                            placeholder="Nombre de la campaña"
                        >
                    </div>
                    <div class="col-md-4">
                        <label for="estado" class="form-label">Estado</label>
                        <select id="estado" name="estado" class="form-select">
                            <option value="">Todos</option>
                            @foreach (['Activa', 'Finalizada', 'Cerrada'] as $estado)
                                <option
                                    value="{{ $estado }}"
                                    @selected(request('estado') === $estado)
                                >
                                    {{ $estado }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h2 class="h6 mb-0">Campañas registradas</h2>
                <span class="small text-body-secondary">{{ $campanias->total() }} resultados</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Periodo</th>
                                <th class="text-center">Asociar Triaje y Admisión</th>
                                <th class="text-center">Servicios</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($campanias as $campania)
                                <tr>
                                    <td class="fw-semibold">{{ $campania->nombre }}</td>
                                    <td>
                                        <span class="text-nowrap">
                                            {{ $campania->fecha_inicio->format('d/m/Y') }} –
                                            {{ $campania->fecha_fin->format('d/m/Y') }}
                                        </span>
                                        <span class="small text-body-secondary d-block">
                                            {{ (int) $campania->fecha_inicio->diffInDays($campania->fecha_fin) + 1 }}
                                            días
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        @include('oferta_flexible.partials.badge', [
                                            'etiqueta' => $campania->asociar_triaje_admision ? 'SÍ' : 'NO',
                                        ])
                                    </td>
                                    <td class="text-center fw-semibold">{{ $campania->servicios_count }}</td>
                                    <td class="text-center">
                                        @include('oferta_flexible.partials.badge', ['etiqueta' => $campania->estado])
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <a
                                                class="btn btn-secondary oferta-icon-button"
                                                href="{{ route('oferta-flexible.campanias.show', $campania) }}"
                                                data-bs-toggle="tooltip"
                                                title="Ver campaña"
                                                aria-label="Ver campaña"
                                            >
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </a>
                                            @unless ($campania->cerrada)
                                                <a
                                                    class="btn btn-primary oferta-icon-button"
                                                    href="{{ route('oferta-flexible.campanias.edit', $campania) }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Editar campaña"
                                                    aria-label="Editar campaña"
                                                >
                                                    <i
                                                        class="bi bi-pencil-square"
                                                        aria-hidden="true"
                                                    >
                                                        </i>
                                                </a>
                                                <form
                                                    method="POST"
                                                    action="{{ route('oferta-flexible.campanias.cerrar', $campania) }}"
                                                    data-confirm-form="¿Cerrar esta campaña? Quedará de solo lectura."
                                                    data-admin-submit
                                                >
                                                    @csrf
                                                    <input type="hidden" name="confirmar" value="1">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-warning oferta-icon-button text-dark"
                                                        data-bs-toggle="tooltip"
                                                        title="Cerrar campaña"
                                                        aria-label="Cerrar campaña"
                                                    >
                                                        <i class="bi bi-lock" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                                <button
                                                    type="button"
                                                    class="btn btn-danger oferta-icon-button"
                                                    data-delete-url="{{ route('oferta-flexible.campanias.destroy', $campania) }}"
                                                    data-confirm="¿Eliminar esta campaña?"
                                                    data-bs-toggle="tooltip"
                                                    title="Eliminar campaña"
                                                    aria-label="Eliminar campaña"
                                                >
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        @include('oferta_flexible.partials.vacio', [
                                            'mensajeVacio' => 'No hay campañas disponibles.',
                                            'ayudaVacio' => 'Pruebe otros filtros o cree una nueva campaña.',
                                            'iconoVacio' => 'bi-calendar2-week',
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $campanias->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>
    </div>
@endsection
