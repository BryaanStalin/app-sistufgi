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

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 1rem;
        }

        .login-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            font-size: 0.95rem;
            opacity: 0.9;
            margin: 0;
        }

        .login-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #2980b9 100%);
            color: white !important;
            border: none;
            border-radius: 6px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1e5fa3 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .spinner-hidden {
            display: none !important;
        }

        .error-alert {
            display: none;
            margin-bottom: 1rem;
        }

        .form-text-error {
            color: var(--accent-color);
            font-size: 0.85rem;
            margin-top: 0.25rem;
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
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }

        .text-center a {
            color: var(--secondary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .text-center a:hover {
            text-decoration: underline;
        }

        .info-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .info-footer small {
            color: #999;
            font-size: 0.85rem;
        }

        @media (max-width: 480px) {
            .login-header {
                padding: 1.5rem 1rem;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }

            .login-body {
                padding: 1.5rem;
            }
        }
    </style>

    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body data-page="auth/login">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>
                    <i class="bi bi-speedometer2"></i> Sistema Integrado UFGI
                </h1>
                <p>Gestión de Campañas DIRIS Lima Norte</p>
            </div>

            <div class="login-body">
                <div id="error-alert" class="alert alert-danger alert-dismissible fade show error-alert" role="alert">
                    <div id="error-message"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>

                <form id="login-form" action="{{ route('login.store') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label for="user" class="form-label text-white">
                            <i class="bi bi-person"></i> Usuario
                        </label>
                        <input type="text" id="user" class="form-control" name="user" placeholder="Ingrese su usuario" required autofocus>
                        <div id="user-error" class="form-text-error"></div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label text-white">
                            <i class="bi bi-lock"></i> Contraseña
                        </label>
                        <input type="password" id="password" class="form-control" name="password" placeholder="Ingrese su contraseña" required>
                        <div id="password-error" class="form-text-error"></div>
                    </div>

                    <button type="submit" id="submit-btn" class="btn btn-login" >
                        <span id="btn-text">Iniciar Sesión</span>
                        <span id="loading-spinner" class="spinner-border spinner-border-sm ms-2 spinner-hidden" role="status" aria-hidden="true"></span>
                    </button>

                    <div class="text-center mt-3">
                        <a href="{{ route('password.request') }}" class="text-decoration-none text-white">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                </form>

                <div class="info-footer">
                    <small>
                        UFGI - OGTI - DIRIS LN - {{ date('Y') }}
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
