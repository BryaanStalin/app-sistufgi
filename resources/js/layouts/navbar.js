import $ from 'jquery';
import Swal from 'sweetalert2';

const MOBILE = window.matchMedia('(max-width: 991.98px)');
const sidebarToggle = document.getElementById('sidebarToggle');

function isSidebarExpanded() {
    return MOBILE.matches
        ? document.body.classList.contains('sidebar-open')
        : !document.body.classList.contains('sidebar-collapsed');
}

function syncAria() {
    sidebarToggle?.setAttribute('aria-expanded', String(isSidebarExpanded()));
}

sidebarToggle?.addEventListener('click', () => {
    document.body.classList.toggle(MOBILE.matches ? 'sidebar-open' : 'sidebar-collapsed');
    syncAria();
});

document.addEventListener('click', (e) => {
    if (!MOBILE.matches || !document.body.classList.contains('sidebar-open')) return;
    if (e.target.closest('.sidebar') || e.target.closest('#sidebarToggle')) return;
    document.body.classList.remove('sidebar-open');
    syncAria();
});

MOBILE.addEventListener('change', () => {
    document.body.classList.remove('sidebar-open');
    syncAria();
});

syncAria();

const dateEl = document.getElementById('current-date');
let clockInterval = null;

if (dateEl) {
    const updateDateTime = () => {
        dateEl.textContent = new Date().toLocaleString('es-ES', {
            weekday: 'long', year: 'numeric', month: 'long',
            day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit',
        });
    };
    updateDateTime();
    clockInterval = setInterval(updateDateTime, 1000);
}

const btnLogout = document.getElementById('btnLogout');

btnLogout?.addEventListener('click', (e) => {
    e.preventDefault();
    const url = btnLogout.dataset.url;
    if (!url) {
        console.error('[logout] falta data-url en #btnLogout');
        return;
    }

    Swal.fire({
        title: '¿Cerrar sesión?',
        text: '¿Estás seguro que deseas cerrar sesión?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Sí, cerrar sesión',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (!result.isConfirmed) return;

        $.post(url)
            .done((response) => {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Hasta luego!',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => { window.location.href = response.redirect; });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'No se pudo cerrar sesión',
                        text: response.message ?? 'Intentá de nuevo.',
                    });
                }
            })
            .fail((xhr) => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.status === 419
                        ? 'Tu sesión expiró. Refresca la página.'
                        : 'Error al cerrar sesión.',
                });
            });
    });
});