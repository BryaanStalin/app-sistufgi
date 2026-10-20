@extends('layouts.app')

@section('script', 'menus/form')

@section('title', 'Nuevo Menú - ' . config('app.name'))

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 mb-0">Nuevo Menú</h2>
                <small class="text-muted">Crea un nuevo menú en el sistema.</small>
            </div>
            <a href="{{ route('menus.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('menus.store') }}" method="POST">
                    @csrf
                    @include('menus._form')
                    <div class="d-flex justify-content-end mt-4">
                        <button type="submit" class="btn btn-primary" style="background-color: #1a4f7c; border-color: #1a4f7c;">
                            <i class="bi bi-save"></i> Guardar Menú
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
