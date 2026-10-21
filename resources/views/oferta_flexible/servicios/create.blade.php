@extends('layouts.app')
@section('title', 'Crear Servicio UPSS')
@section('script', 'oferta_flexible/servicios/form')
@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><h1 class="h4 mb-0">Crear Servicio UPSS</h1><a href="{{ route('oferta-flexible.servicios.index') }}" class="btn btn-outline-secondary">Volver</a></div>
    @include('oferta_flexible._mensajes')
    <div class="card shadow-sm"><div class="card-body">
        <form method="POST" action="{{ route('oferta-flexible.servicios.store') }}" >
            @csrf
            
            @include('oferta_flexible.servicios._form')
            <div class="d-flex justify-content-end mt-4"><button type="submit" class="btn btn-primary" >Guardar servicio</button></div>
        </form>
    </div></div>
</div>
@endsection
