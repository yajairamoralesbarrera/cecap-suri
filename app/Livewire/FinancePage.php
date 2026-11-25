<?php

namespace App\Livewire;

use App\Models\Enrollment;
use App\Models\FeeCharge;
use App\Models\Payer;
use App\Models\PaymentReceipt;
use App\Models\Picklist;
use App\Models\Scholarship;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\FinanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Finanzas y Caja')]
class FinancePage extends Component
{
    public string $tab = 'cashier';

    public ?string $feedback = null;

    // Caja (abonos parciales y múltiples)
    public ?int $pay_student_id = null;

    public ?int $pay_payer_id = null;

    public string $pay_method = 'sinpe_movil';

    public string $pay_reference = '';

    public string $pay_notes = '';

    public array $charge_allocations = [];

    // Becas (0-100% o monto fijo sin alterar monto bruto)
    public ?int $schol_enrollment_id = null;

    public string $schol_name = 'Beca Socioeconómica CeCap';

    public string $schol_type = 'porcentaje';

    public float $schol_value = 50;

    public ?int $schol_payer_id = null;

    public string $schol_reason = '';

    // Pagadores / Patrocinantes
    public string $payer_type = 'empresa';

    public string $payer_name = '';

    public string $payer_id_num = '';

    public string $payer_email = '';

    public string $payer_phone = '';

    // Reverso de recibo (única forma de anulación)
    public ?int $reversingReceiptId = null;

    public string $reversal_reason = '';

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();
    }

    public function updatedPayStudentId(): void
    {
        $this->charge_allocations = [];
        if (! $this->pay_student_id) {
            return;
        }
        $charges = FeeCharge::where('student_id', $this->pay_student_id)
            ->whereIn('status', ['pendiente', 'parcial'])
            ->orderBy('due_date')
            ->get();

        foreach ($charges as $c) {
            $this->charge_allocations[$c->id] = (string) $c->balance;
        }
    }

    public function collectPayment(FinanceService $financeService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('finanzas.payments.collect'), 403);

        $this->validate([
            'pay_student_id' => ['required', 'integer', 'exists:students,id'],
            'pay_method' => ['required', 'string', 'max:40'],
        ]);

        $student = Student::findOrFail($this->pay_student_id);

        try {
            $receipt = $financeService->recordPayment(
                student: $student,
                allocationsByChargeId: $this->charge_allocations,
                paymentMethod: $this->pay_method,
                payerId: $this->pay_payer_id ?: null,
                referenceNumber: $this->pay_reference ?: null,
                notes: $this->pay_notes ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reset(['pay_reference', 'pay_notes', 'charge_allocations']);
        $this->updatedPayStudentId();
        $this->feedback = sprintf('Recibo %s emitido por ₡%s.', $receipt->receipt_number, number_format((float) $receipt->total_amount, 2));
    }

    public function applyScholarship(FinanceService $financeService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('finanzas.scholarships.manage'), 403);

        $this->validate([
            'schol_enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'schol_name' => ['required', 'string', 'max:140'],
            'schol_type' => ['required', 'in:porcentaje,monto_fijo'],
            'schol_value' => ['required', 'numeric', 'min:0'],
        ]);

        $enrollment = Enrollment::findOrFail($this->schol_enrollment_id);

        try {
            $financeService->applyScholarship(
                enrollment: $enrollment,
                name: trim($this->schol_name),
                discountType: $this->schol_type,
                discountValue: (float) $this->schol_value,
                payerId: $this->schol_payer_id ?: null,
                reason: $this->schol_reason ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->schol_reason = '';
        $this->feedback = 'Beca aplicada correctamente sin alterar los montos brutos de los cargos.';
    }

    public function createPayer(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('finanzas.charges.manage'), 403);

        $this->validate([
            'payer_type' => ['required', 'string', 'max:40'],
            'payer_name' => ['required', 'string', 'max:160'],
        ]);

        $payer = Payer::create([
            'payer_type' => $this->payer_type,
            'full_name' => trim($this->payer_name),
            'identification_number' => $this->payer_id_num ?: null,
            'email' => $this->payer_email ?: null,
            'phone' => $this->payer_phone ?: null,
            'is_active' => true,
        ]);

        AuditService::log('finance.payer_created', 'finanzas', $payer);
        $this->reset(['payer_name', 'payer_id_num', 'payer_email', 'payer_phone']);
        $this->feedback = 'Pagador / Patrocinante registrado correctamente.';
    }

    public function startReverseReceipt(int $receiptId): void
    {
        $this->reversingReceiptId = $receiptId;
        $this->reversal_reason = '';
    }

    public function cancelReverseReceipt(): void
    {
        $this->reversingReceiptId = null;
    }

    public function confirmReverseReceipt(FinanceService $financeService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('finanzas.payments.reverse'), 403);
        if (! $this->reversingReceiptId) {
            return;
        }

        $receipt = PaymentReceipt::findOrFail($this->reversingReceiptId);
        try {
            $reversal = $financeService->reverseReceipt($receipt, $this->reversal_reason);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reversingReceiptId = null;
        $this->reversal_reason = '';
        $this->feedback = sprintf('Reverso %s aplicado correctamente. El recibo original permanece en historial inmutable.', $reversal->receipt_number);
    }

    public function render()
    {
        $studentCharges = $this->pay_student_id
            ? FeeCharge::where('student_id', $this->pay_student_id)
                ->whereIn('status', ['pendiente', 'parcial'])
                ->orderBy('due_date')
                ->get()
            : collect();

        $overdueCharges = FeeCharge::with(['student', 'enrollment.edition.course'])
            ->whereIn('status', ['pendiente', 'parcial'])
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->get();

        return view('livewire.finance-page', [
            'students' => Student::orderBy('first_name')->limit(100)->get(),
            'studentCharges' => $studentCharges,
            'enrollments' => Enrollment::with(['student', 'edition.course'])->whereIn('status', ['confirmada', 'en_curso'])->latest()->limit(80)->get(),
            'payers' => Payer::where('is_active', true)->orderBy('full_name')->get(),
            'scholarships' => Scholarship::with(['enrollment.student', 'enrollment.edition.course', 'payer'])->latest()->limit(50)->get(),
            'receipts' => PaymentReceipt::with(['student', 'payer', 'cashier'])->latest('paid_at')->limit(60)->get(),
            'overdueCharges' => $overdueCharges,
            'paymentMethods' => Picklist::optionsFor('payment_method'),
            'payerTypes' => Picklist::optionsFor('payer_type'),
        ]);
    }
}
