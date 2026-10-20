<aside class="sidebar d-flex flex-column">
    <div class="text-center py-2">
        <div class="bg-white p-1 rounded-2 shadow-sm d-inline-block" style="max-width: 75%;">
            <img src="{{ asset('img/logo.png') }}" alt="Logo" class="img-fluid" style="max-height: 150px;">
        </div>
    </div>
    <nav class="nav flex-column flex-grow-1 mt-2" id="sidebar-nav">
        @php
            $usuario = Auth::user();
            $menus = $usuario ? $usuario->getMenus() : collect();
        @endphp

        @if($menus->count() > 0)
            @foreach($menus as $i => $menu)
                @if($menu->hijos->count() > 0)
                    @php
                        // Detectar si algún hijo o sub-hijo está activo
                        $tieneHijoActivo = $menu->hijos->contains(function($hijo) {
                            if (request()->is(ltrim($hijo->link, '/'))) {
                                return true;
                            }
                            // Verificar si algún sub-hijo está activo
                            foreach ($hijo->hijos as $subhijo) {
                                if (request()->is(ltrim($subhijo->link, '/'))) {
                                    return true;
                                }
                            }
                            return false;
                        });
                    @endphp

                    <div class="nav-group mt-3">
                        {{-- Cabecera del grupo principal (nivel 1) --}}
                        <button
                            class="nav-group-toggle w-100 text-start border-0 bg-transparent px-3 pb-1 d-flex align-items-center justify-content-between"
                            data-bs-toggle="collapse"
                            data-bs-target="#menu-{{ $i }}"
                            data-menu-id="{{ $menu->id }}"
                            data-menu-titulo="{{ $menu->titulo }}"
                            aria-expanded="{{ $tieneHijoActivo ? 'true' : 'false' }}"
                            aria-controls="menu-{{ $i }}">
                            <small class="text-uppercase text-secondary text-white">
                                @if($menu->icono)
                                    <i class="{{ $menu->icono }} me-1"></i>
                                @endif
                                {{ $menu->titulo }}
                            </small>
                            <i class="bi bi-chevron-down chevron-icon" style="font-size: 0.7rem; transition: transform 0.25s;"></i>
                        </button>

                        {{-- Hijos colapsables (nivel 2) --}}
                        <div class="collapse {{ $tieneHijoActivo ? 'show' : '' }}" id="menu-{{ $i }}">
                            @foreach($menu->hijos as $submenu)
                                @if($submenu->hijos->count() > 0)
                                    {{-- Sub-menú con hijos (nivel 2) --}}
                                    @php
                                        $tieneSubHijoActivo = $submenu->hijos->contains(fn($h) => 
                                            request()->is(ltrim($h->link, '/'))
                                        );
                                    @endphp
                                    
                                    <div class="nav-group ms-2 mt-2">
                                        {{-- Sub-cabecera (nivel 2) --}}
                                        <button
                                            class="nav-group-toggle w-100 text-start border-0 bg-transparent px-3 pb-1 d-flex align-items-center justify-content-between"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#submenu-{{ $loop->parent->index }}-{{ $loop->index }}"
                                            aria-expanded="{{ $tieneSubHijoActivo ? 'true' : 'false' }}"
                                            aria-controls="submenu-{{ $loop->parent->index }}-{{ $loop->index }}">
                                            <small class="text-uppercase text-secondary text-white">
                                                @if($submenu->icono)
                                                    <i class="{{ $submenu->icono }} me-1"></i>
                                                @endif
                                                {{ $submenu->titulo }}
                                            </small>
                                            <i class="bi bi-chevron-right chevron-icon" style="font-size: 0.7rem; transition: transform 0.25s;"></i>
                                        </button>

                                        {{-- Sub-hijos colapsables (nivel 3) --}}
                                        <div class="collapse {{ $tieneSubHijoActivo ? 'show' : '' }}" id="submenu-{{ $loop->parent->index }}-{{ $loop->index }}">
                                            @foreach($submenu->hijos as $subhijo)
                                                <a class="nav-link {{ request()->is(ltrim($subhijo->link, '/')) ? 'active' : '' }}"
                                                   href="{{ $subhijo->link ? url($subhijo->link) : '#' }}"
                                                   @if(!$subhijo->link) onclick="return false;" @endif
                                                   style="padding-left: 3rem;">
                                                    @if($subhijo->icono)
                                                        <i class="{{ $subhijo->icono }}"></i>
                                                    @else
                                                        <i class="bi bi-dash"></i>
                                                    @endif
                                                    {{ $subhijo->titulo }}
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    {{-- Item sin hijos (nivel 2 directo) --}}
                                    <a class="nav-link {{ request()->is(ltrim($submenu->link, '/')) ? 'active' : '' }}"
                                        href="{{ $submenu->link ? url($submenu->link) : '#' }}"
                                        data-menu-id="{{ $submenu->id }}"
                                        data-menu-titulo="{{ $submenu->titulo }}"
                                       @if(!$submenu->link) onclick="return false;" @endif
                                       style="padding-left: 2rem;">
                                        @if($submenu->icono)
                                            <i class="{{ $submenu->icono }}"></i>
                                        @else
                                            <i class="bi bi-dash"></i>
                                        @endif
                                        {{ $submenu->titulo }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>

                @else
                    {{-- Item sin hijos (nivel 1 directo) --}}
                    <a class="nav-link {{ request()->is(ltrim($menu->link, '/').'*') ? 'active' : '' }}"
                        href="{{ $menu->link ? url($menu->link) : '#' }}"
                        data-menu-id="{{ $menu->id }}"
                        data-menu-titulo="{{ $menu->titulo }}"
                       @if(!$menu->link) onclick="return false;" @endif>
                        @if($menu->icono)
                            <i class="{{ $menu->icono }}"></i>
                        @else
                            <i class="bi bi-box"></i>
                        @endif
                        {{ $menu->titulo }}
                    </a>
                @endif
            @endforeach
        @else
            <div class="alert alert-info text-dark mx-3 mt-3 mb-0">
                <small>No tienes menús disponibles</small>
            </div>
        @endif
    </nav>
    <div class="p-3 border-top border-light mt-auto">
        <small class="d-block text-secondary mb-2 text-center text-nowrap text-white">
            <i class="bi bi-person"></i> UFGI - OGTI - DIRIS LN - {{ date('Y') }}
        </small>
    </div>
</aside>