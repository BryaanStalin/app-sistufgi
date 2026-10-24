@extends('layouts.app')
@section('title', $campania->nombre)
@section('script', 'oferta_flexible/campanias/index')
@section('content')
    <div class="container-fluid oferta-admin">
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-calendar2-week',
            'titulo' => $campania->nombre,
            'subtitulo' => 'Consulte los servicios, cupos y configuración de la campaña.',
            'accionUrl' => route('oferta-flexible.campanias.index'),
            'accionTexto' => 'Volver',
            'accionIcono' => 'bi-arrow-left',
        ])
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                @include('oferta_flexible.partials.badge', ['etiqueta' => $campania->estado])
                <span>
                    <i class="bi bi-calendar3 me-1" aria-hidden="true"></i>
                    {{ $campania->fecha_inicio->format('d/m/Y') }} al {{ $campania->fecha_fin->format('d/m/Y') }}
                </span>
                <span class="small text-body-secondary">
                    {{ (int) $campania->fecha_inicio->diffInDays($campania->fecha_fin) + 1 }} días
                </span>
            </div>
        </div>
        @include('oferta_flexible._mensajes')
        @if ($campania->cerrada)
            <div class="alert alert-info">Esta campaña está cerrada. Sus datos, usuarios y enlaces son de solo lectura.
            </div>
        @endif
        <div class="d-flex flex-wrap gap-2 mb-4">
            @unless ($campania->cerrada)
                <a
                    class="btn btn-primary"
                    href="{{ route('oferta-flexible.campanias.edit', $campania) }}"
                >
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    Editar campaña
                </a>
            @endunless
            <a
                class="btn btn-primary"
                href="{{ route('oferta-flexible.usuarios.index', ['campania_id' => $campania->id]) }}"
            >
                <i class="bi bi-people" aria-hidden="true"></i>
                Usuarios de campaña
            </a>
            <form
                method="POST"
                data-admin-submit
                action="{{ route($campania->cerrada ? 'oferta-flexible.campanias.reabrir' : 'oferta-flexible.campanias.cerrar', $campania) }}"
                data-confirm-form="{{ $campania->cerrada ? '¿Reabrir esta campaña?' : '¿Cerrar esta campaña? Quedará de solo lectura.' }}"
            >
                @csrf
                <input type="hidden" name="confirmar" value="1">
                <button
                    type="submit"
                    class="btn btn-{{ $campania->cerrada ? 'warning text-dark' : 'danger' }}"
                >
                    <i
                        class="bi {{ $campania->cerrada ? 'bi-unlock' : 'bi-lock' }}"
                        aria-hidden="true"
                    >
                        </i>
                    {{ $campania->cerrada ? 'Reabrir campaña' : 'CERRAR CAMPAÑA' }}
                </button>
            </form>
        </div>
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap gap-4">
                <div>
                    <span class="small text-body-secondary d-block mb-2">Asociar Triaje y Admisión</span>
                    @include('oferta_flexible.partials.badge', [
                        'etiqueta' => $campania->asociar_triaje_admision ? 'SÍ' : 'NO',
                    ])
                </div>
                <div>
                    <span class="small text-body-secondary d-block mb-2">Mismos servicios todos los días
                    </span>
                    @include('oferta_flexible.partials.badge', [
                        'etiqueta' => $campania->mismos_servicios ? 'SÍ' : 'NO',
                    ])
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="h6 mb-0">Servicios y uso de cupos</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Servicio</th>
                                <th class="text-center">Cupo</th>
                                <th>Utilizado / total</th>
                                <th>Cupo personalizado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($campania->servicios as $fila)
                                <tr>
                                    <td>{{ $fila->fecha_servicio->format('d/m/Y') }}</td>
                                    <td>{{ $fila->servicioUps->DESCRIPCION_UPS }}</td>
                                    <td class="text-center">{{ $fila->cupos }}</td>
                                    <td>
                                        <span class="small d-block">{{ $usados[$fila->id] ?? 0 }}/{{ $fila->cupos }}
                                        </span>
                                        <progress
                                            class="oferta-usage"
                                            max="{{ $fila->cupos }}"
                                            value="{{ $usados[$fila->id] ?? 0 }}"
                                            aria-label="Cupos usados de {{ $fila->servicioUps->DESCRIPCION_UPS }}"
                                        >
                                            {{ $usados[$fila->id] ?? 0 }}/{{ $fila->cupos }}
                                        </progress>
                                    </td>
                                    <td class="text-center">
                                        @include('oferta_flexible.partials.badge', [
                                            'etiqueta' => $fila->cupo_personalizado ? 'SÍ' : 'NO',
                                        ])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if ($campania->incluye_laboratorio)
            <div
                class="card mt-4"
                data-laboratorio-stock
                data-stock-url="{{ route('oferta-flexible.laboratorio.stock', $campania) }}"
                data-stock-interval="{{ config('oferta_flexible.laboratorio.stock_intervalo_segundos') }}"
            >
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">Exámenes de laboratorio e insumos</h2>
                    <button type="button" class="btn btn-primary" data-stock-refresh>
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                        Actualizar
                    </button>
                </div>
                <div class="card-body">
                    <p class="small text-body-secondary">
                        Registrados: pendientes y realizados que consumen insumos.
                        <span data-stock-updated aria-live="polite"></span>
                    </p>
                    <div class="alert alert-danger" data-stock-error role="alert" hidden></div>
                    <div data-stock-content>
                        @include('oferta_flexible.partials.laboratorio_stock')
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
