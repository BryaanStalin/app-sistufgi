<nav class="navbar navbar-expand-xl navbar-light navbar-top">
    <div class="container-fluid">
        <div class="navbar-heading d-flex align-items-center gap-2">
            <button id="sidebarToggle"
                    class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center"
                    type="button"
                    aria-label="Ocultar o mostrar menú de navegación"
                    aria-controls="sidebar"
                    title="Menú de navegación"
                    aria-expanded="true">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            <span class="navbar-brand mb-0 h1">
                <a class="nav-link link-dark" href="{{ route('dashboard') }}">
                    <span class="navbar-title-full">Gestión de Campañas DIRIS LIMA NORTE - Sistema Integrado UFGI</span>
                    <span class="navbar-title-compact">Sistema Integrado UFGI-DLN</span>
                </a>
            </span>
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Mostrar u ocultar información de cuenta"
                title="Información de cuenta">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <span class="navbar-account-label">Cuenta</span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item navbar-clock">
                    <small class="text-muted">
                            <i class="bi bi-calendar3"></i>
                            <span id="current-date"></span>
                    </small>
                </li>

                @php
                    $usuario = Auth::user();
                    $rolPrincipal = $usuario ? $usuario->getRolPrincipal() : null;
                @endphp

                <li class="nav-item navbar-user">
                    <small class="text-muted d-flex align-items-center gap-2">
                        <i class="bi bi-person"></i>
                        <span class="navbar-user-text">{{ $usuario?->nombre ?? 'Usuario' }} - {{ $rolPrincipal?->titulo ?? 'Invitado' }}</span>
                    </small>
                </li>

                <li class="nav-item navbar-session">
                    <a class="nav-link" href="#" id="btnLogout" 
                        data-url="{{ route('logout') }}"
                        role="button" aria-label="Cerrar sesión" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right text-danger text-bold" aria-hidden="true"></i>
                        <span class="navbar-logout-label">Cerrar sesión</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>