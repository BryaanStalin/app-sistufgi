<div class="table-responsive">
    <table class="table table-hover align-middle lab-asignacion-table">
        <colgroup>
            <col class="lab-col-seleccion">
            <col class="lab-col-numero">
            <col class="lab-col-codigo">
            <col class="lab-col-documento">
            <col class="lab-col-paciente">
            <col class="lab-col-celular">
            @foreach ($columnas as $columna)
                <col>
            @endforeach
            <col class="lab-col-destino">
        </colgroup>
        <thead>
            <tr>
                <th class="text-center">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        id="asignacion-todos"
                        aria-label="Seleccionar todos los pacientes de esta página"
                        @disabled($pacientes->isEmpty())
                    >
                </th>
                <th class="text-center">N°</th>
                <th class="text-center">Código</th>
                <th>Documento</th>
                <th>Paciente</th>
                <th>Celular</th>
                @foreach ($columnas as $columna)
                    <th class="text-center" title="{{ $columna->DESCRIPCION_UPS }}">
                        {{ $columna->DESCRIPCION_UPS }}
                    </th>
                @endforeach
                <th>Laboratorio asignado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pacientes as $paciente)
                @php
                    $asignado = collect($eess)->firstWhere('renipress', $paciente->eess_renipress);
                    $textoAsignado = $asignado
                        ? $asignado['nombre'] . ', RENIPRESS: ' . $asignado['renipress']
                        : 'Sin asignar';
                @endphp
                <tr>
                    <td class="text-center">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            data-lab-seleccion
                            value="{{ $paciente->id }}"
                            aria-label="Seleccionar a {{ $paciente->nombres }} {{ $paciente->apellidos }}"
                        >
                    </td>
                    <td class="text-center">{{ $pacientes->firstItem() + $loop->index }}</td>
                    <td class="text-center fw-semibold">{{ $tm->formatear((int) $paciente->tm_numero) }}</td>
                    <td>{{ $paciente->tipo_doc }}-{{ $paciente->dni }}</td>
                    <td>{{ trim($paciente->nombres . ' ' . $paciente->apellidos) }}</td>
                    <td>{{ $paciente->telefono ?: 'Sin dato' }}</td>
                    @foreach ($columnas as $columna)
                        <td class="text-center">
                            @if ((int) $paciente->{'examen_' . $columna->id} === 1)
                                <i class="bi bi-check-circle-fill text-success fs-6" aria-hidden="true"></i>
                                <span class="visually-hidden">Realizado</span>
                            @endif
                        </td>
                    @endforeach
                    <td>
                        <select
                            class="form-select form-select-sm lab-destino"
                            data-lab-destino
                            data-paciente="{{ $paciente->id }}"
                            data-original="{{ $paciente->eess_renipress }}"
                            aria-label="Laboratorio de {{ $paciente->nombres }} {{ $paciente->apellidos }}"
                            title="{{ $textoAsignado }}"
                        >
                            <option value="" disabled @selected(!$paciente->eess_renipress)>Sin asignar</option>
                            @foreach ($eess as $destino)
                                <option
                                    value="{{ $destino['renipress'] }}"
                                    @selected($destino['renipress'] === $paciente->eess_renipress)
                                >
                                    {{ $destino['nombre'] }}, RENIPRESS: {{ $destino['renipress'] }}
                                </option>
                            @endforeach
                        </select>
                        <small class="d-block text-body-secondary mt-1">{{ $textoAsignado }}</small>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 7 + $columnas->count() }}">
                        @include('oferta_flexible.partials.vacio', [
                            'mensajeVacio' => 'No hay pacientes que coincidan con los filtros.',
                            'iconoVacio' => 'bi-people',
                        ])
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <span class="small text-body-secondary">
        {{ $pacientes->total() }} pacientes encontrados.
        Los checks corresponden a exámenes Realizados en cualquier visita de esta campaña.
    </span>
    {{ $pacientes->links() }}
</div>
