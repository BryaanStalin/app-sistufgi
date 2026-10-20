import $ from 'jquery';
window.$ = window.jQuery = $;

import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import 'bootstrap-icons/font/bootstrap-icons.css';

import DataTable from 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';
import 'datatables.net-buttons/js/buttons.colVis.mjs';
import JSZip from 'jszip';

DataTable.Buttons.jszip(JSZip);
DataTable.Buttons.defaults.dom.button.className = 'btn';
window.DataTable = DataTable;
window.JSZip = JSZip;

import Swal from 'sweetalert2';
window.Swal = Swal;

import select2 from 'select2';
select2(window, $);

import './layouts/sidebar';
import './layouts/navbar';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });
}

const pages = import.meta.glob('./scripts/**/*.js');

(async () => {
    const page = document.body.dataset.page;
    if (!page) return;

    const path = `./scripts/${page}.js`;

    if (!pages[path]) {
        console.warn(`[page] No existe el script: ${path}`);
        return;
    }

    try {
        await pages[path]();
    } catch (err) {
        console.error(`[page:${page}] Error al cargar:`, err);
    }
})();