<div class="card mb-4">
    <div class="card-header">
        <h2 class="h6 mb-0">Datos generales</h2>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-6">
                <label for="nombre" class="form-label">Nombre de campaña</label>
                <input
                    id="nombre"
                    name="nombre"
                    class="form-control @error('nombre') is-invalid @enderror"
                    maxlength="200"
                    required
                    value="{{ old('nombre', $campania->nombre) }}"
                >
                @error('nombre')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="fecha_inicio" class="form-label">Fecha inicio</label>
                <input
                    type="date"
                    id="fecha_inicio"
                    name="fecha_inicio"
                    required
                    class="form-control @error('fecha_inicio') is-invalid @enderror"
                    value="{{ old('fecha_inicio', $campania->fecha_inicio?->toDateString()) }}"
                >
                @error('fecha_inicio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="fecha_fin" class="form-label">Fecha fin</label>
                <input
                    type="date"
                    id="fecha_fin"
                    name="fecha_fin"
                    required
                    class="form-control @error('fecha_fin') is-invalid @enderror"
                    value="{{ old('fecha_fin', $campania->fecha_fin?->toDateString()) }}"
                >
                @error('fecha_fin')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>
<div class="card mb-4">
    <div class="card-header">
        <h2 class="h6 mb-0">Configuración de la campaña</h2>
    </div>
    <div class="card-body">
        <div class="row g-4">
            @foreach ([
                [
                    'asociar_triaje_admision',
                    'Asociar Triaje y Admisión',
                    false,
                    'Cada médico se enlaza a un admisionista que atenderá sus hojas de ruta.',
                ],
                [
                    'mismos_servicios',
                    'Mismos servicios todos los días',
                    true,
                    'Elija una lista general. Podrá personalizar el cupo de cada día sin perder esos cambios.',
                ],
            ] as [$campo, $texto, $predeterminado, $ayuda])
                @php
                    $valor = (string) old($campo, (int) ($campania->{$campo} ?? $predeterminado));
                    $ayudaNo = $campo === 'asociar_triaje_admision'
                        ? 'Admisión consultará las hojas de todos los médicos de la campaña.'
                        : 'Configure servicios y cupos independientes para cada fecha.';
                @endphp
                <div class="col-lg-6">
                    <p class="form-label" id="{{ $campo }}-label">{{ $texto }}</p>
                    <input type="hidden" id="{{ $campo }}" name="{{ $campo }}" value="{{ $valor }}">
                    <div
                        class="btn-group"
                        role="group"
                        aria-labelledby="{{ $campo }}-label"
                        data-config-for="{{ $campo }}"
                    >
                        @foreach (['1' => 'SÍ', '0' => 'NO'] as $opcion => $etiqueta)
                            <button
                                type="button"
                                class="btn {{ $valor === (string) $opcion ? 'btn-primary' : 'btn-secondary' }}"
                                data-config-value="{{ $opcion }}"
                                aria-pressed="{{ $valor === (string) $opcion ? 'true' : 'false' }}"
                            >
                                <i
                                    class="bi {{ $opcion ? 'bi-check-lg' : 'bi-x-lg' }}"
                                    aria-hidden="true"
                                >
                                </i>
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>
                    <p
                        class="form-text mb-0"
                        data-config-help="{{ $campo }}"
                        data-help-yes="{{ $ayuda }}"
                        data-help-no="{{ $ayudaNo }}"
                    >
                        {{ $valor === '1' ? $ayuda : $ayudaNo }}
                    </p>
                    @error($campo)
                        <p class="text-danger small mt-2 mb-0">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
    </div>
</div>
<div
    class="card"
    id="servicios-editor"
    data-generales="{{ json_encode(old('servicios_generales', $generales)) }}"
    data-diarios="{{ json_encode(old('servicios_dia', $diarios)) }}"
    data-personalizados="{{ json_encode($personalizados) }}"
>
    <div class="card-header">
        <h2 class="h6 mb-0">Servicios y cupos</h2>
    </div>
    <div class="card-body">
        <p class="text-body-secondary small">
            Seleccione al menos un servicio por día y asigne un cupo entero mayor que cero.
        </p>
        <div
            id="servicios-dias-tabs"
            class="nav nav-tabs oferta-tabs mb-3"
            role="tablist"
            aria-label="Días de campaña"
        >
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label for="servicio-buscar" class="form-label">Buscar servicios</label>
                <input
                    id="servicio-buscar"
                    type="search"
                    class="form-control"
                    placeholder="Descripción o código UPSS"
                    autocomplete="off"
                >
            </div>
            <div class="col-md-4">
                <label for="servicio-tipo" class="form-label">Tipo de servicio</label>
                <select id="servicio-tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="P">Preventivo</option>
                    <option value="E">Especialidad</option>
                </select>
            </div>
        </div>
        <div class="oferta-service-catalog mb-3" data-service-catalog>
            @foreach ($servicios as $servicio)
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
        <p class="small text-body-secondary" data-catalog-empty hidden>No se encontraron servicios con esos filtros.
        </p>
        <div id="servicios-generales">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="h6 mb-0">Cupos generales y por día</h3>
                <button type="button" class="btn btn-warning text-dark" data-reset-personalizados>
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    Aplicar cupos generales a todos los días…
                </button>
            </div>
            <p class="small text-body-secondary">
                Los cupos diarios personalizados se conservan al cambiar el cupo general.
            </p>
            <div data-general-rows></div>
        </div>
        <div id="servicios-dias"></div>
    </div>
</div>
<input type="hidden" name="sobrescribir_personalizados" id="sobrescribir_personalizados" value="0">
<div id="servicios-payload"></div>
<noscript>
    <div class="alert alert-warning mt-3">Active JavaScript para configurar los servicios y cupos de la campaña.
    </div>
</noscript>
