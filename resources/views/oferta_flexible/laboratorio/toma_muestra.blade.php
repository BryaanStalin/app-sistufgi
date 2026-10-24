@extends('layouts.app')
@section('title', 'Toma de Muestra')
@section('script', 'oferta_flexible/laboratorio/toma_muestra')
@section('content')
    <div
        class="container-fluid oferta-admin"
        data-lab-toma-muestra
        data-url="{{ route('oferta-flexible.laboratorio.toma-muestra.store') }}"
        data-campania="{{ $seleccionada?->id }}"
        data-inicial="{{ json_encode($contexto) }}"
        data-intervalo="{{ config('oferta_flexible.laboratorio.stock_intervalo_segundos', 120) }}"
        data-aviso-hora="{{ \App\Services\LaboratorioAdmisionService::HORA_LIMITE }}"
    >
        @include('oferta_flexible.partials.encabezado', [
            'titulo' => 'Toma de Muestra',
            'subtitulo' => $seleccionada?->nombre ?? 'Registro y seguimiento de exámenes de laboratorio.',
            'icono' => 'bi-eyedropper',
        ])
        @if ($campanias->count() > 1)
            <div class="card mb-3">
                <div class="card-body">
                    <form id="tm-campania-form" method="GET" class="row g-3 align-items-end">
                        <div class="col-md-9">
                            <label for="tm-campania" class="form-label">Campaña de trabajo</label>
                            <select id="tm-campania" name="campania_id" class="form-select">
                                @foreach ($campanias as $campania)
                                    <option
                                        value="{{ $campania->id }}"
                                        @selected($campania->id === $seleccionada->id)
                                    >
                                        {{ $campania->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                                Consultar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
        @if (!$seleccionada)
            <div class="card">
                @include('oferta_flexible.partials.vacio', [
                    'mensajeVacio' => 'No hay una campaña de laboratorio activa hoy',
                    'iconoVacio' => 'bi-calendar-x',
                ])
            </div>
        @else
            <div id="tm-error" class="alert alert-danger" role="alert" hidden></div>
            <div id="tm-exito" class="alert alert-success" role="status" hidden></div>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-5">
                            <form id="tm-buscar" method="POST" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-sm-4">
                                    <label for="tm-tipo" class="form-label">Tipo de documento</label>
                                    <select id="tm-tipo" name="tipo_doc" class="form-select" required>
                                        @foreach ($documentos as $tipo)
                                            <option value="{{ $tipo }}">
                                                {{ $tipo === 'PASS' ? 'Pasaporte' : $tipo }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-5">
                                    <label for="tm-documento" class="form-label">Número de documento</label>
                                    <input
                                        id="tm-documento"
                                        name="dni"
                                        class="form-control"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        required
                                    >
                                    <div class="invalid-feedback" data-error-for="dni">Revise el documento.</div>
                                </div>
                                <div class="col-sm-3">
                                    <button id="tm-buscar-boton" type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                        Buscar
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-5 col-xl-3">
                            <label for="tm-paciente" class="form-label">Paciente</label>
                            <input id="tm-paciente" class="form-control fw-semibold" readonly>
                        </div>
                        <div class="col-md-2 col-xl-1">
                            <label for="tm-edad" class="form-label">Edad</label>
                            <input id="tm-edad" class="form-control" readonly>
                        </div>
                        <div class="col-md-2 col-xl-1">
                            <label for="tm-sexo" class="form-label">Sexo</label>
                            <input id="tm-sexo" class="form-control" readonly>
                        </div>
                        <div class="col-md-3 col-xl-2">
                            <label for="tm-celular" class="form-label">Celular</label>
                            <input id="tm-celular" class="form-control" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-list-check" aria-hidden="true"></i>
                            Exámenes del paciente
                        </div>
                        <div class="card-body">
                            <p class="text-body-secondary small">
                                Solo se pueden corregir los exámenes de hoy.
                                Use Pendiente para dejar el examen sin marcar.
                            </p>
                            <div id="tm-advertencias" aria-live="polite"></div>
                            <form id="tm-estados-form" method="POST">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">N°</th>
                                                <th>Examen a realizar</th>
                                                <th class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tm-examenes"></tbody>
                                    </table>
                                </div>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <button id="tm-grabar" type="submit" class="btn btn-success" disabled>
                                        <i class="bi bi-floppy" aria-hidden="true"></i>
                                        Grabar
                                    </button>
                                    <button id="tm-nuevo" type="button" class="btn btn-secondary" disabled>
                                        <i class="bi bi-person-plus" aria-hidden="true"></i>
                                        Nuevo Pcte
                                    </button>
                                    @if ($asignacionUrl)
                                        <a
                                            class="btn btn-primary"
                                            href="{{ $asignacionUrl }}"
                                        >
                                            <i class="bi bi-building" aria-hidden="true"></i>
                                            Asignar Laboratorio
                                        </a>
                                    @else
                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            disabled
                                            aria-describedby="tm-asignar-ayuda"
                                        >
                                            <i class="bi bi-building" aria-hidden="true"></i>
                                            Asignar Laboratorio
                                        </button>
                                    @endif
                                </div>
                                @unless ($asignacionUrl)
                                    <p id="tm-asignar-ayuda" class="small text-body-secondary mt-2 mb-0">
                                        La asignación de laboratorio estará disponible al habilitar ese módulo.
                                    </p>
                                @endunless
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-body text-center">
                            <div class="text-body-secondary">Nro Pcte</div>
                            <div id="tm-ticket" class="fs-2 fw-bold text-danger" aria-live="polite">TM-N° ---</div>
                        </div>
                    </div>
                    @include('oferta_flexible.partials.laboratorio_insumos_dia', ['prefijo' => 'tm'])
                </div>
            </div>
        @endif
    </div>
@endsection
