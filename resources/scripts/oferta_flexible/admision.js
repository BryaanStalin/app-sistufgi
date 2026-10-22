import { initializeAdministration, initializeTooltips, setBusy } from './administracion';
import '../../css/oferta_flexible/admision.css';

initializeAdministration();

const root = document.getElementById('admision');
const form = document.getElementById('admision-filtros');

if (root && form) {
    const list = document.getElementById('admision-listado');
    const status = document.getElementById('admision-estado');
    const error = document.getElementById('admision-error');
    const refresh = document.getElementById('admision-actualizar');
    const filterButton = document.getElementById('admision-filtrar');
    const lastUpdated = document.getElementById('admision-ultima-actualizacion');
    const loading = document.getElementById('admision-cargando');
    const pollStatus = document.getElementById('admision-poll-estado');
    const pollDot = document.getElementById('admision-poll-dot');
    const interval = Number(root.dataset.pollInterval) || 120000;
    let filters = new URLSearchParams(new FormData(form));
    const initialPage = new URLSearchParams(window.location.search).get('page');
    if (initialPage) {
        filters.set('page', initialPage);
    }
    let pending;
    let sequence = 0;
    let timer;

    function updatePollingStatus(active) {
        pollDot.dataset.active = String(active);
        pollStatus.textContent = active
            ? `Refresco automático activo · cada ${interval / 1000} segundos.`
            : 'Refresco automático en pausa.';
    }

    function updateContext() {
        const template = list.querySelector('template[data-admision-contexto]');
        if (template) {
            document.getElementById('admision-contexto').replaceChildren(template.content);
            template.remove();
        }
        initializeTooltips(list);
    }

    function disposeTooltips() {
        list.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
            window.bootstrap.Tooltip.getInstance(element)?.dispose();
        });
    }

    const showError = (message) => {
        error.textContent = message;
        error.classList.remove('d-none');
    };

    const load = (preserveError = false) => {
        const request = ++sequence;
        pending?.abort();
        setBusy(refresh, true);
        filterButton.disabled = true;
        loading.hidden = false;
        list.setAttribute('aria-busy', 'true');
        status.textContent = 'Actualizando listado…';
        pending = $.ajax({ url: root.dataset.url, data: filters.toString(), dataType: 'json' })
            .done((response) => {
                if (request !== sequence) {
                    return;
                }
                disposeTooltips();
                list.innerHTML = response.html;
                updateContext();
                if (preserveError !== true) {
                    error.classList.add('d-none');
                }
                const time = new Date().toLocaleTimeString('es-PE', {
                    timeZone: 'America/Lima',
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
                lastUpdated.textContent = `Última actualización: ${time}`;
                status.textContent = '';
            })
            .fail((xhr, reason) => {
                if (request !== sequence || reason === 'abort') {
                    return;
                }
                showError(
                    xhr.responseJSON?.message ||
                        'No se pudo actualizar el listado. Revise su conexión o vuelva a iniciar sesión.',
                );
                status.textContent = 'Actualización pendiente.';
                if ([401, 403, 419].includes(xhr.status)) {
                    clearInterval(timer);
                    updatePollingStatus(false);
                }
            })
            .always(() => {
                if (request !== sequence) {
                    return;
                }
                setBusy(refresh, false);
                filterButton.disabled = false;
                loading.hidden = true;
                list.setAttribute('aria-busy', 'false');
            });
    };

    const resume = () => {
        clearInterval(timer);
        if (!document.hidden) {
            timer = setInterval(load, interval);
        }
        updatePollingStatus(!document.hidden);
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
    window.$('#admision-campania').on('change', () => form.requestSubmit());
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
        if (!button || button.disabled) {
            return;
        }
        const hoja = button.dataset.hoja;
        const campania = button.dataset.campania;
        setBusy(button, true);
        const confirmation = await Swal.fire({
            title: '¿Eliminar hoja de ruta?',
            text: `${button.dataset.paciente}. La hoja se eliminará y se liberarán sus cupos.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
        });
        if (!confirmation.isConfirmed) {
            setBusy(button, false);
            return;
        }
        $.ajax({
            url: button.dataset.url,
            method: 'DELETE',
            dataType: 'json',
            data: { campania_id: campania, hoja_id: hoja },
        })
            .done((response) => {
                status.textContent = response.message;
                load();
            })
            .fail((xhr) => {
                const messages = Object.values(xhr.responseJSON?.errors || {}).flat();
                showError(messages[0] || xhr.responseJSON?.message || 'No se pudo eliminar la hoja de ruta.');
                load(true);
            })
            .always(() => {
                setBusy(button, false);
            });
    });
    list.addEventListener('submit', (event) => {
        if (event.target.matches('.admision-imprimir')) {
            const button = event.target.querySelector('[type="submit"]');
            if (button.disabled) {
                event.preventDefault();
                return;
            }
            setBusy(button, true);
            setTimeout(() => {
                setBusy(button, false);
                if (!document.hidden) {
                    load();
                }
            }, 2000);
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            clearInterval(timer);
            updatePollingStatus(false);
            pending?.abort();
        } else {
            load();
            resume();
        }
    });
    window.addEventListener('focus', () => {
        if (!document.hidden && pending?.state() !== 'pending') {
            load();
        }
    });
    window.addEventListener('pagehide', () => {
        clearInterval(timer);
        pending?.abort();
    });
    updateContext();
    resume();
}
