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
                    'incluye_laboratorio',
                    'Incluye Laboratorio',
                    false,
                    'Configure exámenes e insumos por fecha y asigne un equipo propio de laboratorio.',
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
                    $ayudaNo = $campo === 'incluye_laboratorio'
                        ? 'La campaña solo ofrecerá servicios de triaje.'
                        : ($campo === 'asociar_triaje_admision'
                        ? 'Admisión consultará las hojas de todos los médicos de la campaña.'
                        : 'Configure servicios y cupos independientes para cada fecha.');
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
@include('oferta_flexible.partials.editor_servicios', [
    'prefix' => 'servicios',
    'lab' => false,
    'catalogo' => $servicios,
    'datosGenerales' => $generales,
    'datosDiarios' => $diarios,
    'datosPersonalizados' => $personalizados,
])
@include('oferta_flexible.partials.editor_servicios', [
    'prefix' => 'laboratorio',
    'lab' => true,
    'catalogo' => $examenes,
    'datosGenerales' => $labGenerales,
    'datosDiarios' => $labDiarios,
    'datosPersonalizados' => $labPersonalizados,
])
<noscript>
    <div class="alert alert-warning mt-3">
        Active JavaScript para configurar los servicios y cupos de la campaña.
    </div>
</noscript>
