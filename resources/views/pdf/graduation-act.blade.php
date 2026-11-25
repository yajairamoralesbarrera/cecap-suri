<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Acta de Grado {{ $graduation->act_number }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11.5px; color: #1e293b; margin: 30px; line-height: 1.5; }
    .header { text-align: center; border-bottom: 3px solid #107050; padding-bottom: 12px; margin-bottom: 18px; }
    .title { font-size: 16px; font-weight: bold; color: #107050; }
    .box { background: #f8fafc; border: 1px solid #cbd5e1; padding: 12px; margin: 14px 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; }
    th { background: #f1f5f9; width: 30%; text-align: left; }
</style>
</head>
<body>
    <div class="header">
        @if(!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="SURÍ" style="height:68px; margin-bottom:6px;"><br>
        @endif
        <div class="title">{{ $settings->institution_name }}</div>
        <div>ACTA OFICIAL DE GRADUACIÓN N.° {{ $graduation->act_number }}</div>
        <div style="font-size:10px; color:#475569;">Tomo {{ $graduation->tomo }} · Folio {{ $graduation->folio }} · Asiento {{ $graduation->asiento }}</div>
    </div>

    <p>En {{ $settings->country }}, al ser el día <strong>{{ $graduation->graduation_date?->format('d/m/Y') }}</strong>, reunida la Dirección General y la Coordinación Académica de <strong>{{ $settings->institution_name }}</strong>, se deja constancia formal de la aprobación y otorgamiento de título a la siguiente graduada:</p>

    <table>
        <tr><th>Nombre Completo</th><td>{{ $graduation->student?->full_name }}</td></tr>
        <tr><th>Identificación</th><td>{{ $graduation->student?->identification_number }}</td></tr>
        <tr><th>Programa / Curso</th><td>{{ $graduation->enrollment?->edition?->course?->name }} ({{ $graduation->enrollment?->edition?->course?->program_type_label }})</td></tr>
        <tr><th>Edición</th><td>{{ $graduation->enrollment?->edition?->name }}</td></tr>
        <tr><th>Calificación Final</th><td>{{ number_format((float) $graduation->final_grade, 2) }} / 100</td></tr>
        <tr><th>Porcentaje de Asistencia</th><td>{{ number_format((float) $graduation->attendance_percent, 2) }}%</td></tr>
        <tr><th>Solvencia Financiera</th><td>
            @if($graduation->is_financially_solvent)
                Solvente
            @elseif($graduation->solvency_exception_authorized)
                Excepción autorizada por {{ $graduation->solvencyExceptionAuthorizer?->name }} — Motivo: {{ $graduation->solvency_exception_reason }}
            @else
                No requerida
            @endif
        </td></tr>
        <tr><th>Libro Oficial</th><td>Tomo {{ $graduation->tomo }} · Folio {{ $graduation->folio }} · Asiento {{ $graduation->asiento }}</td></tr>
        <tr><th>Código Verificación QR</th><td>{{ $graduation->verification_token }}</td></tr>
    </table>

    <table style="margin-top:40px; border:none;">
        <tr>
            <td style="border:none; text-align:center; width:50%; padding-top:26px;">
                <div style="border-top:1px solid #334155; width:80%; margin:0 auto 4px auto;"></div>
                <strong>{{ $settings->director_name }}</strong><br>{{ $settings->director_title }}
            </td>
            <td style="border:none; text-align:center; width:50%; padding-top:26px;">
                <div style="border-top:1px solid #334155; width:80%; margin:0 auto 4px auto;"></div>
                <strong>{{ $graduation->issuer?->name }}</strong><br>Emisión y Registro Académico
            </td>
        </tr>
    </table>
</body>
</html>
