<nav class="navbar navbar-expand-lg navbar-light navbar-top">
    <div class="container-fluid">
        <div class="d-flex align-items-center gap-2">
            <button id="sidebarToggle"
                    class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center"
                    type="button"
                    aria-label="Ocultar o mostrar menú"
                    aria-expanded="true">
                <i class="bi bi-list"></i>
            </button>

            <span class="navbar-brand mb-0 h1">
                <a class="nav-link link-dark" href="{{ route('dashboard') }}">
                    Gestión de Campañas DIRIS LIMA NORTE - Sistema Integrado UFGI
                </a>
            </span>
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center gap-3">
                <li class="nav-item">
                    <small class="text-muted">
                            <i class="bi bi-calendar3"></i>
                            <span id="current-date"></span>
                    </small>
                </li>

                @php
                    $usuario = Auth::user();
                    $rolPrincipal = $usuario ? $usuario->getRolPrincipal() : null;
                @endphp

                <li class="nav-item">
                    <small class="text-muted d-flex align-items-center gap-2">
                        <i class="bi bi-person"></i>
                        {{ $usuario?->nombre ?? 'Usuario' }} - {{ $rolPrincipal?->titulo ?? 'Invitado' }}
                    </small>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#" id="btnLogout" 
                        data-url="{{ route('logout') }}"
                        role="button" aria-label="Cerrar sesión" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right text-danger text-bold"></i>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>