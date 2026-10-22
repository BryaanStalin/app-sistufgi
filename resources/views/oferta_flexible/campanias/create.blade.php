@extends('layouts.app')
@section('title', 'Crear Campaña')
@section('script', 'oferta_flexible/campanias/form')
@section('content')
    <div class="container-fluid oferta-admin">
        @include('oferta_flexible.partials.encabezado', [
            'icono' => 'bi-calendar2-week',
            'titulo' => 'Crear Campaña',
            'subtitulo' => 'Complete los datos y revise la configuración antes de guardar.',
            'accionUrl' => route('oferta-flexible.campanias.index'),
            'accionTexto' => 'Volver',
            'accionIcono' => 'bi-arrow-left',
        ])
        @include('oferta_flexible._mensajes')
        <form
            method="POST"
            action="{{ route('oferta-flexible.campanias.store') }}"
            id="campania-form"
            data-admin-submit
        >
            @csrf

            @include('oferta_flexible.campanias._form')

            @include('oferta_flexible.partials.resumen_campania', ['textoGuardar' => 'Crear campaña'])
        </form>
    </div>
@endsection
