import { initializeTooltips, setBusy } from './administracion';
import '../../css/oferta_flexible/triaje.css';

const LOW_QUOTA_THRESHOLD = 3;

function initializeTriaje() {
    const root = document.getElementById('triaje');
    const searchForm = document.getElementById('triaje-buscar');
    if (!root || !searchForm) {
        return;
    }
    const get = (id) => document.getElementById(`triaje-${id}`);
    const campaign = get('campania');
    const type = get('tipo');
    const number = get('numero');
    const registerForm = get('registrar');
    const select = get('servicio');
    const add = get('agregar');
    const save = get('grabar');
    const fields = {
        tipo_doc: type,
        dni: number,
        nombres_apellidos: get('nombres'),
        fecha_nacimiento: get('nacimiento'),
        sexo: get('sexo'),
        telefono: get('telefono'),
    };
    let patient = null;
    let services = [];
    let selected = new Set();
    let original = new Set();
    let busy = false;
    let available = false;
    let dirty = false;
    let searchedDocument = null;

    function clearValidation() {
        Object.values(fields).forEach((input) => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        });
    }

    function showValidation(errors = {}) {
        Object.entries(errors).forEach(([field, messages]) => {
            const input = fields[field];
            if (!input) {
                return;
            }
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            const feedback = document.getElementById(`${input.id}-error`);
            if (feedback) {
                feedback.textContent = messages.join(' ');
            }
        });
    }

    Object.values(fields).forEach((input) => {
        input.addEventListener('invalid', () => {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
        });
        input.addEventListener('input', () => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        });
    });

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
        for (const control of [select, campaign]) {
            if (control) {
                control.disabled = busy;
            }
        }
        type.disabled = busy || Boolean(patient);
        number.disabled = busy || Boolean(patient);
        get('otro').disabled = busy;
        get('lista')
            .querySelectorAll('button')
            .forEach((button) => {
                button.disabled = busy;
            });
        root.setAttribute('aria-busy', String(busy));
    }

    async function request(accion, data = {}) {
        const button = {
            buscar: get('buscar-boton'),
            registrar: registerForm.querySelector('button'),
            grabar: save,
            consultar: patient ? get('refrescar') : null,
        }[accion];
        clearValidation();
        busy = true;
        if (button) {
            setBusy(button, true);
        }
        updateButtons();
        try {
            return await window.$.ajax({
                url: root.dataset.endpoint,
                method: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                headers: { Accept: 'application/json' },
                data: JSON.stringify({
                    accion,
                    campania_id: Number(campaign?.value || root.dataset.campania),
                    ...data,
                }),
            });
        } catch (error) {
            if ([401, 403, 404, 419].includes(error.status) || error.responseJSON?.errors?.campania_id) {
                available = false;
            }
            const errors = error.responseJSON?.errors;
            showValidation(errors);
            const text = errors
                ? Object.values(errors).flat().join(' ')
                : error.status === 419
                  ? 'La sesión expiró. Recargue la página antes de continuar.'
                  : 'No se pudo completar la operación. Intente nuevamente.';
            message(text, 'danger');
            get('mensaje').focus();
            throw error;
        } finally {
            if (button) {
                setBusy(button, false);
            }
            busy = false;
            updateButtons();
        }
    }

    function quotaBadge(service) {
        const badge = document.createElement('span');
        const remaining = service.disponibles;
        const variant = remaining === 0 ? 'danger' : remaining <= LOW_QUOTA_THRESHOLD ? 'warning' : 'success';
        badge.className = `badge oferta-badge oferta-badge-${variant}`;
        badge.textContent =
            remaining === 0
                ? 'Agotado'
                : `${remaining} ${remaining === 1 ? 'cupo disponible' : 'cupos disponibles'}`;
        return badge;
    }

    function updateServiceHelp() {
        const help = get('servicios-ayuda');
        help.className = 'small text-muted triaje-cupo-ayuda';
        help.replaceChildren();
        const service = services.find((row) => row.id === Number(select.value));
        if (service) {
            help.append(quotaBadge(service));
            if (original.has(service.id)) {
                help.append(document.createTextNode('Reservado en esta hoja.'));
            }
        } else {
            help.textContent = services.length
                ? 'Los servicios sin cupo están deshabilitados.'
                : 'La campaña no tiene servicios disponibles para hoy.';
        }
    }

    function renderServices() {
        const previous = select.value;
        select.replaceChildren(new Option('Seleccione un servicio', ''));
        for (const service of services) {
            if (selected.has(service.id)) {
                continue;
            }
            const own = original.has(service.id);
            const option = new Option(
                `${service.nombre} · ${service.disponibles} cupos libres${own ? ' · reservado en esta hoja' : ''}`,
                String(service.id),
            );
            option.disabled = service.disponibles < 1 && !own;
            select.append(option);
        }
        if ([...select.options].some((option) => option.value === previous && !option.disabled)) {
            select.value = previous;
        }
        const list = get('lista');
        list.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
            window.bootstrap.Tooltip.getInstance(element)?.dispose();
        });
        list.replaceChildren();
        for (const id of selected) {
            const service = services.find((row) => row.id === id);
            const card = document.createElement('li');
            card.className = 'triaje-servicio-card border rounded p-3';
            const content = document.createElement('div');
            const name = document.createElement('strong');
            name.className = 'd-block';
            name.textContent = service?.nombre || 'Servicio ya no disponible';
            const detail = document.createElement('small');
            detail.className = service ? 'text-muted' : 'text-danger';
            detail.textContent = service?.tipo || 'Quite este servicio y vuelva a seleccionar.';
            content.append(name, detail);
            if (service) {
                content.append(document.createElement('br'), quotaBadge(service));
                if (original.has(id)) {
                    const reserved = document.createElement('small');
                    reserved.className = 'd-block text-body-secondary mt-1';
                    reserved.textContent = 'Reservado en esta hoja';
                    content.append(reserved);
                }
            }
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-danger triaje-quitar';
            remove.dataset.bsToggle = 'tooltip';
            remove.title = 'Quitar servicio';
            const removeIcon = document.createElement('i');
            removeIcon.className = 'bi bi-x-lg';
            removeIcon.setAttribute('aria-hidden', 'true');
            const removeText = document.createElement('span');
            removeText.className = 'triaje-quitar-texto';
            removeText.textContent = 'Quitar';
            remove.append(removeIcon, removeText);
            remove.setAttribute('aria-label', `Quitar ${service?.nombre || 'servicio'}`);
            remove.addEventListener('click', () => {
                selected.delete(id);
                dirty = true;
                renderServices();
            });
            card.append(content, remove);
            list.append(card);
        }
        initializeTooltips(list);
        get('lista-vacia').hidden = selected.size !== 0;
        updateServiceHelp();
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
            get('paciente-sexo').textContent =
                { M: 'Masculino', F: 'Femenino' }[patient.sexo] || 'No registrado';
            get('hoja-aviso').textContent = data.hoja
                ? 'Hoja de hoy cargada. Puede agregar o quitar servicios y guardar los cambios.'
                : 'Se creará una hoja de ruta para hoy.';
            get('hoja-aviso').className = data.hoja
                ? 'alert alert-info mt-3 mb-0'
                : 'text-body-secondary small mt-2 mb-0';
        }
        renderServices();
    }

    function clearPatient() {
        patient = null;
        selected.clear();
        original.clear();
        dirty = false;
        searchedDocument = null;
        for (const id of ['registro', 'paciente', 'servicios']) {
            get(id).hidden = true;
        }
        get('identificacion').hidden = false;
        registerForm.reset();
        clearValidation();
        updateButtons();
    }

    async function confirmDiscard() {
        if (!dirty) {
            return true;
        }
        const result = await window.Swal.fire({
            title: '¿Descartar los cambios sin grabar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Volver',
        });
        return result.isConfirmed;
    }

    async function loadCampaign() {
        if (campaign) {
            root.querySelector('h1').textContent = campaign.selectedOptions[0].textContent.trim();
        }
        const startDate = campaign?.selectedOptions[0]?.dataset.fechaInicio || root.dataset.fechaInicio;
        if (startDate && startDate > root.dataset.hoy) {
            available = false;
            get('identificacion').hidden = true;
            get('registro').hidden = true;
            const [year, month, day] = startDate.split('-');
            message(`La campaña inicia el ${day}/${month}/${year}.`);
            updateButtons();
            return;
        }

        get('identificacion').hidden = false;
        setDocumentMode();
        message('Cargando campaña…');
        try {
            applyContext(await request('consultar'));
            setDocumentMode();
            message('');
        } catch {}
    }

    get('otro').addEventListener('click', async () => {
        if (!(await confirmDiscard())) {
            return;
        }
        clearPatient();
        number.value = '';
        message('');
        setDocumentMode();
        if (type.value === 'S/ DOCUMENTO') {
            get('nombres').focus();
        } else {
            number.focus();
        }
    });

    function setDocumentMode() {
        const without = type.value === 'S/ DOCUMENTO';
        get('numero-grupo').hidden = without;
        get('buscar-boton').hidden = without;
        number.required = !without;
        number.maxLength = type.value === 'DNI' ? 8 : 50;
        number.minLength = type.value === 'DNI' ? 8 : 7;
        number.inputMode = type.value === 'DNI' ? 'numeric' : 'text';
        if (type.value === 'DNI') {
            number.pattern = '[0-9]{8}';
        } else {
            number.removeAttribute('pattern');
        }
        get('documento-ayuda').textContent =
            type.value === 'DNI' ? 'Ingrese exactamente 8 dígitos.' : 'Ingrese al menos 7 caracteres.';
        if (without) {
            searchedDocument = { tipo_doc: type.value };
            get('registro').hidden = false;
            get('registro-aviso').textContent =
                'Registre al paciente. Se generará un identificador interno automáticamente.';
        }
    }

    let previousType = type.value;
    type.addEventListener('change', async () => {
        if (!(await confirmDiscard())) {
            type.value = previousType;
            return;
        }
        previousType = type.value;
        clearPatient();
        number.value = '';
        message('');
        setDocumentMode();
    });
    number.addEventListener('input', () => {
        // A different number must never leave the previous patient's sheet active.
        if (searchedDocument && number.value.trim() !== searchedDocument.dni) {
            clearPatient();
        }
    });
    searchForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !available || !searchForm.reportValidity() || !(await confirmDiscard())) {
            return;
        }
        clearPatient();
        searchedDocument = { tipo_doc: type.value, dni: number.value.trim() };
        message('Buscando paciente…');
        try {
            const data = await request('buscar', searchedDocument);
            applyContext(data);
            message(
                data.paciente ? 'Paciente encontrado.' : 'No se encontró el documento. Registre al paciente.',
            );
            if (!data.paciente) {
                get('registro').hidden = false;
                get('registro-aviso').textContent =
                    'No se encontró el documento. Complete los datos del paciente.';
                get('nombres').focus();
            }
        } catch {
            /* request displays the error without discarding entered data. */
        }
    });
    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !available || !searchedDocument || !registerForm.reportValidity()) {
            return;
        }
        try {
            const data = await request('registrar', {
                ...searchedDocument,
                nombres_apellidos: get('nombres').value,
                fecha_nacimiento: get('nacimiento').value,
                sexo: get('sexo').value,
                telefono: get('telefono').value,
            });
            applyContext(data);
            message('Paciente disponible. Seleccione los servicios de atención.', 'success');
            get('servicio').focus();
        } catch {
            /* Keep registration fields available for correction. */
        }
    });
    select.addEventListener('change', () => {
        updateServiceHelp();
        updateButtons();
    });
    add.addEventListener('click', async () => {
        const service = services.find((row) => row.id === Number(select.value));
        if (
            busy ||
            !service ||
            selected.has(service.id) ||
            (service.disponibles < 1 && !original.has(service.id))
        ) {
            return;
        }
        const result = await window.Swal.fire({
            title: '¿Agregar servicio?',
            text: service.nombre,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, agregar',
            cancelButtonText: 'Cancelar',
        });
        if (result.isConfirmed) {
            selected.add(service.id);
            dirty = true;
            renderServices();
        }
    });
    get('refrescar').addEventListener('click', async () => {
        if (busy || !patient) {
            return;
        }
        try {
            applyContext(await request('consultar', { paciente_id: patient.id }), true);
            message('Cupos actualizados.');
        } catch {
            /* Keep the current selection. */
        }
    });
    save.addEventListener('click', async () => {
        if (busy || !available || !patient || !selected.size) {
            return;
        }
        message('Grabando hoja de ruta…');
        try {
            applyContext(await request('grabar', { paciente_id: patient.id, servicios: [...selected] }));
            message('Hoja de ruta grabada correctamente.', 'success');
        } catch {
            /* No data is cleared when the remaining quota changes. */
        }
    });
    if (campaign) {
        let previousCampaign = campaign.value;
        campaign.addEventListener('change', async () => {
            if (!(await confirmDiscard())) {
                campaign.value = previousCampaign;
                return;
            }
            previousCampaign = campaign.value;
            clearPatient();
            number.value = '';
            available = false;
            await loadCampaign();
        });
    }
    window.addEventListener('beforeunload', (event) => {
        if (dirty) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    loadCampaign();
}

initializeTriaje();
