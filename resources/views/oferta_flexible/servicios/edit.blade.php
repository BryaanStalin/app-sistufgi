@extends('layouts.app')
@section('title', 'Editar Servicio UPSS')
@section('script', 'oferta_flexible/servicios/form')
@section('content')
    <div class="container-fluid oferta-admin">
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-hospital',
            'titulo' => 'Editar Servicio UPSS',
            'subtitulo' => 'Complete los datos y revise la configuración antes de guardar.',
            'accionUrl' => route('oferta-flexible.servicios.index'),
            'accionTexto' => 'Volver',
            'accionIcono' => 'bi-arrow-left',
        ])
        @include('oferta_flexible._mensajes')
        <form
            method="POST"
            action="{{ route('oferta-flexible.servicios.update', $servicio) }}"
            data-admin-submit
        >
            @csrf
            @method('PUT')
            @include('oferta_flexible.servicios._form')

            <div class="d-flex justify-content-end mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    Guardar servicio
                </button>
            </div>
        </form>
    </div>
@endsection
