<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\FeeCharge;
use App\Models\FinancialLedgerEntry;
use App\Models\PaymentAllocation;
use App\Models\PaymentReceipt;
use App\Models\Scholarship;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function generateFeePlanForEnrollment(Enrollment $enrollment, ?int $payerId = null): array
    {
        $edition = $enrollment->edition()->with('course')->firstOrFail();
        $student = $enrollment->student()->with('sponsorPayer')->first();
        $effectivePayerId = $payerId ?? $student?->sponsor_payer_id;
        $created = [];
        $userId = Auth::id() ?? $enrollment->created_by ?? 1;

        DB::transaction(function () use ($enrollment, $edition, $effectivePayerId, $userId, &$created): void {
            if ((float) $edition->enrollment_fee > 0) {
                $charge = FeeCharge::create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'payer_id' => $effectivePayerId,
                    'concept' => 'Matrícula — '.$edition->name,
                    'charge_type' => 'matricula',
                    'installment_number' => 0,
                    'due_date' => now()->toDateString(),
                    'gross_amount' => $edition->enrollment_fee,
                    'scholarship_discount' => 0,
                    'net_amount' => $edition->enrollment_fee,
                    'paid_amount' => 0,
                    'status' => 'pendiente',
                    'created_by' => $userId,
                ]);
                $this->recordLedger('cargo', (float) $charge->gross_amount, 'Cargo de matrícula '.$edition->code, $enrollment->student_id, $enrollment->id, $charge->id, null, $userId);
                $created[] = $charge;
            }

            $installments = max(1, (int) $edition->installments_count);
            if ((float) $edition->monthly_fee > 0) {
                $startDate = $edition->start_date ?? now();
                for ($i = 1; $i <= $installments; $i++) {
                    $charge = FeeCharge::create([
                        'enrollment_id' => $enrollment->id,
                        'student_id' => $enrollment->student_id,
                        'payer_id' => $effectivePayerId,
                        'concept' => sprintf('Mensualidad %d/%d — %s', $i, $installments, $edition->name),
                        'charge_type' => 'mensualidad',
                        'installment_number' => $i,
                        'due_date' => $startDate->copy()->addMonths($i - 1)->toDateString(),
                        'gross_amount' => $edition->monthly_fee,
                        'scholarship_discount' => 0,
                        'net_amount' => $edition->monthly_fee,
                        'paid_amount' => 0,
                        'status' => 'pendiente',
                        'created_by' => $userId,
                    ]);
                    $this->recordLedger('cargo', (float) $charge->gross_amount, $charge->concept, $enrollment->student_id, $enrollment->id, $charge->id, null, $userId);
                    $created[] = $charge;
                }
            }
        });

        if ($student && (float) $student->scholarship_percent > 0 && ! empty($created)) {
            $sponsorLabel = $student->sponsorPayer?->full_name;
            $scholName = $sponsorLabel ? 'Beca Admisiones — '.$sponsorLabel : 'Beca Admisiones';
            $this->applyScholarship(
                enrollment: $enrollment,
                name: $scholName,
                discountType: 'porcentaje',
                discountValue: (float) $student->scholarship_percent,
                payerId: $effectivePayerId,
                reason: $student->scholarship_notes ?: 'Beca configurada en expediente de Admisiones'
            );
        }

        return $created;
    }

    public function syncStudentSponsorAndScholarship(Student $student, ?Enrollment $specificEnrollment = null, ?string $reason = null): void
    {
        $student->loadMissing('sponsorPayer');
        $payerId = $student->sponsor_payer_id;
        $percent = round((float) ($student->scholarship_percent ?? 0), 2);
        $sponsorLabel = $student->sponsorPayer?->full_name;
        $scholName = $sponsorLabel ? 'Beca Admisiones — '.$sponsorLabel : 'Beca Admisiones';

        $enrollments = $specificEnrollment
            ? collect([$specificEnrollment])
            : $student->enrollments()->whereIn('status', ['confirmada', 'en_curso'])->get();

        foreach ($enrollments as $enr) {
            // Si la matrícula aún no tiene plan de cobro (ej. preinscrita), se aplicará automáticamente al confirmarse
            if (! $enr->feeCharges()->exists()) {
                continue;
            }

            // Actualizar pagador en cargos pendientes/parciales/exonerados
            $enr->feeCharges()
                ->whereIn('status', ['pendiente', 'parcial', 'exonerado'])
                ->update(['payer_id' => $payerId]);

            $existingAdmSchol = $enr->scholarships()
                ->where('status', 'activa')
                ->where('name', 'LIKE', 'Beca Admisiones%')
                ->first();

            if ($percent > 0) {
                if (! $existingAdmSchol) {
                    $this->applyScholarship(
                        enrollment: $enr,
                        name: $scholName,
                        discountType: 'porcentaje',
                        discountValue: $percent,
                        payerId: $payerId,
                        reason: $reason ?: ($student->scholarship_notes ?: 'Asignada en Admisiones')
                    );
                } else {
                    $existingAdmSchol->update([
                        'name' => $scholName,
                        'payer_id' => $payerId,
                        'discount_value' => $percent,
                        'reason' => $reason ?: ($student->scholarship_notes ?: $existingAdmSchol->reason),
                    ]);

                    // Recalcular limpiamente desde monto bruto (nunca altera gross_amount ni duplica descuento)
                    foreach ($enr->feeCharges()->whereIn('status', ['pendiente', 'parcial', 'exonerado'])->get() as $charge) {
                        $gross = (float) $charge->gross_amount;
                        $discount = round($gross * ($percent / 100), 2);
                        $newNet = round(max(0.0, $gross - $discount), 2);
                        $newStatus = $newNet <= (float) $charge->paid_amount
                            ? ($newNet == 0.0 ? 'exonerado' : 'pagado')
                            : ((float) $charge->paid_amount > 0 ? 'parcial' : 'pendiente');

                        $charge->update([
                            'scholarship_discount' => $discount,
                            'net_amount' => $newNet,
                            'payer_id' => $payerId,
                            'status' => $newStatus,
                        ]);
                    }
                }
            } elseif ($existingAdmSchol) {
                $existingAdmSchol->update(['status' => 'inactiva']);
                foreach ($enr->feeCharges()->whereIn('status', ['pendiente', 'parcial', 'exonerado'])->get() as $charge) {
                    $gross = (float) $charge->gross_amount;
                    $newStatus = $gross <= (float) $charge->paid_amount
                        ? 'pagado'
                        : ((float) $charge->paid_amount > 0 ? 'parcial' : 'pendiente');

                    $charge->update([
                        'scholarship_discount' => 0,
                        'net_amount' => $gross,
                        'payer_id' => $payerId,
                        'status' => $newStatus,
                    ]);
                }
            }
        }
    }

    /**
     * Apply scholarship (0-100% or fixed amount) WITHOUT altering gross_amount.
     */
    public function applyScholarship(
        Enrollment $enrollment,
        string $name,
        string $discountType,
        float $discountValue,
        ?int $payerId = null,
        ?string $reason = null
    ): Scholarship {
        if ($discountType === 'porcentaje' && ($discountValue < 0 || $discountValue > 100)) {
            throw ValidationException::withMessages(['discount_value' => 'El porcentaje de beca debe estar entre 0% y 100%.']);
        }
        if ($discountValue < 0) {
            throw ValidationException::withMessages(['discount_value' => 'El monto de beca no puede ser negativo.']);
        }

        return DB::transaction(function () use ($enrollment, $name, $discountType, $discountValue, $payerId, $reason): Scholarship {
            $userId = Auth::id() ?? 1;
            $scholarship = Scholarship::create([
                'enrollment_id' => $enrollment->id,
                'payer_id' => $payerId,
                'name' => $name,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'reason' => $reason,
                'status' => 'activa',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $charges = $enrollment->feeCharges()->whereIn('status', ['pendiente', 'parcial'])->get();
            $remainingFixed = $discountType === 'monto_fijo' ? $discountValue : 0.0;

            foreach ($charges as $charge) {
                $gross = (float) $charge->gross_amount;
                if ($discountType === 'porcentaje') {
                    $discount = round($gross * ($discountValue / 100), 2);
                } else {
                    $availableForDiscount = max(0.0, $gross - (float) $charge->scholarship_discount);
                    $discount = round(min($availableForDiscount, $remainingFixed), 2);
                    $remainingFixed = round(max(0.0, $remainingFixed - $discount), 2);
                }

                if ($discount <= 0) {
                    continue;
                }

                $newTotalDiscount = round(min($gross, (float) $charge->scholarship_discount + $discount), 2);
                $newNet = round(max(0.0, $gross - $newTotalDiscount), 2);
                $newStatus = $newNet <= (float) $charge->paid_amount
                    ? ($newNet == 0.0 ? 'exonerado' : 'pagado')
                    : ((float) $charge->paid_amount > 0 ? 'parcial' : 'pendiente');

                // gross_amount is NEVER modified!
                $charge->update([
                    'scholarship_discount' => $newTotalDiscount,
                    'net_amount' => $newNet,
                    'payer_id' => $payerId ?? $charge->payer_id,
                    'status' => $newStatus,
                ]);

                $this->recordLedger('beca', -$discount, 'Beca aplicada: '.$name, $enrollment->student_id, $enrollment->id, $charge->id, null, $userId);
            }

            AuditService::log(
                event: 'finance.scholarship_applied',
                module: 'finanzas',
                auditable: $scholarship,
                newValues: ['name' => $name, 'discount_type' => $discountType, 'discount_value' => $discountValue],
                reason: $reason
            );

            return $scholarship;
        });
    }

    /**
     * Register a payment receipt (REC-YYYY-NNNNN) supporting partial and multi-charge allocations.
     *
     * @param  array<int, float>  $allocationsByChargeId  [fee_charge_id => amount]
     */
    public function recordPayment(
        Student $student,
        array $allocationsByChargeId,
        string $paymentMethod,
        ?int $payerId = null,
        ?int $enrollmentId = null,
        ?string $referenceNumber = null,
        ?string $notes = null
    ): PaymentReceipt {
        $cleanAllocations = [];
        foreach ($allocationsByChargeId as $chargeId => $amount) {
            $amt = round((float) $amount, 2);
            if ($amt > 0) {
                $cleanAllocations[(int) $chargeId] = $amt;
            }
        }

        if (empty($cleanAllocations)) {
            throw ValidationException::withMessages(['allocations' => 'Debe indicar al menos un monto de abono mayor a ₡0.']);
        }

        return DB::transaction(function () use ($student, $cleanAllocations, $paymentMethod, $payerId, $enrollmentId, $referenceNumber, $notes): PaymentReceipt {
            $userId = Auth::id() ?? 1;
            $total = 0.0;
            $charges = FeeCharge::query()->whereIn('id', array_keys($cleanAllocations))->lockForUpdate()->get()->keyBy('id');

            foreach ($cleanAllocations as $chargeId => $amount) {
                $charge = $charges->get($chargeId);
                if (! $charge || $charge->student_id !== $student->id) {
                    throw ValidationException::withMessages(['allocations' => 'Cargo inválido para la alumna seleccionada.']);
                }
                if ($amount > $charge->balance + 0.01) {
                    throw ValidationException::withMessages(['allocations' => sprintf('El abono de ₡%s supera el saldo pendiente de "%s".', number_format($amount, 2), $charge->concept)]);
                }
                $total = round($total + $amount, 2);
            }

            $receipt = PaymentReceipt::create([
                'receipt_number' => PaymentReceipt::nextReceiptNumber(),
                'student_id' => $student->id,
                'enrollment_id' => $enrollmentId ?? $charges->first()?->enrollment_id,
                'payer_id' => $payerId,
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'total_amount' => $total,
                'paid_at' => now(),
                'status' => 'emitido',
                'notes' => $notes,
                'cashier_id' => $userId,
            ]);

            foreach ($cleanAllocations as $chargeId => $amount) {
                $charge = $charges->get($chargeId);
                PaymentAllocation::create([
                    'payment_receipt_id' => $receipt->id,
                    'fee_charge_id' => $charge->id,
                    'amount' => $amount,
                ]);

                $newPaid = round((float) $charge->paid_amount + $amount, 2);
                $newStatus = $newPaid >= (float) $charge->net_amount ? 'pagado' : 'parcial';

                $charge->update([
                    'paid_amount' => $newPaid,
                    'status' => $newStatus,
                ]);

                $this->recordLedger('pago', -$amount, 'Recibo '.$receipt->receipt_number.' — '.$charge->concept, $student->id, $charge->enrollment_id, $charge->id, $receipt->id, $userId);
            }

            AuditService::log(
                event: 'finance.receipt_issued',
                module: 'finanzas',
                auditable: $receipt,
                newValues: ['receipt_number' => $receipt->receipt_number, 'total_amount' => $total, 'payment_method' => $paymentMethod]
            );

            return $receipt->load('allocations.charge', 'student', 'payer', 'cashier');
        });
    }

    /**
     * Void a payment receipt STRICTLY via reversal (never deletes the original receipt or ledger).
     */
    public function reverseReceipt(PaymentReceipt $receipt, string $reason): PaymentReceipt
    {
        if ($receipt->status === 'reversado') {
            throw ValidationException::withMessages(['reversal_reason' => 'Este recibo ya fue reversado previamente.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reversal_reason' => 'Debe indicar un motivo formal para reversar el recibo.']);
        }

        return DB::transaction(function () use ($receipt, $reason): PaymentReceipt {
            $userId = Auth::id() ?? 1;
            $receipt->load('allocations.charge');

            $reversal = PaymentReceipt::create([
                'receipt_number' => PaymentReceipt::nextReceiptNumber(),
                'student_id' => $receipt->student_id,
                'enrollment_id' => $receipt->enrollment_id,
                'payer_id' => $receipt->payer_id,
                'payment_method' => $receipt->payment_method,
                'reference_number' => 'REV-'.$receipt->receipt_number,
                'total_amount' => -abs((float) $receipt->total_amount),
                'paid_at' => now(),
                'status' => 'reversado',
                'reversal_reason' => $reason,
                'notes' => 'Reverso del recibo '.$receipt->receipt_number,
                'cashier_id' => $userId,
            ]);

            $receipt->update([
                'status' => 'reversado',
                'reversed_by_receipt_id' => $reversal->id,
                'reversal_reason' => $reason,
            ]);

            foreach ($receipt->allocations as $allocation) {
                $charge = FeeCharge::query()->lockForUpdate()->find($allocation->fee_charge_id);
                if (! $charge) {
                    continue;
                }

                $newPaid = round(max(0.0, (float) $charge->paid_amount - (float) $allocation->amount), 2);
                $newStatus = $newPaid <= 0 ? 'pendiente' : ($newPaid >= (float) $charge->net_amount ? 'pagado' : 'parcial');

                $charge->update([
                    'paid_amount' => $newPaid,
                    'status' => $newStatus,
                ]);

                $this->recordLedger('reverso', (float) $allocation->amount, 'Reverso de '.$receipt->receipt_number.': '.$reason, $receipt->student_id, $charge->enrollment_id, $charge->id, $reversal->id, $userId);
            }

            AuditService::log(
                event: 'finance.receipt_reversed',
                module: 'finanzas',
                auditable: $receipt,
                oldValues: ['status' => 'emitido'],
                newValues: ['status' => 'reversado', 'reversal_receipt' => $reversal->receipt_number],
                reason: $reason
            );

            return $reversal;
        });
    }

    protected function recordLedger(
        string $type,
        float $amount,
        string $description,
        ?int $studentId,
        ?int $enrollmentId,
        ?int $feeChargeId,
        ?int $paymentReceiptId,
        int $userId
    ): FinancialLedgerEntry {
        return FinancialLedgerEntry::create([
            'entry_code' => FinancialLedgerEntry::nextEntryCode(),
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId,
            'fee_charge_id' => $feeChargeId,
            'payment_receipt_id' => $paymentReceiptId,
            'entry_type' => $type,
            'amount' => $amount,
            'description' => $description,
            'recorded_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
