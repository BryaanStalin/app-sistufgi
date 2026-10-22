@extends('layouts.app')
@section('title', 'Editar Campaña')
@section('script', 'oferta_flexible/campanias/form')
@section('content')
    <div class="container-fluid oferta-admin">
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-calendar2-week',
            'titulo' => 'Editar Campaña',
            'subtitulo' => 'Complete los datos y revise la configuración antes de guardar.',
            'accionUrl' => route('oferta-flexible.campanias.index'),
            'accionTexto' => 'Volver',
            'accionIcono' => 'bi-arrow-left',
        ])
        @include('oferta_flexible._mensajes')
        <form
            method="POST"
            action="{{ route('oferta-flexible.campanias.update', $campania) }}"
            id="campania-form"
            data-admin-submit
        >
            @csrf
            @method('PUT')
            @include('oferta_flexible.campanias._form')

            @include('oferta_flexible.partials.resumen_campania', ['textoGuardar' => 'Actualizar campaña'])
        </form>
    </div>
@endsection
