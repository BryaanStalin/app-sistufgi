@extends('layouts.app')

@section('title', 'Gestión de Usuarios para Campañas')
@section('script', 'oferta_flexible/usuarios/index')

@section('content')
    <div class="container-fluid">
        <h1 class="h4 mb-4">Gestión de Usuarios para Campañas</h1>
        @include('oferta_flexible._mensajes')

        <form method="GET" class="row g-3 align-items-end mb-4">
            <div class="col-md-7">
                <label for="campania_id" class="form-label">Campaña</label>
                <select id="campania_id" name="campania_id" class="form-select" required>
                    <option value="">Seleccione una campaña</option>
                    @foreach($campanias as $opcion)
                        <option value="{{ $opcion->id }}" @selected($campania?->id === $opcion->id)>{{ $opcion->nombre }} — {{ $opcion->estado }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="checkbox" name="solo_activas" id="solo_activas" value="1" class="form-check-input" @checked(request()->boolean('solo_activas'))>
                    <label for="solo_activas" class="form-check-label">Solo campañas activas</label>
                </div>
            </div>
            <div class="col-md-2"><button class="btn btn-primary">Consultar</button></div>
        </form>

        @if($campania)
            <h2 class="h5">{{ $campania->nombre }} <span class="badge bg-secondary">{{ $campania->estado }}</span></h2>
            @if($campania->cerrada)
                <div class="alert alert-info">Campaña cerrada: asignaciones y enlaces de solo lectura.</div>
            @endif
            @include('oferta_flexible.usuarios._asignaciones')
            @include('oferta_flexible.usuarios._enlaces')
        @endif
    </div>
@endsection
