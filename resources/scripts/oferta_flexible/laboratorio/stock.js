import { setBusy } from '../administracion';

export function initializeLaboratoryStock() {
    document.querySelectorAll('[data-laboratorio-stock]').forEach(initializePanel);
}

function initializePanel(panel) {
    const button = panel.querySelector('[data-stock-refresh]');
    const content = panel.querySelector('[data-stock-content]');
    const error = panel.querySelector('[data-stock-error]');
    const updated = panel.querySelector('[data-stock-updated]');
    const interval = Math.max(1, Number(panel.dataset.stockInterval) || 120) * 1000;
    let timer = null;
    let busy = false;

    function refresh() {
        if (busy || document.hidden) {
            return;
        }
        busy = true;
        error.hidden = true;
        setBusy(button, true);
        content.setAttribute('aria-busy', 'true');
        window.$.ajax({ url: panel.dataset.stockUrl, method: 'GET', dataType: 'json' })
            .done((data) => {
                content.innerHTML = data.html;
                updated.textContent = `Última actualización: ${data.generado_en}`;
            })
            .fail(() => {
                error.textContent = 'No se pudieron actualizar los insumos. Vuelva a intentarlo.';
                error.hidden = false;
            })
            .always(() => {
                busy = false;
                content.removeAttribute('aria-busy');
                setBusy(button, false);
            });
    }

    function schedule() {
        window.clearInterval(timer);
        timer = null;
        if (!document.hidden) {
            timer = window.setInterval(refresh, interval);
        }
    }

    button.addEventListener('click', refresh);
    document.addEventListener('visibilitychange', () => {
        schedule();
        if (!document.hidden) {
            refresh();
        }
    });
    window.addEventListener('pagehide', () => window.clearInterval(timer));
    window.addEventListener('pageshow', schedule);
    schedule();
}
