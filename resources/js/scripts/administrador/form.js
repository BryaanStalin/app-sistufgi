export function initializeForm() {
    const $ = window.$;
    document.querySelectorAll('[data-select2]').forEach((select) => {
        const $select = $(select);
        if ($select.hasClass('select2-hidden-accessible')) return;

        $select.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: select.dataset.placeholder,
            allowClear: !select.required,
            language: {
                noResults: () => 'No se encontraron resultados',
                searching: () => 'Buscando…',
                removeAllItems: () => 'Quitar todas las opciones',
            },
        });

        const selection = $select.next('.select2').find('.select2-selection');
        selection.attr('aria-labelledby', `${select.id}-label`);
        if (select.hasAttribute('aria-describedby')) {
            selection.attr('aria-describedby', select.getAttribute('aria-describedby'));
        }
        if (select.classList.contains('is-invalid')) selection.attr('aria-invalid', 'true');
        select.addEventListener('invalid', (event) => {
            event.preventDefault();
            $select.select2('open');
        });
    });

    const password = document.getElementById('password');
    const confirmation = document.getElementById('password_confirmation');
    if (password && confirmation) {
        const updateConfirmation = () => { confirmation.required = password.required || password.value !== ''; };
        password.addEventListener('input', updateConfirmation);
        updateConfirmation();
    }
}
