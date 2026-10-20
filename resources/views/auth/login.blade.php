<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" href="{{ asset('img/favicon.ico') }}" type="image/x-icon">

    <style>
        :root {
            --primary-color: #02182e;
            --secondary-color: #127cc2;
            --accent-color: #e74c3c;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f6f9;
            -webkit-font-smoothing: antialiased;
        }

        .institutional-panel {
            position: relative;
            background-image: url('img/background-0.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            overflow: hidden;
            color: #ffffff;
        }

        .institutional-panel .overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                135deg,
                rgba(2, 24, 46, 0.94) 0%,
                rgba(18, 124, 194, 0.82) 100%
            );
            z-index: 1;
        }

        .institutional-panel .content {
            position: relative;
            z-index: 2;
            padding: 3.5rem 3.5rem;
            height: 100%;
            width: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .brand-logo {
            display: block;
            width: auto;
            max-width: 280px;            
        }

        .brand-logo-container {
            display: flex;
            width: 100%;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1.25rem;
            background-color: var(--primary-color);
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(8px);
            box-sizing: border-box;
        }

        .brand-logo-container .brand-logo {
            display: block;
            width: 100%;
            max-width: 280px;
            height: auto;
            filter: none;
        }

        .hero-title {
            font-size: 2.65rem;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 1.25rem;
            letter-spacing: -0.5px;
        }

        .hero-title span {
            color: #7fc4f0;
        }

        .hero-subtitle {
            font-size: 1.02rem;
            line-height: 1.65;
            opacity: 0.92;
            max-width: 540px;
            margin-bottom: 2.5rem;
            text-align: justify;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 1.05rem;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.9rem;
            font-size: 0.94rem;
            opacity: 0.95;
        }

        .feature-list li i {
            width: 38px;
            height: 38px;
            min-width: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(127, 196, 240, 0.14);
            border: 1px solid rgba(127, 196, 240, 0.30);
            border-radius: 10px;
            color: #7fc4f0;
            font-size: 1.05rem;
        }

        .institutional-footer {
            font-size: 0.82rem;
            opacity: 0.72;
            letter-spacing: 0.3px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            padding-top: 1.5rem;
        }

        .form-panel {
            background-color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.5rem;
        }

        .form-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .form-header {
            margin-bottom: 1.75rem;
        }

        .form-header h2 {
            font-size: 1.7rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.45rem;
            letter-spacing: -0.3px;
        }

        .form-header p {
            font-size: 0.94rem;
            color: #6c757d;
            margin: 0;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 0.87rem;
            letter-spacing: 0.2px;
        }

        .input-group-custom {
            border-radius: 10px;
            overflow: hidden;
            border: 1.5px solid #e2e8f0;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background-color: #ffffff;
        }

        .input-group-custom:focus-within {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 4px rgba(18, 124, 194, 0.12);
        }

        .input-group-custom .input-group-text {
            background-color: transparent;
            border: none;
            color: #94a3b8;
            padding: 0 1rem;
            transition: color 0.2s ease;
        }

        .input-group-custom:focus-within .input-group-text {
            color: var(--secondary-color);
        }

        .input-group-custom .form-control {
            border: none;
            background-color: transparent;
            padding: 0.85rem 1rem 0.85rem 0;
            font-size: 0.95rem;
            color: var(--primary-color);
            box-shadow: none;
        }

        .input-group-custom .form-control:focus {
            box-shadow: none;
            background-color: transparent;
        }

        .input-group-custom .form-control::placeholder {
            color: #b8c2cf;
        }

        .input-group-custom:has(.form-control.is-invalid) {
            border-color: var(--accent-color);
        }

        .input-group-custom:has(.form-control.is-invalid):focus-within {
            box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.12);
        }

        .input-group-custom:has(.form-control.is-invalid) .input-group-text {
            color: var(--accent-color);
        }

        .forgot-link {
            font-size: 0.86rem;
            color: var(--secondary-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #0d5d96 100%);
            color: #ffffff !important;
            border: none;
            border-radius: 10px;
            padding: 0.9rem 1.5rem;
            font-weight: 600;
            width: 100%;
            font-size: 0.95rem;
            letter-spacing: 0.3px;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px rgba(18, 124, 194, 0.25);
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #0f6aac 0%, #0a4a78 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(18, 124, 194, 0.35);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled,
        .btn-login.disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 4px 14px rgba(18, 124, 194, 0.18);
        }

        .spinner-hidden {
            display: none !important;
        }

        .error-alert {
            display: none;
            margin-bottom: 1rem;
            border-radius: 10px;
            border: none;
            font-size: 0.9rem;
            background-color: rgba(231, 76, 60, 0.10);
            color: #b93b2d;
            padding: 0.85rem 1rem;
        }

        .form-text-error {
            color: var(--accent-color);
            font-size: 0.82rem;
            margin-top: 0.4rem;
            display: none;
        }

        .form-text-error.show {
            display: block;
        }

        .form-control.is-invalid {
            border-color: var(--accent-color);
            background-image: none;
        }

        .form-control.is-invalid:focus {
            border-color: var(--accent-color);
            box-shadow: none;
        }

        .info-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eef2f6;
        }

        .info-footer small {
            color: #94a3b8;
            font-size: 0.82rem;
            letter-spacing: 0.2px;
        }

        @media (max-width: 991.98px) {
            .row > .form-panel {
                align-self: center;
                width: calc(100% - clamp(2rem, 8vw, 4rem));
                max-width: 488px;
                min-height: auto;
                margin: clamp(1rem, 4vw, 2rem) auto;
                padding: 2.5rem 2rem;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                background-color: color-mix(in srgb, var(--primary-color) 10%, white);
            }
            .form-wrapper {
                max-width: 420px;
            }
        }

        @media (max-width: 480px) {
            .row > .form-panel {
                padding: 2rem 1.25rem;
                border-radius: 16px;
            }
            .form-header h2 {
                font-size: 1.45rem;
            }
            .form-logo {
                width: 54px;
                height: 54px;
                font-size: 1.65rem;
            }
        }
    </style>

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body data-page="auth/login">
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            <div class="col-lg-7 d-none d-lg-flex institutional-panel">
                <div class="overlay"></div>
                <div class="content">
                    <div class="my-auto py-4">
                        <h1 class="hero-title">
                            Sistema Integrado <span>UFGI</span>
                        </h1>
                        <p class="hero-subtitle">
                            Plataforma centralizada para la gestión de campañas y procesos estratégicos
                            de la Dirección de Redes Integradas de Salud Lima Norte. Acceso exclusivo
                            para personal autorizado.
                        </p>
                        <ul class="feature-list">
                            <li>
                                <i class="bi bi-shield-lock"></i>
                                <span>Acceso seguro y auditable</span>
                            </li>
                            <li>
                                <i class="bi bi-people"></i>
                                <span>Gestión integral de usuarios y roles</span>
                            </li>
                        </ul>
                    </div>
                    <div class="institutional-footer">
                        DIRIS Lima Norte &middot; Unidad Funcional de Gestión de la Información
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5 form-panel">
                <div class="form-wrapper">
                    <div class="form-header">
                        <div>
                            <span class="brand-logo-container">
                                <img src="{{ asset('img/logo-diris-ln.svg') }}"
                                    alt="DIRIS Lima Norte"
                                    class="brand-logo">
                            </span>
                        </div><br>
                        <h2>Bienvenido de nuevo</h2>
                        <p>Ingrese sus credenciales para acceder al sistema</p>
                    </div>
                    <div id="error-alert" class="alert alert-danger alert-dismissible fade show error-alert" role="alert">
                        <div id="error-message"></div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <form id="login-form" action="{{ route('login.store') }}" method="POST" novalidate>
                        @csrf

                        {{-- Usuario --}}
                        <div class="mb-3">
                            <label for="user" class="form-label">Usuario</label>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input
                                    type="text"
                                    id="user"
                                    name="user"
                                    class="form-control"
                                    placeholder="Ingrese su usuario"
                                    required
                                    autofocus
                                    autocomplete="username"
                                >
                            </div>
                            <div id="user-error" class="form-text-error"></div>
                        </div>

                        {{-- Contraseña --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="password" class="form-label mb-0">Contraseña</label>
                                <a href="{{ route('password.request') }}" class="forgot-link">
                                    ¿Olvidó su contraseña?
                                </a>
                            </div>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Ingrese su contraseña"
                                    required
                                    autocomplete="current-password"
                                >
                            </div>
                            <div id="password-error" class="form-text-error"></div>
                        </div>

                        {{-- Botón --}}
                        <button type="submit" id="submit-btn" class="btn btn-login mt-2">
                            <span id="btn-text">Iniciar Sesión</span>
                            <span id="loading-spinner"
                                  class="spinner-border spinner-border-sm ms-2 spinner-hidden"
                                  role="status"
                                  aria-hidden="true"></span>
                        </button>
                    </form>
                    <div class="info-footer">
                        <small>UFGI &middot; OGTI &middot; DIRIS LN &middot; {{ date('Y') }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
