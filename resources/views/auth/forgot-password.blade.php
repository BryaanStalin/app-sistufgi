@extends('layouts.guest')

@section('page', 'auth/forgot-password')
@section('title', 'Recuperar contraseña')

@section('content')
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <h1>
                <i class="bi bi-shield-lock"></i> Sistema Integrado UFGI
            </h1>
            <p>Gestión de Campañas DIRIS Lima Norte</p>
        </div>

        <div class="login-body">
            {{-- Alerta de error general (oculta por defecto con d-none) --}}
            <div id="error-alert" class="alert alert-danger alert-dismissible fade show d-none" role="alert" aria-live="assertive">
                <div id="error-message"></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>

            <div class="text-center mb-4">
                <h4 class="text-white mb-3">
                    <i class="bi bi-key"></i> Recuperar contraseña
                </h4>
                <p class="text-white mb-0">
                    Ingrese su correo y recibirá un enlace para restablecer su contraseña.
                </p>
            </div>

            <form id="forgot-password-form" action="{{ route('password.email') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="email" class="form-label text-white">
                        <i class="bi bi-envelope"></i> Correo
                    </label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        placeholder="Ingrese su correo"
                        maxlength="255"
                        autocomplete="email"
                        required
                        autofocus
                    >
                    <div id="email-error" class="form-text-error"></div>
                </div>
                <br>
                <button type="submit" class="btn btn-login" id="submit-btn">
                    <span id="btn-text">Enviar enlace de recuperación</span>
                    <span id="loading-spinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                </button>

                <div class="text-center mt-3">
                    <a href="{{ route('login') }}" class="text-decoration-none text-white">
                        Volver al Inicio
                    </a>
                </div>
            </form>

            <div class="info-footer">
                <small>UFGI - OGTI - DIRIS LN - {{ date('Y') }}</small>
            </div>
        </div>
    </div>
</div>
@endsection
