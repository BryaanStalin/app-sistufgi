<div class="card mb-4">
    <div class="card-body">
        <h3 class="h6">Asignar usuarios</h3>
        <p class="text-muted">Consulte y asigne usuarios existentes. Para crear cuentas, utilice el módulo de Usuarios.</p>
        @unless($campania->cerrada)
            <form method="POST" action="{{ route('oferta-flexible.usuarios.store', $campania) }}" data-confirm-form="¿Confirmar la asignación de los usuarios seleccionados?">
                @csrf
                <label for="usuarios" id="usuarios-label" class="form-label">Usuarios activos con rol de campaña</label>
                <select id="usuarios" name="usuarios[]" class="form-select" multiple required data-select2 data-placeholder="Seleccione usuarios">
                    <optgroup label="Médico Triaje">
                        @foreach($medicos as $usuario)
                            @unless($campania->asignaciones->contains('usuario_id', $usuario->id))
                                <option value="{{ $usuario->id }}" @selected(in_array($usuario->id, old('usuarios', [])))>{{ $usuario->nombre }} ({{ $usuario->user }})</option>
                            @endunless
                        @endforeach
                    </optgroup>
                    <optgroup label="Admisión">
                        @foreach($admisiones as $usuario)
                            @unless($campania->asignaciones->contains('usuario_id', $usuario->id))
                                <option value="{{ $usuario->id }}" @selected(in_array($usuario->id, old('usuarios', [])))>{{ $usuario->nombre }} ({{ $usuario->user }})</option>
                            @endunless
                        @endforeach
                    </optgroup>
                </select>
                <button class="btn btn-primary mt-3">Asignar usuarios</button>
            </form>
        @endunless
        <div class="table-responsive mt-3">
            <table class="table table-striped">
                <thead><tr><th>Usuario</th><th>Tipo</th><th>Acción</th></tr></thead>
                <tbody>
                    @forelse($campania->asignaciones as $asignacion)
                        <tr>
                            <td>{{ $asignacion->usuario->nombre }}</td>
                            <td>{{ $asignacion->tipo === 'M' ? 'Médico Triaje' : 'Admisión' }}</td>
                            <td>
                                @unless($campania->cerrada)
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-delete-url="{{ route('oferta-flexible.usuarios.destroy', [$campania, $asignacion]) }}" data-confirm="¿Quitar la asignación de este usuario?">Quitar</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No hay usuarios asignados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
