import { initializeForm } from '../../../js/scripts/administrador/form';
import { initializeActions } from '../acciones';
import { initializeAdministration } from '../administracion';

initializeAdministration();
initializeForm();
initializeActions();

document.querySelectorAll('[data-user-panel]').forEach(initializeUserPanel);

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
