<div class="row g-3 mb-4" aria-label="Resumen del listado filtrado">
    @foreach ([
        ['total', 'Total de hojas', 'bi-journal-medical', 'primary'],
        ['impresas', 'Impresas', 'bi-printer', 'success'],
        ['pendientes', 'Pendientes de imprimir', 'bi-hourglass-split', 'warning'],
        ['modificadas', 'Modificadas', 'bi-pencil-square', 'orange'],
    ] as [$clave, $tituloResumen, $iconoResumen, $varianteResumen])
        <div class="col-6 col-lg-3">
            <div class="card h-100 admision-summary">
                <div class="card-body d-flex align-items-center gap-3">
                    <span
                        class="admision-summary-icon oferta-badge-{{ $varianteResumen }}"
                        aria-hidden="true"
                    >
                        <i class="bi {{ $iconoResumen }}"></i>
                    </span>
                    <div>
                        <p class="h3 mb-1" data-resumen="{{ $clave }}">{{ $resumen[$clave] }}</p>
                        <p class="small text-body-secondary mb-0">{{ $tituloResumen }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
