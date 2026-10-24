@foreach ($stock as $fila)
    @if ($fila['alerta'])
        <div class="alert alert-warning" role="status">
            {{ $fila['fecha'] }}: {{ $fila['mensaje_alerta'] }}
        </div>
    @endif
@endforeach
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Examen</th>
                <th class="text-center">Insumos</th>
                <th class="text-center">Registrados</th>
                <th class="text-center">Tomadas</th>
                <th class="text-center">No realizadas</th>
                <th class="text-center">Quedan</th>
                <th class="text-center">Insumos personalizados</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($stock as $fila)
                <tr>
                    <td>{{ implode('/', array_reverse(explode('-', $fila['fecha']))) }}</td>
                    <td>{{ $fila['examen'] }}</td>
                    <td class="text-center">{{ $fila['insumos'] }}</td>
                    <td class="text-center">
                        <span class="small d-block">{{ $fila['registrados'] }}/{{ $fila['insumos'] }}</span>
                        <progress
                            class="oferta-usage"
                            max="{{ $fila['insumos'] }}"
                            value="{{ $fila['registrados'] }}"
                            aria-label="Insumos utilizados de {{ $fila['examen'] }}"
                        >
                            {{ $fila['registrados'] }}/{{ $fila['insumos'] }}
                        </progress>
                    </td>
                    <td class="text-center">{{ $fila['tomadas'] }}</td>
                    <td class="text-center">{{ $fila['no_realizadas'] }}</td>
                    <td
                        @class([
                            'text-center text-white',
                            'bg-danger' => $fila['rojo'],
                            'bg-success' => !$fila['rojo'],
                        ])
                    >
                        {{ $fila['quedan'] }}
                    </td>
                    <td class="text-center">
                        @include('oferta_flexible.partials.badge', [
                            'etiqueta' => $fila['personalizado'] ? 'SÍ' : 'NO',
                        ])
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        @include('oferta_flexible.partials.vacio', [
                            'mensajeVacio' => 'No hay exámenes de laboratorio configurados.',
                            'iconoVacio' => 'bi-droplet',
                        ])
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="fw-semibold">
                <td colspan="2">TOTAL GENERAL</td>
                @foreach (['insumos', 'registrados', 'tomadas', 'no_realizadas', 'quedan'] as $campo)
                    <td class="text-center">{{ collect($stock)->sum($campo) }}</td>
                @endforeach
                <td class="text-center">—</td>
            </tr>
        </tfoot>
    </table>
</div>
