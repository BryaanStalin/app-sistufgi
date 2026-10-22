<div class="oferta-empty" role="status">
    <i class="bi {{ $iconoVacio ?? 'bi-inbox' }}" aria-hidden="true"></i>
    <p class="fw-semibold mb-1">{{ $mensajeVacio }}</p>
    @isset($ayudaVacio)
        <p class="small text-body-secondary mb-0">{{ $ayudaVacio }}</p>
    @endisset
</div>
