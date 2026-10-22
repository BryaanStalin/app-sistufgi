<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Hoja de ruta</title>
        <style>
            @page {
                margin: 34pt;
            }

            body {
                margin: 0;
                color: #171717;
                font-family: 'DejaVu Sans', sans-serif;
                font-size: 9pt;
                line-height: 1.25;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            td,
            th {
                vertical-align: middle;
                word-wrap: break-word;
            }

            tr {
                page-break-inside: avoid;
            }

            .encabezado {
                margin-bottom: 12pt;
                border-bottom: 1.5pt solid #222;
            }

            .encabezado td {
                padding-bottom: 10pt;
            }

            .institucion {
                width: 27%;
            }

            .logo {
                width: 132pt;
                height: 55pt;
                background-color: #02182e;
            }

            .titulo {
                width: 33%;
                padding-right: 7pt;
                padding-left: 7pt;
                text-align: center;
            }

            h1 {
                margin: 0;
                font-size: 17pt;
                line-height: 1.15;
            }

            .campania {
                width: 40%;
                text-align: right;
            }

            .nombre-campania {
                display: block;
                margin-bottom: 6pt;
                font-weight: bold;
            }

            h2 {
                margin: 10pt 0 5pt;
                font-size: 10pt;
            }

            .datos td {
                padding: 4pt 6pt;
                border: 0.6pt solid #555;
                line-height: 1.15;
            }

            .etiqueta {
                display: block;
                margin-bottom: 2pt;
                color: #333;
                font-size: 8pt;
                line-height: 1.1;
            }

            .nombre-paciente {
                font-size: 11pt;
                font-weight: bold;
            }

            .signos {
                margin-top: 10pt;
            }

            .signos td {
                height: 29pt;
                padding: 4pt;
                border: 0.6pt solid #555;
                font-size: 8pt;
                text-align: center;
            }

            .servicios th {
                padding: 5pt 4pt;
                border: 0.6pt solid #333;
                background-color: #ededed;
                font-size: 8pt;
                text-align: center;
            }

            .servicios td {
                height: {{ $altoServicio ?? 56 }}pt;
                padding: 3pt 5pt;
                border: 0.6pt solid #333;
                font-size: {{ $fuenteServicio ?? 10 }}pt;
                line-height: 1.1;
            }

            .servicio {
                width: 30%;
            }

            .sello {
                width: 20%;
            }

            .servicios .mitad-vacia {
                border: 0;
            }

            .nota {
                margin: 8pt 0 0;
                color: #333;
                font-size: 8pt;
            }
        </style>
    </head>
    <body>
        <table class="encabezado">
            <tr>
                <td class="institucion">
                    <img class="logo" src="{{ $logo ?? '' }}" alt="DIRIS Lima Norte">
                </td>
                <td class="titulo">
                    <h1>HOJA DE RUTA</h1>
                </td>
                <td class="campania">
                    <span class="nombre-campania">{{ $hoja->campania->nombre }}</span>
                    <span class="etiqueta">Fecha de atención</span>
                    <strong>{{ $hoja->fecha_atencion->format('d/m/Y') }}</strong>
                </td>
            </tr>
        </table>
        <h2>Datos del paciente</h2>
        <table class="datos">
            <tr>
                <td colspan="3">
                    <span class="etiqueta">Nombres y apellidos</span>
                    <span class="nombre-paciente">{{ $hoja->paciente->nombres_apellidos }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="etiqueta">Documento</span>
                    {{ $hoja->paciente->tipo_doc ?: 'Sin tipo' }}: {{ $hoja->paciente->dni ?: 'Sin número' }}
                </td>
                <td>
                    <span class="etiqueta">Edad</span>
                    {{ $edad }} años
                </td>
                <td>
                    <span class="etiqueta">Sexo</span>
                    {{
                        match ($hoja->paciente->sexo) {
                            'M' => 'Masculino',
                            'F' => 'Femenino',
                            default => 'No registrado',
                        }
                    }}
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="etiqueta">Médico que registró triaje</span>
                    {{ $hoja->medico->nombre }}
                </td>
                <td>
                    <span class="etiqueta">Fecha de Registro</span>
                    {{ $emitidoAt->format('d/m/Y H:i:s') }}
                </td>
            </tr>
        </table>
        <table class="signos">
            <tr>
                <td>PESO: ______ Kg.</td>
                <td>TALLA: ______ Mts.</td>
                <td>PA: _____ / _____ mmHg</td>
                <td>Sat O2: ______ %</td>
                <td>T°: ______ C°</td>
            </tr>
        </table>
        <h2>Servicios indicados</h2>
        <table class="servicios">
            <thead>
                <tr>
                    <th class="servicio">Servicio</th>
                    <th class="sello">Sello de atención</th>
                    <th class="servicio">Servicio</th>
                    <th class="sello">Sello de atención</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hoja->detalles->chunk(2) as $pareja)
                    <tr>
                        @foreach ($pareja as $detalle)
                            <td>{{ $detalle->campaniaServicio->servicioUps->DESCRIPCION_UPS }}</td>
                            <td>&nbsp;</td>
                        @endforeach
                        @if ($pareja->count() === 1)
                            <td colspan="2" class="mitad-vacia">&nbsp;</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="nota">Presente esta hoja en cada servicio para registrar su atención.</p>
    </body>
</html>
