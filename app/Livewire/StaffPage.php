<?php

namespace App\Livewire;

use App\Models\Picklist;
use App\Models\StaffContract;
use App\Models\StaffMember;
use App\Models\StaffPayment;
use App\Services\AttachmentService;
use App\Services\AuditService;
use App\Services\StaffPayrollService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Personal y Contratos CR')]
class StaffPage extends Component
{
    use WithFileUploads;

    public string $tab = 'directory';

    public ?string $feedback = null;

    // Alta de Personal (correo obligatorio + atestados profesionales OBLIGATORIOS para teacher/both)
    public string $staff_role_type = 'teacher';

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $identification_type = 'cedula_nacional';

    public string $identification_number = '';

    public string $email = '';

    public string $phone = '';

    public string $specialty = '';

    public string $academic_degree = '';

    public string $hiring_regime = 'planilla_laboral';

    public $credentials_file = null;

    // Contrato CR (Planilla Laboral vs Servicios Profesionales; editable o PDF adjunto)
    public ?int $contract_staff_id = null;

    public string $contract_regime = 'planilla_laboral';

    public string $contract_mode = 'editable';

    public string $contract_title = 'Contrato de Docencia Técnica CeCap SURÍ';

    public string $contract_start_date = '';

    public ?string $contract_end_date = null;

    public float $contract_gross = 450000;

    public string $contract_rate_unit = 'mensual';

    public float $contract_ccss_rate = 10.67;

    public float $contract_bp_rate = 1.00;

    public float $contract_income_tax_rate = 0.00;

    public bool $contract_aguinaldo = true;

    public bool $contract_vacations = true;

    public float $contract_iva_rate = 2.00;

    public string $contract_body = "Entre CeCap — SURÍ Proyecto Educativo y la persona contratada se acuerdan las funciones docentes/administrativas bajo la legislación vigente de Costa Rica.";

    public $contract_pdf_file = null;

    // Pago CR (Planilla Laboral vs Servicios Profesionales con Factura Hacienda 50 dígitos + IVA)
    public ?int $pay_contract_id = null;

    public string $pay_period_label = '';

    public string $pay_date = '';

    public float $pay_gross_amount = 0;

    public string $pay_method = 'transferencia';

    public string $pay_hacienda_key = '';

    public string $pay_reference = '';

    public function mount(): void
    {
        $this->contract_start_date = now()->toDateString();
        $this->pay_date = now()->toDateString();
        $this->pay_period_label = now()->translatedFormat('F Y');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();
    }

    public function updatedPayContractId($value): void
    {
        if (! $value) {
            return;
        }
        $contract = StaffContract::find($value);
        if ($contract) {
            $this->pay_gross_amount = (float) $contract->gross_salary_or_rate;
        }
    }

    public function createStaff(AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('personal.manage'), 403);

        $rules = [
            'staff_role_type' => ['required', 'in:teacher,administrative,both'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'identification_type' => ['required', 'string', 'max:40'],
            'identification_number' => ['required', 'string', 'max:40', 'unique:staff_members,identification_number'],
            'email' => ['required', 'email', 'max:255', new \App\Rules\ValidEmailDomain()], // OBLIGATORIO
            'phone' => ['required', 'string', 'max:30'],
            'hiring_regime' => ['required', 'in:planilla_laboral,servicios_profesionales'],
            'credentials_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ];

        // Atestados Profesionales OBLIGATORIOS para Profesoras (teacher / both)
        if (in_array($this->staff_role_type, ['teacher', 'both'], true)) {
            $rules['credentials_file'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'];
        }

        $this->validate($rules, [
            'email.required' => 'El correo electrónico es obligatorio en el módulo de Personal.',
            'credentials_file.required' => 'Los Atestados Profesionales (títulos/certificaciones) son OBLIGATORIOS para Profesoras (docente o mixto).',
        ]);

        $member = StaffMember::create([
            'staff_code' => StaffMember::nextCode(),
            'staff_role_type' => $this->staff_role_type,
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
            'second_last_name' => $this->second_last_name ?: null,
            'identification_type' => $this->identification_type,
            'identification_number' => trim($this->identification_number),
            'email' => strtolower(trim($this->email)),
            'phone' => trim($this->phone),
            'specialty' => $this->specialty ?: null,
            'academic_degree' => $this->academic_degree ?: null,
            'hiring_regime' => $this->hiring_regime,
            'status' => 'activo',
            'created_by' => Auth::id(),
        ]);

        if ($this->credentials_file) {
            $attachmentService->store($member, $this->credentials_file, 'atestado_profesional', 'Atestados profesionales verificados al ingreso');
        }

        AuditService::log('staff.member_created', 'personal', $member, null, $member->only(['staff_code', 'staff_role_type', 'email', 'hiring_regime']));
        $this->reset(['first_name', 'last_name', 'second_last_name', 'identification_number', 'email', 'phone', 'specialty', 'academic_degree', 'credentials_file']);
        $this->feedback = 'Colaboradora registrada correctamente con su expediente privado.';
    }

    public function createContract(AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('personal.manage'), 403);

        $rules = [
            'contract_staff_id' => ['required', 'integer', 'exists:staff_members,id'],
            'contract_regime' => ['required', 'in:planilla_laboral,servicios_profesionales'],
            'contract_mode' => ['required', 'in:editable,pdf_adjunto'],
            'contract_title' => ['required', 'string', 'max:180'],
            'contract_start_date' => ['required', 'date'],
            'contract_gross' => ['required', 'numeric', 'min:1'],
        ];

        if ($this->contract_mode === 'pdf_adjunto') {
            $rules['contract_pdf_file'] = ['required', 'file', 'mimes:pdf', 'max:25600'];
        }

        $this->validate($rules);

        $member = StaffMember::findOrFail($this->contract_staff_id);
        if ($member->requiresProfessionalCredentials() && ! $member->hasActiveProfessionalCredentials()) {
            $this->addError('contract_staff_id', 'No se puede emitir contrato docente sin Atestados Profesionales vigentes adjuntos.');

            return;
        }

        $attachmentId = null;
        if ($this->contract_mode === 'pdf_adjunto' && $this->contract_pdf_file) {
            $att = $attachmentService->store($member, $this->contract_pdf_file, 'contrato_pdf', 'Contrato PDF firmado');
            $attachmentId = $att->id;
        }

        $contract = StaffContract::create([
            'staff_member_id' => $member->id,
            'contract_number' => StaffContract::nextNumber(),
            'hiring_regime' => $this->contract_regime,
            'contract_mode' => $this->contract_mode,
            'title' => trim($this->contract_title),
            'start_date' => $this->contract_start_date,
            'end_date' => $this->contract_end_date ?: null,
            'gross_salary_or_rate' => $this->contract_gross,
            'rate_unit' => $this->contract_rate_unit,
            'ccss_employee_rate' => $this->contract_regime === 'planilla_laboral' ? $this->contract_ccss_rate : 0,
            'banco_popular_rate' => $this->contract_regime === 'planilla_laboral' ? $this->contract_bp_rate : 0,
            'income_tax_rate' => $this->contract_regime === 'planilla_laboral' ? $this->contract_income_tax_rate : 0,
            'accrues_aguinaldo' => $this->contract_regime === 'planilla_laboral' ? $this->contract_aguinaldo : false,
            'accrues_vacations' => $this->contract_regime === 'planilla_laboral' ? $this->contract_vacations : false,
            'iva_rate' => $this->contract_regime === 'servicios_profesionales' ? $this->contract_iva_rate : 0,
            'contract_body' => $this->contract_mode === 'editable' ? $this->contract_body : null,
            'attachment_id' => $attachmentId,
            'status' => 'vigente',
            'created_by' => Auth::id(),
        ]);

        AuditService::log('staff.contract_created', 'personal', $contract, null, $contract->only(['contract_number', 'hiring_regime', 'contract_mode']));
        $this->contract_pdf_file = null;
        $this->feedback = sprintf('Contrato %s creado bajo régimen %s.', $contract->contract_number, $contract->hiring_regime);
    }

    public function recordPayment(StaffPayrollService $payrollService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('personal.payroll.manage'), 403);

        $this->validate([
            'pay_contract_id' => ['required', 'integer', 'exists:staff_contracts,id'],
            'pay_period_label' => ['required', 'string', 'max:100'],
            'pay_date' => ['required', 'date'],
            'pay_gross_amount' => ['required', 'numeric', 'min:1'],
            'pay_method' => ['required', 'string', 'max:40'],
        ]);

        $contract = StaffContract::with('staffMember')->findOrFail($this->pay_contract_id);

        try {
            $payment = $payrollService->recordPayment(
                contract: $contract,
                periodLabel: trim($this->pay_period_label),
                paymentDate: $this->pay_date,
                grossAmount: (float) $this->pay_gross_amount,
                paymentMethod: $this->pay_method,
                haciendaInvoiceKey: $this->pay_hacienda_key ?: null,
                referenceNumber: $this->pay_reference ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reset(['pay_hacienda_key', 'pay_reference']);
        $this->feedback = sprintf('Comprobante %s registrado por neto ₡%s.', $payment->voucher_number, number_format((float) $payment->net_amount, 2));
    }

    public function render()
    {
        $selectedPayContract = $this->pay_contract_id ? StaffContract::find($this->pay_contract_id) : null;

        return view('livewire.staff-page', [
            'staffMembers' => StaffMember::with('attachments')->latest()->get(),
            'contracts' => StaffContract::with(['staffMember', 'attachment'])->latest()->get(),
            'payments' => StaffPayment::with(['staffMember', 'contract'])->latest('payment_date')->limit(50)->get(),
            'selectedPayContract' => $selectedPayContract,
            'idTypes' => Picklist::optionsFor('id_type'),
            'paymentMethods' => Picklist::optionsFor('payment_method'),
        ]);
    }
}
