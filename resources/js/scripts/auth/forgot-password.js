import $ from 'jquery';
import Swal from 'sweetalert2';

const $form = $('#forgot-password-form');

if ($form.length) {
    const $submitBtn    = $('#submit-btn');
    const $btnText      = $('#btn-text');
    const $spinner      = $('#loading-spinner');
    const $errorAlert   = $('#error-alert');
    const $errorMessage = $('#error-message');
    const $email        = $('#email');
    const $emailError   = $('#email-error');

    const BTN_TEXT      = 'Enviar enlace de recuperación';
    const BTN_TEXT_LOAD = 'Enviando...';

    let submitting = false;

    const setLoading = (loading) => {
        submitting = loading;
        $submitBtn.prop('disabled', loading);
        $btnText.text(loading ? BTN_TEXT_LOAD : BTN_TEXT);
        $spinner.toggleClass('d-none', !loading);
    };

    const clearErrors = () => {
        $email.removeClass('is-invalid');
        $emailError.removeClass('show').text('');
        $errorAlert.addClass('d-none');
    };

    const showAlertError = (message) => {
        $errorMessage.text(message);
        $errorAlert.removeClass('d-none');
    };

    const showFieldError = (message) => {
        $email.addClass('is-invalid');
        $emailError.text(message).addClass('show');
    };

    $form.on('submit', function (e) {
        e.preventDefault();
        if (submitting) return;

        clearErrors();
        setLoading(true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
        })
            .done((response) => {
                setLoading(false);

                if (response?.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Enlace enviado',
                        text: response.message ?? 'Revisa tu bandeja de entrada.',
                    });
                    $form.trigger('reset');
                    return;
                }

                showAlertError(response?.message ?? 'No se pudo procesar la solicitud.');
            })
            .fail((xhr) => {
                setLoading(false);
                handleError(xhr);
            });
    });

    // Limpiar error al escribir
    $email.on('input', function () {
        $email.removeClass('is-invalid');
        $emailError.removeClass('show').text('');
    });

    function handleError(xhr) {
        const data = xhr.responseJSON ?? {};

        // 419 — CSRF expirado
        if (xhr.status === 419) {
            Swal.fire({
                icon: 'warning',
                title: 'Sesión expirada',
                text: 'Recarga la página e intenta nuevamente.',
            }).then(() => window.location.reload());
            return;
        }

        // 429 — rate limit (throttle de Laravel)
        if (xhr.status === 429) {
            const retryAfter = Number(xhr.getResponseHeader('Retry-After'));
            const minutes = Number.isFinite(retryAfter) && retryAfter > 0
                ? Math.ceil(retryAfter / 60)
                : 1;

            Swal.fire({
                icon: 'warning',
                title: 'Demasiadas solicitudes',
                text: `Espera ${minutes} minuto(s) antes de reintentar.`,
                confirmButtonText: 'Entendido',
            });
            showAlertError(`Demasiadas solicitudes. Espera ${minutes} minuto(s).`);
            return;
        }

        // 422 — validación
        if (xhr.status === 422) {
            const errors = data.errors ?? {};

            if (data.message) showAlertError(data.message);
            if (errors.email?.[0]) showFieldError(errors.email[0]);

            $email.trigger('focus');
            return;
        }

        // 403 — acceso denegado
        if (xhr.status === 403) {
            const message = data.message ?? 'No tienes permiso para realizar esta acción.';
            Swal.fire({
                icon: 'error',
                title: 'Acceso denegado',
                text: message,
            });
            showAlertError(message);
            return;
        }

        // Fallback
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error en el sistema. Intente nuevamente.',
        });
    }
}
