import $ from 'jquery';
import 'bootstrap';
import Swal from 'sweetalert2';

window.$ = window.jQuery = $;
window.Swal = Swal;

import 'bootstrap-icons/font/bootstrap-icons.css';

// CSRF helper
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrf) {
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
    });
}

// Carga de scripts por página (solo auth)
const pages = import.meta.glob('./scripts/auth/**/*.js');

(async () => {
    const page = document.body.dataset.page;
    if (!page) return;

    const path = `./scripts/${page}.js`;
    if (!pages[path]) {
        console.warn(`[guest] No existe: ${path}`);
        return;
    }

    try {
        await pages[path]();
    } catch (err) {
        console.error(`[guest:${page}]`, err);
    }
})();