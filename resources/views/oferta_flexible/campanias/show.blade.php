@extends('layouts.app')
@section('title', $campania->nombre)
@section('script', 'oferta_flexible/campanias/index')
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-4"><div><h1 class="h4">{{ $campania->nombre }}</h1><span class="badge bg-primary">{{ $campania->estado }}</span> {{ $campania->fecha_inicio->format('d/m/Y') }} al {{ $campania->fecha_fin->format('d/m/Y') }}</div><a class="btn btn-outline-secondary align-self-start" href="{{ route('oferta-flexible.campanias.index') }}">Volver</a></div>
    @include('oferta_flexible._mensajes')
    @if($campania->cerrada)<div class="alert alert-info">Esta campaña está cerrada. Sus datos, usuarios y enlaces son de solo lectura.</div>@endif
    <div class="d-flex flex-wrap gap-2 mb-4">@unless($campania->cerrada)<a class="btn btn-outline-primary" href="{{ route('oferta-flexible.campanias.edit', $campania) }}">Editar campaña</a>@endunless<a class="btn btn-outline-primary" href="{{ route('oferta-flexible.usuarios.index', ['campania_id' => $campania->id]) }}">Usuarios de campaña</a>
    <form method="POST" action="{{ route($campania->cerrada ? 'oferta-flexible.campanias.reabrir' : 'oferta-flexible.campanias.cerrar', $campania) }}" data-confirm-form="{{ $campania->cerrada ? '¿Reabrir esta campaña?' : '¿Cerrar esta campaña? Quedará de solo lectura.' }}">@csrf<input type="hidden" name="confirmar" value="1"><button class="btn btn-{{ $campania->cerrada ? 'warning' : 'danger' }}">{{ $campania->cerrada ? 'Reabrir campaña' : 'CERRAR CAMPAÑA' }}</button></form></div>
    <p>Asociar Triaje y Admisión: <strong>{{ $campania->asociar_triaje_admision ? 'SÍ' : 'NO' }}</strong>. Mismos servicios: <strong>{{ $campania->mismos_servicios ? 'SÍ' : 'NO' }}</strong>.</p>
    <div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-striped"><thead><tr><th>Fecha</th><th>Servicio</th><th>Cupo</th><th>Utilizado</th><th>Cupo personalizado</th></tr></thead><tbody>@foreach($campania->servicios as $fila)<tr><td>{{ $fila->fecha_servicio->format('d/m/Y') }}</td><td>{{ $fila->servicioUps->DESCRIPCION_UPS }}</td><td>{{ $fila->cupos }}</td><td>{{ $usados[$fila->id] ?? 0 }}</td><td>{{ $fila->cupo_personalizado ? 'SÍ' : 'NO' }}</td></tr>@endforeach</tbody></table></div></div></div>
</div>
@endsection
