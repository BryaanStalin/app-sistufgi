import { buttonState } from './admision_estado';
import { confirmAction, initializeAdministration, initializeTooltips, setBusy } from '../administracion';

initializeAdministration();

const root = document.querySelector('[data-lab-admision]');
const endpoint = root.dataset.url;
const campaign = root.dataset.campania;
const initial = JSON.parse(root.dataset.inicial);

if (initial) {
    initializeAdmission(initial);
}

function initializeAdmission(initialContext) {
    const node = (id) => document.getElementById(`lab-${id}`);
    const searchForm = node('buscar');
    const form = node('atencion-form');
    const added = new Map();
    const removed = new Set();
    const interval = Math.max(1, Number(root.dataset.intervalo) || 120) * 1000;
    let context = initialContext;
    let editing = true;
    let busy = false;
    let stockBusy = false;
    let revision = 0;
    let timer = null;
    let baseline = '';

    function element(tag, classes = '', text = '') {
        const value = document.createElement(tag);
        value.className = classes;
        value.textContent = text;
        return value;
    }

    function fields() {
        return {
            fua: node('fua').value,
            ups_id_solicita: node('servicio').value,
            telefono: node('celular').value,
        };
    }

    function dirty() {
        return added.size > 0 || removed.size > 0 || (context.paciente && JSON.stringify(fields()) !== baseline);
    }

    function quantities(row) {
        const returned = context.historial.filter((item) =>
            removed.has(item.id) && item.campania_servicio_id === row.campania_servicio_id).length;
        return Math.max(0, row.quedan - (added.has(row.campania_servicio_id) ? 1 : 0) + returned);
    }

    function renderStock() {
        const table = element('table', 'table table-bordered table-success align-middle mb-0');
        const head = element('thead');
        const headers = element('tr');
        headers.append(element('th', '', 'Pruebas'), element('th', 'text-center', 'Quedan'));
        head.append(headers);
        const body = element('tbody');
        node('alertas').replaceChildren();
        for (const row of context.stock) {
            const remaining = quantities(row);
            const line = element('tr');
            const color = remaining <= Math.ceil(row.insumos * 0.15) ? 'bg-danger' : 'bg-success';
            line.append(element('td', '', row.examen), element('td', `text-center text-white ${color}`, remaining));
            body.append(line);
            if (remaining <= 10) {
                const alert = element('div', 'alert alert-warning');
                alert.setAttribute('role', 'status');
                alert.textContent = `NOS ESTAMOS QUEDANDO SIN INSUMOS DE “${row.examen}” `
                    + 'ABASTEZCA A TRAVÉS DEL ADMINISTRADOR O INDIQUE A LOS PACIENTES QUE ESTA PRUEBA '
                    + 'ESTARÁ RESTRINGIDA DENTRO DE POCO';
                node('alertas').append(alert);
            }
        }
        if (!context.stock.length) {
            const line = element('tr');
            const cell = element('td', '', 'No hay insumos configurados para hoy.');
            cell.colSpan = 2;
            line.append(cell);
            body.append(line);
        }
        table.append(head, body);
        const wrapper = element('div', 'table-responsive');
        wrapper.append(table);
        node('stock').replaceChildren(wrapper);
        renderChoices();
    }

    function renderChoices() {
        const select = node('examen');
        const selected = select.value;
        select.replaceChildren(new Option('Seleccione un examen', ''));
        for (const exam of context.examenes) {
            const row = context.stock.find((item) => item.campania_servicio_id === exam.id);
            const remaining = row ? quantities(row) : 0;
            const label = exam.sin_configuracion ? 'Sin insumos para hoy'
                : (remaining === 0 ? 'Agotado' : `${remaining} disponibles`);
            const option = new Option(`${exam.nombre} — ${label}`, exam.id || `sin-${exam.ups_id}`);
            option.disabled = !exam.activo || !exam.id || !row || remaining <= 0 ||
                (!row.disponible && row.quedan > 0) || added.has(exam.id);
            select.append(option);
        }
        select.value = selected;
        updateButtons();
    }

    function renderServices(selected = '') {
        node('servicio').replaceChildren(new Option('Seleccione un servicio', ''));
        context.servicios.forEach((service) => {
            node('servicio').append(new Option(service.nombre, service.id));
        });
        node('servicio').value = selected;
    }

    function renderRows() {
        const body = node('examenes');
        body.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((button) => {
            window.bootstrap.Tooltip.getInstance(button)?.dispose();
        });
        body.replaceChildren();
        const rows = [
            ...[...added.values()].reverse().map((exam) => ({ ...exam, nuevo: true })),
            ...context.historial.filter((exam) => !removed.has(exam.id)),
        ];
        rows.forEach((exam, index) => {
            const removable = editing && (exam.nuevo || exam.editable);
            const line = element('tr', !exam.nuevo && !exam.editable ? 'table-secondary text-secondary' : '');
            const description = element('td');
            description.append(element('span', 'd-block fw-semibold', exam.nombre));
            const variant = exam.nuevo || exam.estado === 'P' ? 'bg-warning text-dark'
                : (exam.estado === 'T' ? 'bg-success' : 'bg-secondary');
            description.append(element('span', `badge ${variant}`,
                exam.nuevo ? 'Nuevo · Pendiente de grabar' : exam.etiqueta));
            if (!exam.nuevo) {
                description.append(element('small', 'text-body-secondary ms-2', exam.fecha));
            }
            const actions = element('td', 'text-center');
            if (removable) {
                const button = element('button', 'btn btn-danger');
                button.type = 'button';
                button.disabled = busy;
                button.dataset.bsToggle = 'tooltip';
                button.title = `Quitar ${exam.nombre}`;
                button.setAttribute('aria-label', button.title);
                const icon = element('i', 'bi bi-trash');
                icon.setAttribute('aria-hidden', 'true');
                button.append(icon);
                button.addEventListener('click', () => removeExam(exam));
                actions.append(button);
            } else {
                actions.append(element('span', 'badge bg-secondary', 'Solo lectura'));
            }
            line.append(element('td', 'text-center', index + 1), description, actions);
            body.append(line);
        });
        if (!rows.length) {
            const line = element('tr');
            const cell = element('td', 'text-center text-body-secondary py-4', 'No hay exámenes agregados.');
            cell.colSpan = 3;
            line.append(cell);
            body.append(line);
        }
        initializeTooltips(body);
        updateButtons();
    }

    async function removeExam(exam) {
        if (busy || !editing) {
            return;
        }
        const result = await confirmAction(`¿Quitar ${exam.nombre} de ${context.paciente.nombre_completo}?`);
        if (!result.isConfirmed || busy) {
            return;
        }
        if (exam.nuevo) {
            added.delete(exam.id);
        } else if (exam.editable) {
            removed.add(exam.id);
        }
        renderRows();
        renderStock();
    }

    function updateButtons() {
        const enabled = editing && Boolean(context.paciente) && !busy;
        [...form.elements].forEach((input) => {
            if (input.type !== 'hidden') {
                input.disabled = !enabled;
            }
        });
        node('examen').disabled = !enabled;
        const buttons = buttonState({
            busy,
            editing,
            hasPatient: Boolean(context.paciente),
            hasAttention: Boolean(context.atencion),
            hasChanges: added.size > 0 || removed.size > 0,
            validFields: form.checkValidity(),
            hasSelectedExam: Boolean(node('examen').value) && !node('examen').selectedOptions[0]?.disabled,
        });
        node('agregar').disabled = !buttons.add;
        node('grabar').disabled = !buttons.save;
        node('modificar').disabled = !buttons.modify;
        node('nuevo').disabled = !buttons.newPatient;
        node('buscar-boton').disabled = busy || Boolean(context.paciente);
        node('documento').disabled = busy || Boolean(context.paciente);
        node('tipo').disabled = busy || Boolean(context.paciente);
        node('eliminar').hidden = editing || !context.atencion;
        node('eliminar').disabled = !buttons.remove;
        node('modo').textContent = modeMessage();
    }

    function modeMessage() {
        if (!context.paciente) {
            return 'Busque un paciente con hoja de ruta de hoy para continuar.';
        }
        if (editing && !context.servicios.length) {
            return 'No hay servicios de triaje disponibles para este paciente hoy.';
        }
        return editing ? 'Edición: los cambios se aplican al pulsar Grabar.' : 'Guardado. Pulse Modificar para editar.';
    }

    function applyContext(data, resetFields = true) {
        const selected = node('servicio').value;
        context = data;
        node('paciente').value = data.paciente?.nombre_completo || '';
        node('edad').value = data.paciente ? `${data.paciente.edad} A` : '';
        const sex = data.paciente?.sexo;
        node('paciente-sexo').value = data.paciente ? ({ M: 'Masculino', F: 'Femenino' }[sex] || 'Sin dato') : '';
        node('tm').textContent = data.tm ? data.tm.replace('TM-', 'TM-N° ') : 'TM-N° ---';
        renderServices(resetFields ? String(data.atencion?.ups_id_solicita || '') : selected);
        if (resetFields) {
            added.clear();
            removed.clear();
            node('fua').value = data.atencion?.fua || '';
            node('celular').value = data.paciente?.telefono || '';
            editing = !data.atencion;
            baseline = JSON.stringify(fields());
        }
        renderRows();
        renderStock();
    }

    function showError(response) {
        const errors = response.responseJSON?.errors || {};
        const message = Object.values(errors).flat().join(' · ')
            || ({ 403: 'No tiene permiso para esta operación.',
                404: 'La campaña o el paciente ya no están disponibles. Vuelva a consultar.',
                419: 'Su sesión venció. Recargue la página.' }[response.status])
            || response.responseJSON?.message || 'No se pudo completar la operación. Vuelva a intentarlo.';
        const alert = node('error');
        alert.textContent = message;
        alert.hidden = false;
        Object.entries(errors).forEach(([field, messages]) => {
            const input = root.querySelector(`[name="${CSS.escape(field)}"]`);
            input?.classList.add('is-invalid');
            input?.setAttribute('aria-invalid', 'true');
            const feedback = root.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
            if (feedback) {
                feedback.textContent = messages.join(' ');
            }
        });
    }

    function clearValidation() {
        root.querySelectorAll('.is-invalid').forEach((input) => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        });
    }

    async function request(action, data, button) {
        busy = true;
        revision += 1;
        node('error').hidden = true;
        node('exito').hidden = true;
        clearValidation();
        setBusy(button, true);
        updateButtons();
        try {
            return await window.$.ajax({
                url: endpoint,
                method: action === 'eliminar' ? 'DELETE' : 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({ accion: action, campania_id: Number(campaign), ...data }),
            });
        } catch (error) {
            showError(error);
            return null;
        } finally {
            busy = false;
            setBusy(button, false);
            updateButtons();
        }
    }

    searchForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !searchForm.reportValidity()) {
            return;
        }
        const documentData = { tipo_doc: node('tipo').value, dni: node('documento').value };
        const data = await request('buscar', documentData, node('buscar-boton'));
        if (data) {
            applyContext(data);
            if (editing) {
                node('fua').focus();
            }
        }
    });

    node('eliminar').addEventListener('click', async () => {
        if (busy || editing || !context.atencion) {
            return;
        }
        busy = true;
        updateButtons();
        let confirmation;
        try {
            confirmation = await window.Swal.fire({
                title: '¿Eliminar el registro de laboratorio?',
                text: context.paciente.nombre_completo + '. Se eliminarán los exámenes de la atención de hoy.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar registro',
                cancelButtonText: 'Cancelar',
            });
        } finally {
            busy = false;
            updateButtons();
        }
        if (!confirmation.isConfirmed) {
            return;
        }
        const data = await request('eliminar', {
            paciente_id: context.paciente.id,
            atencion_id: context.atencion.id,
        }, node('eliminar'));
        if (data) {
            searchForm.reset();
            form.reset();
            applyContext(data);
            documentRule();
            node('exito').textContent = data.message;
            node('exito').hidden = false;
            node('documento').focus();
        }
    });

    node('agregar').addEventListener('click', async () => {
        if (busy || !editing || !context.paciente) {
            return;
        }
        const exam = context.examenes.find((item) => String(item.id) === node('examen').value);
        if (!exam || added.has(exam.id)) {
            return;
        }
        const data = await request('validar', {
            paciente_id: context.paciente.id,
            examenes: [...added.keys(), exam.id],
            eliminar: [...removed],
        }, node('agregar'));
        if (data) {
            added.set(exam.id, exam);
            applyContext(data, false);
            node('examen').value = '';
            updateButtons();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || node('grabar').disabled || !form.reportValidity()) {
            return;
        }
        const data = await request('grabar', {
            ...fields(), paciente_id: context.paciente.id,
            examenes: [...added.keys()], eliminar: [...removed],
        }, node('grabar'));
        if (data) {
            applyContext(data);
            editing = false;
            node('exito').textContent = 'Atención de laboratorio guardada correctamente.';
            node('exito').hidden = false;
            renderRows();
        }
    });

    node('modificar').addEventListener('click', () => {
        editing = true;
        node('exito').hidden = true;
        renderRows();
        node('fua').focus();
    });
    node('nuevo').addEventListener('click', async () => {
        if (busy || (dirty() && !(await confirmAction('¿Descartar los cambios sin guardar?')).isConfirmed)) {
            return;
        }
        searchForm.reset();
        form.reset();
        clearValidation();
        applyContext({ ...context, paciente: null, tm: null, atencion: null, servicios: [], historial: [] });
        node('error').hidden = true;
        node('exito').hidden = true;
        documentRule();
        node('documento').focus();
    });

    function documentRule() {
        const dni = node('tipo').value === 'DNI';
        node('documento').minLength = dni ? 8 : 7;
        node('documento').maxLength = dni ? 8 : 50;
        node('documento').pattern = dni ? '[0-9]{8}' : '.{7,50}';
        node('documento').inputMode = dni ? 'numeric' : 'text';
    }

    async function refreshStock() {
        if (document.hidden || stockBusy || busy) {
            return;
        }
        stockBusy = true;
        const version = revision;
        try {
            const data = await window.$.ajax({
                url: endpoint, method: 'GET', dataType: 'json',
                data: { campania_id: campaign, stock: 1 },
            });
            if (version === revision && !busy) {
                context.stock = data.stock;
                renderStock();
                node('stock-hora').textContent = 'Insumos actualizados desde el servidor.';
            }
        } catch {
            node('stock-hora').textContent =
                'No se pudieron refrescar los insumos. El servidor los revalidará al grabar.';
        } finally {
            stockBusy = false;
        }
    }

    function schedule() {
        window.clearInterval(timer);
        timer = document.hidden ? null : window.setInterval(refreshStock, interval);
    }

    node('tipo').addEventListener('change', documentRule);
    node('examen').addEventListener('change', updateButtons);
    form.addEventListener('input', updateButtons);
    form.addEventListener('change', updateButtons);
    document.addEventListener('visibilitychange', () => {
        schedule();
        if (!document.hidden) {
            refreshStock();
        }
    });
    window.addEventListener('pagehide', () => window.clearInterval(timer));
    window.addEventListener('pageshow', schedule);
    window.addEventListener('beforeunload', (event) => {
        if (dirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    node('campania-form')?.addEventListener('submit', async (event) => {
        if (busy || dirty()) {
            event.preventDefault();
            const result = !busy && await confirmAction('¿Cambiar de campaña y descartar los cambios sin guardar?');
            if (result && result.isConfirmed) {
                added.clear();
                removed.clear();
                baseline = JSON.stringify(fields());
                event.target.submit();
            }
        }
    });
    documentRule();
    applyContext(initialContext);
    schedule();
}
