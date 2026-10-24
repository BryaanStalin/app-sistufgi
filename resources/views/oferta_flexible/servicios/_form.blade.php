<div class="card">
    <div class="card-header">
        <h2 class="h6 mb-0">Información del servicio UPSS</h2>
    </div>
    <div class="card-body">
        <p class="text-body-secondary small">
            Complete los datos del servicio. La hora límite de laboratorio es opcional.
        </p>
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
            <div class="col-12" data-laboratorio-fields hidden>
                <div class="row g-3 border-top pt-3">
                    <div class="col-md-6">
                        <label for="HORA_LIMITE" class="form-label">Hora límite (opcional)</label>
                        <input
                            type="time"
                            id="HORA_LIMITE"
                            name="HORA_LIMITE"
                            class="form-control @error('HORA_LIMITE') is-invalid @enderror"
                            value="{{ old('HORA_LIMITE', substr($servicio->HORA_LIMITE ?? '', 0, 5)) }}"
                        >
                        <p class="form-text">Hora del servidor en America/Lima.</p>
                        @error('HORA_LIMITE')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <input type="hidden" name="ENVIA_EESS" value="0">
                        <div class="form-check mt-md-4">
                            <input
                                type="checkbox"
                                id="ENVIA_EESS"
                                name="ENVIA_EESS"
                                value="1"
                                class="form-check-input @error('ENVIA_EESS') is-invalid @enderror"
                                @checked(old('ENVIA_EESS', $servicio->ENVIA_EESS ?? true))
                            >
                            <label for="ENVIA_EESS" class="form-check-label">
                                Se envía a un EESS para su procesamiento
                            </label>
                            @error('ENVIA_EESS')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
