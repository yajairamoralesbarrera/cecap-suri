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
use App\Rules\ValidEmailDomain;
use App\Rules\ValidIdentificationNumber;
use App\Services\AttachmentService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
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

    // Datos del representante legal (obligatorios cuando la alumna es menor de 18 años)
    public string $guardian_full_name = '';

    public string $guardian_identification_number = '';

    public string $guardian_relationship = 'Madre';

    public string $guardian_phone = '';

    public string $guardian_email = '';

    public string $notes = '';

    public $file_cedula = null;

    public $file_atestado = null;

    public bool $genderBlocked = false;

    public string $genderBlockedMessage = '';

    public bool $educationBlocked = false;

    public string $educationBlockedMessage = '';

    public bool $duplicateCourseBlocked = false;

    public string $duplicateCourseMessage = '';

    public string $priorUnapprovedNotice = '';

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

    // Retomar carga de adjuntos faltantes para solicitantes que regresan posteriormente (búsqueda solo por cédula)
    public bool $showResumeLookup = false;

    public string $lookup_identification = '';

    /** @var list<array<string, mixed>> */
    public array $lookupMatches = [];

    public bool $exitedForm = false;

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
        $this->evaluateDuplicateCourseEnrollment();
    }

    public function updatedIdentificationType(): void
    {
        $this->resetErrorBag(['identification_type', 'identification_number']);
        if (trim($this->identification_number) !== '') {
            $this->validateIdentificationPair();
        }
        $this->evaluateDuplicateCourseEnrollment();
    }

    public function updatedIdentificationNumber(): void
    {
        $this->resetErrorBag(['identification_type', 'identification_number']);
        if (trim($this->identification_number) !== '') {
            $this->validateIdentificationPair();
        }
        $this->evaluateDuplicateCourseEnrollment();
    }

    protected function validateIdentificationPair(): bool
    {
        $validTypes = array_keys(Picklist::optionsFor('id_type'));
        $validator = \Illuminate\Support\Facades\Validator::make([
            'identification_type' => $this->identification_type,
            'identification_number' => $this->identification_number,
        ], [
            'identification_type' => ['required', 'string', 'max:40', Rule::in($validTypes)],
            'identification_number' => ['required', 'string', 'max:40', new ValidIdentificationNumber($this->identification_type)],
        ], [
            'identification_type.in' => 'Seleccione un tipo de identificación válido.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $msgs) {
                $this->addError($field, $msgs[0]);
            }

            return false;
        }

        $existing = Student::findByIdentification($this->identification_number);
        if ($existing && $existing->identification_type && $existing->identification_type !== $this->identification_type) {
            $registeredLabel = Picklist::labelFor('id_type', $existing->identification_type);
            $this->addError('identification_type', sprintf(
                'La identificación %s ya está registrada como %s. Seleccione el tipo de identificación correcto.',
                $existing->identification_number,
                $registeredLabel
            ));

            return false;
        }

        return true;
    }

    public function updatedEducationLevel(): void
    {
        $this->evaluateEducationRequirement();
    }

    protected function evaluateDuplicateCourseEnrollment(): void
    {
        $this->duplicateCourseBlocked = false;
        $this->duplicateCourseMessage = '';
        $this->priorUnapprovedNotice = '';

        if (! $this->course_edition_id || trim($this->identification_number) === '') {
            return;
        }

        $edition = CourseEdition::with('course')->find($this->course_edition_id);
        if (! $edition) {
            return;
        }

        $student = Student::findByIdentification($this->identification_number);
        if (! $student) {
            return;
        }

        $existing = $student->activeEnrollmentInCourse((int) $edition->course_id);
        if ($existing) {
            $this->duplicateCourseBlocked = true;
            $this->duplicateCourseMessage = sprintf(
                'La cédula %s (%s) ya tiene una inscripción activa en el curso "%s" (cohorte %s, solicitud %s, estado: %s). No se permite duplicar la inscripción en el mismo curso.',
                $student->identification_number,
                $student->full_name,
                $edition->course?->name ?? 'este curso',
                $existing->edition?->name ?? 'cohorte vigente',
                $existing->enrollment_code,
                strtoupper(str_replace('_', ' ', (string) $existing->status))
            );

            return;
        }

        $priorUnapproved = $student->priorUnapprovedEnrollmentInCourse((int) $edition->course_id);
        if ($priorUnapproved) {
            $this->priorUnapprovedNotice = sprintf(
                'Se detectó un registro previo sin aprobar en "%s" (solicitud %s: %s). Puede enviar esta nueva inscripción; quedará registrada para autorización en Admisiones con el motivo: "%s".',
                $edition->course?->name ?? 'este curso',
                $priorUnapproved->enrollment_code,
                $priorUnapproved->unapprovedSummary(),
                Enrollment::DEFAULT_REENROLLMENT_REASON
            );
        }
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
        $this->resetErrorBag(['identification_type', 'identification_number']);
        $id = trim($this->identification_number);
        if ($id === '') {
            $this->duplicateCourseBlocked = false;
            $this->duplicateCourseMessage = '';

            return;
        }

        if (! $this->validateIdentificationPair()) {
            return;
        }

        $student = Student::findByIdentification($id);
        if (! $student) {
            $this->duplicateCourseBlocked = false;
            $this->duplicateCourseMessage = '';

            return;
        }

        $this->first_name = $student->first_name;
        $this->last_name = $student->last_name;
        $this->second_last_name = (string) ($student->second_last_name ?? '');
        $this->email = $student->email;
        $this->phone = $student->phone;
        $this->birth_date = $student->birth_date?->toDateString() ?? '';
        $this->education_level = (string) ($student->education_level ?? $this->education_level);
        $this->province_id = $student->province_id;
        $this->canton_id = $student->canton_id;
        $this->district_id = $student->district_id;
        $this->address_detail = (string) ($student->address_detail ?? '');
        $this->guardian_full_name = (string) ($student->guardian_full_name ?? '');
        $this->guardian_identification_number = (string) ($student->guardian_identification_number ?? '');
        $this->guardian_relationship = (string) ($student->guardian_relationship ?: 'Madre');
        $this->guardian_phone = (string) ($student->guardian_phone ?? '');
        $this->guardian_email = (string) ($student->guardian_email ?? '');
        $this->evaluateEducationRequirement();
        $this->evaluateDuplicateCourseEnrollment();
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

        $isMinor = Student::isBirthDateMinor($this->birth_date);
        $validIdTypes = array_keys(Picklist::optionsFor('id_type'));

        $rules = [
            'course_edition_id' => ['required', 'integer', 'exists:course_editions,id'],
            'identification_type' => ['required', 'string', 'max:40', Rule::in($validIdTypes)],
            'identification_number' => ['required', 'string', 'max:40', new ValidIdentificationNumber($this->identification_type)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'second_last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', new ValidEmailDomain()],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'in:femenino'],
            'birth_date' => ['required', 'date', 'before:today'],
            'education_level' => ['required', 'string', 'max:80'],
            'province_id' => ['nullable', 'integer', 'exists:cr_provinces,id'],
            'canton_id' => ['nullable', 'integer', 'exists:cr_cantons,id'],
            'district_id' => ['nullable', 'integer', 'exists:cr_districts,id'],
            'file_cedula' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'file_atestado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ];

        if ($isMinor) {
            $rules['guardian_full_name'] = ['required', 'string', 'max:160'];
            $rules['guardian_identification_number'] = ['required', 'string', 'max:40', new ValidIdentificationNumber()];
            $rules['guardian_relationship'] = ['required', 'string', 'max:60'];
            $rules['guardian_phone'] = ['required', 'string', 'max:30'];
            $rules['guardian_email'] = ['nullable', 'email', 'max:255', new ValidEmailDomain()];
        }

        $this->validate($rules, [
            'identification_type.in' => 'Seleccione un tipo de identificación válido.',
            'email.required' => 'El correo electrónico es obligatorio para enviar su comprobante y planilla.',
            'gender.in' => $settings->gender_policy_message,
            'birth_date.required' => 'La fecha de nacimiento es obligatoria.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'guardian_full_name.required' => 'Por ser menor de edad, debe indicar el nombre completo del representante legal.',
            'guardian_identification_number.required' => 'Por ser menor de edad, debe indicar la cédula o DIMEX del representante legal.',
            'guardian_relationship.required' => 'Por ser menor de edad, debe indicar el parentesco del representante legal.',
            'guardian_phone.required' => 'Por ser menor de edad, debe indicar el teléfono del representante legal.',
        ]);

        if (! $this->validateIdentificationPair()) {
            return;
        }

        $edition = CourseEdition::with('course')->findOrFail($this->course_edition_id);
        $this->evaluateCapacityRequirement();
        $this->evaluateDuplicateCourseEnrollment();

        if ($this->duplicateCourseBlocked) {
            $this->addError('identification_number', $this->duplicateCourseMessage);

            return;
        }

        if ($eduError = $edition->course?->educationLevelRequirementError($this->education_level)) {
            $this->educationBlocked = true;
            $this->educationBlockedMessage = $eduError;
            $this->addError('education_level', $eduError);

            return;
        }

        $guardianData = $isMinor ? [
            'guardian_full_name' => trim($this->guardian_full_name),
            'guardian_identification_number' => trim($this->guardian_identification_number),
            'guardian_relationship' => trim($this->guardian_relationship),
            'guardian_phone' => trim($this->guardian_phone),
            'guardian_email' => $this->guardian_email !== '' ? strtolower(trim($this->guardian_email)) : null,
        ] : [];

        $student = Student::findByIdentification($this->identification_number);
        if ($student) {
            $student->update(array_merge([
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name !== '' ? trim($this->second_last_name) : null,
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'birth_date' => $this->birth_date,
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail !== '' ? trim($this->address_detail) : null,
                'emergency_contact_name' => $this->emergency_contact_name !== '' ? trim($this->emergency_contact_name) : null,
                'emergency_contact_phone' => $this->emergency_contact_phone !== '' ? trim($this->emergency_contact_phone) : null,
            ], $guardianData));
        } else {
            $student = Student::create(array_merge([
                'student_code' => Student::nextCode(),
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name !== '' ? trim($this->second_last_name) : null,
                'identification_type' => $this->identification_type,
                'identification_number' => trim($this->identification_number),
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'gender' => 'femenino',
                'birth_date' => $this->birth_date,
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail !== '' ? trim($this->address_detail) : null,
                'emergency_contact_name' => $this->emergency_contact_name !== '' ? trim($this->emergency_contact_name) : null,
                'emergency_contact_phone' => $this->emergency_contact_phone !== '' ? trim($this->emergency_contact_phone) : null,
                'status' => 'activo',
            ], $guardianData));
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
        $this->lookupMatches = [];
        $this->resetValidation();
    }

    public function resumePendingEnrollment(): void
    {
        $this->validate([
            'lookup_identification' => ['required', 'string', 'max:40'],
        ], [
            'lookup_identification.required' => 'Ingrese su número de cédula o DIMEX.',
        ]);

        $idNum = trim($this->lookup_identification);
        $normalized = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $idNum));

        $enrollments = Enrollment::with(['student', 'edition.course'])
            ->whereHas('student', function ($q) use ($idNum, $normalized): void {
                $q->where('identification_number', $idNum);
                if ($normalized !== '') {
                    $q->orWhereRaw("UPPER(REGEXP_REPLACE(identification_number, '[^0-9A-Za-z]', '', 'g')) = ?", [$normalized]);
                }
            })
            ->orderByDesc('id')
            ->get();

        if ($enrollments->isEmpty()) {
            $this->lookupMatches = [];
            $this->addError('lookup_identification', 'No se encontró ninguna solicitud registrada con esa cédula / DIMEX.');

            return;
        }

        $nonRejected = $enrollments->where('status', '!=', 'rechazada')->values();
        if ($nonRejected->isEmpty()) {
            $this->lookupMatches = [];
            $this->addError('lookup_identification', 'Su solicitud se encuentra rechazada. Por favor acuda o comuníquese con Secretaría / Admisiones para presentar sus adjuntos y solicitar su reapertura.');

            return;
        }

        // Priorizar solicitudes que tienen documentos faltantes
        $incomplete = $nonRejected->filter(fn (Enrollment $enr) => ! empty($enr->missingDocuments()))->values();
        $candidates = $incomplete->isNotEmpty() ? $incomplete : $nonRejected;

        if ($candidates->count() === 1) {
            $this->loadFoundEnrollment($candidates->first());

            return;
        }

        $this->lookupMatches = $candidates->map(fn (Enrollment $enr) => [
            'id' => $enr->id,
            'code' => $enr->enrollment_code,
            'course' => $enr->edition?->course?->name ?? 'Curso',
            'cohort' => $enr->edition?->name ?? 'Cohorte',
            'status' => strtoupper(str_replace('_', ' ', $enr->status)),
            'missing' => implode(', ', $enr->missingDocumentLabels()),
        ])->all();
    }

    public function selectLookupEnrollment(int $enrollmentId): void
    {
        $allowedIds = array_column($this->lookupMatches, 'id');
        if (! in_array($enrollmentId, $allowedIds, true)) {
            return;
        }

        $enrollment = Enrollment::with(['student', 'edition.course'])->findOrFail($enrollmentId);
        $this->loadFoundEnrollment($enrollment);
    }

    protected function loadFoundEnrollment(Enrollment $enrollment): void
    {
        $settings = InstitutionSetting::current();
        $this->createdEnrollmentId = $enrollment->id;
        $this->successCode = $enrollment->enrollment_code;
        $this->successStatus = $enrollment->status;
        $this->provisionalExpiresAt = $enrollment->provisional_expires_at?->format('d/m/Y H:i') ?? 'Vigente';
        $this->missingDocumentKeys = $enrollment->missingDocuments();
        $this->missingDocumentLabels = $enrollment->missingDocumentLabels();
        $this->showPendingUploadForm = ! empty($this->missingDocumentKeys);
        $this->showResumeLookup = false;
        $this->lookupMatches = [];
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
            'emergency_contact_phone', 'guardian_full_name', 'guardian_identification_number',
            'guardian_phone', 'guardian_email', 'notes', 'file_cedula', 'file_atestado',
            'createdEnrollmentId', 'successCode', 'successStatus', 'signedPlanillaUrl',
            'provisionalExpiresAt', 'missingDocumentLabels', 'missingDocumentKeys',
            'showPendingUploadForm', 'uploadFeedback', 'pending_file_cedula', 'pending_file_atestado',
            'showResumeLookup', 'lookup_identification', 'lookupMatches',
        ]);
        $this->gender = 'femenino';
        $this->guardian_relationship = 'Madre';
        $this->genderBlocked = false;
        $this->educationBlocked = false;
        $this->educationBlockedMessage = '';
        $this->duplicateCourseBlocked = false;
        $this->duplicateCourseMessage = '';
        $this->isWaitlistMode = false;
        $this->waitlistNotice = '';
        $this->exitedForm = false;
    }

    public function exitEnrollment(): mixed
    {
        $this->resetForm();

        return $this->redirect('https://proyectosuri.org/');
    }

    public function returnToForm(): void
    {
        $this->exitedForm = false;
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

        $planillaPdfDataUri = null;
        if ($this->createdEnrollmentId) {
            $createdEnrollment = Enrollment::with([
                'student.province',
                'student.canton',
                'student.district',
                'edition.course.category',
                'edition.shift',
                'edition.modality',
                'edition.schedules',
            ])->find($this->createdEnrollmentId);

            if ($createdEnrollment) {
                $pdfBytes = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.enrollment-form', [
                    'enrollment' => $createdEnrollment,
                    'settings' => $settings,
                    'documentTypes' => config('cecap.document_types'),
                    'logoDataUri' => $settings->logoDataUri(),
                    'isHtmlPreview' => false,
                ])->setPaper('letter', 'portrait')->output();

                $planillaPdfDataUri = 'data:application/pdf;base64,'.base64_encode($pdfBytes);
            }
        }

        $applicantAge = Student::ageFromBirthDate($this->birth_date);

        return view('livewire.public-enrollment-page', [
            'settings' => $settings,
            'editions' => $editions,
            'selectedEdition' => $selectedEdition,
            'planillaPdfDataUri' => $planillaPdfDataUri,
            'applicantAge' => $applicantAge,
            'isMinorApplicant' => $applicantAge !== null && $applicantAge < 18,
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
