@if ($campania)
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-2">
                <h2 class="h5 mb-0">{{ $campania->nombre }}</h2>
                <div class="d-flex flex-wrap gap-2">
                    @include('oferta_flexible.partials.badge', ['etiqueta' => $campania->estado])
                    @if ($esAdministrador)
                        <span class="badge oferta-badge oferta-badge-primary">
                            <i class="bi bi-person-gear me-1" aria-hidden="true"></i>
                            Vista Administrador
                        </span>
                    @endif
                </div>
            </div>
            @if ($esAdministrador)
                <p class="small text-body-secondary mb-0">Puede consultar las hojas de todos los médicos de esta campaña.
                </p>
            @elseif ($campania->asociar_triaje_admision)
                <p class="small text-body-secondary mb-0">
                    <strong>Médicos enlazados:</strong>
                    {{ $medicos->pluck('nombre')->join(', ') ?: 'No tiene médicos enlazados en esta campaña.' }}
                </p>
            @else
                <p class="small text-body-secondary mb-0">
                    Esta campaña no requiere enlaces: puede consultar las hojas de todos sus médicos.
                </p>
            @endif
        </div>
    </div>
    @if ($campania->cerrada)
        <div class="alert alert-info d-flex gap-2" role="status">
            <i class="bi bi-lock" aria-hidden="true"></i>
            Campaña cerrada: puede consultar e imprimir. No se permite eliminar hojas.
        </div>
    @endif
    @include('oferta_flexible.partials.resumen_hojas')
@endif
