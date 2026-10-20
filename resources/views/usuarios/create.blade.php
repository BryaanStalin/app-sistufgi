@extends('layouts.app')

@section('script', 'usuarios/form')

@section('title', 'Nuevo Usuario - ' . config('app.name'))

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 mb-0">Nuevo Usuario</h2>
                <small class="text-muted">Registra un usuario y asigna su rol institucional.</small>
            </div>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('usuarios.store') }}" method="POST">
                    @csrf
                    @include('usuarios._form')
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary" style="background-color: #1a4f7c; border-color: #1a4f7c;">
                            <i class="bi bi-save"></i> Guardar Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
