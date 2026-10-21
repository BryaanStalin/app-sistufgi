@if(!$campania)
    <div class="alert alert-info">No tiene campañas disponibles asignadas a Admisión.</div>
@else
    <div class="mb-3">
        <h2 class="h5 mb-2">{{ $campania->nombre }} <span class="badge bg-secondary">{{ $campania->estado }}</span></h2>
        @if($campania->asociar_triaje_admision)
            <p class="mb-0"><strong>Médicos enlazados:</strong> {{ $medicos->pluck('nombre')->join(', ') ?: 'No tiene médicos enlazados en esta campaña.' }}</p>
        @else
            <p class="mb-0">Esta campaña no requiere enlaces: puede consultar las hojas de todos sus médicos.</p>
        @endif
    </div>
    @if($campania->cerrada)
        <div class="alert alert-info">Campaña cerrada: puede consultar e imprimir. No se permite eliminar hojas.</div>
    @endif
    <p class="small text-muted">{{ $hojas->total() }} {{ $hojas->total() === 1 ? 'paciente encontrado' : 'pacientes encontrados' }} · {{ \Carbon\CarbonImmutable::parse($filters['fecha'])->format('d/m/Y') }}</p>
    <div class="admision-hojas">
        @forelse($hojas as $hoja)
            <article class="card mb-3" data-hoja="{{ $hoja->id }}">
                <div class="card-body">
                    <div class="admision-datos">
                        <div>
                            <h3 class="h6 mb-1">{{ $hoja->nombres_apellidos }}</h3>
                            <p class="small text-muted mb-2">{{ $hoja->tipo_doc ?: 'Sin tipo de documento' }}: {{ $hoja->dni ?: 'Sin número' }}</p>
                            @if($hoja->modificada)<span class="badge bg-warning text-dark mb-2">Modificada</span>@endif
                        </div>
                        <div>
                            <p class="small fw-semibold mb-1">Servicios</p>
                            <ul class="ps-3 mb-2">@foreach($hoja->servicios as $servicio)<li>{{ $servicio }}</li>@endforeach</ul>
                        </div>
                        <dl class="small mb-2">
                            <dt>Médico</dt><dd>{{ $hoja->medico }}</dd>
                            <dt>Hora de registro</dt><dd>{{ $hoja->hora_registro }}</dd>
                        </dl>
                        <dl class="small mb-2">
                            <dt>Impresiones</dt><dd>{{ $hoja->cantidad_impresiones }}</dd>
                            <dt>Última impresión</dt><dd>{{ $hoja->ultima_impresion_texto }}</dd>
                        </dl>
                    </div>
                    <div class="admision-acciones">
                        <form method="POST" action="{{ route('oferta-flexible.admision.imprimir') }}" target="_blank" rel="noopener" class="admision-imprimir">
                            @csrf
                            <input type="hidden" name="campania_id" value="{{ $campania->id }}">
                            <input type="hidden" name="hoja_id" value="{{ $hoja->id }}">
                            <button type="submit" class="btn btn-primary w-100" aria-label="Imprimir hoja de {{ $hoja->nombres_apellidos }}">Imprimir hoja de ruta</button>
                        </form>
                        @unless($campania->cerrada)
                            <button class="btn btn-outline-danger admision-eliminar" type="button" data-hoja="{{ $hoja->id }}"
                                    data-campania="{{ $campania->id }}" data-url="{{ route('oferta-flexible.admision.destroy') }}"
                                    aria-label="Eliminar hoja de {{ $hoja->nombres_apellidos }}">Eliminar</button>
                        @endunless
                    </div>
                </div>
            </article>
        @empty
            <div class="alert alert-light border">No se encontraron pacientes con los filtros seleccionados.</div>
        @endforelse
    </div>
    @if($hojas->hasPages())
        <nav aria-label="Páginas del listado de pacientes" class="admision-paginacion d-flex flex-wrap gap-2 align-items-center">
            @if($hojas->onFirstPage())
                <span class="btn btn-outline-secondary disabled">Anterior</span>
            @else
                <a class="btn btn-outline-primary" href="{{ $hojas->previousPageUrl() }}">Anterior</a>
            @endif
            <span class="small">Página {{ $hojas->currentPage() }} de {{ $hojas->lastPage() }}</span>
            @if($hojas->hasMorePages())<a class="btn btn-outline-primary" href="{{ $hojas->nextPageUrl() }}">Siguiente</a>@endif
        </nav>
    @endif
@endif
