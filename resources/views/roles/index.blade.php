@extends('layouts.app')

@section('script', 'roles/index')

@section('title', 'Roles - ' . config('app.name'))

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 mb-0">Roles</h2>
                <small class="text-muted">Listado de roles registrados en el sistema.</small>
            </div>
            <a href="{{ route('roles.create') }}" class="btn btn-primary" style="background-color: #1a4f7c; border-color: #1a4f7c;">
                <i class="bi bi-plus-circle"></i> Nuevo Rol
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
                    <table id="roles-table" class="table table-striped table-bordered align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>#</th>
                                <th>Rol</th>
                                <th>Descripción</th>
                                <th>Menús</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roles as $rol)
                                <tr>
                                    <td>{{ $rol->id }}</td>
                                    <td>{{ $rol->titulo }}</td>
                                    <td>{{ $rol->descripcion }}</td>
                                    <td class="text-center">
                                        @foreach($rol->menus as $menu)
                                            <span class="badge bg-secondary">{{ $menu->titulo }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        @if($rol->status)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('roles.edit', $rol) }}" class="btn btn-sm btn-outline-primary me-1" aria-label="Editar registro">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-rol" data-delete-url="{{ route('roles.destroy', $rol) }}" aria-label="Inactivar registro">
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
