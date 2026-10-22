import { initializeForm } from '../../../js/scripts/administrador/form';
import { initializeActions } from '../acciones';
import { initializeAdministration } from '../administracion';

initializeAdministration();
initializeForm();
initializeActions();
initializeTabs();

document.querySelectorAll('[data-user-panel]').forEach(initializeUserPanel);

function initializeTabs() {
    const root = document.querySelector('.oferta-admin[data-campania-id]');
    if (!root?.dataset.campaniaId) {
        return;
    }
    const key = `oferta-flexible:usuarios:${root.dataset.campaniaId}:tab`;
    const tabs = [...root.querySelectorAll('#asignar-tab, #enlazar-tab')];
    const storedTab = readStoredTab(key);
    const tab = tabs.find((option) => option.id === storedTab);
    tabs.forEach((option) => {
        option.addEventListener('shown.bs.tab', () => storeTab(key, option.id));
    });
    if (tab && !tab.disabled && !tab.classList.contains('disabled') && tab.getAttribute('aria-disabled') !== 'true') {
        window.bootstrap.Tab.getOrCreateInstance(tab).show();
    }
    const active = tabs.find((option) => option.classList.contains('active'));
    if (active) {
        storeTab(key, active.id);
    }
}

function readStoredTab(key) {
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

function storeTab(key, tab) {
    try {
        window.sessionStorage.setItem(key, tab);
    } catch {
        return;
    }
}

function initializeUserPanel(panel) {
    const search = panel.querySelector('[data-user-search]');
    const users = [...panel.querySelectorAll('[data-user-item]')];
    const confirm = panel.querySelector('[data-confirm-assignment]');
    const counter = panel.querySelector('[data-user-selected]');
    const form = panel.querySelector('[data-assign-form]');

    function updateSelection() {
        const selected = form.querySelectorAll('[data-user-checkbox]:checked:not(:disabled)').length;
        if (confirm) {
            confirm.disabled = selected === 0;
            counter.textContent = `${selected} seleccionados`;
        }
    }

    search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase('es');
        let visible = 0;
        users.forEach((user) => {
            user.hidden = !user.dataset.search.toLocaleLowerCase('es').includes(term);
            if (!user.hidden) {
                visible += 1;
            }
        });
        panel.querySelector('[data-user-empty]').hidden = visible !== 0 || users.length === 0;
    });
    form.addEventListener('change', updateSelection);
    updateSelection();
}
