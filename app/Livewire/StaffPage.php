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
#[Title('Colaboradores')]
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

    public string $birth_date = '';

    public string $specialty = '';

    public string $specialty_1 = '';

    public string $specialty_2 = '';

    public string $specialty_3 = '';

    public string $academic_degree = '';

    public string $hiring_regime = 'planilla_laboral';

    public $identification_file = null;

    public $credentials_file = null;

    public array $credentials_files = [];

    public $signed_contract_file = null;

    // Filtros de consulta en Expedientes de Colaboradores
    public string $search = '';

    public string $filter_role_type = '';

    public string $filter_regime = '';

    // Carga inline de atestados adicionales en un expediente existente
    public ?int $attachingCredentialsStaffId = null;

    public array $extra_credentials_files = [];

    public string $extra_credentials_notes = '';

    // Carga inline de contrato firmado sobre un contrato existente
    public ?int $attachingContractId = null;

    public $existing_contract_signed_file = null;

    public string $existing_contract_signed_notes = '';

    // Contrato CR (Planilla Laboral vs Servicios Profesionales; editable o archivo adjunto)
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

        $validIdTypes = array_keys(Picklist::optionsFor('id_type'));

        $rules = [
            'staff_role_type' => ['required', 'in:teacher,administrative,both'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'identification_type' => ['required', 'string', 'max:40', \Illuminate\Validation\Rule::in($validIdTypes)],
            'identification_number' => ['required', 'string', 'max:40', new \App\Rules\ValidIdentificationNumber($this->identification_type), 'unique:staff_members,identification_number'],
            'birth_date' => ['required', 'date', 'before:today'],
            'email' => ['required', 'email', 'max:255', new \App\Rules\ValidEmailDomain()], // OBLIGATORIO
            'phone' => ['required', 'string', 'max:30'],
            'academic_degree' => ['nullable', 'string', 'max:140'],
            'specialty' => ['nullable', 'string', 'max:140'],
            'specialty_1' => ['nullable', 'string', 'max:140'],
            'specialty_2' => ['nullable', 'string', 'max:140'],
            'specialty_3' => ['nullable', 'string', 'max:140'],
            'hiring_regime' => ['required', 'in:planilla_laboral,servicios_profesionales'],
            'identification_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'credentials_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'credentials_files' => ['nullable', 'array', 'max:10'],
            'credentials_files.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'signed_contract_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ];

        // Atestados Profesionales OBLIGATORIOS para Profesoras (teacher / both)
        if (in_array($this->staff_role_type, ['teacher', 'both'], true) && empty($this->credentials_files)) {
            $rules['credentials_file'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'];
        }

        $this->validate($rules, [
            'identification_type.in' => 'Seleccione un tipo de identificación válido.',
            'birth_date.required' => 'La fecha de nacimiento es obligatoria en el expediente de Colaboradores.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'email.required' => 'El correo electrónico es obligatorio en el módulo de Colaboradores.',
            'identification_file.required' => 'Debe adjuntar el documento de identificación (Cédula / DIMEX / Pasaporte).',
            'credentials_file.required' => 'Los Atestados Profesionales (títulos/certificaciones) son OBLIGATORIOS para Profesoras (docente o mixto).',
            'credentials_files.max' => 'Puede adjuntar hasta 10 archivos de atestados por carga.',
            'credentials_files.*.mimes' => 'Cada atestado debe ser PDF, JPG, PNG o WebP.',
        ]);

        $rawSpecialties = [
            trim($this->specialty_1 !== '' ? $this->specialty_1 : $this->specialty),
            trim($this->specialty_2),
            trim($this->specialty_3),
        ];
        $specialties = [];
        $seen = [];
        foreach ($rawSpecialties as $spec) {
            if ($spec === '') {
                continue;
            }
            $norm = mb_strtolower($spec);
            if (! isset($seen[$norm])) {
                $seen[$norm] = true;
                $specialties[] = $spec;
            }
            if (count($specialties) === 3) {
                break;
            }
        }

        $member = StaffMember::create([
            'staff_code' => StaffMember::nextCode(),
            'staff_role_type' => $this->staff_role_type,
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name),
            'second_last_name' => $this->second_last_name ?: null,
            'identification_type' => $this->identification_type,
            'identification_number' => trim($this->identification_number),
            'birth_date' => $this->birth_date,
            'email' => strtolower(trim($this->email)),
            'phone' => trim($this->phone),
            'academic_degree' => trim($this->academic_degree) !== '' ? trim($this->academic_degree) : null,
            'specialty' => ! empty($specialties) ? implode(' · ', $specialties) : null,
            'specialties' => ! empty($specialties) ? $specialties : null,
            'hiring_regime' => $this->hiring_regime,
            'status' => 'activo',
            'created_by' => Auth::id(),
        ]);

        if ($this->identification_file) {
            $attachmentService->store($member, $this->identification_file, 'cedula_dimex', 'Documento de identificación personal');
        }

        $credentialUploads = [];
        if ($this->credentials_file) {
            $credentialUploads[] = $this->credentials_file;
        }
        foreach ($this->credentials_files as $credFile) {
            if ($credFile) {
                $credentialUploads[] = $credFile;
            }
        }
        foreach ($credentialUploads as $credFile) {
            $attachmentService->store(
                attachable: $member,
                file: $credFile,
                documentType: 'atestado_profesional',
                notes: 'Atestado profesional verificado al ingreso',
                replaceExisting: false
            );
        }

        if ($this->signed_contract_file) {
            $attachmentService->store($member, $this->signed_contract_file, 'contrato_pdf', 'Contrato existente / firmado adjunto al ingreso');
        }

        AuditService::log('staff.member_created', 'personal', $member, null, $member->only(['staff_code', 'staff_role_type', 'email', 'hiring_regime', 'birth_date', 'academic_degree', 'specialties']));
        $this->reset(['first_name', 'last_name', 'second_last_name', 'identification_number', 'birth_date', 'email', 'phone', 'specialty', 'specialty_1', 'specialty_2', 'specialty_3', 'academic_degree', 'identification_file', 'credentials_file', 'credentials_files', 'signed_contract_file']);
        $this->identification_type = 'cedula_nacional';
        $this->feedback = 'Colaboradora registrada correctamente con su expediente y documentos privados.';
    }

    public function clearDirectoryFilters(): void
    {
        $this->reset(['search', 'filter_role_type', 'filter_regime']);
    }

    public function startAttachCredentials(int $staffId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('personal.manage'), 403);
        $this->attachingCredentialsStaffId = $staffId;
        $this->extra_credentials_files = [];
        $this->extra_credentials_notes = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelAttachCredentials(): void
    {
        $this->attachingCredentialsStaffId = null;
        $this->reset(['extra_credentials_files', 'extra_credentials_notes']);
        $this->resetValidation();
    }

    public function uploadExtraCredentials(AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('personal.manage'), 403);
        if (! $this->attachingCredentialsStaffId) {
            return;
        }

        $this->validate([
            'extra_credentials_files' => ['required', 'array', 'min:1', 'max:10'],
            'extra_credentials_files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'extra_credentials_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'extra_credentials_files.required' => 'Seleccione al menos un archivo de atestado (PDF o imagen).',
            'extra_credentials_files.min' => 'Seleccione al menos un archivo de atestado (PDF o imagen).',
            'extra_credentials_files.max' => 'Puede adjuntar hasta 10 atestados por carga.',
            'extra_credentials_files.*.mimes' => 'Cada atestado debe ser PDF, JPG, PNG o WebP.',
        ]);

        $member = StaffMember::findOrFail($this->attachingCredentialsStaffId);
        $notes = trim($this->extra_credentials_notes) !== ''
            ? trim($this->extra_credentials_notes)
            : 'Atestado profesional adicional adjuntado al expediente';

        $uploadedCount = 0;
        foreach ($this->extra_credentials_files as $file) {
            if (! $file) {
                continue;
            }
            $attachmentService->store(
                attachable: $member,
                file: $file,
                documentType: 'atestado_profesional',
                notes: $notes,
                replaceExisting: false
            );
            $uploadedCount++;
        }

        AuditService::log(
            event: 'staff.credentials_added',
            module: 'personal',
            auditable: $member,
            newValues: ['count' => $uploadedCount, 'notes' => $notes]
        );

        $this->cancelAttachCredentials();
        $this->feedback = sprintf(
            '%d %s agregado(s) al expediente de %s sin reemplazar los anteriores.',
            $uploadedCount,
            $uploadedCount === 1 ? 'atestado profesional' : 'atestados profesionales',
            $member->full_name
        );
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
            'contract_pdf_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ];

        if ($this->contract_mode === 'pdf_adjunto') {
            $rules['contract_pdf_file'] = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'];
        }

        $this->validate($rules, [
            'contract_pdf_file.required' => 'Seleccione el archivo del contrato existente o ya firmado (PDF o imagen).',
        ]);

        $member = StaffMember::findOrFail($this->contract_staff_id);
        if ($member->requiresProfessionalCredentials() && ! $member->hasActiveProfessionalCredentials()) {
            $this->addError('contract_staff_id', 'No se puede emitir contrato docente sin Atestados Profesionales vigentes adjuntos.');

            return;
        }

        $attachmentId = null;
        if ($this->contract_pdf_file) {
            $att = $attachmentService->store($member, $this->contract_pdf_file, 'contrato_pdf', 'Contrato existente / firmado adjunto');
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

        AuditService::log('staff.contract_created', 'personal', $contract, null, $contract->only(['contract_number', 'hiring_regime', 'contract_mode', 'attachment_id']));
        $this->contract_pdf_file = null;
        $this->feedback = sprintf('Contrato %s creado bajo régimen %s.', $contract->contract_number, $contract->hiring_regime);
    }

    public function startAttachContractFile(int $contractId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('personal.manage'), 403);
        $this->attachingContractId = $contractId;
        $this->existing_contract_signed_file = null;
        $this->existing_contract_signed_notes = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelAttachContractFile(): void
    {
        $this->attachingContractId = null;
        $this->reset(['existing_contract_signed_file', 'existing_contract_signed_notes']);
        $this->resetValidation();
    }

    public function uploadSignedContractFile(AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('personal.manage'), 403);
        if (! $this->attachingContractId) {
            return;
        }

        $this->validate([
            'existing_contract_signed_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'existing_contract_signed_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'existing_contract_signed_file.required' => 'Seleccione el archivo del contrato firmado (PDF, JPG, PNG o WebP).',
        ]);

        $contract = StaffContract::with('staffMember')->findOrFail($this->attachingContractId);
        $att = $attachmentService->store(
            attachable: $contract->staffMember,
            file: $this->existing_contract_signed_file,
            documentType: 'contrato_pdf',
            notes: $this->existing_contract_signed_notes ?: ('Contrato firmado adjunto a '.$contract->contract_number)
        );

        $oldAttachmentId = $contract->attachment_id;
        $contract->update(['attachment_id' => $att->id]);

        AuditService::log(
            event: 'staff.contract_signed_uploaded',
            module: 'personal',
            auditable: $contract,
            oldValues: ['attachment_id' => $oldAttachmentId],
            newValues: ['attachment_id' => $att->id, 'version' => $att->version],
            reason: $this->existing_contract_signed_notes ?: 'Archivo de contrato firmado adjuntado'
        );

        $this->cancelAttachContractFile();
        $this->feedback = sprintf('Archivo de contrato firmado (v%d) adjuntado correctamente al contrato %s.', $att->version, $contract->contract_number);
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

        $allStaffMembers = StaffMember::orderBy('first_name')->orderBy('last_name')->get();

        $staffQuery = StaffMember::with('attachments')->latest();

        if (trim($this->search) !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $staffQuery->where(function ($q) use ($term) {
                $q->whereRaw("LOWER(TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '') || ' ' || COALESCE(second_last_name, ''))) LIKE ?", [$term])
                    ->orWhereRaw('LOWER(identification_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(staff_code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw("LOWER(COALESCE(academic_degree, '')) LIKE ?", [$term])
                    ->orWhereRaw("LOWER(COALESCE(specialty, '')) LIKE ?", [$term]);
            });
        }

        if ($this->filter_role_type !== '') {
            $staffQuery->where('staff_role_type', $this->filter_role_type);
        }

        if ($this->filter_regime !== '') {
            $staffQuery->where('hiring_regime', $this->filter_regime);
        }

        return view('livewire.staff-page', [
            'staffMembers' => $staffQuery->get(),
            'allStaffMembers' => $allStaffMembers,
            'roleTypeLabels' => StaffMember::roleTypeLabels(),
            'contracts' => StaffContract::with(['staffMember', 'attachment'])->latest()->get(),
            'payments' => StaffPayment::with(['staffMember', 'contract'])->latest('payment_date')->limit(50)->get(),
            'selectedPayContract' => $selectedPayContract,
            'idTypes' => Picklist::optionsFor('id_type'),
            'paymentMethods' => Picklist::optionsFor('payment_method'),
        ]);
    }
}
