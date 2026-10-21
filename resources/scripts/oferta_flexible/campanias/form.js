const form = document.getElementById('campania-form');
const editor = document.getElementById('servicios-editor');
const template = document.getElementById('servicio-row-template');
const start = document.getElementById('fecha_inicio');
const end = document.getElementById('fecha_fin');
const mode = document.getElementById('mismos_servicios');
const save = form.querySelector('[data-save-campania]');
const notice = document.getElementById('cupos-aviso');
const general = JSON.parse(editor.dataset.generales).map((row) => ({ ...row, ups_id: String(row.ups_id) }));
const groups = new Map();
const custom = new Set(JSON.parse(editor.dataset.personalizados));
for (const row of JSON.parse(editor.dataset.diarios)) {
    if (!groups.has(row.fecha)) groups.set(row.fecha, []);
    groups.get(row.fecha).push({ ups_id: String(row.ups_id), cupos: row.cupos });
}

function dates() {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(start.value) || !/^\d{4}-\d{2}-\d{2}$/.test(end.value) || end.value < start.value) return [];
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
    if (mode.value !== '1') return;
    for (const date of dates()) {
        const previous = groups.get(date) || [];
        groups.set(date, general.map((row) => {
            const existing = previous.find((item) => item.ups_id === row.ups_id);
            return { ups_id: row.ups_id, cupos: custom.has(`${date}:${row.ups_id}`) && existing ? existing.cupos : row.cupos };
        }));
    }
}

function validRows(rows) {
    return rows.length > 0 && new Set(rows.map((row) => row.ups_id)).size === rows.length
        && rows.every((row) => row.ups_id !== '' && Number.isInteger(Number(row.cupos)) && Number(row.cupos) > 0 && Number(row.cupos) <= 2147483647);
}

function validate() {
    const days = dates();
    const valid = days.length > 0 && (mode.value !== '1' || validRows(general)) && days.every((date) => validRows(groups.get(date) || []));
    save.disabled = !valid;
    notice.textContent = valid ? '' : 'Complete las fechas y seleccione servicios sin repetir, con un cupo válido por cada día.';
}

function rowElement(row, rows, date = null, fixed = false) {
    const element = template.content.firstElementChild.cloneNode(true);
    const select = element.querySelector('[data-ups]');
    const input = element.querySelector('[data-cupo]');
    select.value = row.ups_id;
    for (const option of select.options) {
        option.disabled = option.dataset.activo === '0' && option.value !== row.ups_id;
    }
    select.disabled = fixed;
    input.value = row.cupos ?? '';
    const badge = element.querySelector('[data-personalizado]');
    if (date && custom.has(`${date}:${row.ups_id}`)) badge.textContent = 'Cupo personalizado';
    select.addEventListener('change', () => {
        row.ups_id = select.value;
        synchronize();
        renderDays();
        validate();
    });
    input.addEventListener('input', () => {
        row.cupos = input.value;
        if (date && mode.value === '1') {
            custom.add(`${date}:${row.ups_id}`);
            badge.textContent = 'Cupo personalizado';
        } else if (!date) {
            synchronize();
            renderDays();
        }
        validate();
    });
    const remove = element.querySelector('[data-remove]');
    remove.hidden = fixed;
    remove.addEventListener('click', () => {
        rows.splice(rows.indexOf(row), 1);
        synchronize();
        render();
    });
    return element;
}

function renderDays() {
    const container = document.getElementById('servicios-dias');
    container.replaceChildren();
    for (const date of dates()) {
        if (!groups.has(date)) groups.set(date, []);
        const rows = groups.get(date);
        const section = document.createElement('fieldset');
        section.className = 'border rounded p-3 mb-3';
        const legend = document.createElement('legend');
        legend.className = 'float-none w-auto px-2 fs-6';
        legend.textContent = date.split('-').reverse().join('/');
        section.append(legend);
        for (const row of rows) section.append(rowElement(row, rows, date, mode.value === '1'));
        if (mode.value !== '1') {
            const add = document.createElement('button');
            add.type = 'button'; add.className = 'btn btn-sm btn-outline-primary'; add.textContent = 'Añadir servicio';
            add.addEventListener('click', () => { rows.push({ ups_id: '', cupos: '' }); renderDays(); validate(); });
            section.append(add);
        }
        container.append(section);
    }
}

function render() {
    document.getElementById('servicios-generales').hidden = mode.value !== '1';
    const container = editor.querySelector('[data-general-rows]');
    container.replaceChildren();
    for (const row of general) container.append(rowElement(row, general));
    // Inputs in the hidden general section must not participate in native form validation.
    container.querySelectorAll('input, select').forEach((input) => { input.disabled = mode.value !== '1'; });
    renderDays();
    validate();
}

editor.querySelector('[data-add-general]').addEventListener('click', () => { general.push({ ups_id: '', cupos: '' }); synchronize(); render(); });
for (const input of [start, end, mode]) input.addEventListener('change', () => { synchronize(); render(); });
editor.querySelector('[data-reset-personalizados]').addEventListener('click', async () => {
    const result = await window.Swal.fire({ title: '¿Sobrescribir todos los cupos personalizados?', text: 'Se aplicará el cupo general de cada servicio a todos los días.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, sobrescribir', cancelButtonText: 'Cancelar' });
    if (result.isConfirmed) {
        custom.clear();
        document.getElementById('sobrescribir_personalizados').value = '1';
        synchronize(); render();
    }
});

form.addEventListener('submit', (event) => {
    validate();
    if (save.disabled) { event.preventDefault(); return; }
    const payload = document.getElementById('servicios-payload');
    payload.replaceChildren();
    // One field avoids PHP max_input_vars truncating a large service/day matrix.
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'servicios_config';
    input.value = JSON.stringify({
        generales: mode.value === '1' ? general : [],
        diarios: dates().flatMap((date) => (groups.get(date) || []).map((row) => ({ fecha: date, ...row }))),
    });
    payload.append(input);
});

// Preserve unsaved daily differences after server validation redirects back to the form.
for (const [date, rows] of groups) for (const row of rows) {
    const base = general.find((item) => item.ups_id === row.ups_id);
    if (base && Number(base.cupos) !== Number(row.cupos)) custom.add(`${date}:${row.ups_id}`);
}
synchronize();
render();
