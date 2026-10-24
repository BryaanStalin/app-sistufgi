import { confirmAction, initializeAdministration, setBusy } from '../administracion';
import { examinationChanges, stockPreview } from './toma_muestra_estado';

initializeAdministration();

const root = document.querySelector('[data-lab-toma-muestra]');
const initial = JSON.parse(root.dataset.inicial);

if (initial) {
    initializeSampleCollection(initial);
}

function initializeSampleCollection(initialContext) {
    const endpoint = root.dataset.url;
    const campaign = root.dataset.campania;
    const interval = Math.max(1, Number(root.dataset.intervalo) || 120) * 1000;
    const node = (id) => document.getElementById(`tm-${id}`);
    const searchForm = node('buscar');
    const form = node('estados-form');
    const selected = new Map();
    let context = initialContext;
    let busy = false;
    let confirming = false;
    let stockBusy = false;
    let revision = 0;
    let timer = null;

    function element(tag, classes = '', text = '') {
        const value = document.createElement(tag);
        value.className = classes;
        value.textContent = text;
        return value;
    }

    function iconButton(classes, icon, text) {
        const button = element('button', `btn ${classes}`);
        button.type = 'button';
        const symbol = element('i', `bi ${icon}`);
        symbol.setAttribute('aria-hidden', 'true');
        button.append(symbol, document.createTextNode(` ${text}`));
        return button;
    }

    function changes() {
        return examinationChanges(context.examenes, selected);
    }

    function updateButtons() {
        const frozen = busy || confirming;
        node('grabar').disabled = frozen || !context.paciente || changes().length === 0;
        node('nuevo').disabled = frozen || !context.paciente;
        node('buscar-boton').disabled = frozen || Boolean(context.paciente);
        node('tipo').disabled = frozen || Boolean(context.paciente);
        node('documento').disabled = frozen || Boolean(context.paciente);
        node('examenes').querySelectorAll('input, button').forEach((control) => {
            control.disabled = frozen || control.dataset.editable !== 'true';
        });
        node('campania-form')?.querySelectorAll('button, select').forEach((control) => {
            control.disabled = frozen;
        });
    }

    function badge(state) {
        const classes = { P: 'bg-warning text-dark', T: 'bg-success', N: 'bg-secondary' };
        const labels = { P: 'Pendiente', T: 'Realizado', N: 'No realizado' };
        return element('span', `badge ${classes[state]}`, labels[state]);
    }

    function renderRows() {
        const body = node('examenes');
        body.replaceChildren();
        context.examenes.forEach((exam, index) => {
            const line = element('tr', exam.editable ? '' : 'table-secondary text-secondary');
            const description = element('td');
            description.append(element('span', 'd-block fw-semibold', exam.nombre));
            const status = element('span');
            status.dataset.statusId = exam.id;
            status.append(badge(selected.get(exam.id) ?? exam.estado));
            description.append(status, element('small', 'text-body-secondary ms-2', exam.fecha));
            const actions = element('td');
            const options = element('div', 'd-flex flex-wrap gap-2 justify-content-center');
            options.setAttribute('role', 'group');
            options.setAttribute('aria-label', `Estado de ${exam.nombre}`);
            for (const [state, text, symbol, color] of [
                ['T', 'Realizado', 'bi-check-circle-fill', 'text-success'],
                ['N', 'No realizado', 'bi-x-circle-fill', 'text-danger'],
            ]) {
                const label = element('label', 'form-check d-flex align-items-center gap-2 mb-0');
                const input = element('input', 'form-check-input mt-0');
                input.type = 'radio';
                input.name = `estado-${exam.id}`;
                input.value = state;
                input.dataset.examId = exam.id;
                input.dataset.editable = String(exam.editable);
                input.checked = (selected.get(exam.id) ?? exam.estado) === state;
                input.disabled = !exam.editable;
                const icon = element('i', `bi ${symbol} ${color}`);
                icon.setAttribute('aria-hidden', 'true');
                label.append(input, icon, document.createTextNode(text));
                input.addEventListener('change', () => selectState(exam, state));
                options.append(label);
            }
            if (exam.editable) {
                const pending = iconButton('btn-secondary btn-sm', 'bi-arrow-counterclockwise', 'Pendiente');
                pending.dataset.editable = 'true';
                pending.addEventListener('click', () => selectState(exam, 'P'));
                options.append(pending);
            } else {
                options.append(element('span', 'badge bg-secondary', 'Solo lectura'));
            }
            actions.append(options);
            line.append(element('td', 'text-center', index + 1), description, actions);
            body.append(line);
        });
        if (!context.examenes.length) {
            const line = element('tr');
            const cell = element('td', 'text-center text-body-secondary py-4',
                context.paciente ? 'El paciente no tiene exámenes registrados.' : 'Busque un paciente para continuar.');
            cell.colSpan = 3;
            line.append(cell);
            body.append(line);
        }
        updateButtons();
    }

    function selectState(exam, state) {
        if (busy || confirming || !exam.editable) {
            return;
        }
        selected.set(exam.id, state);
        node('examenes').querySelectorAll(`[data-exam-id="${exam.id}"]`).forEach((input) => {
            input.checked = input.value === state;
        });
        node('examenes').querySelector(`[data-status-id="${exam.id}"]`).replaceChildren(badge(state));
        node('exito').hidden = true;
        renderWarnings();
        renderStock();
        updateButtons();
    }

    function renderWarnings(savedWarnings = null) {
        const target = node('advertencias');
        target.replaceChildren();
        const warnings = savedWarnings ?? changes()
            .filter((change) => change.estado === 'T')
            .map((change) => context.examenes.find((exam) => exam.id === change.id))
            .filter((exam) => exam.hora_superada)
            .map((exam) => ({ examen: exam.nombre, mensaje: root.dataset.avisoHora }));
        for (const warning of warnings) {
            target.append(element('div', 'alert alert-warning', `${warning.examen}: ${warning.mensaje}`));
        }
    }

    function renderStock() {
        const table = element('table', 'table table-bordered table-success align-middle mb-0');
        const head = element('thead');
        const headers = element('tr');
        headers.append(element('th', '', 'Pruebas'), element('th', 'text-center', 'Quedan'),
            element('th', 'text-center', 'Tomadas'));
        head.append(headers);
        const body = element('tbody');
        node('alertas').replaceChildren();
        for (const row of context.stock) {
            const preview = stockPreview(row, context.examenes, selected);
            const line = element('tr');
            const red = preview.remaining <= Math.ceil(row.insumos * 0.15);
            line.append(element('td', '', row.examen),
                element('td', `text-center fw-bold ${red ? 'bg-danger text-white' : 'bg-success text-white'}`,
                    preview.remaining), element('td', 'text-center fw-semibold', preview.taken));
            body.append(line);
            if (preview.remaining <= 10) {
                const message = row.mensaje_alerta ||
                    `NOS ESTAMOS QUEDANDO SIN INSUMOS DE “${row.examen}” ABASTEZCA A TRAVÉS DEL ADMINISTRADOR ` +
                    'O INDIQUE A LOS PACIENTES QUE ESTA PRUEBA ESTARÁ RESTRINGIDA DENTRO DE POCO';
                node('alertas').append(element('div', 'alert alert-warning small', message));
            }
        }
        if (!context.stock.length) {
            const line = element('tr');
            const cell = element('td', 'text-center py-3', 'No hay insumos configurados para hoy.');
            cell.colSpan = 3;
            line.append(cell);
            body.append(line);
        }
        table.append(head, body);
        node('stock').replaceChildren(table);
        if (changes().length) {
            node('stock').append(element('p', 'small text-body-secondary mt-2 mb-0',
                'Las cantidades incluyen los cambios pendientes de grabar de esta pantalla.'));
        }
    }

    function applyContext(data) {
        context = data;
        selected.clear();
        revision += 1;
        const patient = data.paciente;
        node('paciente').value = patient?.nombre_completo || '';
        node('edad').value = patient ? `${patient.edad} A` : '';
        node('sexo').value = patient ? ({ M: 'Masculino', F: 'Femenino' }[patient.sexo] || 'Sin dato') : '';
        node('celular').value = patient?.telefono || '';
        node('ticket').textContent = data.tm ? `TM-N° ${data.tm.replace('TM-', '')}` : 'TM-N° ---';
        renderRows();
        renderStock();
        renderWarnings(data.advertencias || []);
    }

    function clearValidation() {
        root.querySelectorAll('.is-invalid').forEach((input) => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        });
    }

    function showError(error) {
        const response = error.responseJSON || {};
        const messages = Object.values(response.errors || {}).flat();
        node('error').textContent = messages.join(' ') || response.message ||
            'No se pudo completar la operación. Intente nuevamente.';
        node('error').hidden = false;
        for (const [field, values] of Object.entries(response.errors || {})) {
            const input = searchForm.elements.namedItem(field);
            if (input) {
                input.classList.add('is-invalid');
                input.setAttribute('aria-invalid', 'true');
                const feedback = root.querySelector(`[data-error-for="${field}"]`);
                if (feedback) {
                    feedback.textContent = values.join(' ');
                }
            }
        }
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
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ ...data, accion: action, campania_id: campaign }),
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
        if (busy || confirming || context.paciente || !searchForm.reportValidity()) {
            return;
        }
        const data = await request('buscar', {
            tipo_doc: node('tipo').value,
            dni: node('documento').value,
        }, node('buscar-boton'));
        if (data) {
            applyContext(data);
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || confirming || !context.paciente || !changes().length) {
            return;
        }
        confirming = true;
        updateButtons();
        let result;
        try {
            result = await confirmAction(
                '¿Guardar los estados de los exámenes del paciente seleccionado?'
            );
        } finally {
            confirming = false;
            updateButtons();
        }
        if (!result.isConfirmed) {
            return;
        }
        const data = await request('grabar', {
            lab_paciente_id: context.lab_paciente_id,
            estados: changes(),
        }, node('grabar'));
        if (data) {
            applyContext(data);
            node('exito').textContent = 'Estados de los exámenes guardados correctamente.';
            node('exito').hidden = false;
        }
    });

    node('nuevo').addEventListener('click', async () => {
        if (busy || confirming || !(await canDiscard())) {
            return;
        }
        searchForm.reset();
        clearValidation();
        applyContext({ ...context, paciente: null, lab_paciente_id: null, tm: null, examenes: [], advertencias: [] });
        node('error').hidden = true;
        node('exito').hidden = true;
        documentRule();
        node('documento').focus();
    });

    async function canDiscard() {
        if (!changes().length) {
            return true;
        }
        confirming = true;
        updateButtons();
        try {
            return (await confirmAction('¿Descartar los cambios sin guardar?')).isConfirmed;
        } finally {
            confirming = false;
            updateButtons();
        }
    }

    function documentRule() {
        const dni = node('tipo').value === 'DNI';
        node('documento').minLength = dni ? 8 : 7;
        node('documento').maxLength = dni ? 8 : 50;
        node('documento').pattern = dni ? '[0-9]{8}' : '.{7,50}';
        node('documento').inputMode = dni ? 'numeric' : 'text';
    }

    async function refreshStock() {
        if (document.hidden || stockBusy || busy || confirming) {
            return;
        }
        stockBusy = true;
        const version = revision;
        try {
            const data = await window.$.ajax({
                url: endpoint,
                method: 'GET',
                dataType: 'json',
                data: { campania_id: campaign, stock: 1 },
            });
            if (version === revision && !busy) {
                context.stock = data.stock;
                renderStock();
                node('refresco').textContent = 'Insumos actualizados. Refresco automático activo.';
            }
        } catch {
            node('refresco').textContent = 'No se pudo actualizar. El servidor verificará los insumos al grabar.';
        } finally {
            stockBusy = false;
        }
    }

    function scheduleRefresh() {
        window.clearInterval(timer);
        if (document.hidden) {
            timer = null;
            node('refresco').textContent = 'Refresco automático pausado mientras la pestaña está oculta.';
            return;
        }
        timer = window.setInterval(refreshStock, interval);
        node('refresco').textContent = `Refresco automático cada ${interval / 1000} segundos.`;
    }

    node('tipo').addEventListener('change', documentRule);
    node('campania-form')?.addEventListener('submit', async (event) => {
        if (busy || confirming) {
            event.preventDefault();
            return;
        }
        if (changes().length) {
            event.preventDefault();
            if (await canDiscard()) {
                selected.clear();
                event.target.submit();
            }
        }
    });
    document.addEventListener('visibilitychange', () => {
        scheduleRefresh();
        if (!document.hidden) {
            refreshStock();
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (changes().length || busy) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    window.addEventListener('pagehide', () => window.clearInterval(timer));
    window.addEventListener('pageshow', () => {
        updateButtons();
        scheduleRefresh();
    });
    applyContext(context);
    documentRule();
    scheduleRefresh();
}
