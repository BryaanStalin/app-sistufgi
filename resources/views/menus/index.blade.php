@extends('layouts.app')

@section('script', 'menus/index')

@section('title', 'Menús - ' . config('app.name'))

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 mb-0">Menús</h2>
                <small class="text-muted">Listado de menús registrados en el sistema.</small>
            </div>
            <a href="{{ route('menus.create') }}" class="btn btn-primary" style="background-color: #1a4f7c; border-color: #1a4f7c;">
                <i class="bi bi-plus-circle"></i> Nuevo Menú
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="menus-table" class="table table-striped table-bordered align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>#</th>
                                <th>Título</th>
                                <th>Ruta</th>
                                <th>Padre</th>
                                <th>Orden</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($menus as $menu)
                                <tr>
                                    <td>{{ $menu->id }}</td>
                                    <td>{{ $menu->titulo }}</td>
                                    <td>{{ $menu->link }}</td>
                                    <td>{{ $menu->padre?->titulo }}</td>
                                    <td class="text-center">{{ $menu->orden }}</td>
                                    <td class="text-center">
                                        @if($menu->status)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('menus.edit', $menu) }}" class="btn btn-sm btn-outline-primary me-1" aria-label="Editar registro">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-menu" data-delete-url="{{ route('menus.destroy', $menu) }}" aria-label="Inactivar registro">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
