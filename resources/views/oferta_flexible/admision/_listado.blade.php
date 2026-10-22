@if (!$campania)
    <div class="card">
        @include('oferta_flexible.partials.vacio', [
            'mensajeVacio' => $esAdministrador
                ? 'No hay campañas activas disponibles.'
                : 'No tiene campañas disponibles asignadas a Admisión.',
            'iconoVacio' => 'bi-calendar2-week',
        ])
    </div>
@else
    <template data-admision-contexto>
        @include('oferta_flexible.partials.contexto_admision')
    </template>
    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h6 mb-0">Hojas de ruta</h2>
            <p class="small text-body-secondary mb-0">
                {{ $hojas->total() }} {{ $hojas->total() === 1 ? 'paciente encontrado' : 'pacientes encontrados' }}
                · {{ \Carbon\CarbonImmutable::parse($filters['fecha'])->format('d/m/Y') }}
            </p>
        </div>
        <div class="card-body">
            @if ($hojas->isEmpty())
                @include('oferta_flexible.partials.vacio', [
                    'mensajeVacio' => 'No se encontraron pacientes con los filtros seleccionados.',
                    'ayudaVacio' => 'Revise la fecha, el médico o el documento ingresado.',
                    'iconoVacio' => 'bi-search',
                ])
            @else
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover align-middle admision-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Servicios</th>
                                <th>Médico</th>
                                <th class="text-center">Hora</th>
                                <th>Impresión</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hojas as $hoja)
                                <tr data-hoja="{{ $hoja->id }}">
                                    <td>
                                        <span class="fw-semibold d-block">
                                            {{ $hoja->nombre_completo }}
                                        </span>
                                        <span class="small text-body-secondary">
                                            {{ $hoja->tipo_doc ?: 'Sin tipo de documento' }}:
                                            {{ $hoja->dni ?: 'Sin número' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($hoja->servicios as $servicio)
                                                <span
                                                    class="badge oferta-badge oferta-badge-secondary
                                                    admision-service-badge"
                                                >
                                                    {{ $servicio }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>{{ $hoja->medico }}</td>
                                    <td class="text-center text-nowrap">{{ $hoja->hora_registro }}</td>
                                    <td>
                                        @include('oferta_flexible.partials.estado_impresion')
                                    </td>
                                    <td>
                                        @include('oferta_flexible.partials.acciones_hoja')
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admision-hojas d-md-none">
                    @foreach ($hojas as $hoja)
                        <article class="card mb-3" data-hoja="{{ $hoja->id }}">
                            <div class="card-body">
                                <h3 class="h6 mb-1">{{ $hoja->nombre_completo }}</h3>
                                <p class="small text-body-secondary mb-3">
                                    {{ $hoja->tipo_doc ?: 'Sin tipo de documento' }}: {{ $hoja->dni ?: 'Sin número' }}
                                </p>
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    @foreach ($hoja->servicios as $servicio)
                                        <span
                                            class="badge oferta-badge oferta-badge-secondary
                                                    admision-service-badge"
                                        >
                                            {{ $servicio }}
                                        </span>
                                    @endforeach
                                </div>
                                <p class="small mb-1">
                                    <strong>Médico:</strong>
                                    {{ $hoja->medico }}
                                </p>
                                <p class="small mb-3">
                                    <strong>Hora de registro:</strong>
                                    {{ $hoja->hora_registro }}
                                </p>
                                @include('oferta_flexible.partials.estado_impresion')
                                <div class="border-top mt-3 pt-3">
                                    @include('oferta_flexible.partials.acciones_hoja')
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
            @if ($hojas->hasPages())
                <nav
                    aria-label="Páginas del listado de pacientes"
                    class="admision-paginacion d-flex flex-wrap gap-2 align-items-center mt-3"
                >
                    @if ($hojas->onFirstPage())
                        <span class="btn btn-secondary disabled" aria-disabled="true">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            Anterior
                        </span>
                    @else
                        <a class="btn btn-primary" href="{{ $hojas->previousPageUrl() }}">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            Anterior
                        </a>
                    @endif
                    <span class="small">Página {{ $hojas->currentPage() }} de {{ $hojas->lastPage() }}</span>
                    @if ($hojas->hasMorePages())
                        <a class="btn btn-primary" href="{{ $hojas->nextPageUrl() }}">
                            Siguiente
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    @endif
                </nav>
            @endif
        </div>
    </div>
@endif
