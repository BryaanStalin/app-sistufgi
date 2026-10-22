<div class="row g-4">
    @foreach ([
        ['M', 'Médicos Triaje', $medicos, 'bi-heart-pulse'],
        ['A', 'Admisión', $admisiones, 'bi-person-vcard'],
    ] as [$tipoUsuario, $tituloRol, $usuariosRol, $iconoRol])
        @php
            $asignacionesRol = $campania->asignaciones->where('tipo', $tipoUsuario);
            $listaUsuarios = $usuariosRol->concat($asignacionesRol->pluck('usuario'))->unique('id');
        @endphp
        <div class="col-xl-6" data-user-panel>
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <h2 class="h6 mb-0">
                        <i class="bi {{ $iconoRol }} me-2" aria-hidden="true"></i>
                        {{ $tituloRol }}
                    </h2>
                    <span class="small text-body-secondary">
                        {{ $asignacionesRol->count() }}/{{ $listaUsuarios->count() }} asignados
                    </span>
                </div>
                <div class="card-body">
                    <form
                        method="POST"
                        action="{{ route('oferta-flexible.usuarios.store', $campania) }}"
                        data-confirm-form="¿Confirmar la asignación de los usuarios seleccionados?"
                        data-admin-submit
                        data-assign-form
                    >
                        @csrf
                        <label for="buscar-{{ $tipoUsuario }}" class="form-label">Buscar {{ $tituloRol }}
                        </label>
                        <input
                            type="search"
                            id="buscar-{{ $tipoUsuario }}"
                            class="form-control mb-3"
                            placeholder="Nombre o usuario"
                            data-user-search
                            autocomplete="off"
                        >
                        <div class="oferta-user-list">
                            @forelse ($listaUsuarios as $usuario)
                                @php
                                    $asignacion = $asignacionesRol->firstWhere('usuario_id', $usuario->id);
                                    $iniciales = collect(preg_split('/\s+/u', trim($usuario->nombre)))
                                        ->take(2)
                                        ->map(fn($parte) => mb_substr($parte, 0, 1))
                                        ->implode('');
                                @endphp
                                <div
                                    class="oferta-user"
                                    data-user-item
                                    data-search="{{ $usuario->nombre }} {{ $usuario->user }}"
                                >
                                    <input
                                        type="checkbox"
                                        name="usuarios[]"
                                        value="{{ $usuario->id }}"
                                        id="usuario-{{ $tipoUsuario }}-{{ $usuario->id }}"
                                        class="form-check-input"
                                        data-user-checkbox
                                        @checked($asignacion || in_array($usuario->id, old('usuarios', [])))
                                        @disabled($campania->cerrada || $asignacion)
                                    >
                                    <label
                                        class="oferta-user-label"
                                        for="usuario-{{ $tipoUsuario }}-{{ $usuario->id }}"
                                    >
                                        <span class="oferta-avatar" aria-hidden="true">{{ mb_strtoupper($iniciales) }}
                                        </span>
                                        <span class="oferta-user-name">
                                            <span class="fw-semibold d-block">{{ $usuario->nombre }}</span>
                                            <span class="small text-body-secondary">{{ $usuario->user }}
                                            </span>
                                        </span>
                                    </label>
                                    @if ($asignacion)
                                        @include('oferta_flexible.partials.badge', ['etiqueta' => 'Asignado'])
                                        @unless ($campania->cerrada)
                                            <button
                                                type="button"
                                                class="btn btn-danger oferta-icon-button"
                                                data-delete-url="{{ route('oferta-flexible.usuarios.destroy', [$campania, $asignacion]) }}"
                                                data-confirm="¿Quitar la asignación de este usuario?"
                                                data-bs-toggle="tooltip"
                                                title="Quitar asignación"
                                                aria-label="Quitar asignación"
                                            >
                                                <i class="bi bi-person-dash" aria-hidden="true"></i>
                                            </button>
                                        @endunless
                                    @endif
                                </div>
                            @empty
                                @include('oferta_flexible.partials.vacio', [
                                    'mensajeVacio' => 'No hay usuarios disponibles para este rol.',
                                    'iconoVacio' => 'bi-person',
                                ])
                            @endforelse
                        </div>
                        <p class="small text-body-secondary mt-3" data-user-empty hidden>
                            No se encontraron usuarios con esa búsqueda.
                        </p>
                        @unless ($campania->cerrada)
                            <div
                                class="border-top mt-3 pt-3 d-flex flex-wrap
                                    justify-content-between align-items-center gap-2"
                            >
                                <span class="small text-body-secondary" data-user-selected>0 seleccionados
                                </span>
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    data-confirm-assignment
                                    disabled
                                >
                                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                                    Confirmar asignación
                                </button>
                            </div>
                        @endunless
                    </form>
                </div>
            </div>
        </div>
    @endforeach
</div>
