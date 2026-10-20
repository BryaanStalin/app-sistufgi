import $ from 'jquery';
import Swal from 'sweetalert2';

const $form = $('#login-form');

if ($form.length) {
    const $submitBtn = $('#submit-btn');
    const $btnText   = $('#btn-text');
    const $spinner   = $('#loading-spinner');
    const $errorAlert = $('#error-alert');
    const $errorMessage = $('#error-message');
    const $fields = $('#user, #password');

    let submitting = false;

    const setLoading = (loading) => {
        submitting = loading;
        $submitBtn.prop('disabled', loading);
        $btnText.text(loading ? 'Verificando...' : 'Iniciar Sesión');
        $spinner.toggleClass('spinner-hidden', !loading);
    };

    const clearErrors = () => {
        $fields.removeClass('is-invalid');
        $('#user-error, #password-error').removeClass('show').text('');
        $errorAlert.addClass('d-none');
    };

    const showAlertError = (message) => {
        $errorMessage.text(message);
        $errorAlert.removeClass('d-none');
    };

    const showFieldError = (field, message) => {
        $(`#${field}`).addClass('is-invalid');
        $(`#${field}-error`).text(message).addClass('show');
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
                if (response?.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Bienvenido!',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false,
                    }).then(() => {
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        } else {
                            console.error('[login] falta response.redirect');
                            window.location.reload();
                        }
                    });
                    return;
                }

                setLoading(false);
                showAlertError(response?.message ?? 'No se pudo iniciar sesión.');
            })
            .fail((xhr) => {
                setLoading(false);
                handleError(xhr);
            });
    });

    $fields.on('input', function () {
        $(this).removeClass('is-invalid');
        $(`#${this.id}-error`).removeClass('show').text('');
    });

    function handleError(xhr) {
        const data = xhr.responseJSON ?? {};

        if (xhr.status === 419) {
            Swal.fire({
                icon: 'warning',
                title: 'Sesión expirada',
                text: 'Tu sesión expiró. Recarga la página e intenta de nuevo.',
            }).then(() => window.location.reload());
            return;
        }

        if (xhr.status === 429) {
            const retryAfter = Number(xhr.getResponseHeader('Retry-After'));
            const minutes = Number.isFinite(retryAfter) && retryAfter > 0
                ? Math.ceil(retryAfter / 60)
                : 1;

            Swal.fire({
                icon: 'warning',
                title: 'Cuenta bloqueada',
                text: `Demasiados intentos fallidos. Espere ${minutes} minuto(s) para intentar nuevamente.`,
                confirmButtonText: 'Entendido',
            });
            showAlertError(`Cuenta bloqueada temporalmente. Espere ${minutes} minuto(s).`);
            return;
        }

        if (xhr.status === 403) {
            const message = data.message ?? 'No tienes permiso para acceder.';
            Swal.fire({
                icon: 'error',
                title: 'Acceso denegado',
                text: message,
                confirmButtonText: 'Entendido',
            });
            showAlertError(message);
            return;
        }

        // 422 — validación
        if (xhr.status === 422) {
            const errors = data.errors ?? {};

            if (data.message) showAlertError(data.message);
            if (errors.user?.[0])     showFieldError('user', errors.user[0]);
            if (errors.password?.[0]) showFieldError('password', errors.password[0]);

            // autofocus al primer campo inválido
            const firstInvalid = Object.keys(errors)[0];
            if (firstInvalid) $(`#${firstInvalid}`).trigger('focus');
            return;
        }

        // Fallback
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error en el sistema. Intente más tarde.',
        });
    }
}