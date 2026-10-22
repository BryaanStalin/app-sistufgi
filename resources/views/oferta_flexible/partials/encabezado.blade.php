<div class="oferta-heading">
    <div class="d-flex align-items-start gap-3">
        <span class="oferta-heading-icon" aria-hidden="true">
            <i class="bi {{ $icono }}"></i>
        </span>
        <div>
            <p class="oferta-eyebrow">Oferta Flexible</p>
            <h1 class="h4 mb-1">{{ $titulo }}</h1>
            <p class="text-body-secondary mb-0">{{ $subtitulo }}</p>
        </div>
    </div>
    @isset($accionUrl)
        <a class="btn btn-primary" href="{{ $accionUrl }}">
            <i class="bi {{ $accionIcono ?? 'bi-plus-lg' }}" aria-hidden="true"></i>
            {{ $accionTexto }}
        </a>
    @endisset
</div>
