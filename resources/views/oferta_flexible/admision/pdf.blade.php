<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja de ruta</title>
    <style>
        @page { margin: 32pt 38pt 40pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #202b35; line-height: 1.4; }
        .encabezado { width: 100%; border-bottom: 2pt solid #263c50; margin-bottom: 20pt; }
        .encabezado td { vertical-align: middle; padding: 0 0 14pt; }
        .logo-reservado { width: 86pt; height: 54pt; }
        h1 { font-size: 20pt; margin: 0 0 4pt; }
        .campania { font-size: 12pt; margin: 0; }
        .fecha { margin: 0; color: #506070; }
        h2 { font-size: 11pt; margin: 16pt 0 8pt; }
        .datos, .servicios { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .datos td { padding: 7pt 9pt; border: 0.5pt solid #ccd3d9; vertical-align: top; word-wrap: break-word; }
        .etiqueta { display: block; font-size: 8pt; color: #506070; margin-bottom: 3pt; }
        .servicios th { background: #eef1f4; text-align: left; font-size: 9pt; }
        .servicios td, .servicios th { padding: 9pt; border-bottom: 0.5pt solid #ccd3d9; word-wrap: break-word; }
        .servicios thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .emision { margin-top: 22pt; font-size: 8pt; color: #506070; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="logo-reservado">&nbsp;</td>
            <td><h1>Hoja de ruta</h1><p class="campania">{{ $hoja->campania->nombre }}</p></td>
        </tr>
    </table>
    <p class="fecha">Fecha de atención: <strong>{{ $hoja->fecha_atencion->format('d/m/Y') }}</strong></p>
    <h2>Datos del paciente</h2>
    <table class="datos">
        <tr><td colspan="3"><span class="etiqueta">Nombres y apellidos</span>{{ $hoja->paciente->nombres_apellidos }}</td></tr>
        <tr>
            <td><span class="etiqueta">Documento</span>{{ $hoja->paciente->tipo_doc ?: 'Sin tipo' }}: {{ $hoja->paciente->dni ?: 'Sin número' }}</td>
            <td><span class="etiqueta">Edad</span>{{ $edad }} años</td>
            <td><span class="etiqueta">Sexo</span>{{ match($hoja->paciente->sexo) { 'M' => 'Masculino', 'F' => 'Femenino', default => 'No registrado' } }}</td>
        </tr>
        <tr><td colspan="3"><span class="etiqueta">Médico que registró</span>{{ $hoja->medico->nombre }}</td></tr>
    </table>
    <h2>Servicios indicados</h2>
    <table class="servicios">
        <thead><tr><th>Servicio</th></tr></thead>
        <tbody>
            @foreach($hoja->detalles as $detalle)
                <tr><td>{{ $detalle->campaniaServicio->servicioUps->DESCRIPCION_UPS }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <p class="emision">Fecha y hora de emisión: {{ $emitidoAt->format('d/m/Y H:i:s') }} (Lima)</p>
</body>
</html>
