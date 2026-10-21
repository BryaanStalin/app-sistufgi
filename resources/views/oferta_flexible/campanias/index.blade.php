@extends('layouts.app')
@section('title', 'Gestión de Campañas')
@section('script', 'oferta_flexible/campanias/index')
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><h1 class="h4 mb-0">Gestión de Campañas</h1><a class="btn btn-primary" href="{{ route('oferta-flexible.campanias.create') }}">Nueva campaña</a></div>
    @include('oferta_flexible._mensajes')
    <form method="GET" class="row g-2 mb-3"><div class="col-md-6"><label for="q" class="form-label">Buscar campaña</label><input id="q" name="q" class="form-control" value="{{ request('q') }}" maxlength="200"></div><div class="col-md-4"><label for="estado" class="form-label">Estado</label><select id="estado" name="estado" class="form-select"><option value="">Todos</option>@foreach(['Activa','Finalizada','Cerrada'] as $estado)<option value="{{ $estado }}" @selected(request('estado') === $estado)>{{ $estado }}</option>@endforeach</select></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary">Filtrar</button></div></form>
    <div class="card shadow-sm"><div class="card-body"><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>Campaña</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    @forelse($campanias as $campania)<tr><td>{{ $campania->nombre }}</td><td>{{ $campania->fecha_inicio->format('d/m/Y') }}</td><td>{{ $campania->fecha_fin->format('d/m/Y') }}</td><td><span class="badge bg-{{ $campania->cerrada ? 'secondary' : 'primary' }}">{{ $campania->estado }}</span></td><td><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('oferta-flexible.campanias.show', $campania) }}">Ver</a>@unless($campania->cerrada)<a class="btn btn-sm btn-outline-primary" href="{{ route('oferta-flexible.campanias.edit', $campania) }}">Editar</a><button type="button" class="btn btn-sm btn-outline-danger" data-delete-url="{{ route('oferta-flexible.campanias.destroy', $campania) }}" data-confirm="¿Eliminar esta campaña?">Eliminar</button>@endunless</div></td></tr>@empty<tr><td colspan="5">No hay campañas disponibles.</td></tr>@endforelse
    </tbody></table></div>{{ $campanias->links('pagination::bootstrap-5') }}</div></div>
</div>
@endsection
