import { initializeAdministration } from '../administracion';

initializeAdministration();

const form = document.getElementById('campania-form');
const start = document.getElementById('fecha_inicio');
const end = document.getElementById('fecha_fin');
const mode = document.getElementById('mismos_servicios');
const includesLab = document.getElementById('incluye_laboratorio');
const save = form.querySelector('[data-save-campania]');
const notice = document.getElementById('cupos-aviso');
const editors = [];

for (const root of form.querySelectorAll('[data-quota-editor]')) {
    editors.push(createEditor(root, validateForm));
}

function validateForm() {
    const named = document.getElementById('nombre').value.trim() !== '';
    const valid = editors.every((editor) => editor.isValid());
    save.disabled = !valid || !named;
    notice.textContent = !named ? 'Ingrese el nombre de la campaña.' : '';
    if (named && !valid) {
        notice.textContent = 'Complete las fechas, servicios y exámenes seleccionados con cantidades válidas por día.';
    }
    updateSummary('services', 'quotas', editors.filter((editor) => !editor.lab));
    updateSummary('exams', 'supplies', editors.filter((editor) => editor.lab));
    form.querySelectorAll('[data-summary-lab]').forEach((item) => {
        item.hidden = includesLab.value !== '1';
    });
}

function updateSummary(count, quantity, selectedEditors) {
    const rows = selectedEditors.flatMap((editor) => editor.rows());
    form.querySelector(`[data-summary-${count}]`).textContent = new Set(rows.map((row) => row.ups_id)).size;
    const total = rows.reduce((sum, row) => sum + (Number(row.cupos) > 0 ? Number(row.cupos) : 0), 0);
    form.querySelector(`[data-summary-${quantity}]`).textContent = total.toLocaleString('es-PE');
}

form.querySelectorAll('[data-config-for]').forEach((group) => {
    group.querySelectorAll('[data-config-value]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(group.dataset.configFor);
            input.value = button.dataset.configValue;
            const help = form.querySelector(`[data-config-help="${group.dataset.configFor}"]`);
            help.textContent = input.value === '1' ? help.dataset.helpYes : help.dataset.helpNo;
            group.querySelectorAll('[data-config-value]').forEach((option) => {
                const selected = option.dataset.configValue === input.value;
                option.classList.toggle('btn-primary', selected);
                option.classList.toggle('btn-secondary', !selected);
                option.setAttribute('aria-pressed', String(selected));
            });
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
});

function createEditor(editor, onChange) {
    const prefix = editor.dataset.prefix;
    const lab = editor.dataset.laboratorio === '1';
    const quota = lab ? 'Insumos' : 'Cupo';
    const field = (suffix) => editor.querySelector(`#${prefix}-${suffix}`);
    const search = field('buscar');
    const type = field('tipo');
    let enabled = !lab || includesLab.value === '1';
    const catalog = [...editor.querySelectorAll('[data-catalog-item]')];
    const general = JSON.parse(editor.dataset.generales).map((row) => ({ ...row, ups_id: String(row.ups_id) }));
    const groups = new Map();
    const custom = new Set(JSON.parse(editor.dataset.personalizados));
    let activeDate = '';

    for (const row of JSON.parse(editor.dataset.diarios)) {
        if (!groups.has(row.fecha)) {
            groups.set(row.fecha, []);
        }
        groups.get(row.fecha).push({ ups_id: String(row.ups_id), cupos: row.cupos });
    }

    function dates() {
        if (
            !/^\d{4}-\d{2}-\d{2}$/.test(start.value) ||
            !/^\d{4}-\d{2}-\d{2}$/.test(end.value) ||
            end.value < start.value
        ) {
            return [];
        }
        const values = [];
        const current = new Date(`${start.value}T00:00:00Z`);
        const last = new Date(`${end.value}T00:00:00Z`);
        while (current <= last) {
            values.push(current.toISOString().slice(0, 10));
            current.setUTCDate(current.getUTCDate() + 1);
        }
        return values;
    }

    function synchronize() {
        if (mode.value !== '1') {
            return;
        }
        for (const date of dates()) {
            const previous = groups.get(date) || [];
            groups.set(
                date,
                general.map((row) => {
                    const existing = previous.find((item) => item.ups_id === row.ups_id);
                    return {
                        ups_id: row.ups_id,
                        cupos: custom.has(`${date}:${row.ups_id}`) && existing ? existing.cupos : row.cupos,
                    };
                }),
            );
        }
    }

    function validRows(rows) {
        return (
            rows.length > 0 &&
            new Set(rows.map((row) => row.ups_id)).size === rows.length &&
            rows.every(
                (row) =>
                    row.ups_id !== '' &&
                    Number.isInteger(Number(row.cupos)) &&
                    Number(row.cupos) > 0 &&
                    Number(row.cupos) <= 2147483647,
            )
        );
    }

    function isValid() {
        const days = dates();
        return !enabled || (
            days.length > 0 &&
            (mode.value !== '1' || validRows(general)) &&
            days.every((date) => validRows(groups.get(date) || []))
        );
    }

    function validate() {
        onChange();
    }

    function element(tag, className = '', text = '') {
        const node = document.createElement(tag);
        node.className = className;
        node.textContent = text;
        return node;
    }

    function description(id) {
        return catalog.find((item) => item.dataset.upsId === id)?.dataset.descripcion
            || (lab ? 'Examen no disponible' : 'Servicio no disponible');
    }

    function dateLabel(date) {
        return date
            .split('-')
            .reverse()
            .join('/');
    }

    function quotaCell(row, date = null) {
        const cell = element('td');
        const input = element('input', 'form-control oferta-quota-input');
        input.type = 'number';
        input.min = '1';
        input.max = '2147483647';
        input.step = '1';
        input.required = enabled;
        input.disabled = !enabled;
        input.inputMode = 'numeric';
        input.value = row.cupos ?? '';
        input.setAttribute('aria-label', `${quota} de ${description(row.ups_id)}, ${date || 'general'}`);
        if (date) {
            input.dataset.date = date;
            input.dataset.upsId = row.ups_id;
        }
        const badge = element('small', 'text-body-secondary d-block mt-1');
        badge.dataset.personalizado = '';
        badge.textContent = date && custom.has(`${date}:${row.ups_id}`) ? `${quota} personalizado` : '';
        const message = lab ? 'Ingrese insumos enteros mayores que cero.' : 'Ingrese un cupo entero mayor que cero.';
        const feedback = element('div', 'invalid-feedback', message);
        input.addEventListener('input', () => {
            const target = date ? groups.get(date).find((item) => item.ups_id === row.ups_id) : row;
            target.cupos = input.value;
            input.classList.toggle('is-invalid', !input.validity.valid);
            input.setAttribute('aria-invalid', String(!input.validity.valid));
            if (date && mode.value === '1') {
                custom.add(`${date}:${row.ups_id}`);
                badge.textContent = `${quota} personalizado`;
            } else if (!date) {
                synchronize();
                updateDailyInputs();
            }
            validate();
        });
        cell.append(input, feedback, badge);
        return cell;
    }

    function updateDailyInputs() {
        editor.querySelectorAll('input[data-date]').forEach((input) => {
            const row = groups.get(input.dataset.date)?.find((item) => item.ups_id === input.dataset.upsId);
            input.value = row?.cupos ?? '';
            input.classList.toggle('is-invalid', !input.validity.valid);
        });
    }

    function removeButton(row, rows) {
        const button = element('button', 'btn btn-danger');
        button.type = 'button';
        button.disabled = !enabled;
        const icon = element('i', 'bi bi-x-lg');
        icon.setAttribute('aria-hidden', 'true');
        button.append(icon, document.createTextNode('Quitar'));
        button.setAttribute('aria-label', `Quitar ${description(row.ups_id)}`);
        button.addEventListener('click', () => {
            rows.splice(rows.indexOf(row), 1);
            synchronize();
            render();
        });
        return button;
    }

    function serviceTable(rows, days = []) {
        const wrapper = element('div', 'table-responsive');
        const table = element('table', 'table table-hover align-middle oferta-quota-table');
        const head = element('thead');
        const headings = element('tr');
        headings.append(
            element('th', '', lab ? 'Examen' : 'Servicio'),
            element('th', '', days.length ? `${quota} general` : quota),
        );
        for (const date of days) {
            headings.append(element('th', '', dateLabel(date)));
        }
        headings.append(element('th', 'text-center', 'Acción'));
        head.append(headings);
        const body = element('tbody');
        for (const row of rows) {
            const line = element('tr');
            line.append(element('td', 'fw-semibold', description(row.ups_id)), quotaCell(row));
            for (const date of days) {
                const daily = groups.get(date).find((item) => item.ups_id === row.ups_id);
                line.append(quotaCell(daily, date));
            }
            const actions = element('td', 'text-center');
            actions.append(removeButton(row, rows));
            line.append(actions);
            body.append(line);
        }
        table.append(head, body);
        wrapper.append(table);
        return wrapper;
    }

    function emptySelection() {
        const empty = element('div', 'oferta-empty');
        const icon = element('i', 'bi bi-list-check');
        icon.setAttribute('aria-hidden', 'true');
        const message = lab
            ? 'Seleccione exámenes para configurar sus insumos.'
            : 'Seleccione servicios para configurar sus cupos.';
        empty.append(icon, element('p', 'mb-0', message));
        return empty;
    }

    function renderTabs(days) {
        const tabs = field('dias-tabs');
        tabs.replaceChildren();
        tabs.hidden = mode.value === '1' || days.length === 0;
        if (!days.includes(activeDate)) {
            activeDate = days[0] || '';
        }
        if (tabs.hidden) {
            return;
        }
        days.forEach((date, index) => {
            const selected = date === activeDate;
            const button = element('button', `nav-link${selected ? ' active' : ''}`);
            const icon = element('i', 'bi bi-calendar3 me-2');
            icon.setAttribute('aria-hidden', 'true');
            button.append(icon, document.createTextNode(dateLabel(date)));
            button.type = 'button';
            button.disabled = !enabled;
            button.id = `${prefix}-dia-tab-${index}`;
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', String(selected));
            button.setAttribute('aria-controls', `${prefix}-dias`);
            button.tabIndex = selected ? 0 : -1;
            button.addEventListener('click', () => selectDate(date, index));
            button.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                    event.preventDefault();
                    const offset = event.key === 'ArrowRight' ? 1 : -1;
                    const next = (index + offset + days.length) % days.length;
                    selectDate(days[next], next);
                }
            });
            tabs.append(button);
        });
    }

    function selectDate(date, index) {
        activeDate = date;
        render();
        editor.querySelector(`#${prefix}-dia-tab-${index}`)?.focus();
    }

    function renderCatalog() {
        const selected = mode.value === '1' ? general : groups.get(activeDate) || [];
        const term = search.value.trim().toLocaleLowerCase('es');
        let visible = 0;
        for (const item of catalog) {
            const checkbox = item.querySelector('[data-catalog-check]');
            const checked = selected.some((row) => row.ups_id === item.dataset.upsId);
            checkbox.checked = checked;
            checkbox.disabled = !enabled || dates().length === 0 || (item.dataset.activo === '0' && !checked);
            const text = `${item.dataset.descripcion} ${item.dataset.codigo}`.toLocaleLowerCase('es');
            item.hidden = !text.includes(term) || (type.value !== '' && item.dataset.tipo !== type.value);
            if (!item.hidden) {
                visible += 1;
            }
        }
        editor.querySelector('[data-catalog-empty]').hidden = visible !== 0;
    }

    function render() {
        const days = dates();
        for (const date of days) {
            if (!groups.has(date)) {
                groups.set(date, []);
            }
        }
        renderTabs(days);
        const same = mode.value === '1';
        field('generales').hidden = !same;
        const generalContainer = editor.querySelector('[data-general-rows]');
        generalContainer.replaceChildren();
        if (same) {
            generalContainer.append(general.length ? serviceTable(general, days) : emptySelection());
        }
        const dailyContainer = field('dias');
        dailyContainer.replaceChildren();
        dailyContainer.hidden = same;
        if (!same && activeDate) {
            dailyContainer.setAttribute('role', 'tabpanel');
            dailyContainer.setAttribute('aria-labelledby', `${prefix}-dia-tab-${days.indexOf(activeDate)}`);
            const rows = groups.get(activeDate);
            dailyContainer.append(rows.length ? serviceTable(rows) : emptySelection());
        }
        renderCatalog();
        validate();
    }

    catalog.forEach((item) => {
        item.querySelector('[data-catalog-check]').addEventListener('change', (event) => {
            const rows = mode.value === '1' ? general : groups.get(activeDate);
            if (event.target.checked && !rows.some((row) => row.ups_id === item.dataset.upsId)) {
                rows.push({ ups_id: item.dataset.upsId, cupos: '' });
            } else if (!event.target.checked) {
                const index = rows.findIndex((row) => row.ups_id === item.dataset.upsId);
                if (index !== -1) {
                    rows.splice(index, 1);
                }
            }
            synchronize();
            render();
        });
    });

    for (const input of [start, end, mode]) {
        input.addEventListener('change', () => {
            synchronize();
            render();
        });
    }

    search.addEventListener('input', renderCatalog);
    type.addEventListener('change', renderCatalog);

    editor.querySelector('[data-reset-personalizados]').addEventListener('click', async () => {
        const result = await window.Swal.fire({
            title: `¿Sobrescribir todos los ${lab ? 'insumos' : 'cupos'} personalizados?`,
            text: `Se aplicará la cantidad general de cada ${lab ? 'examen' : 'servicio'} a todos los días.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, sobrescribir',
            cancelButtonText: 'Cancelar',
        });
        if (result.isConfirmed) {
            custom.clear();
            field('sobrescribir').value = '1';
            synchronize();
            render();
        }
    });

    for (const [date, rows] of groups) {
        for (const row of rows) {
            const base = general.find((item) => item.ups_id === row.ups_id);
            if (base && Number(base.cupos) !== Number(row.cupos)) {
                custom.add(`${date}:${row.ups_id}`);
            }
        }
    }

    return {
        lab,
        isValid,
        rows: () => enabled ? dates().flatMap((date) => groups.get(date) || []) : [],
        setEnabled(value) {
            enabled = value;
            editor.hidden = !value;
            editor.querySelectorAll('input, select, button').forEach((input) => {
                input.disabled = !value;
            });
            synchronize();
            render();
        },
        serialize() {
            const payload = field('payload');
            payload.replaceChildren();
            if (!enabled) {
                return;
            }
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `${prefix}_config`;
            input.value = JSON.stringify({
                generales: mode.value === '1' ? general : [],
                diarios: dates().flatMap((date) => (groups.get(date) || []).map((row) => ({ fecha: date, ...row }))),
            });
            payload.append(input);
        },
    };
}

function toggleLaboratory() {
    editors.forEach((editor) => editor.setEnabled(!editor.lab || includesLab.value === '1'));
    validateForm();
}

includesLab.addEventListener('change', toggleLaboratory);
document.getElementById('nombre').addEventListener('input', validateForm);
form.addEventListener('submit', (event) => {
    validateForm();
    if (save.disabled) {
        event.preventDefault();
        return;
    }
    editors.forEach((editor) => editor.serialize());
});

toggleLaboratory();
