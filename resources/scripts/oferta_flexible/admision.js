import '../../css/oferta_flexible/admision.css';

const root = document.getElementById('admision');
const form = document.getElementById('admision-filtros');

if (root && form) {
    const list = document.getElementById('admision-listado');
    const status = document.getElementById('admision-estado');
    const error = document.getElementById('admision-error');
    const refresh = document.getElementById('admision-actualizar');
    const interval = Number(root.dataset.pollInterval) || 120000;
    let filters = new URLSearchParams(new FormData(form));
    const initialPage = new URLSearchParams(window.location.search).get('page');
    if (initialPage) filters.set('page', initialPage);
    let pending;
    let sequence = 0;
    let timer;

    const showError = (message) => {
        error.textContent = message;
        error.classList.remove('d-none');
    };

    const load = (preserveError = false) => {
        const request = ++sequence;
        pending?.abort();
        refresh.disabled = true;
        list.setAttribute('aria-busy', 'true');
        status.textContent = 'Actualizando listado…';
        pending = $.ajax({ url: root.dataset.url, data: filters.toString(), dataType: 'json' })
            .done((response) => {
                if (request !== sequence) return;
                list.innerHTML = response.html;
                if (preserveError !== true) error.classList.add('d-none');
                status.textContent = `Actualizado a las ${new Date().toLocaleTimeString('es-PE')}.`;
            })
            .fail((xhr, reason) => {
                if (request !== sequence || reason === 'abort') return;
                showError(xhr.responseJSON?.message || 'No se pudo actualizar el listado. Revise su conexión o vuelva a iniciar sesión.');
                status.textContent = 'Actualización pendiente.';
                if ([401, 403, 419].includes(xhr.status)) clearInterval(timer);
            })
            .always(() => {
                if (request !== sequence) return;
                refresh.disabled = false;
                list.setAttribute('aria-busy', 'false');
            });
    };

    const resume = () => {
        clearInterval(timer);
        if (!document.hidden) timer = setInterval(load, interval);
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        filters = new URLSearchParams(new FormData(form));
        const url = new URL(root.dataset.url);
        url.search = filters.toString();
        if (filters.get('campania_id') !== root.dataset.campania) {
            url.searchParams.delete('medico_id');
            window.location.assign(url.href);
            return;
        }
        window.history.replaceState(null, '', url);
        load();
    });
    document.getElementById('admision-campania')?.addEventListener('change', () => form.requestSubmit());
    refresh.addEventListener('click', load);
    list.addEventListener('click', async (event) => {
        const page = event.target.closest('.admision-paginacion a');
        if (page) {
            event.preventDefault();
            filters.set('page', new URL(page.href).searchParams.get('page'));
            window.history.replaceState(null, '', `${root.dataset.url}?${filters}`);
            load();
            return;
        }
        const button = event.target.closest('.admision-eliminar');
        if (!button || button.disabled) return;
        const hoja = button.dataset.hoja;
        const campania = button.dataset.campania;
        const confirmation = await Swal.fire({ title: '¿Eliminar hoja de ruta?',
            text: 'La hoja se eliminará y se liberarán sus cupos.', icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
        if (!confirmation.isConfirmed) return;
        button.disabled = true;
        $.ajax({ url: button.dataset.url, method: 'DELETE', dataType: 'json', data: { campania_id: campania, hoja_id: hoja } })
            .done((response) => { status.textContent = response.message; load(); })
            .fail((xhr) => {
                const messages = Object.values(xhr.responseJSON?.errors || {}).flat();
                showError(messages[0] || xhr.responseJSON?.message || 'No se pudo eliminar la hoja de ruta.');
                load(true);
            })
            .always(() => { button.disabled = false; });
    });
    list.addEventListener('submit', (event) => {
        if (event.target.matches('.admision-imprimir')) setTimeout(() => { if (!document.hidden) load(); }, 2000);
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(timer);
            pending?.abort();
        } else {
            load();
            resume();
        }
    });
    window.addEventListener('focus', () => { if (!document.hidden && pending?.state() !== 'pending') load(); });
    window.addEventListener('pagehide', () => { clearInterval(timer); pending?.abort(); });
    resume();
}
