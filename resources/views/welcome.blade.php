<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'SISTUFGI-DLN') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
        <div class="card shadow-sm border-0 w-100" style="max-width: 32rem;">
            <div class="card-body p-4 p-md-5 text-center">
                <h1 class="h3 fw-semibold mb-2">{{ config('app.name', 'SISTUFGI-DLN') }}</h1>
                <p class="text-muted mb-4">
                    Sistema Integrado UFGI de la Unidad Funcional de Gestión de la Información - DIRISLN
                </p>
                
                <!-- BOTÓN DE PRUEBA ADICIONAL -->
                <div class="mb-4">
                    <button id="btn-test" class="btn btn-primary px-4 shadow-sm">
                        Probar Librerías (SweetAlert2 + jQuery)
                    </button>
                </div>

                <p class="small text-secondary mb-0">
                    Laravel {{ Illuminate\Foundation\Application::VERSION }} · PHP {{ PHP_VERSION }}
                </p>
            </div>
        </div>
    </div>

    <!-- SCRIPT DE VERIFICACIÓN -->    
    <script type="module">
        document.addEventListener('DOMContentLoaded', () => {
            if (!window.jQuery || !window.Swal) {
                console.error('❌ Algunas librerías no están disponibles.');
                return;
            }

            window.$('#btn-test').on('click', function () {
                window.Swal.fire({
                    title: '¡Ecosistema Activo!',
                    html: `
                        <p>Las librerías se cargaron correctamente.</p>
                        <strong>Laravel 13 + Vite</strong>
                        <p class="mt-2">Bootstrap 5, jQuery,
                        DataTables, SweetAlert2 y Select2.</p>
                    `,
                    icon: 'success',
                    confirmButtonText: 'Excelente'
                });
            });
        });
    </script>
</body>
</html>