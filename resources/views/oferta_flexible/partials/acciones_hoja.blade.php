<div class="admision-acciones">
    <form
        method="POST"
        action="{{ route('oferta-flexible.admision.imprimir') }}"
        target="_blank"
        rel="noopener"
        class="admision-imprimir"
    >
        @csrf
        <input type="hidden" name="campania_id" value="{{ $campania->id }}">
        <input type="hidden" name="hoja_id" value="{{ $hoja->id }}">
        <button
            type="submit"
            class="btn btn-primary admision-action-button"
            aria-label="Imprimir hoja de {{ $hoja->nombre_completo }}"
            title="Imprimir hoja de ruta"
            data-bs-toggle="tooltip"
        >
            <i class="bi bi-printer" aria-hidden="true"></i>
            <span class="admision-action-text">Imprimir</span>
        </button>
    </form>
    @unless ($campania->cerrada)
        <button
            class="btn btn-danger admision-eliminar admision-action-button"
            type="button"
            data-hoja="{{ $hoja->id }}"
            data-campania="{{ $campania->id }}"
            data-paciente="{{ $hoja->nombre_completo }}"
            data-url="{{ route('oferta-flexible.admision.destroy') }}"
            aria-label="Eliminar hoja de {{ $hoja->nombre_completo }}"
            title="Eliminar hoja de ruta"
            data-bs-toggle="tooltip"
        >
            <i class="bi bi-trash" aria-hidden="true"></i>
            <span class="admision-action-text">Eliminar</span>
        </button>
    @endunless
</div>
