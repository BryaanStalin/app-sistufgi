@extends('layouts.app')
@section('title', 'Gestión de Servicios UPSS')
@section('script', 'oferta_flexible/servicios/index')
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><h1 class="h4 mb-0">Gestión de Servicios UPSS</h1><a class="btn btn-primary" href="{{ route('oferta-flexible.servicios.create') }}">Nuevo servicio UPSS</a></div>
    @include('oferta_flexible._mensajes')
    <form method="GET" class="row g-2 mb-3"><div class="col-md-5"><label for="q" class="form-label">Buscar por código o descripción</label><input id="q" name="q" class="form-control" value="{{ request('q') }}" maxlength="255"></div><div class="col-md-3"><label for="tipo" class="form-label">Tipo</label><select id="tipo" name="tipo" class="form-select"><option value="">Todos</option>@foreach(\App\Models\ServicioUps::TIPOS as $value => $label)<option value="{{ $value }}" @selected(request('tipo') === $value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-2"><label for="activo" class="form-label">Estado</label><select id="activo" name="activo" class="form-select"><option value="">Todos</option><option value="1" @selected(request('activo') === '1')>Activo</option><option value="0" @selected(request('activo') === '0')>Inactivo</option></select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary">Filtrar</button></div></form>
    <div class="card shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Código</th><th>Descripción</th><th>Tipo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
        @forelse($servicios as $servicio)<tr><td>{{ $servicio->ID_UPS }}</td><td>{{ $servicio->DESCRIPCION_UPS }}</td><td>{{ $servicio->tipo_etiqueta }}</td><td>{{ $servicio->ACTIVO ? 'Activo' : 'Inactivo' }}</td><td><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('oferta-flexible.servicios.edit', $servicio) }}">Editar</a>@if($servicio->ACTIVO)<button type="button" class="btn btn-sm btn-outline-danger" data-delete-url="{{ route('oferta-flexible.servicios.destroy', $servicio) }}" data-confirm="¿Desactivar este servicio UPSS?">Desactivar</button>@endif</div></td></tr>@empty<tr><td colspan="5">No hay servicios disponibles.</td></tr>@endforelse
    </tbody></table></div>{{ $servicios->links('pagination::bootstrap-5') }}</div></div>
</div>
@endsection
