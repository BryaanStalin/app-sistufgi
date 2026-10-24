import { initializeForm } from '../../../js/scripts/administrador/form';
import { initializeAdministration } from '../administracion';

initializeAdministration();
initializeForm();

const type = document.getElementById('TIPO_UPS');
const laboratory = document.querySelector('[data-laboratorio-fields]');

function toggleLaboratory() {
    laboratory.hidden = type.value !== 'L';
    laboratory.querySelectorAll('input').forEach((input) => {
        input.disabled = laboratory.hidden;
    });
}

type.addEventListener('change', toggleLaboratory);
toggleLaboratory();
