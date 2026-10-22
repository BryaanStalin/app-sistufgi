<div class="card">
    <div class="card-header">
        <h2 class="h6 mb-0">Información del servicio UPSS</h2>
    </div>
    <div class="card-body">
        <p class="text-body-secondary small">Todos los campos son obligatorios.</p>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="ID_UPS" class="form-label">Código UPSS</label>
                <input
                    type="number"
                    id="ID_UPS"
                    name="ID_UPS"
                    class="form-control @error('ID_UPS') is-invalid @enderror"
                    min="1"
                    max="2147483647"
                    step="1"
                    required
                    value="{{ old('ID_UPS', $servicio->ID_UPS ?? '') }}"
                >
                @error('ID_UPS')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-9">
                <label for="DESCRIPCION_UPS" class="form-label">Descripción</label>
                <input
                    id="DESCRIPCION_UPS"
                    name="DESCRIPCION_UPS"
                    class="form-control @error('DESCRIPCION_UPS') is-invalid @enderror"
                    maxlength="255"
                    required
                    value="{{ old('DESCRIPCION_UPS', $servicio->DESCRIPCION_UPS ?? '') }}"
                >
                @error('DESCRIPCION_UPS')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="TIPO_UPS" class="form-label">Tipo de servicio</label>
                <select
                    id="TIPO_UPS"
                    name="TIPO_UPS"
                    class="form-select @error('TIPO_UPS') is-invalid @enderror"
                    required
                >
                    <option value="">Seleccione</option>
                    @foreach (\App\Models\ServicioUps::TIPOS as $value => $label)
                        <option
                            value="{{ $value }}"
                            @selected(old('TIPO_UPS', $servicio->TIPO_UPS ?? '') === $value)
                        >
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                @error('TIPO_UPS')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="ACTIVO" class="form-label">Estado</label>
                <select
                    id="ACTIVO"
                    name="ACTIVO"
                    class="form-select @error('ACTIVO') is-invalid @enderror"
                    required
                >
                    <option
                        value="1"
                        @selected(
                            (string) old(
                                'ACTIVO',
                                isset($servicio) ? (int) $servicio->ACTIVO : 1,
                            ) === '1'
                        )
                    >
                        Activo
                    </option>
                    <option
                        value="0"
                        @selected(
                            (string) old(
                                'ACTIVO',
                                isset($servicio) ? (int) $servicio->ACTIVO : 1,
                            ) === '0'
                        )
                    >
                        Inactivo
                    </option>
                </select>
                @error('ACTIVO')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

    </div>
</div>
