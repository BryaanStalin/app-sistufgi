<div class="mb-3">
    <label for="{{ $prefijoPaciente }}-nombres" class="form-label">Nombres</label>
    <input
        id="{{ $prefijoPaciente }}-nombres"
        aria-describedby="{{ $prefijoPaciente }}-nombres-error"
        class="form-control"
        name="nombres"
        maxlength="150"
        autocomplete="given-name"
        required
    >
    <div class="invalid-feedback" id="{{ $prefijoPaciente }}-nombres-error">
        Ingrese los nombres del paciente.
    </div>
</div>
<div class="mb-3">
    <label for="{{ $prefijoPaciente }}-apellidos" class="form-label">Apellidos</label>
    <input
        id="{{ $prefijoPaciente }}-apellidos"
        aria-describedby="{{ $prefijoPaciente }}-apellidos-error"
        class="form-control"
        name="apellidos"
        maxlength="150"
        autocomplete="family-name"
        required
    >
    <div class="invalid-feedback" id="{{ $prefijoPaciente }}-apellidos-error">
        Ingrese los apellidos del paciente.
    </div>
</div>
<div class="mb-3">
    <label for="{{ $prefijoPaciente }}-nacimiento" class="form-label">Fecha de nacimiento</label>
    <input
        id="{{ $prefijoPaciente }}-nacimiento"
        aria-describedby="{{ $prefijoPaciente }}-nacimiento-error"
        class="form-control"
        name="fecha_nacimiento"
        type="date"
        max="{{ today('America/Lima')->toDateString() }}"
        required
    >
    <div class="invalid-feedback" id="{{ $prefijoPaciente }}-nacimiento-error">
        Ingrese una fecha de nacimiento válida.
    </div>
</div>
<div class="mb-3">
    <label for="{{ $prefijoPaciente }}-sexo" class="form-label">Sexo</label>
    <select
        id="{{ $prefijoPaciente }}-sexo"
        aria-describedby="{{ $prefijoPaciente }}-sexo-error"
        class="form-select"
        name="sexo"
        required
    >
        <option value="">Seleccione</option>
        <option value="F">Femenino</option>
        <option value="M">Masculino</option>
    </select>
    <div class="invalid-feedback" id="{{ $prefijoPaciente }}-sexo-error">
        Seleccione el sexo del paciente.
    </div>
</div>
@if ($incluirTelefono ?? true)
    <div class="mb-3">
        <label for="{{ $prefijoPaciente }}-telefono" class="form-label">Teléfono (opcional)</label>
        <input
            id="{{ $prefijoPaciente }}-telefono"
            aria-describedby="{{ $prefijoPaciente }}-telefono-error"
            class="form-control"
            name="telefono"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            maxlength="150"
        >
        <div class="invalid-feedback" id="{{ $prefijoPaciente }}-telefono-error">
            Revise el teléfono ingresado.
        </div>
    </div>
@endif
