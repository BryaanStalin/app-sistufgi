<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recuperación de contraseña</title>
</head>

<body style="font-family: Arial, sans-serif; background-color: #f8f9fa; padding: 20px;">

    <table
        width="100%"
        cellpadding="0"
        cellspacing="0"
        style="max-width: 600px; margin:auto; background-color:#ffffff; padding:30px; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.05);">
        <tr>
            <td>
                <h2 style="color:#343a40;">
                    Recuperación de contraseña
                </h2>
                <p style="font-size:16px; color:#212529;">
                    <strong>Estimado(a):</strong>
                    <span style="color:#0d6efd;">
                        {{ $nombre }}
                    </span>
                </p>
                <p style="font-size:16px; color:#495057; text-align:justify;">
                    Hemos recibido una solicitud para restablecer la contraseña
                    de su cuenta en la plataforma.
                </p>
                <p style="font-size:16px; color:#495057;">
                    Para crear una nueva contraseña, haga clic en el siguiente botón:
                </p>
                <p style="text-align:center; margin:30px 0;">
                    <a href="{{ $url }}"
                       style="
                       background-color:#0d6efd;
                       color:#ffffff;
                       padding:12px 25px;
                       text-decoration:none;
                       border-radius:5px;
                       font-size:16px;
                       display:inline-block;
                       ">
                        Restablecer contraseña
                    </a>
                </p>
                <p style="font-size:15px; color:#495057; text-align:justify;">
                    Este enlace tendrá una vigencia de
                    <strong>5 minutos</strong>.
                    Después de ese tiempo deberá solicitar nuevamente
                    la recuperación de contraseña.
                </p>
                <p style="font-size:15px; color:#495057; text-align:justify;">
                    Si usted no realizó esta solicitud, puede ignorar este correo.
                    Su contraseña actual continuará siendo válida.
                </p>
                <p style="font-size:16px; color:#495057;">
                    Atentamente,<br>
                    <strong>
                        Equipo de la Unidad Funcional de Gestión de la Información
                    </strong>
                    <br>
                    <strong>
                        OGTI - DIRIS Lima Norte - 2026
                    </strong>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
