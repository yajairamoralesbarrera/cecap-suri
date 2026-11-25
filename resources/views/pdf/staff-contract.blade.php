<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Contrato {{ $contract->contract_number }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 28px; line-height: 1.55; }
    .header { border-bottom: 3px solid #0F4C4C; padding-bottom: 10px; margin-bottom: 16px; }
    .title { font-size: 15px; font-weight: bold; color: #0F4C4C; }
    table { width: 100%; border-collapse: collapse; margin: 12px 0; }
    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
    th { background: #f1f5f9; width: 28%; }
    .clause { white-space: pre-wrap; border: 1px solid #e2e8f0; background: #f8fafc; padding: 12px; margin-top: 12px; }
</style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $settings->institution_name }}</div>
        <div>CONTRATO N.° {{ $contract->contract_number }} — {{ $contract->hiring_regime === 'planilla_laboral' ? 'RÉGIMEN DE PLANILLA LABORAL (COSTA RICA)' : 'CONTRATO DE SERVICIOS PROFESIONALES (COSTA RICA)' }}</div>
    </div>

    <table>
        <tr><th>Colaboradora / Docente</th><td>{{ $contract->staffMember?->full_name }}</td></tr>
        <tr><th>Identificación</th><td>{{ $contract->staffMember?->identification_number }}</td></tr>
        <tr><th>Correo / Teléfono</th><td>{{ $contract->staffMember?->email }} · {{ $contract->staffMember?->phone }}</td></tr>
        <tr><th>Título del Contrato</th><td>{{ $contract->title }}</td></tr>
        <tr><th>Vigencia</th><td>Del {{ $contract->start_date?->format('d/m/Y') }} al {{ $contract->end_date?->format('d/m/Y') ?? 'Indefinido' }}</td></tr>
        <tr><th>Remuneración Bruta</th><td>₡{{ number_format((float) $contract->gross_salary_or_rate, 2) }} ({{ $contract->rate_unit }})</td></tr>
        @if($contract->hiring_regime === 'planilla_laboral')
            <tr><th>Cargas Sociales CR</th><td>CCSS: {{ $contract->ccss_employee_rate }}% · Banco Popular: {{ $contract->banco_popular_rate }}% · Renta: {{ $contract->income_tax_rate }}% · Aguinaldo: {{ $contract->accrues_aguinaldo ? 'Sí' : 'No' }} · Vacaciones: {{ $contract->accrues_vacations ? 'Sí' : 'No' }}</td></tr>
        @else
            <tr><th>Régimen Tributario CR</th><td>Servicios Profesionales (Sin relación laboral ni cargas CCSS). Requiere Factura Electrónica Ministerio de Hacienda (50 dígitos) + IVA {{ $contract->iva_rate }}%.</td></tr>
        @endif
    </table>

    @if($contract->contract_body)
        <div class="clause">{{ $contract->contract_body }}</div>
    @endif

    <table style="margin-top:42px; border:none;">
        <tr>
            <td style="border:none; text-align:center; width:50%; padding-top:26px;">
                <div style="border-top:1px solid #334155; width:78%; margin:0 auto 4px auto;"></div>
                <strong>{{ $contract->staffMember?->full_name }}</strong><br>Colaboradora / Contratista
            </td>
            <td style="border:none; text-align:center; width:50%; padding-top:26px;">
                <div style="border-top:1px solid #334155; width:78%; margin:0 auto 4px auto;"></div>
                <strong>{{ $settings->director_name }}</strong><br>{{ $settings->institution_name }}
            </td>
        </tr>
    </table>
</body>
</html>
