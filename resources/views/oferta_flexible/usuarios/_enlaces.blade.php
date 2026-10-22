<div class="card">
    <div class="card-header">
        <h2 class="h6 mb-0">Enlazar usuarios</h2>
    </div>
    <div class="card-body">
        @if (!$campania->asociar_triaje_admision)
            <div class="alert alert-info">Esta campaña no tiene habilitada la asociación de Triaje y Admisión.
            </div>
        @elseif ($puedeEnlazar)
            <form
                class="row g-3 align-items-end"
                data-admin-submit
                method="POST"
                action="{{ route('oferta-flexible.enlaces.store', $campania) }}"
                data-confirm-form="¿Enlazar estos usuarios?"
            >
                @csrf
                <div class="col-md-5">
                    <label for="medico_usuario_id" id="medico_usuario_id-label" class="form-label">Médico asignado
                    </label>
                    <select
                        id="medico_usuario_id"
                        data-admin-select
                        name="medico_usuario_id"
                        class="form-select"
                        required
                    >
                        <option value="">Seleccione</option>
                        @foreach ($medicos as $usuario)
                            @if (
                                $campania->asignaciones->where('tipo', 'M')->contains('usuario_id', $usuario->id)
                                && !$campania->enlaces->contains('medico_usuario_id', $usuario->id)
                            )
                                <option value="{{ $usuario->id }}">{{ $usuario->nombre }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label
                        for="admision_usuario_id"
                        id="admision_usuario_id-label"
                        class="form-label"
                    >
                        Admisión asignada
                    </label>
                    <select
                        id="admision_usuario_id"
                        data-admin-select
                        name="admision_usuario_id"
                        class="form-select"
                        required
                    >
                        <option value="">Seleccione</option>
                        @foreach ($admisiones as $usuario)
                            @if ($campania->asignaciones->where('tipo', 'A')->contains('usuario_id', $usuario->id))
                                <option value="{{ $usuario->id }}">{{ $usuario->nombre }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i>
                        ENLAZAR
                    </button>
                </div>
            </form>
        @elseif (!$campania->cerrada)
            <div class="alert alert-info">
                Para crear enlaces, la campaña debe estar Activa y tener al menos un médico y un usuario de Admisión asignados.
            </div>
        @endif

        <div class="table-responsive mt-3">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Médico Triaje</th>
                        <th>Admisión</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campania->enlaces as $enlace)
                        <tr>
                            <td>{{ $enlace->medico->nombre }}</td>
                            <td>{{ $enlace->admision->nombre }}</td>
                            <td class="text-center">
                                @unless ($campania->cerrada)
                                    <button
                                        type="button"
                                        class="btn btn-danger oferta-icon-button"
                                        data-bs-toggle="tooltip"
                                        title="Eliminar enlace"
                                        aria-label="Eliminar enlace"
                                        data-delete-url="{{ route('oferta-flexible.enlaces.destroy', [$campania, $enlace]) }}"
                                        data-confirm="¿Eliminar este enlace?"
                                    >
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                @include('oferta_flexible.partials.vacio', [
                                    'mensajeVacio' => 'No hay enlaces registrados.',
                                    'ayudaVacio' => 'Seleccione un médico y un usuario de Admisión para enlazarlos.',
                                    'iconoVacio' => 'bi-link-45deg',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
