<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Comprobante {{ $payment->voucher_number }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 24px; }
    .header { border-bottom: 3px solid #0F4C4C; padding-bottom: 10px; margin-bottom: 14px; }
    .title { font-size: 15px; font-weight: bold; color: #0F4C4C; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
    th { background: #f1f5f9; }
    .num { text-align: right; }
    .total td { background: #E6F4F1; font-weight: bold; font-size: 12px; }
</style>
</head>
<body>
    <div class="header">
        @if(!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="SURÍ" style="float:left; height:58px; margin-right:12px;">
        @endif
        <div class="title">{{ $settings->institution_name }}</div>
        <div>COMPROBANTE DE PAGO {{ $payment->voucher_number }} · {{ $payment->hiring_regime === 'planilla_laboral' ? 'PLANILLA LABORAL CR' : 'SERVICIOS PROFESIONALES CR' }}</div>
        <div style="font-size:10px; color:#475569;">Fecha de pago: {{ $payment->payment_date?->format('d/m/Y') }} · Período: {{ $payment->period_label }}</div>
        <div style="clear:both;"></div>
    </div>

    <table>
        <tr><th style="width:28%;">Colaboradora</th><td>{{ $payment->staffMember?->full_name }} ({{ $payment->staffMember?->identification_number }})</td></tr>
        <tr><th>Contrato</th><td>{{ $payment->contract?->contract_number }} — {{ $payment->contract?->title }}</td></tr>
        <tr><th>Método / Referencia</th><td>{{ strtoupper($payment->payment_method) }} · {{ $payment->reference_number ?: 'N/A' }}</td></tr>
        @if($payment->hiring_regime === 'servicios_profesionales')
            <tr><th>Clave Hacienda (50 dígitos)</th><td style="font-family:monospace; font-size:10px;">{{ $payment->hacienda_electronic_invoice_key }}</td></tr>
        @endif
    </table>

    <table>
        <thead>
            <tr><th>Concepto</th><th class="num" style="width:28%;">Monto (CRC)</th></tr>
        </thead>
        <tbody>
            <tr><td>Monto Bruto del Período</td><td class="num">₡{{ number_format((float) $payment->gross_amount, 2) }}</td></tr>
            @if($payment->hiring_regime === 'planilla_laboral')
                <tr><td>Deducción CCSS Obrera</td><td class="num">- ₡{{ number_format((float) $payment->ccss_deduction, 2) }}</td></tr>
                <tr><td>Deducción Banco Popular</td><td class="num">- ₡{{ number_format((float) $payment->banco_popular_deduction, 2) }}</td></tr>
                <tr><td>Impuesto sobre la Renta</td><td class="num">- ₡{{ number_format((float) $payment->income_tax_deduction, 2) }}</td></tr>
                <tr class="total"><td>SALARIO NETO A PAGAR</td><td class="num">₡{{ number_format((float) $payment->net_amount, 2) }}</td></tr>
                <tr><td>Provisión Aguinaldo (Informativo patronal)</td><td class="num">₡{{ number_format((float) $payment->aguinaldo_provision, 2) }}</td></tr>
                <tr><td>Provisión Vacaciones (Informativo patronal)</td><td class="num">₡{{ number_format((float) $payment->vacation_provision, 2) }}</td></tr>
            @else
                <tr><td>IVA Servicios Profesionales CR</td><td class="num">+ ₡{{ number_format((float) $payment->iva_amount, 2) }}</td></tr>
                <tr class="total"><td>TOTAL A CANCELAR CONTRA FACTURA ELECTRÓNICA</td><td class="num">₡{{ number_format((float) $payment->net_amount, 2) }}</td></tr>
            @endif
        </tbody>
    </table>

    <p style="margin-top:20px; font-size:9.5px; color:#64748b;">Emitido por {{ $payment->recorder?->name }} · {{ $settings->institution_name }}, Costa Rica.</p>
</body>
</html>
