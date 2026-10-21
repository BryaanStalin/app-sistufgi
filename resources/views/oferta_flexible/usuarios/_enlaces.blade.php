<div class="card">
    <div class="card-body">
        <h3 class="h6">Enlazar usuarios</h3>
        @if(!$campania->asociar_triaje_admision)
            <div class="alert alert-info">Esta campaña no tiene habilitada la asociación de Triaje y Admisión.</div>
        @elseif(!$campania->cerrada && $campania->estado === 'Activa')
            <form class="row g-3" method="POST" action="{{ route('oferta-flexible.enlaces.store', $campania) }}" data-confirm-form="¿Enlazar estos usuarios?">
                @csrf
                <div class="col-md-5">
                    <label for="medico_usuario_id" class="form-label">Médico asignado</label>
                    <select id="medico_usuario_id" name="medico_usuario_id" class="form-select" required>
                        <option value="">Seleccione</option>
                        @foreach($medicos as $usuario)
                            @if($campania->asignaciones->where('tipo', 'M')->contains('usuario_id', $usuario->id) && !$campania->enlaces->contains('medico_usuario_id', $usuario->id))
                                <option value="{{ $usuario->id }}">{{ $usuario->nombre }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="admision_usuario_id" class="form-label">Admisión asignada</label>
                    <select id="admision_usuario_id" name="admision_usuario_id" class="form-select" required>
                        <option value="">Seleccione</option>
                        @foreach($admisiones as $usuario)
                            @if($campania->asignaciones->where('tipo', 'A')->contains('usuario_id', $usuario->id))
                                <option value="{{ $usuario->id }}">{{ $usuario->nombre }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary">ENLAZAR</button></div>
            </form>
        @elseif(!$campania->cerrada)
            <div class="alert alert-info">Para crear enlaces, seleccione una campaña Activa. Puede usar el filtro «Solo campañas activas».</div>
        @endif

        <div class="table-responsive mt-3">
            <table class="table table-striped">
                <thead><tr><th>Médico Triaje</th><th>Admisión</th><th>Acción</th></tr></thead>
                <tbody>
                    @forelse($campania->enlaces as $enlace)
                        <tr>
                            <td>{{ $enlace->medico->nombre }}</td>
                            <td>{{ $enlace->admision->nombre }}</td>
                            <td>
                                @unless($campania->cerrada)
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-delete-url="{{ route('oferta-flexible.enlaces.destroy', [$campania, $enlace]) }}" data-confirm="¿Eliminar este enlace?">Eliminar</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No hay enlaces registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
