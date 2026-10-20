@extends('layouts.app')

@section('script', 'usuarios/index')

@section('title', 'Usuarios - ' . config('app.name'))

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 mb-0">Usuarios</h2>
                <small class="text-muted">Listado de usuarios registrados en el sistema.</small>
            </div>
            <a href="{{ route('usuarios.create') }}" class="btn btn-primary" style="background-color: #1a4f7c; border-color: #1a4f7c;">
                <i class="bi bi-plus-circle"></i> Nuevo Usuario
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
                    <table id="usuarios-table" class="table table-striped table-bordered align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usuarios as $usuario)
                                <tr>
                                    <td>{{ $usuario->id }}</td>
                                    <td>{{ $usuario->nombre }}</td>
                                    <td>{{ $usuario->user }}</td>
                                    <td>{{ $usuario->email }}</td>
                                    <td class="text-center">
                                        @foreach($usuario->roles as $rol)
                                            <span class="badge bg-secondary">{{ $rol->titulo }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        @if($usuario->status)
                                            <span class="badge bg-success">Activo</span>
                                        @else
                                            <span class="badge bg-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('usuarios.edit', $usuario) }}" class="btn btn-sm btn-outline-primary me-1" aria-label="Editar registro">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-user" data-delete-url="{{ route('usuarios.destroy', $usuario) }}" aria-label="Inactivar registro">
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
