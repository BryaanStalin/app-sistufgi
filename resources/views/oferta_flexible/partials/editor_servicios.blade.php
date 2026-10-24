<div
    class="card mb-4"
    id="{{ $prefix }}-editor"
    @if ($lab && !old('incluye_laboratorio', $campania->incluye_laboratorio))
        hidden
    @endif
    data-quota-editor
    data-prefix="{{ $prefix }}"
    data-laboratorio="{{ $lab ? '1' : '0' }}"
    data-generales="{{ json_encode(old($lab ? 'laboratorio_generales' : 'servicios_generales', $datosGenerales)) }}"
    data-diarios="{{ json_encode(old($lab ? 'laboratorio_dia' : 'servicios_dia', $datosDiarios)) }}"
    data-personalizados="{{ json_encode($datosPersonalizados) }}"
>
    <div class="card-header">
        <h2 class="h6 mb-0">{{ $lab ? 'Exámenes de laboratorio e insumos' : 'Servicios y cupos' }}</h2>
    </div>
    <div class="card-body">
        <p class="text-body-secondary small">
            {{ $lab
                ? 'Seleccione al menos un examen por día y asigne insumos mayores que cero.'
                : 'Seleccione al menos un servicio por día y asigne un cupo entero mayor que cero.' }}
        </p>
        <div
            id="{{ $prefix }}-dias-tabs"
            class="nav nav-tabs oferta-tabs mb-3"
            role="tablist"
            aria-label="Días de campaña"
        >
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label for="{{ $prefix }}-buscar" class="form-label">
                    {{ $lab ? 'Buscar exámenes' : 'Buscar servicios' }}
                </label>
                <input
                    id="{{ $prefix }}-buscar"
                    type="search"
                    class="form-control"
                    placeholder="Descripción o código UPSS"
                    autocomplete="off"
                >
            </div>
            <div class="col-md-4">
                <label for="{{ $prefix }}-tipo" class="form-label">Tipo de servicio</label>
                <select id="{{ $prefix }}-tipo" class="form-select">
                    <option value="">Todos</option>
                    @if ($lab)
                        <option value="L">Laboratorio (examen)</option>
                    @else
                        <option value="P">Preventivo</option>
                        <option value="E">Especialidad</option>
                    @endif
                </select>
            </div>
        </div>
        <div class="oferta-service-catalog mb-3" data-service-catalog>
            @foreach ($catalogo as $servicio)
                <label
                    class="oferta-catalog-item"
                    data-catalog-item
                    data-ups-id="{{ $servicio->id }}"
                    data-tipo="{{ $servicio->TIPO_UPS }}"
                    data-activo="{{ (int) $servicio->ACTIVO }}"
                    data-descripcion="{{ $servicio->DESCRIPCION_UPS }}"
                    data-codigo="{{ $servicio->ID_UPS }}"
                >
                    <input
                        type="checkbox"
                        class="form-check-input mt-0"
                        data-catalog-check
                        value="{{ $servicio->id }}"
                    >
                    <span>
                        <span class="fw-semibold d-block">{{ $servicio->DESCRIPCION_UPS }}</span>
                        <span class="small text-body-secondary">{{ $servicio->ID_UPS }}</span>
                        @include('oferta_flexible.partials.badge', ['etiqueta' => $servicio->tipo_etiqueta])
                        @unless ($servicio->ACTIVO)
                            @include('oferta_flexible.partials.badge', ['etiqueta' => 'Inactivo'])
                        @endunless
                    </span>
                </label>
            @endforeach
        </div>
        <p class="small text-body-secondary" data-catalog-empty hidden>
            No se encontraron resultados con esos filtros.
        </p>
        <div id="{{ $prefix }}-generales">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="h6 mb-0">{{ $lab ? 'Insumos generales y por día' : 'Cupos generales y por día' }}</h3>
                <button type="button" class="btn btn-warning text-dark" data-reset-personalizados>
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    {{ $lab ? 'Aplicar insumos generales a todos los días…' : 'Aplicar cupos generales a todos los días…' }}
                </button>
            </div>
            <p class="small text-body-secondary">
                {{ $lab
                    ? 'Los insumos diarios personalizados se conservan al cambiar la cantidad general.'
                    : 'Los cupos diarios personalizados se conservan al cambiar el cupo general.' }}
            </p>
            <div data-general-rows></div>
        </div>
        <div id="{{ $prefix }}-dias"></div>
    </div>
    <input
        type="hidden"
        name="{{ $lab ? 'laboratorio_sobrescribir_personalizados' : 'sobrescribir_personalizados' }}"
        id="{{ $prefix }}-sobrescribir"
        value="0"
    >
    <div id="{{ $prefix }}-payload"></div>
</div>
