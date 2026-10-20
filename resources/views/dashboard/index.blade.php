@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    <div class="container-fluid">
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        <!-- Encabezado del Dashboard -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="bi bi-speedometer2"></i> Bienvenido(a), {{ Auth::user()->nombre }}
                        </h1>
                    </div>
                    <div class="text-end">
                        @php
                            $rolUsuario = Auth::user()->roles->first();
                        @endphp
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Usuario -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-person-badge"></i> Información del Usuario
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row">
                            <dt class="col-sm-4">Nombre:</dt>
                            <dd class="col-sm-8">{{ Auth::user()->nombre }}</dd>

                            <dt class="col-sm-4">Usuario:</dt>
                            <dd class="col-sm-8"><code>{{ Auth::user()->user }}</code></dd>

                            <dt class="col-sm-4">Email:</dt>
                            <dd class="col-sm-8">{{ Auth::user()->email }}</dd>

                            <dt class="col-sm-4">Estado:</dt>
                            <dd class="col-sm-8">
                                @if(Auth::user()->status)
                                    <span class="badge bg-success">Activo</span>
                                @else
                                    <span class="badge bg-danger">Inactivo</span>
                                @endif
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-shield-lock"></i> Roles Asignados
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($rolUsuario)
                            <div class="list-group list-group-flush">
                                <div class="list-group-item">
                                    <h6 class="mb-1">{{ $rolUsuario->titulo }}</h6>
                                    <p class="mb-0 small text-muted">{{ $rolUsuario->descripcion ?? 'Sin descripción' }}</p>
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning mb-0">
                                <small>No tienes roles asignados</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del Sistema -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="bi bi-info-circle"></i> Información del Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <h6 class="alert-heading">
                                <i class="bi bi-lightbulb"></i> Bienvenido(a) al Sistema Integrado UFGI - DLN
                            </h6>
                            <p class="mb-0">
                                Este es el Sistema Integrado para Gestión de Campañas DIRIS LIMA NORTE.<br>
                                Usa el menú lateral para acceder a las diferentes funciones disponibles según tu rol asignado.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
