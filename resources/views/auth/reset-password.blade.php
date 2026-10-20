@extends('layouts.guest')

@section('page', 'auth/reset-password')
@section('title', 'Restablecer contraseña')

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
            <div id="error-alert" class="alert alert-danger alert-dismissible fade show d-none" role="alert" aria-live="assertive">
                <div id="error-message"></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>

            <div class="text-center mb-4">
                <h4 class="text-white mb-3">
                    <i class="bi bi-key"></i> Nueva contraseña
                </h4>
                <p class="text-white mb-0">
                    Ingresa tu nueva contraseña. Debe tener al menos 8 caracteres, mayúsculas, minúsculas y números.
                </p>
            </div>

            <form id="reset-password-form" action="{{ route('password.update') }}" method="POST">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="form-group">
                    <label for="password" class="form-label text-white">
                        <i class="bi bi-lock"></i> Nueva contraseña
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Ingresa tu nueva contraseña"
                            maxlength="120"
                            autocomplete="new-password"
                            required
                            autofocus
                        >
                        <button
                            type="button"
                            class="btn btn-secondary toggle-password"
                            data-target="password"
                            aria-label="Mostrar contraseña"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div id="password-error" class="form-text-error"></div>
                    <div class="password-strength mt-2 d-none" id="password-strength">
                        <div class="strength-bar"><span class="strength-fill"></span></div>
                        <small class="strength-label"></small>
                    </div>
                </div><br>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label text-white">
                        <i class="bi bi-lock-fill"></i> Confirmar contraseña
                    </label>
                    <div class="input-group">
                        <input
                            type="password"
                            class="form-control"
                            id="password_confirmation"
                            name="password_confirmation"
                            placeholder="Confirma tu nueva contraseña"
                            maxlength="120"
                            autocomplete="new-password"
                            required
                        >
                        <button
                            type="button"
                            class="btn btn-secondary toggle-password"
                            data-target="password_confirmation"
                            aria-label="Mostrar contraseña"
                        >
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div id="password_confirmation-error" class="form-text-error"></div>
                </div>
                <br>
                <button type="submit" class="btn btn-login" id="submit-btn">
                    <span id="btn-text">Restablecer contraseña</span>
                    <span id="loading-spinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
                </button>
            </form>

            <div class="info-footer">
                <small>UFGI - OGTI - DIRIS LN - {{ date('Y') }}</small>
            </div>
        </div>
    </div>
</div>
@endsection
