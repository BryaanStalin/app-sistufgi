@php
    $variante = match ($etiqueta) {
        'Activa', 'Activo', 'Asignado', 'SÍ', 'Impresa' => 'success',
        'Pendiente' => 'warning',
        'Modificada' => 'orange',
        'Cerrada' => 'danger',
        'Preventivo', 'PREVENTIVO' => 'primary',
        'Especialidad', 'ESPECIALIDAD' => 'info',
        default => 'secondary',
    };
@endphp
<span class="badge oferta-badge oferta-badge-{{ $variante }}">
    @if ($etiqueta === 'Modificada')
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
    @endif
    {{ $etiqueta }}
</span>
