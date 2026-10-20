@php
    $isEditing = isset($usuario);
    $selectedRole = old('role', $isEditing ? $usuario->roles->first()?->id : '');
    $selectedEess = old('eess', $isEditing ? $usuario->establecimientos->first()?->RENIPRESS : '');
@endphp

<p class="text-body-secondary small mb-4">Los campos marcados con <span aria-hidden="true">*</span> son obligatorios.</p>

<fieldset class="border rounded-3 p-3 p-lg-4 mb-4">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold">Datos del usuario</legend>
    <div class="row g-3">
        <div class="col-md-8">
            <label for="nombre" class="form-label">Nombre completo *</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $usuario->nombre ?? '') }}" class="form-control @error('nombre') is-invalid @enderror" maxlength="255" autocomplete="name" aria-describedby="nombre-error" required>
            @error('nombre') <div id="nombre-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label for="DNI" class="form-label">DNI {{ $isEditing ? '(opcional)' : '*' }}</label>
            <input type="text" name="DNI" id="DNI" value="{{ old('DNI', $usuario->DNI ?? '') }}" class="form-control @error('DNI') is-invalid @enderror" maxlength="12" inputmode="numeric" aria-describedby="DNI-error" @required(! $isEditing)>
            @error('DNI') <div id="DNI-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="user" class="form-label">Nombre de usuario *</label>
            <input type="text" name="user" id="user" value="{{ old('user', $usuario->user ?? '') }}" class="form-control @error('user') is-invalid @enderror" maxlength="120" autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="user-error" required>
            @error('user') <div id="user-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Correo electrónico *</label>
            <input type="email" name="email" id="email" value="{{ old('email', $usuario->email ?? '') }}" class="form-control @error('email') is-invalid @enderror" maxlength="255" autocomplete="email" aria-describedby="email-error" required>
            @error('email') <div id="email-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="border rounded-3 p-3 p-lg-4 mb-4">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold">Acceso institucional</legend>
    <div class="row g-3">
        <div class="col-md-8">
            <label for="role" id="role-label" class="form-label">Rol asignado *</label>
            <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" data-select2 data-placeholder="Seleccione un rol" aria-describedby="role-help role-error" required>
                <option value=""></option>
                @foreach($roles as $rol)
                    <option value="{{ $rol->id }}" @selected((string) $selectedRole === (string) $rol->id)>{{ $rol->titulo }}</option>
                @endforeach
            </select>
            @error('role') <div id="role-error" class="invalid-feedback">{{ $message }}</div> @enderror
            <div id="role-help" class="form-text">Cada usuario tiene un único rol.</div>
        </div>
        <div class="col-md-4">
            <label for="status" class="form-label">Estado *</label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" aria-describedby="status-error" required>
                <option value="1" @selected((string) old('status', $usuario->status ?? 1) === '1')>Activo</option>
                <option value="0" @selected((string) old('status', $usuario->status ?? 1) === '0')>Inactivo</option>
            </select>
            @error('status') <div id="status-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label for="eess" id="eess-label" class="form-label">Establecimiento de salud (opcional)</label>
            <select name="eess" id="eess" class="form-select @error('eess') is-invalid @enderror" data-select2 data-placeholder="Seleccione un establecimiento" aria-describedby="eess-error">
                <option value=""></option>
                @foreach($eess as $establishment)
                    <option value="{{ $establishment->RENIPRESS }}" @selected((string) $selectedEess === (string) $establishment->RENIPRESS)>{{ $establishment->NOM_EESS }}</option>
                @endforeach
            </select>
            @error('eess') <div id="eess-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="border rounded-3 p-3 p-lg-4">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold">{{ $isEditing ? 'Cambiar contraseña (opcional)' : 'Contraseña de acceso' }}</legend>
    <p id="password-help" class="text-body-secondary small">Use al menos 8 caracteres, una mayúscula, una minúscula y un número. @if($isEditing) Deje ambos campos vacíos para conservar la contraseña actual. @endif</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="password" class="form-label">{{ $isEditing ? 'Nueva contraseña' : 'Contraseña *' }}</label>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" aria-describedby="password-help password-error" @required(! $isEditing)>
            @error('password') <div id="password-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="password_confirmation" class="form-label">Confirmar contraseña {{ $isEditing ? '' : '*' }}</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" autocomplete="new-password" aria-describedby="password_confirmation-error" @required(! $isEditing)>
            @error('password_confirmation') <div id="password_confirmation-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>
