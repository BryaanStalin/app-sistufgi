@extends('layouts.app')

@section('title', 'Dashboard - ' . config('app.name'))

@section('content')
    <style>
        :root {
            --primary-color: #06325f;
            --secondary-color: #127cc2;
            --accent-color: #e74c3c;
        }

        /* Hero */
        .dashboard-hero {
            background: var(--primary-color) 100%;
            border-radius: 16px;
            padding: 2.5rem 2.5rem;
            color: #fff;
            position: relative;
            overflow: hidden;
            margin-bottom: 1.75rem;
        }

        .dashboard-hero::before {
            content: '';
            position: absolute;
            right: -80px;
            top: -80px;
            width: 280px;
            height: 280px;
            background: rgba(255,255,255,0.06);
            border-radius: 50%;
        }

        .dashboard-hero::after {
            content: '';
            position: absolute;
            right: 40px;
            bottom: -120px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }

        .dashboard-hero > * {
            position: relative;
            z-index: 1;
        }

        .hero-greeting {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.8;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .hero-name {
            font-size: 2.1rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            letter-spacing: -0.5px;
        }

        .dash-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(2, 24, 46, 0.06);
            height: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dash-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(2, 24, 46, 0.10);
        }

        .dash-card .card-header {
            background: #fff;
            border-bottom: 1px solid #eef2f6;
            padding: 1.1rem 1.4rem;
            border-radius: 14px 14px 0 0 !important;
            font-weight: 600;
            color: var(--primary-color);
        }

        .dash-card .card-header h5,
        .dash-card .card-header h6 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .dash-card .card-header i {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        .dash-card .card-body {
            padding: 1.4rem;
        }

        /* Profile */
        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.8rem;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(18, 124, 194, 0.3);
        }

        .profile-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.15rem;
        }

        .profile-role {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .info-row {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f4f8;
        }

        .info-row:last-child { border-bottom: none; }

        .info-row .info-icon {
            width: 36px;
            height: 36px;
            min-width: 36px;
            border-radius: 10px;
            background: rgba(18, 124, 194, 0.08);
            color: var(--secondary-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.85rem;
        }

        .info-row .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 0;
        }

        .info-row .info-value {
            font-size: 0.92rem;
            color: var(--primary-color);
            font-weight: 500;
            margin: 0;
            word-break: break-word;
        }

        /* System info */
        .system-info-card {
            background: linear-gradient(135deg, rgba(18,124,194,0.04) 0%, rgba(2,24,46,0.02) 100%);
            border: 1px solid #e6edf5;
            border-radius: 14px;
            padding: 1.5rem 1.65rem;
        }

        .system-info-card h6 {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.6rem;
        }

        .system-info-card p {
            font-size: 16px;
            color: #000;
            margin: 0;
            line-height: 1.6;
        }

        .role-cta {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            border-radius: 14px;
            padding: 1.5rem;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            box-shadow: 0 6px 20px rgba(18, 124, 194, 0.25);
        }

        .role-cta .cta-title {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0.2rem;
        }

        .role-cta .cta-desc {
            font-size: 0.85rem;
            opacity: 0.85;
            margin: 0;
        }

        .role-cta .btn-cta {
            background: #fff;
            color: var(--primary-color);
            border: none;
            border-radius: 10px;
            padding: 0.65rem 1.25rem;
            font-weight: 600;
            font-size: 0.87rem;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .role-cta .btn-cta:hover {
            background: #f0f6fb;
            transform: translateY(-1px);
        }

        @media (max-width: 767.98px) {
            .dashboard-hero {
                padding: 1.75rem 1.5rem;
            }
            .hero-name {
                font-size: 1.6rem;
            }
            .role-cta {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>

    <div class="container-fluid py-4">
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @php
            $usuario = Auth::user();
            $rolUsuario = $usuario->roles->first();
            $iniciales = collect(explode(' ', $usuario->nombre))
                ->filter()
                ->map(fn($p) => strtoupper(mb_substr($p, 0, 1)))
                ->take(2)
                ->implode('');
        @endphp

        <div class="dashboard-hero">
            <div class="row align-items-center">
                <div class="col-lg-8 mb-2 mb-lg-0">
                    <div class="hero-greeting">Acceso al Sistema</div>
                    <h1 class="hero-name">Bienvenido(a), {{ $usuario->nombre }}</h1>
                </div>
            </div>
        </div>

        {{-- ACCESO RÁPIDO SEGÚN ROL --}}
        @if($rolUsuario && ($rolUsuario->titulo === 'Administrador' || $rolUsuario->titulo === 'Médico Triaje'))
            <div class="role-cta mb-4">
                <div>
                    <div class="cta-title"><i class="bi bi-stethoscope"></i> Acceso rápido a Consultas</div>
                    <p class="cta-desc">Gestione sus pacientes y campañas asignadas desde el módulo médico.</p>
                </div>
                {{-- <a href="{{ route('campanias.triaje') }}" class="btn btn-cta w-100">... --}}
                <a href="#" class="btn-cta">
                    <i class="bi bi-arrow-right-circle"></i> Ir al módulo
                </a>
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="card dash-card">
                    <div class="card-header">
                        <h5><i class="bi bi-person-badge"></i> Información Personal</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="profile-avatar me-3">{{ $iniciales ?: 'U' }}</div>
                            <div>
                                <div class="profile-name">{{ $usuario->nombre }}</div>
                                <div class="profile-role">{{ $rolUsuario->titulo ?? 'Sin rol asignado' }}</div>
                            </div>
                        </div>

                        <div class="info-row">
                            <span class="info-icon"><i class="bi bi-person"></i></span>
                            <div>
                                <p class="info-label">Usuario</p>
                                <p class="info-value"><code>{{ $usuario->user }}</code></p>
                            </div>
                        </div>

                        <div class="info-row">
                            <span class="info-icon"><i class="bi bi-envelope"></i></span>
                            <div>
                                <p class="info-label">Correo electrónico</p>
                                <p class="info-value">{{ $usuario->email }}</p>
                            </div>
                        </div>

                        <div class="info-row">
                            <span class="info-icon"><i class="bi bi-toggle-on"></i></span>
                            <div>
                                <p class="info-label">Estado de cuenta</p>
                                <p class="info-value mb-0">
                                    @if($usuario->status)
                                        <span class="badge bg-success">Activo</span>
                                    @else
                                        <span class="badge bg-danger">Inactivo</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card dash-card">
                    <div class="card-header">
                        <h5><i class="bi bi-shield-lock"></i> Información del Sistema</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="system-info-card">
                            <p style="text-align: justify">
                                Plataforma oficial para la gestión de campañas de la Dirección de Redes Integradas de Salud Lima Norte.
                                Use el menú lateral para acceder a los módulos habilitados según su rol. Para soporte técnico, contacte
                                a la Oficina de Gestión de Tecnologías de la Información (OGTI).
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection