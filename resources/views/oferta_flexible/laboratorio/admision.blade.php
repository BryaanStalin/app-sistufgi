@extends('layouts.app')
@section('title', 'Admisión Laboratorio')
@section('script', 'oferta_flexible/laboratorio/admision')
@section('content')
    <div
        class="container-fluid oferta-admin"
        data-lab-admision
        data-url="{{ route('oferta-flexible.laboratorio.admision.store') }}"
        data-campania="{{ $seleccionada?->id }}"
        data-inicial="{{ json_encode($contexto) }}"
        data-intervalo="{{ config('oferta_flexible.laboratorio.stock_intervalo_segundos', 120) }}"
    >
        @include('oferta_flexible.partials.encabezado', [
            'titulo' => 'Admisión Laboratorio',
            'subtitulo' => $seleccionada?->nombre ?? 'Identificación de pacientes y solicitud de exámenes.',
            'icono' => 'bi-droplet',
        ])
        @if ($campanias->count() > 1)
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" id="lab-campania-form" class="row g-3 align-items-end">
                        <div class="col-md-9">
                            <label for="lab-campania" class="form-label">Campaña de trabajo</label>
                            <select id="lab-campania" name="campania_id" class="form-select">
                                @foreach ($campanias as $campania)
                                    <option value="{{ $campania->id }}" @selected($campania->id === $seleccionada->id)>
                                        {{ $campania->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary" type="submit">
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
            <div class="alert alert-danger" id="lab-error" role="alert" hidden></div>
            <div class="alert alert-success" id="lab-exito" role="status" hidden></div>
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-xl-6">
                            <form id="lab-buscar" class="row g-2 align-items-end" method="POST">
                                @csrf
                                <div class="col-sm-4">
                                    <label for="lab-tipo" class="form-label">Tipo de documento</label>
                                    <select id="lab-tipo" name="tipo_doc" class="form-select" required>
                                        @foreach ($documentos as $tipo)
                                            <option value="{{ $tipo }}">
                                                {{ $tipo === 'PASS' ? 'Pasaporte' : $tipo }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-5">
                                    <label for="lab-documento" class="form-label">Número de documento</label>
                                    <input
                                        id="lab-documento"
                                        name="dni"
                                        class="form-control"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        required
                                    >
                                    <div class="invalid-feedback" data-error-for="dni">Revise el documento.</div>
                                </div>
                                <div class="col-sm-3">
                                    <button id="lab-buscar-boton" type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search" aria-hidden="true"></i>
                                        Buscar
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-7 col-xl-3">
                            <label for="lab-paciente" class="form-label">Paciente</label>
                            <input id="lab-paciente" class="form-control fw-semibold" readonly>
                        </div>
                        <div class="col-md-2 col-xl-1">
                            <label for="lab-edad" class="form-label">Edad</label>
                            <input id="lab-edad" class="form-control" readonly>
                        </div>
                        <div class="col-md-3 col-xl-2">
                            <label for="lab-paciente-sexo" class="form-label">Sexo</label>
                            <input id="lab-paciente-sexo" class="form-control" readonly>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body">
                    <form id="lab-atencion-form" class="row g-3" method="POST">
                        @csrf
                        <div class="col-md-3">
                            <label for="lab-fua" class="form-label">N° FUA</label>
                            <input
                                id="lab-fua"
                                name="fua"
                                class="form-control"
                                maxlength="20"
                                pattern="[0-9\-]+"
                                inputmode="numeric"
                                required
                            >
                            <div class="invalid-feedback" data-error-for="fua">
                                Use números y guiones, hasta 20 caracteres.
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label for="lab-servicio" class="form-label">Servicio que solicita</label>
                            <select id="lab-servicio" name="ups_id_solicita" class="form-select" required></select>
                            <div class="invalid-feedback" data-error-for="ups_id_solicita">Seleccione un servicio.</div>
                        </div>
                        <div class="col-md-4">
                            <label for="lab-celular" class="form-label">Celular del paciente</label>
                            <input
                                id="lab-celular"
                                name="telefono"
                                class="form-control"
                                type="tel"
                                maxlength="21"
                                pattern="[0-9]{9}([\s\/,]+[0-9]{9})?"
                                placeholder="999999999 / 988888888"
                                required
                            >
                            <div class="invalid-feedback" data-error-for="telefono">
                                Ingrese uno o dos celulares de 9 dígitos.
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-body text-center">
                            <p class="mb-1">Nro Pcte:</p>
                            <p class="fs-2 fw-bold text-danger mb-0" id="lab-tm">TM-N° ---</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <h2 class="h6 mb-0">Insumos del día</h2>
                        </div>
                        <div class="card-body">
                            <p class="small text-body-secondary">
                                Refleja las altas y los retiros pendientes de Grabar en esta pantalla.
                            </p>
                            <div id="lab-stock" aria-live="polite"></div>
                            <p class="small text-body-secondary mb-0" id="lab-stock-hora"></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="h6 mb-0">Exámenes solicitados</h2>
                        </div>
                        <div class="card-body">
                            <div id="lab-alertas" aria-live="polite"></div>
                            <div class="row g-2 align-items-end mb-3">
                                <div class="col-md-9">
                                    <label for="lab-examen" class="form-label">Examen solicitado</label>
                                    <select id="lab-examen" class="form-select"></select>
                                </div>
                                <div class="col-md-3">
                                    <button id="lab-agregar" type="button" class="btn btn-primary w-100" disabled>
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                        Agregar
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th class="text-center">N°</th>
                                            <th>Examen a realizar</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="lab-examenes"></tbody>
                                </table>
                            </div>
                            <p id="lab-modo" class="small text-body-secondary" role="status"></p>
                            <div class="d-flex flex-wrap gap-2">
                                <button
                                    id="lab-grabar"
                                    type="submit"
                                    form="lab-atencion-form"
                                    class="btn btn-success"
                                    disabled
                                >
                                    <i class="bi bi-save" aria-hidden="true"></i>
                                    Grabar
                                </button>
                                <button id="lab-modificar" type="button" class="btn btn-warning text-dark" disabled>
                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                    Modificar
                                </button>
                                <button id="lab-nuevo" type="button" class="btn btn-secondary">
                                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                                    Nuevo Pcte
                                </button>
                                <button id="lab-eliminar" type="button" class="btn btn-danger" hidden>
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                    Eliminar registro
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
