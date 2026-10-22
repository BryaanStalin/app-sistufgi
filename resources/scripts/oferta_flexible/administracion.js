import '../../css/oferta_flexible/administracion.css';

export function initializeAdministration() {
    const root = document.querySelector('.oferta-admin');
    if (!root) {
        return;
    }
    initializeTooltips(root);
    initializeSelects(root);
    initializeValidation(root);
}

export function initializeTooltips(root) {
    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        window.bootstrap.Tooltip.getOrCreateInstance(element, { trigger: 'hover focus' });
    });
}

function initializeSelects(root) {
    root.querySelectorAll('[data-admin-select]').forEach((select) => {
        window.$(select).select2({
            theme: 'bootstrap-5',
            width: '100%',
            minimumResultsForSearch: 8,
            language: {
                noResults: () => 'No se encontraron resultados',
                searching: () => 'Buscando…',
            },
        });
        const selection = window.$(select)
            .next('.select2')
            .find('.select2-selection');
        selection.attr('aria-labelledby', `${select.id}-label`);
        select.addEventListener('invalid', (event) => {
            event.preventDefault();
            selection.attr('aria-invalid', 'true');
            window.$(select).select2('open');
        });
        window.$(select).on('change', () => {
            if (select.validity.valid) {
                select.classList.remove('is-invalid');
                selection.removeAttr('aria-invalid');
            }
        });
    });
}

function initializeValidation(root) {
    root.querySelectorAll('input, select').forEach((input) => {
        input.addEventListener('invalid', () => {
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
        });
        input.addEventListener('input', () => {
            if (input.validity.valid) {
                input.classList.remove('is-invalid');
                input.removeAttribute('aria-invalid');
            }
        });
    });
    root.querySelectorAll('form[data-admin-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            queueMicrotask(() => {
                if (!event.defaultPrevented) {
                    form.querySelectorAll('[type="submit"]').forEach((button) => setBusy(button, true));
                }
            });
        });
    });
    window.addEventListener('pageshow', () => {
        root.querySelectorAll('[data-busy]').forEach((button) => setBusy(button, false));
    });
}

export function setBusy(button, busy) {
    if (busy && !button.dataset.busy) {
        button.dataset.busy = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        spinner.setAttribute('aria-hidden', 'true');
        button.prepend(spinner);
    } else if (!busy && button.dataset.busy) {
        button.innerHTML = button.dataset.busy;
        delete button.dataset.busy;
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}
