<div class="d-flex flex-wrap gap-1 mb-1">
    @include('oferta_flexible.partials.badge', [
        'etiqueta' => $hoja->modificada
            ? 'Modificada'
            : ($hoja->cantidad_impresiones > 0 ? 'Impresa' : 'Pendiente'),
    ])
</div>
@if ($hoja->cantidad_impresiones > 0)
    <span class="small d-block">
        {{ $hoja->cantidad_impresiones }} {{ $hoja->cantidad_impresiones === 1 ? 'impresión' : 'impresiones' }}
    </span>
    <span class="small text-body-secondary d-block">Última: {{ $hoja->ultima_impresion_texto }}</span>
@else
    <span class="small text-body-secondary">Sin impresiones</span>
@endif
