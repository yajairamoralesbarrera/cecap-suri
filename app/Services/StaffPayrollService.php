<?php

namespace App\Services;

use App\Models\StaffContract;
use App\Models\StaffPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffPayrollService
{
    public function calculatePaymentBreakdown(StaffContract $contract, float $grossAmount): array
    {
        $gross = round($grossAmount, 2);

        if ($contract->hiring_regime === 'planilla_laboral') {
            $ccss = round($gross * ((float) $contract->ccss_employee_rate / 100), 2);
            $bp = round($gross * ((float) $contract->banco_popular_rate / 100), 2);
            $renta = round($gross * ((float) $contract->income_tax_rate / 100), 2);
            $aguinaldo = $contract->accrues_aguinaldo ? round($gross / 12, 2) : 0.0;
            $vacaciones = $contract->accrues_vacations ? round($gross * 0.041667, 2) : 0.0;
            $net = round(max(0.0, $gross - $ccss - $bp - $renta), 2);

            return [
                'hiring_regime' => 'planilla_laboral',
                'gross_amount' => $gross,
                'ccss_deduction' => $ccss,
                'banco_popular_deduction' => $bp,
                'income_tax_deduction' => $renta,
                'aguinaldo_provision' => $aguinaldo,
                'vacation_provision' => $vacaciones,
                'iva_amount' => 0.0,
                'net_amount' => $net,
            ];
        }

        // Servicios Profesionales Costa Rica: Factura Electrónica Hacienda + IVA (NO CCSS/BP/Aguinaldo/Vacaciones)
        $iva = round($gross * ((float) $contract->iva_rate / 100), 2);
        $net = round($gross + $iva, 2);

        return [
            'hiring_regime' => 'servicios_profesionales',
            'gross_amount' => $gross,
            'ccss_deduction' => 0.0,
            'banco_popular_deduction' => 0.0,
            'income_tax_deduction' => 0.0,
            'aguinaldo_provision' => 0.0,
            'vacation_provision' => 0.0,
            'iva_amount' => $iva,
            'net_amount' => $net,
        ];
    }

    public function recordPayment(
        StaffContract $contract,
        string $periodLabel,
        string $paymentDate,
        float $grossAmount,
        string $paymentMethod = 'transferencia',
        ?string $haciendaInvoiceKey = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): StaffPayment {
        $cleanKey = $haciendaInvoiceKey !== null ? preg_replace('/\D+/', '', $haciendaInvoiceKey) : null;

        if ($contract->hiring_regime === 'servicios_profesionales') {
            if (! $cleanKey || strlen($cleanKey) !== 50) {
                throw ValidationException::withMessages([
                    'hacienda_electronic_invoice_key' => 'Para Servicios Profesionales en Costa Rica es obligatoria la clave numérica de Factura Electrónica de Hacienda de exactamente 50 dígitos.',
                ]);
            }
        } else {
            $cleanKey = null;
        }

        $calc = $this->calculatePaymentBreakdown($contract, $grossAmount);

        $payment = StaffPayment::create([
            'voucher_number' => StaffPayment::nextVoucherNumber(),
            'staff_member_id' => $contract->staff_member_id,
            'staff_contract_id' => $contract->id,
            'hiring_regime' => $contract->hiring_regime,
            'period_label' => $periodLabel,
            'payment_date' => $paymentDate,
            'gross_amount' => $calc['gross_amount'],
            'ccss_deduction' => $calc['ccss_deduction'],
            'banco_popular_deduction' => $calc['banco_popular_deduction'],
            'income_tax_deduction' => $calc['income_tax_deduction'],
            'aguinaldo_provision' => $calc['aguinaldo_provision'],
            'vacation_provision' => $calc['vacation_provision'],
            'iva_amount' => $calc['iva_amount'],
            'hacienda_electronic_invoice_key' => $cleanKey,
            'net_amount' => $calc['net_amount'],
            'payment_method' => $paymentMethod,
            'reference_number' => $referenceNumber,
            'status' => 'pagado',
            'notes' => $notes,
            'recorded_by' => Auth::id() ?? 1,
        ]);

        AuditService::log(
            event: 'staff.payment_recorded',
            module: 'personal',
            auditable: $payment,
            newValues: $payment->only(['voucher_number', 'hiring_regime', 'gross_amount', 'net_amount', 'hacienda_electronic_invoice_key'])
        );

        return $payment;
    }
}
