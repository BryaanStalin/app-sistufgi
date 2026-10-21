@extends('layouts.app')
@section('title', 'Editar Campaña')
@section('script', 'oferta_flexible/campanias/form')
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><h1 class="h4 mb-0">Editar Campaña</h1><a href="{{ route('oferta-flexible.campanias.index') }}" class="btn btn-outline-secondary">Volver</a></div>
    @include('oferta_flexible._mensajes')
    <div class="card shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('oferta-flexible.campanias.update', $campania) }}" id="campania-form">
            @csrf
            @method('PUT')
            @include('oferta_flexible.campanias._form')
            <div class="d-flex justify-content-end mt-4"><button type="submit" class="btn btn-primary" data-save-campania disabled>Actualizar campaña</button></div>
        </form>
    </div></div>
</div>
@endsection
