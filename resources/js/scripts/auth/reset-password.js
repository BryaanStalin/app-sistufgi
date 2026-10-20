import $ from 'jquery';
import Swal from 'sweetalert2';

const $form = $('#reset-password-form');

if ($form.length) {
    const $submitBtn         = $('#submit-btn');
    const $btnText           = $('#btn-text');
    const $spinner           = $('#loading-spinner');
    const $errorAlert        = $('#error-alert');
    const $errorMessage      = $('#error-message');
    const $password          = $('#password');
    const $passwordConfirm   = $('#password_confirmation');
    const $passwordError     = $('#password-error');
    const $passwordConfirmError = $('#password_confirmation-error');

    const BTN_TEXT      = 'Restablecer contraseña';
    const BTN_TEXT_LOAD = 'Procesando...';
    const LOGIN_URL     = $form.data('login-url') || '/login';

    let submitting = false;

    const setLoading = (loading) => {
        submitting = loading;
        $submitBtn.prop('disabled', loading);
        $btnText.text(loading ? BTN_TEXT_LOAD : BTN_TEXT);
        $spinner.toggleClass('d-none', !loading);
    };

    const clearErrors = () => {
        $password.add($passwordConfirm).removeClass('is-invalid');
        $passwordError.add($passwordConfirmError).removeClass('show').text('');
        $errorAlert.addClass('d-none');
    };

    const showAlertError = (message) => {
        $errorMessage.text(message);
        $errorAlert.removeClass('d-none');
    };

    const showFieldError = ($field, $error, message) => {
        $field.addClass('is-invalid');
        $error.text(message).addClass('show');
    };

    $('.toggle-password').on('click', function () {
        const targetId = $(this).data('target');
        const $input   = $(`#${targetId}`);
        const $icon    = $(this).find('i');

        const isPassword = $input.attr('type') === 'password';
        $input.attr('type', isPassword ? 'text' : 'password');
        $icon.toggleClass('bi-eye bi-eye-slash');
    });

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
                        title: 'Contraseña actualizada',
                        text: response.message ?? 'Puedes iniciar sesión con tu nueva contraseña.',
                        confirmButtonText: 'Ir al inicio',
                    }).then(() => {
                        window.location.href = response.redirect ?? LOGIN_URL;
                    });
                    return;
                }

                showAlertError(response?.message ?? 'No se pudo restablecer la contraseña.');
            })
            .fail((xhr) => {
                setLoading(false);
                handleError(xhr);
            });
    });

    $password.add($passwordConfirm).on('input', function () {
        $(this).removeClass('is-invalid');
        $(`#${this.id}-error`).removeClass('show').text('');
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

        // 429 — throttle
        if (xhr.status === 429) {
            const retryAfter = Number(xhr.getResponseHeader('Retry-After'));
            const minutes = Number.isFinite(retryAfter) && retryAfter > 0
                ? Math.ceil(retryAfter / 60)
                : 1;
            Swal.fire({
                icon: 'warning',
                title: 'Demasiadas solicitudes',
                text: `Espera ${minutes} minuto(s) antes de reintentar.`,
            });
            showAlertError(`Demasiadas solicitudes. Espera ${minutes} minuto(s).`);
            return;
        }

        // 422 — validación
        if (xhr.status === 422) {
            const errors = data.errors ?? {};

            if (errors.email?.[0]) {
                Swal.fire({
                    icon: 'error',
                    title: 'Enlace inválido',
                    text: errors.email[0],
                    confirmButtonText: 'Solicitar uno nuevo',
                }).then(() => {
                    window.location.href = '/forgot-password';
                });
                return;
            }

            if (errors.token?.[0]) {
                showAlertError(errors.token[0]);
                return;
            }

            if (data.message) showAlertError(data.message);

            const isConfirmationError = (msg) =>
                /coincid|match|confirm/i.test(msg);

            if (errors.password?.[0]) {
                if (isConfirmationError(errors.password[0])) {
                    showFieldError($passwordConfirm, $passwordConfirmError, errors.password[0]);
                    $passwordConfirm.trigger('focus');
                } else {
                    showFieldError($password, $passwordError, errors.password[0]);
                    $password.trigger('focus');
                }
            }

            if (errors.password_confirmation?.[0]) {
                showFieldError($passwordConfirm, $passwordConfirmError, errors.password_confirmation[0]);
            }

            return;
        }

        // 403 — acceso denegado
        if (xhr.status === 403) {
            const message = data.message ?? 'No tienes permiso para realizar esta acción.';
            Swal.fire({ icon: 'error', title: 'Acceso denegado', text: message });
            showAlertError(message);
            return;
        }

        // Fallback
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Ocurrió un error inesperado. Intente nuevamente.',
        });
    }
}