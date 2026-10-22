import '../../css/oferta_flexible/administracion.css';

const submissionStates = new WeakMap();

export function initializeAdministration() {
    const root = document.querySelector('.oferta-admin');
    if (!root) {
        return;
    }
    initializeTooltips(root);
    initializeSelects(root);
    initializeValidation(root);
    initializeSubmissions(root);
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
}

function initializeSubmissions(root) {
    root.addEventListener('submit', handleSubmission);
    window.addEventListener('pageshow', () => {
        root.querySelectorAll('form').forEach((form) => submissionStates.delete(form));
        root.querySelectorAll('[data-busy]').forEach((button) => setBusy(button, false));
    });
}

async function handleSubmission(event) {
    const form = event.target;
    if (!form.matches('form[data-admin-submit], form[data-confirm-form]') || event.defaultPrevented) {
        return;
    }
    if (!submissionStates.has(form)) {
        submissionStates.set(form, { confirming: false, confirmed: false, submitting: false });
    }
    const state = submissionStates.get(form);
    if (state.submitting) {
        event.preventDefault();
        return;
    }
    if (form.hasAttribute('data-confirm-form') && !state.confirmed) {
        event.preventDefault();
        if (state.confirming) {
            return;
        }
        state.confirming = true;
        try {
            const result = await confirmAction(form.dataset.confirmForm);
            if (result.isConfirmed && submissionStates.get(form) === state) {
                state.confirmed = true;
                form.requestSubmit(event.submitter || undefined);
            }
        } finally {
            state.confirmed = false;
            state.confirming = false;
        }
        return;
    }
    state.submitting = true;
    form.querySelectorAll('[type="submit"]:not(:disabled)').forEach((button) => setBusy(button, true));
}

export function confirmAction(message) {
    return window.Swal.fire({
        title: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar',
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
