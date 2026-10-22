import { setBusy } from './administracion';

export function initializeActions() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-url]');
        if (!button || button.disabled) {
            return;
        }
        setBusy(button, true);
        const result = await window.Swal.fire({
            title: button.dataset.confirm || '¿Confirmar la operación?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar',
        });
        if (!result.isConfirmed) {
            setBusy(button, false);
            return;
        }
        try {
            const response = await window.$.ajax({
                url: button.dataset.deleteUrl,
                method: 'DELETE',
                dataType: 'json',
                headers: { Accept: 'application/json' },
            });
            await window.Swal.fire({ icon: 'success', text: response.message });
            window.location.reload();
        } catch (error) {
            await window.Swal.fire({
                icon: 'error',
                title: 'No se pudo completar',
                text: error.responseJSON?.message || 'Intente nuevamente.',
            });
        } finally {
            setBusy(button, false);
        }
    });
    document.querySelectorAll('[data-confirm-form]').forEach((form) => {
        let confirmed = false;
        let confirming = false;
        form.addEventListener('submit', async (event) => {
            if (confirmed) {
                return;
            }
            event.preventDefault();
            if (confirming) {
                return;
            }
            confirming = true;
            const result = await window.Swal.fire({
                title: form.dataset.confirmForm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, confirmar',
                cancelButtonText: 'Cancelar',
            });
            if (result.isConfirmed) {
                confirmed = true;
                form.requestSubmit();
            }
            confirming = false;
        });
    });
}
