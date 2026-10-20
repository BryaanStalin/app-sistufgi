<p class="text-body-secondary small mb-4">Los campos marcados con <span aria-hidden="true">*</span> son obligatorios.</p>

<fieldset class="border rounded-3 p-3 p-lg-4 mb-4">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold">Información del menú</legend>
    <div class="row g-3">
        <div class="col-md-6">
            <label for="titulo" class="form-label">Título *</label>
            <input type="text" name="titulo" id="titulo" value="{{ old('titulo', $menu->titulo ?? '') }}" class="form-control @error('titulo') is-invalid @enderror" maxlength="255" aria-describedby="titulo-error" required>
            @error('titulo') <div id="titulo-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="link" class="form-label">Ruta *</label>
            <input type="text" name="link" id="link" value="{{ old('link', $menu->link ?? '') }}" class="form-control @error('link') is-invalid @enderror" maxlength="255" autocomplete="off" spellcheck="false" aria-describedby="link-help link-error" required>
            @error('link') <div id="link-error" class="invalid-feedback">{{ $message }}</div> @enderror
            <div id="link-help" class="form-text">Ingrese la ruta que corresponde a esta opción del sistema.</div>
        </div>
        <div class="col-12">
            <label for="descripcion" class="form-label">Descripción (opcional)</label>
            <textarea name="descripcion" id="descripcion" rows="3" maxlength="1000" class="form-control @error('descripcion') is-invalid @enderror" aria-describedby="descripcion-error">{{ old('descripcion', $menu->descripcion ?? '') }}</textarea>
            @error('descripcion') <div id="descripcion-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

<fieldset class="border rounded-3 p-3 p-lg-4">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold">Ubicación y disponibilidad</legend>
    <div class="row g-3">
        <div class="col-lg-6">
            <label for="padre_id" id="padre_id-label" class="form-label">Menú padre (opcional)</label>
            <select name="padre_id" id="padre_id" class="form-select @error('padre_id') is-invalid @enderror" data-select2 data-placeholder="Sin menú padre" aria-describedby="padre_id-help padre_id-error">
                <option value=""></option>
                @foreach($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((string) old('padre_id', $menu->padre_id ?? '') === (string) $parent->id)>{{ $parent->titulo }}</option>
                @endforeach
            </select>
            @error('padre_id') <div id="padre_id-error" class="invalid-feedback">{{ $message }}</div> @enderror
            <div id="padre_id-help" class="form-text">Sin padre, el menú se mostrará en el primer nivel.</div>
        </div>
        <div class="col-md-6 col-lg-3">
            <label for="orden" class="form-label">Orden *</label>
            <input type="number" name="orden" id="orden" value="{{ old('orden', $menu->orden ?? 0) }}" min="0" step="1" class="form-control @error('orden') is-invalid @enderror" aria-describedby="orden-error" required>
            @error('orden') <div id="orden-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 col-lg-3">
            <label for="status" class="form-label">Estado *</label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" aria-describedby="status-error" required>
                <option value="1" @selected((string) old('status', $menu->status ?? 1) === '1')>Activo</option>
                <option value="0" @selected((string) old('status', $menu->status ?? 1) === '0')>Inactivo</option>
            </select>
            @error('status') <div id="status-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>
