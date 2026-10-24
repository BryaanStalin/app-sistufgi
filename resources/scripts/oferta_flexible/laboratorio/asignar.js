import '../../../css/oferta_flexible/laboratorio_asignacion.css';
import { confirmAction, initializeAdministration, setBusy } from '../administracion';

initializeAdministration();

const root = document.querySelector('[data-lab-asignacion]');

if (root?.dataset.campania) {
    initializeAssignments();
}

function initializeAssignments() {
    const endpoint = root.dataset.url;
    const campaign = Number(root.dataset.campania);
    const node = (id) => document.getElementById(`asignacion-${id}`);
    const controls = new Map();
    let busy = false;
    let page = Number(new URL(window.location.href).searchParams.get('page')) || 1;

    function checkedPatients() {
        return [...root.querySelectorAll('[data-lab-seleccion]:checked')].map((checkbox) => Number(checkbox.value));
    }

    function updateSelection() {
        const selected = checkedPatients();
        const checkboxes = [...root.querySelectorAll('[data-lab-seleccion]')];
        node('seleccion').textContent = `${selected.length} pacientes seleccionados de esta página.`;
        node('aplicar').disabled = busy || selected.length === 0 || !node('destino').value;
        const all = node('todos');
        if (all) {
            all.checked = checkboxes.length > 0 && selected.length === checkboxes.length;
            all.indeterminate = selected.length > 0 && selected.length < checkboxes.length;
            all.disabled = busy || checkboxes.length === 0;
        }
    }

    function freezeControls(frozen) {
        root.setAttribute('aria-busy', String(frozen));
        if (frozen) {
            controls.clear();
            root.querySelectorAll('button, input:not([type="hidden"]), select').forEach((control) => {
                controls.set(control, control.disabled);
                control.disabled = true;
            });
        } else {
            controls.forEach((disabled, control) => {
                if (control.isConnected) {
                    control.disabled = disabled;
                }
            });
            controls.clear();
        }
    }

    async function locked(operation) {
        if (busy) {
            return;
        }
        busy = true;
        freezeControls(true);
        node('error').hidden = true;
        node('exito').hidden = true;
        try {
            await operation();
        } catch (error) {
            showError(error);
        } finally {
            busy = false;
            freezeControls(false);
            updateSelection();
        }
    }

    function showError(error) {
        const response = error.responseJSON || {};
        node('error').textContent = Object.values(response.errors || {}).flat().join(' ') ||
            response.message || error.message || 'No se pudo completar la operación. Intente nuevamente.';
        node('error').hidden = false;
    }

    async function reloadTable(targetPage = page) {
        const url = new URL(root.dataset.exportacion, window.location.href);
        url.searchParams.delete('exportar');
        url.searchParams.set('tabla', '1');
        url.searchParams.set('page', String(targetPage));
        node('cargando').hidden = false;
        try {
            const data = await window.$.ajax({ url: url.href, method: 'GET', dataType: 'json' });
            node('listado').innerHTML = data.html;
            page = data.pagina;
            const current = new URL(window.location.href);
            current.searchParams.set('page', String(page));
            window.history.replaceState(null, '', current);
        } finally {
            node('cargando').hidden = true;
        }
    }

    async function assignPatients(ids, renipress) {
        const data = await window.$.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ campania_id: campaign, pacientes: ids, renipress }),
        });
        node('exito').textContent = `${data.message} Pacientes actualizados: ${data.cantidad}.`;
        node('exito').hidden = false;
    }

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-lab-seleccion], #asignacion-destino')) {
            updateSelection();
        } else if (event.target.id === 'asignacion-todos') {
            root.querySelectorAll('[data-lab-seleccion]').forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
            updateSelection();
        } else if (event.target.matches('[data-lab-destino]')) {
            const select = event.target;
            const destination = select.value;
            if (!destination || destination === select.dataset.original) {
                return;
            }
            locked(async () => {
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm ms-2';
                spinner.setAttribute('role', 'status');
                spinner.setAttribute('aria-label', 'Guardando asignación');
                select.after(spinner);
                try {
                    await assignPatients([Number(select.dataset.paciente)], destination);
                    select.dataset.original = destination;
                    const label = select.selectedOptions[0].textContent.trim();
                    select.title = label;
                    select.parentElement.querySelector('small').textContent = label;
                    await reloadTable();
                } catch (error) {
                    select.value = select.dataset.original;
                    throw error;
                } finally {
                    spinner.remove();
                }
            });
        }
    });

    node('masiva').addEventListener('submit', (event) => {
        event.preventDefault();
        const ids = checkedPatients();
        const destination = node('destino').value;
        if (busy || !ids.length || !destination || !event.target.reportValidity()) {
            return;
        }
        locked(async () => {
            const confirmation = await confirmAction(
                `¿Asignar el laboratorio a ${ids.length} pacientes seleccionados?`
            );
            if (!confirmation.isConfirmed) {
                return;
            }
            setBusy(node('aplicar'), true);
            try {
                await assignPatients(ids, destination);
                await reloadTable();
            } finally {
                setBusy(node('aplicar'), false);
            }
        });
    });

    node('listado').addEventListener('click', (event) => {
        const link = event.target.closest('.pagination a');
        if (!link) {
            return;
        }
        event.preventDefault();
        if (!busy) {
            const targetPage = Number(new URL(link.href).searchParams.get('page')) || 1;
            locked(() => reloadTable(targetPage));
        }
    });

    node('filtros').addEventListener('submit', (event) => {
        if (busy) {
            event.preventDefault();
        }
    });

    node('exportar').addEventListener('click', () => {
        locked(async () => {
            setBusy(node('exportar'), true);
            try {
                const response = await window.fetch(root.dataset.exportacion, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok || !response.headers.get('Content-Type')?.includes('spreadsheetml.sheet')) {
                    const detail = await response.json().catch(() => ({}));
                    throw new Error(
                        detail.message || 'No se pudo descargar el Excel. Revise su sesión e intente nuevamente.'
                    );
                }
                const blob = await response.blob();
                const match = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/i);
                const filename = (match?.[1] || 'Laboratorio_Asignacion.xlsx').replace(/[^A-Za-z0-9_.-]/g, '_');
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                document.body.append(link);
                link.click();
                link.remove();
                window.setTimeout(() => window.URL.revokeObjectURL(url), 1000);
                node('exito').textContent =
                    'Excel generado con todas las filas que coinciden con los filtros aplicados.';
                node('exito').hidden = false;
            } finally {
                setBusy(node('exportar'), false);
            }
        });
    });

    window.addEventListener('beforeunload', (event) => {
        if (busy) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    updateSelection();
}
