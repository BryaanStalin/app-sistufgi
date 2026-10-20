@php
    $selectedMenus = session()->hasOldInput()
        ? old('menus', [])
        : (isset($rol) ? $rol->menus->pluck('id')->all() : []);
@endphp

<p class="text-body-secondary small mb-4">Los campos marcados con <span aria-hidden="true">*</span> son obligatorios.</p>

<fieldset class="border rounded-3 p-3 p-lg-4 mb-4">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold">Información del rol</legend>
    <div class="row g-3">
        <div class="col-md-8">
            <label for="titulo" class="form-label">Nombre del rol *</label>
            <input type="text" name="titulo" id="titulo" value="{{ old('titulo', $rol->titulo ?? '') }}" class="form-control @error('titulo') is-invalid @enderror" maxlength="255" aria-describedby="titulo-error" required>
            @error('titulo') <div id="titulo-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label for="status" class="form-label">Estado *</label>
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" aria-describedby="status-error" required>
                <option value="1" @selected((string) old('status', $rol->status ?? 1) === '1')>Activo</option>
                <option value="0" @selected((string) old('status', $rol->status ?? 1) === '0')>Inactivo</option>
            </select>
            @error('status') <div id="status-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label for="descripcion" class="form-label">Descripción (opcional)</label>
            <textarea name="descripcion" id="descripcion" rows="3" maxlength="1000" class="form-control @error('descripcion') is-invalid @enderror" aria-describedby="descripcion-error">{{ old('descripcion', $rol->descripcion ?? '') }}</textarea>
            @error('descripcion') <div id="descripcion-error" class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</fieldset>

@php
    $selectedMenus = old('menus', isset($rol) ? $rol->menus->pluck('id')->toArray() : []);
@endphp

<fieldset class="border rounded-3 p-3 p-lg-4">
    <legend class="float-none w-auto px-2 fs-6 fw-semibold">
        Menús asignados
    </legend>

    <div class="border rounded p-3 bg-body" style="min-height: 200px; max-height: 450px; overflow-y: auto;" id="menu-tree">
        @foreach($menus as $menu)
            {{-- Nivel 1 (Padre Principal) --}}
            <div class="menu-item mb-2">
                <div class="form-check">
                    <input class="form-check-input parent-checkbox" 
                           type="checkbox" 
                           name="menus[]" 
                           id="menu_{{ $menu->id }}" 
                           value="{{ $menu->id }}"
                           @checked(in_array($menu->id, (array)$selectedMenus))>
                    <label class="form-check-label fw-bold" for="menu_{{ $menu->id }}">
                        @if($menu->icono)
                            <i class="{{ $menu->icono }} me-1 text-primary"></i>
                        @else
                            <i class="bi bi-folder me-1 text-primary"></i>
                        @endif
                        {{ $menu->titulo }}
                    </label>
                </div>

                {{-- Nivel 2 (Hijos) --}}
                @if($menu->hijos->count() > 0)
                    <div class="ms-4 mt-2 border-start ps-3">
                        @foreach($menu->hijos as $child)
                            <div class="menu-item mb-2">
                                <div class="form-check">
                                    <input class="form-check-input child-checkbox" 
                                           type="checkbox" 
                                           name="menus[]" 
                                           id="menu_{{ $child->id }}" 
                                           value="{{ $child->id }}"
                                           @checked(in_array($child->id, (array)$selectedMenus))>
                                    <label class="form-check-label fw-medium" for="menu_{{ $child->id }}">
                                        @if($child->icono)
                                            <i class="{{ $child->icono }} me-1"></i>
                                        @else
                                            <i class="bi bi-dash me-1 text-muted"></i>
                                        @endif
                                        {{ $child->titulo }}
                                    </label>
                                </div>

                                {{-- Nivel 3 (Sub-hijos) --}}
                                @if($child->hijos->count() > 0)
                                    <div class="ms-4 mt-1 border-start ps-3">
                                        @foreach($child->hijos as $subChild)
                                            <div class="form-check mb-1">
                                                <input class="form-check-input subchild-checkbox" 
                                                       type="checkbox" 
                                                       name="menus[]" 
                                                       id="menu_{{ $subChild->id }}" 
                                                       value="{{ $subChild->id }}"
                                                       @checked(in_array($subChild->id, (array)$selectedMenus))>
                                                <label class="form-check-label text-secondary small" for="menu_{{ $subChild->id }}">
                                                    @if($subChild->icono)
                                                        <i class="{{ $subChild->icono }} me-1"></i>
                                                    @else
                                                        <i class="bi bi-dot me-1"></i>
                                                    @endif
                                                    {{ $subChild->titulo }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Errores de validación --}}
    @if($errors->has('menus') || $errors->has('menus.*'))
        <div class="text-danger small mt-2 d-block">
            <i class="bi bi-exclamation-circle me-1"></i>
            {{ $errors->first('menus') ?: $errors->first('menus.*') }}
        </div>
    @endif

    {{-- Texto de ayuda --}}
    <div id="menus-help" class="form-text mt-2">
        <i class="bi bi-info-circle me-1"></i>
        Selecciona los menús a los que tendrá acceso este rol.
    </div>
</fieldset>
