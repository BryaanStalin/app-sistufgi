import { confirmAction, setBusy } from './administracion';

const pendingButtons = new WeakSet();

export function initializeActions() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-url]');
        if (!button || button.disabled || pendingButtons.has(button)) {
            return;
        }
        pendingButtons.add(button);
        try {
            const result = await confirmAction(button.dataset.confirm || '¿Confirmar la operación?');
            if (!result.isConfirmed) {
                return;
            }
            setBusy(button, true);
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
            pendingButtons.delete(button);
        }
    });
}
