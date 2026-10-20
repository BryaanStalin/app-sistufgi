export function initializeTable(selector) {
    const { $, DataTable, Swal } = window;
    const table = document.querySelector(selector);
    if (!table || DataTable.isDataTable(table)) return;

    new DataTable(table, {
        order: [],
        columnDefs: [{ targets: -1, orderable: false, searchable: false }],
        language: {
            emptyTable: 'No hay registros disponibles',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Sin registros',
            infoFiltered: '(filtrado de _MAX_ registros)',
            lengthMenu: 'Mostrar _MENU_ registros',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron resultados',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' },
        },
    });

    table.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-delete-url]');
        if (!button || button.disabled) return;

        const result = await Swal.fire({
            title: '¿Inactivar registro?',
            text: 'El registro se conservará como inactivo en el sistema.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, inactivar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#1a4f7c',
        });
        if (!result.isConfirmed) return;

        button.disabled = true;
        try {
            const response = await $.ajax({
                url: button.dataset.deleteUrl,
                method: 'DELETE',
                dataType: 'json',
                headers: { Accept: 'application/json' },
            });
            if (!response.success) throw new Error(response.message || 'No se pudo inactivar el registro.');
            await Swal.fire({ icon: 'success', title: 'Registro inactivado', text: response.message });
            window.location.reload();
        } catch (error) {
            await Swal.fire({
                icon: 'error',
                title: 'No se pudo inactivar',
                text: error.responseJSON?.message || error.message || 'Intente nuevamente.',
            });
        } finally {
            button.disabled = false;
        }
    });
}
