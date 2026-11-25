<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo {{ $receipt->receipt_number }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 22px; }
    .header { border-bottom: 3px solid #0F4C4C; padding-bottom: 10px; margin-bottom: 14px; }
    .title { font-size: 16px; font-weight: bold; color: #0F4C4C; }
    .meta { font-size: 10px; color: #475569; }
    .receipt-box { float: right; border: 2px solid #0F4C4C; padding: 6px 12px; text-align: center; }
    .receipt-num { font-size: 14px; font-weight: bold; color: #0F4C4C; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
    th { background: #f1f5f9; }
    .num { text-align: right; }
    .total-row td { background: #E6F4F1; font-weight: bold; font-size: 12px; }
    .reversed { background: #fee2e2; border: 2px solid #dc2626; color: #991b1b; padding: 8px; margin: 10px 0; font-weight: bold; }
</style>
</head>
<body>
    <div class="header">
        <div class="receipt-box">
            <div style="font-size:9px; text-transform:uppercase;">Recibo de Dinero</div>
            <div class="receipt-num">{{ $receipt->receipt_number }}</div>
            <div style="font-size:9px;">{{ $receipt->paid_at?->format('d/m/Y H:i') }}</div>
        </div>
        <div class="title">{{ $settings->institution_name }}</div>
        <div class="meta">Cédula Jurídica: {{ $settings->legal_id }} · Tel: {{ $settings->phone }}</div>
        <div class="meta">{{ $settings->address }}</div>
        <div style="clear:both;"></div>
    </div>

    @if($receipt->status === 'reversado')
        <div class="reversed">
            DOCUMENTO REVERSADO — Motivo: {{ $receipt->reversal_reason }}
        </div>
    @endif

    <table>
        <tr>
            <th style="width:22%;">Alumna</th>
            <td>{{ $receipt->student?->full_name }} ({{ $receipt->student?->identification_number }})</td>
            <th style="width:22%;">Método de Pago</th>
            <td>{{ strtoupper(str_replace('_', ' ', $receipt->payment_method)) }}</td>
        </tr>
        <tr>
            <th>Pagador / Patrocinante</th>
            <td>{{ $receipt->payer?->full_name ?? $receipt->student?->full_name }}</td>
            <th>Referencia</th>
            <td>{{ $receipt->reference_number ?: 'N/A' }}</td>
        </tr>
        <tr>
            <th>Cajero / Emisor</th>
            <td>{{ $receipt->cashier?->name }}</td>
            <th>Estado</th>
            <td>{{ strtoupper($receipt->status) }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Concepto Aplicado</th>
                <th class="num" style="width:20%;">Monto Bruto</th>
                <th class="num" style="width:18%;">Beca</th>
                <th class="num" style="width:22%;">Abono Recibido</th>
            </tr>
        </thead>
        <tbody>
            @foreach($receipt->allocations as $alloc)
                <tr>
                    <td>{{ $alloc->charge?->concept }}</td>
                    <td class="num">₡{{ number_format((float) $alloc->charge?->gross_amount, 2) }}</td>
                    <td class="num">₡{{ number_format((float) $alloc->charge?->scholarship_discount, 2) }}</td>
                    <td class="num">₡{{ number_format((float) $alloc->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3" class="num">TOTAL RECIBIDO (CRC)</td>
                <td class="num">₡{{ number_format((float) $receipt->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($receipt->notes)
        <p style="margin-top:10px;"><strong>Observaciones:</strong> {{ $receipt->notes }}</p>
    @endif

    <p style="margin-top:24px; font-size:9.5px; color:#64748b;">
        Política financiera CeCap: los recibos emitidos son inmutables; cualquier anulación se tramita exclusivamente mediante asiento de reverso auditado.
    </p>
</body>
</html>
