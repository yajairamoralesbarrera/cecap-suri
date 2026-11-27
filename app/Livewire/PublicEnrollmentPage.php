<?php

namespace App\Livewire;

use App\Models\CourseEdition;
use App\Models\CrCanton;
use App\Models\CrDistrict;
use App\Models\CrProvince;
use App\Models\Enrollment;
use App\Models\InstitutionSetting;
use App\Models\Picklist;
use App\Models\Student;
use App\Services\AttachmentService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.guest')]
#[Title('Inscripción en Línea — CeCap SURÍ')]
class PublicEnrollmentPage extends Component
{
    use WithFileUploads;

    public ?int $course_edition_id = null;

    public string $identification_type = 'cedula_nacional';

    public string $identification_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $gender = 'femenino';

    public ?string $birth_date = null;

    public string $education_level = 'bachillerato_secundaria';

    public ?int $province_id = null;

    public ?int $canton_id = null;

    public ?int $district_id = null;

    public string $address_detail = '';

    public string $emergency_contact_name = '';

    public string $emergency_contact_phone = '';

    public string $notes = '';

    public $file_cedula = null;

    public $file_atestado = null;

    public bool $genderBlocked = false;

    public string $genderBlockedMessage = '';

    public bool $educationBlocked = false;

    public string $educationBlockedMessage = '';

    public bool $isWaitlistMode = false;

    public string $waitlistNotice = '';

    #[Locked]
    public ?int $createdEnrollmentId = null;

    public ?string $successCode = null;

    public ?string $successStatus = null;

    public ?string $signedPlanillaUrl = null;

    public ?string $provisionalExpiresAt = null;

    /** @var list<string> */
    public array $missingDocumentLabels = [];

    /** @var list<string> */
    public array $missingDocumentKeys = [];

    public bool $showPendingUploadForm = false;

    public ?string $uploadFeedback = null;

    public $pending_file_cedula = null;

    public $pending_file_atestado = null;

    // Retomar carga de adjuntos faltantes para solicitantes que regresan posteriormente
    public bool $showResumeLookup = false;

    public string $lookup_identification = '';

    public string $lookup_enrollment_code = '';

    public function updatedGender(string $value): void
    {
        $settings = InstitutionSetting::current();
        if (strtolower($value) !== 'femenino') {
            $this->genderBlocked = true;
            $this->genderBlockedMessage = $settings->gender_policy_message;
        } else {
            $this->genderBlocked = false;
            $this->genderBlockedMessage = '';
        }
    }

    public function updatedCourseEditionId(): void
    {
        $this->evaluateCapacityRequirement();
        $this->evaluateEducationRequirement();
    }

    public function updatedEducationLevel(): void
    {
        $this->evaluateEducationRequirement();
    }

    protected function evaluateCapacityRequirement(): void
    {
        if (! $this->course_edition_id) {
            $this->isWaitlistMode = false;
            $this->waitlistNotice = '';

            return;
        }

        $edition = CourseEdition::find($this->course_edition_id);
        if (! $edition) {
            $this->isWaitlistMode = false;
            $this->waitlistNotice = '';

            return;
        }

        if ($edition->availableSeats() <= 0) {
            $this->isWaitlistMode = true;
            $this->waitlistNotice = sprintf(
                'Cupo máximo alcanzado (%d / %d cupos ocupados). Puede enviar su solicitud y quedará registrada automáticamente en Lista de Espera en Línea; Secretaría le contactará si se libera un cupo o se abre un nuevo grupo.',
                $edition->activeEnrollmentsCount(),
                (int) $edition->max_capacity
            );
        } else {
            $this->isWaitlistMode = false;
            $this->waitlistNotice = '';
        }
    }

    protected function evaluateEducationRequirement(): void
    {
        if (! $this->course_edition_id) {
            $this->educationBlocked = false;
            $this->educationBlockedMessage = '';

            return;
        }

        $edition = CourseEdition::with('course')->find($this->course_edition_id);
        $error = $edition?->course?->educationLevelRequirementError($this->education_level);

        $this->educationBlocked = $error !== null;
        $this->educationBlockedMessage = (string) ($error ?? '');
    }

    public function updatedProvinceId(): void
    {
        $this->canton_id = null;
        $this->district_id = null;
    }

    public function updatedCantonId(): void
    {
        $this->district_id = null;
    }

    public function autofillByIdentification(): void
    {
        $id = trim($this->identification_number);
        if ($id === '') {
            return;
        }

        $student = Student::where('identification_number', $id)->first();
        if (! $student) {
            return;
        }

        $this->first_name = $student->first_name;
        $this->last_name = $student->last_name;
        $this->second_last_name = (string) ($student->second_last_name ?? '');
        $this->email = $student->email;
        $this->phone = $student->phone;
        $this->education_level = (string) ($student->education_level ?? $this->education_level);
        $this->province_id = $student->province_id;
        $this->canton_id = $student->canton_id;
        $this->district_id = $student->district_id;
        $this->address_detail = (string) ($student->address_detail ?? '');
        $this->evaluateEducationRequirement();
    }

    public function submit(EnrollmentService $enrollmentService, AttachmentService $attachmentService): void
    {
        $settings = InstitutionSetting::current();

        if (strtolower($this->gender) !== 'femenino') {
            $this->genderBlocked = true;
            $this->genderBlockedMessage = $settings->gender_policy_message;
            $this->addError('gender', $settings->gender_policy_message);

            return;
        }

        $this->validate([
            'course_edition_id' => ['required', 'integer', 'exists:course_editions,id'],
            'identification_type' => ['required', 'string', 'max:40'],
            'identification_number' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'in:femenino'],
            'birth_date' => ['nullable', 'date'],
            'education_level' => ['required', 'string', 'max:80'],
            'province_id' => ['nullable', 'integer', 'exists:cr_provinces,id'],
            'canton_id' => ['nullable', 'integer', 'exists:cr_cantons,id'],
            'district_id' => ['nullable', 'integer', 'exists:cr_districts,id'],
            'file_cedula' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'file_atestado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio para enviar su comprobante y planilla.',
            'gender.in' => $settings->gender_policy_message,
        ]);

        $edition = CourseEdition::with('course')->findOrFail($this->course_edition_id);
        $this->evaluateCapacityRequirement();

        if ($eduError = $edition->course?->educationLevelRequirementError($this->education_level)) {
            $this->educationBlocked = true;
            $this->educationBlockedMessage = $eduError;
            $this->addError('education_level', $eduError);

            return;
        }

        $student = Student::where('identification_number', trim($this->identification_number))->first();
        if ($student) {
            $student->update([
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name !== '' ? trim($this->second_last_name) : null,
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail !== '' ? trim($this->address_detail) : null,
                'emergency_contact_name' => $this->emergency_contact_name !== '' ? trim($this->emergency_contact_name) : null,
                'emergency_contact_phone' => $this->emergency_contact_phone !== '' ? trim($this->emergency_contact_phone) : null,
            ]);
        } else {
            $student = Student::create([
                'student_code' => Student::nextCode(),
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name !== '' ? trim($this->second_last_name) : null,
                'identification_type' => $this->identification_type,
                'identification_number' => trim($this->identification_number),
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'gender' => 'femenino',
                'birth_date' => $this->birth_date ?: null,
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail !== '' ? trim($this->address_detail) : null,
                'emergency_contact_name' => $this->emergency_contact_name !== '' ? trim($this->emergency_contact_name) : null,
                'emergency_contact_phone' => $this->emergency_contact_phone !== '' ? trim($this->emergency_contact_phone) : null,
                'status' => 'activo',
            ]);
        }

        $submittedDocs = [];
        if ($this->file_cedula) {
            $attachmentService->store($student, $this->file_cedula, 'cedula_dimex', 'Adjunto en inscripción en línea');
            $submittedDocs[] = 'cedula_dimex';
        }
        if ($this->file_atestado) {
            $attachmentService->store($student, $this->file_atestado, 'atestado_academico', 'Atestado adjunto en inscripción en línea');
            $submittedDocs[] = 'atestado_academico';
        }

        try {
            $enrollment = $enrollmentService->createOnlinePreEnrollment(
                student: $student,
                edition: $edition,
                submittedDocuments: $submittedDocs,
                notes: $this->notes !== '' ? $this->notes : null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->createdEnrollmentId = $enrollment->id;
        $this->successCode = $enrollment->enrollment_code;
        $this->successStatus = $enrollment->status;
        $this->provisionalExpiresAt = $enrollment->provisional_expires_at?->format('d/m/Y H:i');
        $this->missingDocumentKeys = $enrollment->missingDocuments();
        $this->missingDocumentLabels = $enrollment->missingDocumentLabels();
        $this->showPendingUploadForm = false;
        $this->uploadFeedback = null;
        $this->signedPlanillaUrl = URL::temporarySignedRoute(
            'inscripcion.planilla.publica',
            now()->addDays(max(1, (int) $settings->online_enrollment_provisional_days)),
            ['enrollment' => $enrollment->id],
            absolute: false
        );
    }

    public function openPendingUploadForm(): void
    {
        $this->resetValidation();
        $this->uploadFeedback = null;
        $this->showPendingUploadForm = true;
    }

    public function closePendingUploadForm(): void
    {
        $this->resetValidation();
        $this->reset(['pending_file_cedula', 'pending_file_atestado']);
        $this->showPendingUploadForm = false;
    }

    public function uploadMissingDocuments(EnrollmentService $enrollmentService, AttachmentService $attachmentService): void
    {
        if (! $this->createdEnrollmentId) {
            return;
        }

        $this->validate([
            'pending_file_cedula' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'pending_file_atestado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ]);

        if (! $this->pending_file_cedula && ! $this->pending_file_atestado) {
            $this->addError('pending_file_atestado', 'Seleccione al menos un documento (PDF, JPG, PNG o WebP) antes de guardar.');

            return;
        }

        $enrollment = Enrollment::with('student')->findOrFail($this->createdEnrollmentId);
        $addedDocs = [];

        if ($this->pending_file_cedula) {
            $attachmentService->store($enrollment->student, $this->pending_file_cedula, 'cedula_dimex', 'Cédula/DIMEX adjuntada posteriormente en línea');
            $addedDocs[] = 'cedula_dimex';
        }
        if ($this->pending_file_atestado) {
            $attachmentService->store($enrollment->student, $this->pending_file_atestado, 'atestado_academico', 'Atestado académico adjuntado posteriormente en línea');
            $addedDocs[] = 'atestado_academico';
        }

        try {
            $updated = $enrollmentService->attachOnlinePendingDocuments($enrollment, $addedDocs);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->successStatus = $updated->status;
        $this->missingDocumentKeys = $updated->missingDocuments();
        $this->missingDocumentLabels = $updated->missingDocumentLabels();
        $this->reset(['pending_file_cedula', 'pending_file_atestado']);
        $this->showPendingUploadForm = false;
        $this->uploadFeedback = $updated->documents_complete
            ? sprintf('Documentos guardados en la solicitud %s. Su documentación está ahora completa (estado: %s) y su Planilla PDF se ha actualizado.', $updated->enrollment_code, strtoupper(str_replace('_', ' ', $updated->status)))
            : sprintf('Documento(s) guardados en la solicitud %s. Aún quedan documentos pendientes indicados abajo.', $updated->enrollment_code);
    }

    public function toggleResumeLookup(): void
    {
        $this->showResumeLookup = ! $this->showResumeLookup;
        $this->resetValidation();
    }

    public function resumePendingEnrollment(): void
    {
        $this->validate([
            'lookup_identification' => ['required', 'string', 'max:40'],
            'lookup_enrollment_code' => ['required', 'string', 'max:40'],
        ], [
            'lookup_identification.required' => 'Ingrese su número de cédula o DIMEX.',
            'lookup_enrollment_code.required' => 'Ingrese su código de solicitud (ej. MAT-2026-0001).',
        ]);

        $idNum = trim($this->lookup_identification);
        $code = strtoupper(trim($this->lookup_enrollment_code));

        $enrollment = Enrollment::with('student')
            ->whereRaw('UPPER(enrollment_code) = ?', [$code])
            ->whereHas('student', fn ($q) => $q->where('identification_number', $idNum))
            ->first();

        if (! $enrollment) {
            $this->addError('lookup_enrollment_code', 'No se encontró ninguna solicitud que coincida con esa cédula y código. Verifique los datos en su comprobante o Planilla PDF.');

            return;
        }

        if ($enrollment->status === 'rechazada') {
            $this->addError('lookup_enrollment_code', 'Esta solicitud se encuentra rechazada. Por favor acuda o comuníquese con Secretaría / Admisiones para presentar sus adjuntos y solicitar su reapertura.');

            return;
        }

        $settings = InstitutionSetting::current();
        $this->createdEnrollmentId = $enrollment->id;
        $this->successCode = $enrollment->enrollment_code;
        $this->successStatus = $enrollment->status;
        $this->provisionalExpiresAt = $enrollment->provisional_expires_at?->format('d/m/Y H:i') ?? 'Vigente';
        $this->missingDocumentKeys = $enrollment->missingDocuments();
        $this->missingDocumentLabels = $enrollment->missingDocumentLabels();
        $this->showPendingUploadForm = ! empty($this->missingDocumentKeys);
        $this->showResumeLookup = false;
        $this->uploadFeedback = empty($this->missingDocumentKeys)
            ? sprintf('Solicitud %s encontrada: su documentación ya se encuentra completa.', $enrollment->enrollment_code)
            : null;
        $this->signedPlanillaUrl = URL::temporarySignedRoute(
            'inscripcion.planilla.publica',
            now()->addDays(max(1, (int) $settings->online_enrollment_provisional_days)),
            ['enrollment' => $enrollment->id],
            absolute: false
        );
    }

    public function resetForm(): void
    {
        $this->reset([
            'course_edition_id', 'identification_number', 'first_name', 'last_name',
            'second_last_name', 'email', 'phone', 'birth_date', 'province_id',
            'canton_id', 'district_id', 'address_detail', 'emergency_contact_name',
            'emergency_contact_phone', 'notes', 'file_cedula', 'file_atestado',
            'createdEnrollmentId', 'successCode', 'successStatus', 'signedPlanillaUrl',
            'provisionalExpiresAt', 'missingDocumentLabels', 'missingDocumentKeys',
            'showPendingUploadForm', 'uploadFeedback', 'pending_file_cedula', 'pending_file_atestado',
            'showResumeLookup', 'lookup_identification', 'lookup_enrollment_code',
        ]);
        $this->gender = 'femenino';
        $this->genderBlocked = false;
        $this->educationBlocked = false;
        $this->educationBlockedMessage = '';
        $this->isWaitlistMode = false;
        $this->waitlistNotice = '';
    }

    public function render()
    {
        $settings = InstitutionSetting::current();
        $editions = CourseEdition::query()
            ->where('is_public_enrollment_open', true)
            ->whereIn('status', ['abierta', 'planificada'])
            ->with(['course.category', 'shift', 'modality', 'schedules'])
            ->orderBy('start_date')
            ->get();

        $selectedEdition = $this->course_edition_id
            ? $editions->firstWhere('id', $this->course_edition_id)
            : null;

        return view('livewire.public-enrollment-page', [
            'settings' => $settings,
            'editions' => $editions,
            'selectedEdition' => $selectedEdition,
            'requiredDocs' => $selectedEdition?->course?->required_documents ?? ['cedula_dimex'],
            'documentTypes' => config('cecap.document_types'),
            'idTypes' => Picklist::optionsFor('id_type'),
            'genders' => Picklist::optionsFor('gender'),
            'educationLevels' => Picklist::optionsFor('education_level'),
            'provinces' => CrProvince::where('is_active', true)->orderBy('code')->get(),
            'cantons' => $this->province_id ? CrCanton::where('province_id', $this->province_id)->where('is_active', true)->orderBy('name')->get() : collect(),
            'districts' => $this->canton_id ? CrDistrict::where('canton_id', $this->canton_id)->where('is_active', true)->orderBy('name')->get() : collect(),
        ]);
    }
}
