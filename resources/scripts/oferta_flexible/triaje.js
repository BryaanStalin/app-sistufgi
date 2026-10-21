import '../../css/oferta_flexible/triaje.css';

function initializeTriaje() {
    const root = document.getElementById('triaje');
    const searchForm = document.getElementById('triaje-buscar');
    if (!root || !searchForm) return;
    const get = (id) => document.getElementById(`triaje-${id}`);
    const campaign = get('campania');
    const type = get('tipo');
    const number = get('numero');
    const registerForm = get('registrar');
    const select = get('servicio');
    const add = get('agregar');
    const save = get('grabar');
    let patient = null;
    let services = [];
    let selected = new Set();
    let original = new Set();
    let busy = false;
    let available = false;
    let dirty = false;
    let searchedDocument = null;

    function message(text, kind = 'info') {
        const element = get('mensaje');
        element.textContent = text;
        element.className = `alert alert-${kind}`;
        element.hidden = !text;
    }

    function updateButtons() {
        save.disabled = busy || !available || !patient || selected.size === 0;
        add.disabled = busy || !available || !select.value || select.selectedOptions[0]?.disabled;
        get('refrescar').disabled = busy || !available;
        searchForm.querySelector('button').disabled = busy || !available || Boolean(patient);
        registerForm.querySelector('button').disabled = busy || !available;
        for (const control of [select, campaign]) if (control) control.disabled = busy;
        type.disabled = busy || Boolean(patient);
        number.disabled = busy || Boolean(patient);
        get('otro').disabled = busy;
        get('lista').querySelectorAll('button').forEach((button) => { button.disabled = busy; });
        root.setAttribute('aria-busy', String(busy));
    }

    async function request(accion, data = {}) {
        busy = true;
        updateButtons();
        try {
            return await window.$.ajax({
                url: root.dataset.endpoint, method: 'POST', dataType: 'json', contentType: 'application/json',
                headers: { Accept: 'application/json' },
                data: JSON.stringify({ accion, campania_id: Number(campaign?.value || root.dataset.campania), ...data }),
            });
        } catch (error) {
            if ([401, 403, 404, 419].includes(error.status) || error.responseJSON?.errors?.campania_id) available = false;
            const errors = error.responseJSON?.errors;
            const text = errors ? Object.values(errors).flat().join(' ') : (error.status === 419
                ? 'La sesión expiró. Recargue la página antes de continuar.' : 'No se pudo completar la operación. Intente nuevamente.');
            message(text, 'danger');
            get('mensaje').focus();
            throw error;
        } finally {
            busy = false;
            updateButtons();
        }
    }

    function renderServices() {
        const previous = select.value;
        select.replaceChildren(new Option('Seleccione un servicio', ''));
        for (const service of services) {
            if (selected.has(service.id)) continue;
            const own = original.has(service.id);
            const option = new Option(`${service.nombre} · ${service.disponibles} cupos libres${own ? ' · reservado en esta hoja' : ''}`, String(service.id));
            option.disabled = service.disponibles < 1 && !own;
            select.append(option);
        }
        if ([...select.options].some((option) => option.value === previous && !option.disabled)) select.value = previous;
        const list = get('lista');
        list.replaceChildren();
        for (const id of selected) {
            const service = services.find((row) => row.id === id);
            const card = document.createElement('li');
            card.className = 'triaje-servicio-card border rounded p-3';
            const content = document.createElement('div');
            const name = document.createElement('strong');
            name.className = 'd-block'; name.textContent = service?.nombre || 'Servicio ya no disponible';
            const detail = document.createElement('small');
            detail.className = service ? 'text-muted' : 'text-danger';
            detail.textContent = service?.tipo || 'Quite este servicio y vuelva a seleccionar.';
            content.append(name, detail);
            const remove = document.createElement('button');
            remove.type = 'button'; remove.className = 'btn btn-outline-danger'; remove.textContent = 'Quitar';
            remove.setAttribute('aria-label', `Quitar ${service?.nombre || 'servicio'}`);
            remove.addEventListener('click', () => { selected.delete(id); dirty = true; renderServices(); });
            card.append(content, remove); list.append(card);
        }
        get('lista-vacia').hidden = selected.size !== 0;
        get('servicios-ayuda').textContent = services.length ? 'Los servicios sin cupo están deshabilitados.' : 'La campaña no tiene servicios disponibles para hoy.';
        updateButtons();
    }

    function applyContext(data, preserveSelection = false) {
        available = true;
        patient = data.paciente;
        services = data.servicios;
        original = new Set(data.hoja?.servicios || []);
        if (!preserveSelection) {
            selected = new Set(original);
            dirty = false;
        }
        get('paciente').hidden = !patient;
        get('servicios').hidden = !patient;
        get('identificacion').hidden = Boolean(patient);
        if (patient) {
            get('registro').hidden = true;
            get('paciente-nombre').textContent = patient.nombres_apellidos;
            get('paciente-documento').textContent = `${patient.tipo_doc || ''} ${patient.dni || ''}`.trim();
            get('paciente-edad').textContent = `${patient.edad} ${patient.edad === 1 ? 'año' : 'años'}`;
            get('paciente-sexo').textContent = { M: 'Masculino', F: 'Femenino' }[patient.sexo] || 'No registrado';
            get('hoja-aviso').textContent = data.hoja ? 'Hoja de hoy cargada. Puede agregar o quitar servicios y guardar los cambios.' : 'Se creará una hoja de ruta para hoy.';
        }
        renderServices();
    }

    function clearPatient() {
        patient = null; selected.clear(); original.clear(); dirty = false; searchedDocument = null;
        for (const id of ['registro', 'paciente', 'servicios']) get(id).hidden = true;
        get('identificacion').hidden = false;
        registerForm.reset();
        updateButtons();
    }

    async function confirmDiscard() {
        if (!dirty) return true;
        const result = await window.Swal.fire({ title: '¿Descartar los cambios sin grabar?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, continuar', cancelButtonText: 'Volver' });
        return result.isConfirmed;
    }

    get('otro').addEventListener('click', async () => {
        if (!await confirmDiscard()) return;
        clearPatient(); number.value = ''; message(''); setDocumentMode();
        if (type.value === 'S/ DOCUMENTO') get('nombres').focus(); else number.focus();
    });

    function setDocumentMode() {
        const without = type.value === 'S/ DOCUMENTO';
        get('numero-grupo').hidden = without;
        get('buscar-boton').hidden = without;
        number.required = !without;
        number.maxLength = type.value === 'DNI' ? 8 : 50;
        number.minLength = type.value === 'DNI' ? 8 : 7;
        number.inputMode = type.value === 'DNI' ? 'numeric' : 'text';
        if (type.value === 'DNI') number.pattern = '[0-9]{8}'; else number.removeAttribute('pattern');
        get('documento-ayuda').textContent = type.value === 'DNI' ? 'Ingrese exactamente 8 dígitos.' : 'Ingrese al menos 7 caracteres.';
        if (without) {
            searchedDocument = { tipo_doc: type.value };
            get('registro').hidden = false;
            get('registro-aviso').textContent = 'Registre al paciente. Se generará un identificador interno automáticamente.';
        }
    }

    let previousType = type.value;
    type.addEventListener('change', async () => {
        if (!await confirmDiscard()) { type.value = previousType; return; }
        previousType = type.value;
        clearPatient(); number.value = ''; message(''); setDocumentMode();
    });
    number.addEventListener('input', () => {
        // A different number must never leave the previous patient's sheet active.
        if (searchedDocument && number.value.trim() !== searchedDocument.dni) clearPatient();
    });
    searchForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !available || !searchForm.reportValidity() || !await confirmDiscard()) return;
        clearPatient();
        searchedDocument = { tipo_doc: type.value, dni: number.value.trim() };
        message('Buscando paciente…');
        try {
            const data = await request('buscar', searchedDocument);
            applyContext(data);
            message(data.paciente ? 'Paciente encontrado.' : 'No se encontró el documento. Registre al paciente.');
            if (!data.paciente) {
                get('registro').hidden = false;
                get('registro-aviso').textContent = 'No se encontró el documento. Complete los datos del paciente.';
                get('nombres').focus();
            }
        } catch { /* request displays the error without discarding entered data. */ }
    });
    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !available || !searchedDocument || !registerForm.reportValidity()) return;
        try {
            const data = await request('registrar', { ...searchedDocument, nombres_apellidos: get('nombres').value,
                fecha_nacimiento: get('nacimiento').value, sexo: get('sexo').value, telefono: get('telefono').value });
            applyContext(data); message('Paciente disponible. Seleccione los servicios de atención.', 'success');
            get('servicio').focus();
        } catch { /* Keep registration fields available for correction. */ }
    });
    select.addEventListener('change', updateButtons);
    add.addEventListener('click', async () => {
        const service = services.find((row) => row.id === Number(select.value));
        if (busy || !service || selected.has(service.id) || (service.disponibles < 1 && !original.has(service.id))) return;
        const result = await window.Swal.fire({ title: '¿Agregar servicio?', text: service.nombre, icon: 'question', showCancelButton: true, confirmButtonText: 'Sí, agregar', cancelButtonText: 'Cancelar' });
        if (result.isConfirmed) { selected.add(service.id); dirty = true; renderServices(); }
    });
    get('refrescar').addEventListener('click', async () => {
        if (busy || !patient) return;
        try { applyContext(await request('consultar', { paciente_id: patient.id }), true); message('Cupos actualizados.'); } catch { /* Keep the current selection. */ }
    });
    save.addEventListener('click', async () => {
        if (busy || !available || !patient || !selected.size) return;
        message('Grabando hoja de ruta…');
        try {
            applyContext(await request('grabar', { paciente_id: patient.id, servicios: [...selected] }));
            message('Hoja de ruta grabada correctamente.', 'success');
        } catch { /* No data is cleared when the remaining quota changes. */ }
    });
    if (campaign) {
        let previousCampaign = campaign.value;
        campaign.addEventListener('change', async () => {
            if (!await confirmDiscard()) { campaign.value = previousCampaign; return; }
            previousCampaign = campaign.value;
            clearPatient(); number.value = ''; available = false;
            message('Cargando campaña…');
            try { applyContext(await request('consultar')); setDocumentMode(); message(''); } catch { /* Show the reason and prevent writes. */ }
        });
    }
    window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
    setDocumentMode();
    message('Cargando campaña…');
    request('consultar').then((data) => { applyContext(data); message(''); }).catch(() => {});
}

initializeTriaje();
