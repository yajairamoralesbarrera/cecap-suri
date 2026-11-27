<?php

namespace App\Livewire;

use App\Models\CourseEdition;
use App\Models\CrCanton;
use App\Models\CrDistrict;
use App\Models\CrProvince;
use App\Models\Enrollment;
use App\Models\Picklist;
use App\Models\RecordAttachment;
use App\Models\Student;
use App\Rules\ValidEmailDomain;
use App\Rules\ValidIdentificationNumber;
use App\Services\AttachmentService;
use App\Services\AuditService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Admisiones y Matrícula')]
class AdmissionsPage extends Component
{
    use WithFileUploads;

    public string $tab = 'queue';

    public ?string $feedback = null;

    // Formulario creación / edición controlada de alumna + matrícula presencial
    public ?int $editingStudentId = null;

    public string $edit_reason = '';

    public string $identification_type = 'cedula_nacional';

    public string $identification_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $second_last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $gender = 'femenino'; // Precargado en Femenino en /admisiones

    public string $birth_date = '';

    public string $education_level = 'bachillerato_secundaria';

    public ?int $province_id = null;

    public ?int $canton_id = null;

    public ?int $district_id = null;

    public string $address_detail = '';

    // Datos del representante legal (obligatorios si la alumna es menor de 18 años)
    public string $guardian_full_name = '';

    public string $guardian_identification_number = '';

    public string $guardian_relationship = 'Madre';

    public string $guardian_phone = '';

    public string $guardian_email = '';

    public bool $duplicateCourseBlocked = false;

    public string $duplicateCourseMessage = '';

    public bool $priorUnapprovedDetected = false;

    public string $priorUnapprovedMessage = '';

    public bool $authorize_reenrollment = false;

    public string $reenrollment_reason = Enrollment::DEFAULT_REENROLLMENT_REASON;

    public ?int $course_edition_id = null;

    public array $submitted_documents = ['cedula_dimex'];

    public bool $confirm_immediately = true;

    public bool $authorize_exception = false;

    public string $exception_reason = '';

    public string $exception_commitment_date = '';

    public $attachment_file = null;

    public string $attachment_doc_type = 'cedula_dimex';

    // Confirmación desde cola de preinscritas
    public ?int $confirmingEnrollmentId = null;

    public array $confirm_docs = [];

    public bool $confirm_exception = false;

    public string $confirm_exception_reason = '';

    public string $confirm_exception_date = '';

    public bool $confirm_needs_reenrollment = false;

    public string $confirm_reenrollment_info = '';

    public bool $confirm_authorize_reenrollment = false;

    public string $confirm_reenrollment_reason = Enrollment::DEFAULT_REENROLLMENT_REASON;

    // Rechazo auditado con motivo de picklist y notificación por correo
    public ?int $rejectingEnrollmentId = null;

    public string $rejection_reason_code = '';

    public string $rejection_notes = '';

    // Carga inline de adjuntos faltantes sobre una solicitud en Cola de Admisiones
    public ?int $attachingEnrollmentId = null;

    public string $enr_attachment_doc_type = 'atestado_academico';

    public $enr_attachment_file = null;

    public string $enr_attachment_notes = '';

    // Reapertura auditada de una solicitud rechazada (con opción de adjuntar faltante al mismo tiempo)
    public ?int $reopeningEnrollmentId = null;

    public string $reopen_reason = '';

    public bool $reopen_authorize_overcapacity = false;

    public string $reopen_attachment_doc_type = 'atestado_academico';

    public $reopen_attachment_file = null;

    // Carga inline de adjuntos en Directorio de Alumnas
    public ?int $attachingStudentId = null;

    public string $student_attachment_doc_type = 'atestado_academico';

    public $student_attachment_file = null;

    public string $student_attachment_notes = '';

    public ?int $showingArchivedStudentId = null;

    public string $queue_search = '';

    public string $queue_status_filter = '';

    public string $queue_edition_filter = '';

    // Decisión del funcionario en matrícula presencial (cupo disponible o cupo máximo alcanzado)
    public string $presencial_action = 'inscribir'; // inscribir | lista_espera | rechazar

    public string $presencial_rejection_reason_code = '';

    public string $presencial_rejection_notes = '';

    public string $full_capacity_decision = '';

    public string $full_capacity_notes = '';

    public bool $selectedEditionIsFull = false;

    public string $selectedEditionCapacityInfo = '';

    public function resetQueueFilters(): void
    {
        $this->queue_search = '';
        $this->queue_status_filter = '';
        $this->queue_edition_filter = '';
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();
    }

    public function updatedCourseEditionId(): void
    {
        $this->presencial_action = 'inscribir';
        $this->presencial_rejection_reason_code = '';
        $this->presencial_rejection_notes = '';
        $this->full_capacity_decision = '';
        $this->full_capacity_notes = '';
        if (! $this->course_edition_id) {
            $this->selectedEditionIsFull = false;
            $this->selectedEditionCapacityInfo = '';

            return;
        }
        $edition = CourseEdition::find($this->course_edition_id);
        if (! $edition) {
            $this->selectedEditionIsFull = false;
            $this->selectedEditionCapacityInfo = '';

            return;
        }
        $active = $edition->activeEnrollmentsCount();
        $max = (int) $edition->max_capacity;
        $this->selectedEditionIsFull = $edition->availableSeats() <= 0;
        $this->selectedEditionCapacityInfo = sprintf('%d / %d cupos ocupados', $active, $max);
        $this->authorize_exception = false;
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
        $validator = Validator::make([
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

    protected function evaluateDuplicateCourseEnrollment(): void
    {
        $this->duplicateCourseBlocked = false;
        $this->duplicateCourseMessage = '';
        $this->priorUnapprovedDetected = false;
        $this->priorUnapprovedMessage = '';

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
            $this->priorUnapprovedDetected = true;
            $this->priorUnapprovedMessage = sprintf(
                'La alumna %s (%s) tiene una inscripción previa sin aprobar en "%s" (cohorte %s, solicitud %s: %s). Puede inscribirla nuevamente marcando la autorización con el motivo correspondiente.',
                $student->full_name,
                $student->identification_number,
                $edition->course?->name ?? 'este curso',
                $priorUnapproved->edition?->name ?? 'cohorte anterior',
                $priorUnapproved->enrollment_code,
                $priorUnapproved->unapprovedSummary()
            );
            if (trim($this->reenrollment_reason) === '') {
                $this->reenrollment_reason = Enrollment::DEFAULT_REENROLLMENT_REASON;
            }
        }
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

    public function autofillStudent(): void
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
        $this->loadStudentIntoForm($student);
        $this->evaluateDuplicateCourseEnrollment();
        $this->feedback = $this->duplicateCourseBlocked
            ? null
            : 'Datos de la alumna autollenados desde el expediente existente.';
    }

    public function editStudent(int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $this->loadStudentIntoForm($student);
        $this->attachingStudentId = null;
        $this->editingStudentId = $student->id;
        $this->edit_reason = '';
        $this->resetValidation();
        $this->tab = 'students';
    }

    protected function loadStudentIntoForm(Student $student): void
    {
        $this->identification_type = $student->identification_type;
        $this->identification_number = $student->identification_number;
        $this->first_name = $student->first_name;
        $this->last_name = $student->last_name;
        $this->second_last_name = (string) ($student->second_last_name ?? '');
        $this->email = $student->email;
        $this->phone = $student->phone;
        $this->gender = $student->gender ?: 'femenino';
        $this->birth_date = $student->birth_date?->toDateString() ?? '';
        $this->education_level = (string) ($student->education_level ?? 'bachillerato_secundaria');
        $this->province_id = $student->province_id;
        $this->canton_id = $student->canton_id;
        $this->district_id = $student->district_id;
        $this->address_detail = (string) ($student->address_detail ?? '');
        $this->guardian_full_name = (string) ($student->guardian_full_name ?? '');
        $this->guardian_identification_number = (string) ($student->guardian_identification_number ?? '');
        $this->guardian_relationship = (string) ($student->guardian_relationship ?: 'Madre');
        $this->guardian_phone = (string) ($student->guardian_phone ?? '');
        $this->guardian_email = (string) ($student->guardian_email ?? '');
    }

    public function cancelEditStudent(): void
    {
        $this->reset([
            'editingStudentId', 'edit_reason', 'identification_number', 'first_name',
            'last_name', 'second_last_name', 'email', 'phone', 'birth_date', 'province_id',
            'canton_id', 'district_id', 'address_detail',
            'guardian_full_name', 'guardian_identification_number', 'guardian_phone', 'guardian_email',
            'duplicateCourseBlocked', 'duplicateCourseMessage',
        ]);
        $this->gender = 'femenino';
        $this->guardian_relationship = 'Madre';
    }

    public function saveStudentOnly(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);

        $isMinor = Student::isBirthDateMinor($this->birth_date);

        $rules = [
            'identification_type' => ['required', 'string', 'max:40'],
            'identification_number' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', new ValidEmailDomain()], // OBLIGATORIO en creación y edición
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date', 'before:today'],
        ];

        if ($isMinor) {
            $rules['guardian_full_name'] = ['required', 'string', 'max:160'];
            $rules['guardian_identification_number'] = ['required', 'string', 'max:40'];
            $rules['guardian_relationship'] = ['required', 'string', 'max:60'];
            $rules['guardian_phone'] = ['required', 'string', 'max:30'];
            $rules['guardian_email'] = ['nullable', 'email', 'max:255', new ValidEmailDomain()];
        }

        if ($this->editingStudentId) {
            $rules['edit_reason'] = ['required', 'string', 'min:5'];
        }

        $this->validate($rules, [
            'email.required' => 'El correo electrónico es obligatorio en creación y edición de expediente.',
            'edit_reason.required' => 'La edición controlada exige indicar el motivo de la modificación para auditoría.',
            'guardian_full_name.required' => 'Por ser menor de edad, debe indicar el nombre completo del representante legal.',
            'guardian_identification_number.required' => 'Por ser menor de edad, debe indicar la cédula o DIMEX del representante legal.',
            'guardian_relationship.required' => 'Por ser menor de edad, debe indicar el parentesco del representante legal.',
            'guardian_phone.required' => 'Por ser menor de edad, debe indicar el teléfono del representante legal.',
        ]);

        if ($this->editingStudentId) {
            $student = Student::findOrFail($this->editingStudentId);
            $old = $student->only(['first_name', 'last_name', 'email', 'phone', 'gender', 'birth_date', 'education_level', 'guardian_full_name', 'guardian_phone']);
            $student->update([
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name ?: null,
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'gender' => $this->gender,
                'birth_date' => $this->birth_date ?: $student->birth_date,
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail ?: null,
                'guardian_full_name' => $isMinor ? trim($this->guardian_full_name) : ($this->guardian_full_name !== '' ? trim($this->guardian_full_name) : $student->guardian_full_name),
                'guardian_identification_number' => $isMinor ? trim($this->guardian_identification_number) : ($this->guardian_identification_number !== '' ? trim($this->guardian_identification_number) : $student->guardian_identification_number),
                'guardian_relationship' => $isMinor ? trim($this->guardian_relationship) : ($this->guardian_relationship !== '' ? trim($this->guardian_relationship) : $student->guardian_relationship),
                'guardian_phone' => $isMinor ? trim($this->guardian_phone) : ($this->guardian_phone !== '' ? trim($this->guardian_phone) : $student->guardian_phone),
                'guardian_email' => $this->guardian_email !== '' ? strtolower(trim($this->guardian_email)) : $student->guardian_email,
                'updated_by' => Auth::id(),
            ]);

            AuditService::log(
                event: 'student.updated_controlled',
                module: 'admisiones',
                auditable: $student,
                oldValues: $old,
                newValues: $student->only(['first_name', 'last_name', 'email', 'phone', 'gender', 'birth_date', 'education_level', 'guardian_full_name', 'guardian_phone']),
                reason: $this->edit_reason
            );
            $this->cancelEditStudent();
            $this->feedback = 'Expediente actualizado bajo edición controlada y auditada.';
        }
    }

    public function createPresencialEnrollment(EnrollmentService $enrollmentService, AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);

        $isMinor = Student::isBirthDateMinor($this->birth_date);
        $validIdTypes = array_keys(Picklist::optionsFor('id_type'));

        $rules = [
            'identification_type' => ['required', 'string', 'max:40', Rule::in($validIdTypes)],
            'identification_number' => ['required', 'string', 'max:40', new ValidIdentificationNumber($this->identification_type)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', new ValidEmailDomain()],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'string', 'max:30'],
            'birth_date' => ['required', 'date', 'before:today'],
            'education_level' => ['required', 'string', 'max:80'],
            'course_edition_id' => ['required', 'integer', 'exists:course_editions,id'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
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
            'email.required' => 'El correo electrónico es obligatorio en Admisiones.',
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
        $this->evaluateDuplicateCourseEnrollment();
        if ($this->duplicateCourseBlocked) {
            $this->addError('identification_number', $this->duplicateCourseMessage);

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
        if (! $student) {
            $student = Student::create(array_merge([
                'student_code' => Student::nextCode(),
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name ?: null,
                'identification_type' => $this->identification_type,
                'identification_number' => trim($this->identification_number),
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'gender' => $this->gender ?: 'femenino',
                'birth_date' => $this->birth_date,
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail ?: null,
                'status' => 'activo',
                'created_by' => Auth::id(),
            ], $guardianData));
        } else {
            $student->update(array_merge([
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'birth_date' => $this->birth_date,
                'education_level' => $this->education_level,
                'updated_by' => Auth::id(),
            ], $guardianData));
        }

        $docs = $this->submitted_documents;
        if ($this->attachment_file) {
            $attachmentService->store($student, $this->attachment_file, $this->attachment_doc_type, 'Adjunto en matrícula presencial');
            if (! in_array($this->attachment_doc_type, $docs, true)) {
                $docs[] = $this->attachment_doc_type;
            }
        }

        if ($this->authorize_reenrollment && ! Auth::user()->hasEffectivePermission('admisiones.exceptions.authorize')) {
            $this->addError('reenrollment_reason', 'No tiene permiso para autorizar re-inscripciones por curso anteriormente sin aprobar.');

            return;
        }

        try {
            $enrollment = $enrollmentService->createPresencialEnrollment(
                student: $student,
                edition: $edition,
                submittedDocuments: $docs,
                confirmImmediately: $this->confirm_immediately,
                authorizeException: $this->authorize_exception,
                exceptionReason: $this->exception_reason ?: null,
                exceptionCommitmentDate: $this->exception_commitment_date ?: null,
                capacityDecisionCode: $this->full_capacity_decision ?: null,
                capacityDecisionNotes: $this->full_capacity_notes ?: null,
                presencialAction: $this->presencial_action ?: 'inscribir',
                rejectionReasonCode: $this->presencial_rejection_reason_code ?: null,
                rejectionNotes: $this->presencial_rejection_notes ?: null,
                authorizeReenrollment: $this->authorize_reenrollment,
                reenrollmentReason: $this->reenrollment_reason ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reset([
            'editingStudentId',
            'edit_reason',
            'identification_number',
            'first_name',
            'last_name',
            'second_last_name',
            'email',
            'phone',
            'birth_date',
            'province_id',
            'canton_id',
            'district_id',
            'address_detail',
            'guardian_full_name',
            'guardian_identification_number',
            'guardian_phone',
            'guardian_email',
            'duplicateCourseBlocked',
            'duplicateCourseMessage',
            'priorUnapprovedDetected',
            'priorUnapprovedMessage',
            'authorize_reenrollment',
            'course_edition_id',
            'attachment_file',
            'authorize_exception',
            'exception_reason',
            'exception_commitment_date',
            'presencial_rejection_reason_code',
            'presencial_rejection_notes',
            'full_capacity_decision',
            'full_capacity_notes',
            'selectedEditionIsFull',
            'selectedEditionCapacityInfo',
        ]);
        $this->identification_type = 'cedula_nacional';
        $this->gender = 'femenino';
        $this->guardian_relationship = 'Madre';
        $this->education_level = 'bachillerato_secundaria';
        $this->submitted_documents = ['cedula_dimex'];
        $this->attachment_doc_type = 'cedula_dimex';
        $this->confirm_immediately = true;
        $this->presencial_action = 'inscribir';
        $this->reenrollment_reason = Enrollment::DEFAULT_REENROLLMENT_REASON;
        $this->resetValidation();

        $this->feedback = match ($enrollment->status) {
            'lista_espera' => sprintf('Solicitud %s registrada en LISTA DE ESPERA para %s. Formulario listo para una nueva inscripción.', $enrollment->enrollment_code, $student->full_name),
            'rechazada' => sprintf('Solicitud %s registrada como RECHAZADA (%s) para %s y notificada por correo. Formulario listo para una nueva inscripción.', $enrollment->enrollment_code, $enrollment->rejection_reason_label ?: 'motivo registrado', $student->full_name),
            default => sprintf('Matrícula %s registrada correctamente para %s (%s). Formulario listo para una nueva inscripción.', $enrollment->enrollment_code, $student->full_name, strtoupper(str_replace('_', ' ', $enrollment->status))),
        };
        $this->tab = 'enroll';
    }

    public function updatedAttachmentFile(): void
    {
        if ($this->attachment_file && $this->attachment_doc_type && ! in_array($this->attachment_doc_type, $this->submitted_documents, true)) {
            $this->submitted_documents[] = $this->attachment_doc_type;
        }
    }

    public function startConfirmEnrollment(int $enrollmentId): void
    {
        $enr = Enrollment::with(['student.attachments', 'edition.course'])->findOrFail($enrollmentId);
        $this->rejectingEnrollmentId = null;
        $this->attachingEnrollmentId = null;
        $this->reopeningEnrollmentId = null;
        $this->confirmingEnrollmentId = $enr->id;
        $activeTypes = $enr->student?->attachments?->where('status', 'activo')->pluck('document_type')->all() ?? [];
        $this->confirm_docs = array_values(array_unique(array_merge($enr->submitted_documents ?? [], $activeTypes)));
        $this->confirm_exception = false;
        $this->confirm_exception_reason = '';
        $this->confirm_exception_date = now()->addDays(15)->toDateString();

        $priorUnapproved = ($enr->student && $enr->edition)
            ? $enr->student->priorUnapprovedEnrollmentInCourse((int) $enr->edition->course_id, $enr->id)
            : null;
        $this->confirm_needs_reenrollment = ! $enr->reenrollment_authorized
            && ($priorUnapproved !== null || filled($enr->reenrollment_reason));
        $this->confirm_reenrollment_info = $priorUnapproved
            ? sprintf('Antecedente sin aprobar: %s (%s).', $priorUnapproved->enrollment_code, $priorUnapproved->unapprovedSummary())
            : (filled($enr->reenrollment_reason) ? 'Solicitud marcada para autorización de re-inscripción.' : '');
        $this->confirm_authorize_reenrollment = false;
        $this->confirm_reenrollment_reason = filled($enr->reenrollment_reason)
            ? (string) $enr->reenrollment_reason
            : Enrollment::DEFAULT_REENROLLMENT_REASON;

        $this->resetValidation();
    }

    public function cancelConfirmEnrollment(): void
    {
        $this->confirmingEnrollmentId = null;
    }

    public function startRejectEnrollment(int $enrollmentId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        $enr = Enrollment::findOrFail($enrollmentId);
        $this->confirmingEnrollmentId = null;
        $this->attachingEnrollmentId = null;
        $this->reopeningEnrollmentId = null;
        $this->rejectingEnrollmentId = $enr->id;
        $this->rejection_reason_code = '';
        $this->rejection_notes = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelRejectEnrollment(): void
    {
        $this->rejectingEnrollmentId = null;
        $this->rejection_reason_code = '';
        $this->rejection_notes = '';
        $this->resetValidation();
    }

    public function submitRejection(EnrollmentService $enrollmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        if (! $this->rejectingEnrollmentId) {
            return;
        }

        $this->validate([
            'rejection_reason_code' => ['required', 'string', 'max:60'],
            'rejection_notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'rejection_reason_code.required' => 'Debe seleccionar un motivo de rechazo del catálogo (picklist).',
        ]);

        $enrollment = Enrollment::with(['student', 'edition.course'])->findOrFail($this->rejectingEnrollmentId);

        try {
            $result = $enrollmentService->rejectEnrollment(
                enrollment: $enrollment,
                reasonCode: $this->rejection_reason_code,
                additionalNotes: $this->rejection_notes ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->cancelRejectEnrollment();
        $email = $result['enrollment']->student?->email;
        $this->feedback = $result['mail_sent']
            ? sprintf('Solicitud %s rechazada (%s). Se envió notificación por correo a %s y el cupo quedó liberado.', $result['enrollment']->enrollment_code, $result['reason_label'], $email)
            : sprintf('Solicitud %s rechazada (%s) y cupo liberado (no se pudo entregar correo a %s).', $result['enrollment']->enrollment_code, $result['reason_label'], $email ?: 'sin correo');
    }

    public function submitConfirmation(EnrollmentService $enrollmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        if (! $this->confirmingEnrollmentId) {
            return;
        }

        $this->resetValidation();

        if ($this->confirm_exception && ! Auth::user()->hasEffectivePermission('admisiones.exceptions.authorize')) {
            $this->addError('confirm_exception', 'No tiene permiso para autorizar excepciones documentales.');

            return;
        }

        if ($this->confirm_authorize_reenrollment && ! Auth::user()->hasEffectivePermission('admisiones.exceptions.authorize')) {
            $this->addError('confirm_reenrollment_reason', 'No tiene permiso para autorizar re-inscripciones por curso anteriormente sin aprobar.');

            return;
        }

        $enrollment = Enrollment::findOrFail($this->confirmingEnrollmentId);

        try {
            $enrollmentService->confirmEnrollment(
                enrollment: $enrollment,
                submittedDocuments: $this->confirm_docs,
                authorizeException: $this->confirm_exception,
                exceptionReason: $this->confirm_exception_reason ?: null,
                exceptionCommitmentDate: $this->confirm_exception_date ?: null,
                authorizeReenrollment: $this->confirm_authorize_reenrollment,
                reenrollmentReason: $this->confirm_reenrollment_reason ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->confirmingEnrollmentId = null;
        $this->feedback = 'Matrícula confirmada y plan de cobro generado automáticamente.';
    }

    public function archiveAttachment(int $attachmentId, AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        $att = RecordAttachment::findOrFail($attachmentId);
        $attachmentService->archive($att, 'Archivado desde Admisiones sin borrado físico');
        $this->feedback = 'Adjunto archivado de forma segura (sin borrado físico). Puede verlo o restaurarlo desde "Ver archivado(s) / historial".';
    }

    public function toggleStudentArchivedAttachments(int $studentId): void
    {
        $this->showingArchivedStudentId = $this->showingArchivedStudentId === $studentId ? null : $studentId;
    }

    public function restoreAttachment(int $attachmentId, AttachmentService $attachmentService, EnrollmentService $enrollmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        $att = RecordAttachment::findOrFail($attachmentId);
        $attachmentService->restore($att, 'Restaurado desde Directorio de Alumnas');

        $syncedCount = 0;
        if ($att->attachable_type === Student::class) {
            $student = Student::find($att->attachable_id);
            if ($student) {
                $syncedCount = $enrollmentService->syncStudentAttachmentsToEnrollments(
                    student: $student,
                    addedDocumentKeys: [$att->document_type],
                    notes: 'Adjunto restaurado a estado activo'
                );
            }
        }

        $this->feedback = $syncedCount > 0
            ? sprintf('Adjunto %s (v%d) restaurado a ACTIVO y checklist actualizado en %d solicitud(es).', $att->document_type_label, $att->version, $syncedCount)
            : sprintf('Adjunto %s (v%d) restaurado nuevamente a estado ACTIVO.', $att->document_type_label, $att->version);
    }

    public function startAttachEnrollment(int $enrollmentId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        $enr = Enrollment::findOrFail($enrollmentId);
        $this->confirmingEnrollmentId = null;
        $this->rejectingEnrollmentId = null;
        $this->reopeningEnrollmentId = null;
        $this->attachingEnrollmentId = $enr->id;
        $missing = $enr->missingDocuments();
        $this->enr_attachment_doc_type = $missing[0] ?? 'atestado_academico';
        $this->enr_attachment_file = null;
        $this->enr_attachment_notes = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelAttachEnrollment(): void
    {
        $this->attachingEnrollmentId = null;
        $this->reset(['enr_attachment_file', 'enr_attachment_notes']);
        $this->resetValidation();
    }

    public function uploadEnrollmentAttachment(AttachmentService $attachmentService, EnrollmentService $enrollmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        if (! $this->attachingEnrollmentId) {
            return;
        }

        $this->validate([
            'enr_attachment_doc_type' => ['required', 'string', 'max:60'],
            'enr_attachment_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'enr_attachment_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'enr_attachment_file.required' => 'Seleccione el archivo (PDF, JPG, PNG o WebP) que desea adjuntar.',
        ]);

        $enrollment = Enrollment::with('student')->findOrFail($this->attachingEnrollmentId);
        $attachmentService->store(
            attachable: $enrollment->student,
            file: $this->enr_attachment_file,
            documentType: $this->enr_attachment_doc_type,
            notes: $this->enr_attachment_notes ?: ('Adjunto cargado en solicitud '.$enrollment->enrollment_code)
        );

        $updated = $enrollmentService->attachDocumentsToEnrollment(
            enrollment: $enrollment,
            addedDocumentKeys: [$this->enr_attachment_doc_type],
            notes: $this->enr_attachment_notes ?: null
        );

        $this->reset(['enr_attachment_file', 'enr_attachment_notes']);
        $missing = $updated->missingDocuments();
        if (! empty($missing)) {
            $this->enr_attachment_doc_type = $missing[0];
        } else {
            $this->attachingEnrollmentId = null;
        }

        if ($updated->documents_complete) {
            $this->feedback = $updated->status === 'rechazada'
                ? sprintf('Adjunto guardado en %s. Documentación COMPLETA. Ahora puede pulsar «Reabrir» para reactivar esta solicitud.', $updated->enrollment_code)
                : sprintf('Adjunto guardado en %s. Documentación COMPLETA (estado actualizado: %s).', $updated->enrollment_code, strtoupper(str_replace('_', ' ', $updated->status)));
        } else {
            $this->feedback = sprintf('Adjunto guardado en %s. Aún falta: %s.', $updated->enrollment_code, implode(', ', $updated->missingDocumentLabels()));
        }
    }

    public function startReopenEnrollment(int $enrollmentId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        $enr = Enrollment::findOrFail($enrollmentId);
        $this->confirmingEnrollmentId = null;
        $this->rejectingEnrollmentId = null;
        $this->attachingEnrollmentId = null;
        $this->reopeningEnrollmentId = $enr->id;
        $this->reopen_reason = '';
        $this->reopen_authorize_overcapacity = false;
        $missing = $enr->missingDocuments();
        $this->reopen_attachment_doc_type = $missing[0] ?? 'atestado_academico';
        $this->reopen_attachment_file = null;
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelReopenEnrollment(): void
    {
        $this->reopeningEnrollmentId = null;
        $this->reset(['reopen_reason', 'reopen_authorize_overcapacity', 'reopen_attachment_file']);
        $this->resetValidation();
    }

    public function submitReopenEnrollment(EnrollmentService $enrollmentService, AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        if (! $this->reopeningEnrollmentId) {
            return;
        }

        $this->validate([
            'reopen_reason' => ['required', 'string', 'min:5', 'max:500'],
            'reopen_attachment_doc_type' => ['nullable', 'string', 'max:60'],
            'reopen_attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ], [
            'reopen_reason.required' => 'Debe indicar el motivo de reapertura de la solicitud para auditoría.',
            'reopen_reason.min' => 'El motivo de reapertura debe tener al menos 5 caracteres.',
        ]);

        if ($this->reopen_authorize_overcapacity && ! Auth::user()->hasEffectivePermission('admisiones.exceptions.authorize')) {
            $this->addError('reopen_authorize_overcapacity', 'No tiene permiso para autorizar sobrecupo excepcional.');

            return;
        }

        $enrollment = Enrollment::with(['student', 'edition'])->findOrFail($this->reopeningEnrollmentId);

        // Si el funcionario adjuntó de una vez el documento faltante, guardarlo primero en el expediente
        if ($this->reopen_attachment_file) {
            $docType = $this->reopen_attachment_doc_type ?: 'atestado_academico';
            $attachmentService->store(
                attachable: $enrollment->student,
                file: $this->reopen_attachment_file,
                documentType: $docType,
                notes: 'Adjunto cargado al reabrir solicitud '.$enrollment->enrollment_code
            );
            $enrollment = $enrollmentService->attachDocumentsToEnrollment(
                enrollment: $enrollment,
                addedDocumentKeys: [$docType],
                notes: 'Carga documental previa a reapertura: '.$this->reopen_reason
            );
        }

        try {
            $reopened = $enrollmentService->reopenRejectedEnrollment(
                enrollment: $enrollment,
                reason: $this->reopen_reason,
                authorizeOvercapacity: $this->reopen_authorize_overcapacity
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->cancelReopenEnrollment();
        $this->feedback = match ($reopened->status) {
            'lista_espera' => sprintf('Solicitud %s reabierta en LISTA DE ESPERA (la cohorte alcanzó su cupo máximo).', $reopened->enrollment_code),
            'preinscrita' => sprintf('Solicitud %s reabierta con documentación completa (estado: PREINSCRITA, lista para confirmar).', $reopened->enrollment_code),
            default => sprintf('Solicitud %s reabierta en estado %s.', $reopened->enrollment_code, strtoupper(str_replace('_', ' ', $reopened->status))),
        };
    }

    public function startAttachStudent(int $studentId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        $this->editingStudentId = null;
        $this->attachingStudentId = $studentId;
        $this->student_attachment_doc_type = 'atestado_academico';
        $this->student_attachment_file = null;
        $this->student_attachment_notes = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelAttachStudent(): void
    {
        $this->attachingStudentId = null;
        $this->reset(['student_attachment_file', 'student_attachment_notes']);
        $this->resetValidation();
    }

    public function uploadStudentAttachment(AttachmentService $attachmentService, EnrollmentService $enrollmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);
        if (! $this->attachingStudentId) {
            return;
        }

        $this->validate([
            'student_attachment_doc_type' => ['required', 'string', 'max:60'],
            'student_attachment_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
            'student_attachment_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'student_attachment_file.required' => 'Seleccione el archivo (PDF, JPG, PNG o WebP) que desea subir al expediente.',
        ]);

        $student = Student::findOrFail($this->attachingStudentId);
        $att = $attachmentService->store(
            attachable: $student,
            file: $this->student_attachment_file,
            documentType: $this->student_attachment_doc_type,
            notes: $this->student_attachment_notes ?: 'Adjunto agregado al expediente desde Directorio de Alumnas'
        );

        // Sincronizar automáticamente el checklist en TODAS las solicitudes incompletas (pendientes, lista de espera, confirmadas con excepción o rechazadas)
        $syncedCount = $enrollmentService->syncStudentAttachmentsToEnrollments(
            student: $student,
            addedDocumentKeys: [$this->student_attachment_doc_type],
            notes: $this->student_attachment_notes ?: 'Sincronizado desde expediente de alumna'
        );

        $this->cancelAttachStudent();
        $this->feedback = $syncedCount > 0
            ? sprintf('Adjunto %s (v%d) guardado en el expediente de %s y checklist actualizado automáticamente en %d solicitud(es).', $att->document_type_label, $att->version, $student->full_name, $syncedCount)
            : sprintf('Adjunto %s (v%d) guardado en el expediente de %s.', $att->document_type_label, $att->version, $student->full_name);
    }

    public function render()
    {
        $applicantAge = Student::ageFromBirthDate($this->birth_date);
        $matchedStudent = trim($this->identification_number) !== ''
            ? Student::findByIdentification($this->identification_number)?->load('attachments')
            : null;
        $existingStudentAttachments = $matchedStudent
            ? $matchedStudent->attachments->where('status', 'activo')->values()
            : collect();

        $admissionsQueueStatuses = [
            'preinscrita',
            'documentacion_pendiente',
            'lista_espera',
            'confirmada',
            'rechazada',
        ];

        $enrollmentsQuery = Enrollment::with(['student.attachments', 'edition.course'])
            ->whereIn('status', $admissionsQueueStatuses)
            ->latest();

        if ($this->queue_status_filter === 'pendiente_autorizacion') {
            $enrollmentsQuery->where('reenrollment_authorized', false)
                ->whereNotNull('reenrollment_reason')
                ->where('reenrollment_reason', '!=', '');
        } elseif ($this->queue_status_filter !== '' && in_array($this->queue_status_filter, $admissionsQueueStatuses, true)) {
            $enrollmentsQuery->where('status', $this->queue_status_filter);
        }

        if ($this->queue_edition_filter !== '') {
            $enrollmentsQuery->where('course_edition_id', (int) $this->queue_edition_filter);
        }

        $qSearch = trim($this->queue_search);
        if ($qSearch !== '') {
            $like = '%'.strtolower($qSearch).'%';
            $normalizedId = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $qSearch));
            $enrollmentsQuery->where(function ($q) use ($like, $normalizedId): void {
                $q->whereRaw('LOWER(enrollment_code) LIKE ?', [$like])
                    ->orWhereHas('student', function ($sq) use ($like, $normalizedId): void {
                        $sq->whereRaw('LOWER(first_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(second_last_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(identification_number) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                        if ($normalizedId !== '') {
                            $sq->orWhereRaw("UPPER(REGEXP_REPLACE(identification_number, '[^0-9A-Za-z]', '', 'g')) LIKE ?", ['%'.$normalizedId.'%']);
                        }
                    });
            });
        }

        $queueStatusOptions = [
            'preinscrita' => 'Preinscrita',
            'documentacion_pendiente' => 'Documentación Pendiente',
            'lista_espera' => 'Lista de Espera',
            'pendiente_autorizacion' => 'Pendiente de Autorización (Curso sin aprobar)',
            'confirmada' => 'Confirmada',
            'rechazada' => 'Rechazada',
        ];

        return view('livewire.admissions-page', [
            'enrollments' => $enrollmentsQuery->limit(100)->get(),
            'queueEditions' => CourseEdition::with('course')->orderByDesc('id')->get(),
            'queueStatusOptions' => $queueStatusOptions,
            'students' => Student::confirmedDirectory()
                ->with([
                    'attachments',
                    'enrollments' => fn ($q) => $q->whereIn('status', Student::CONFIRMED_ENROLLMENT_STATUSES)->with('edition.course'),
                ])
                ->withCount(['enrollments' => fn ($q) => $q->whereIn('status', Student::CONFIRMED_ENROLLMENT_STATUSES)])
                ->latest()
                ->limit(60)
                ->get(),
            'editions' => CourseEdition::with('course')->whereIn('status', ['abierta', 'planificada', 'en_curso'])->orderByDesc('id')->get(),
            'selectedEdition' => $this->course_edition_id ? CourseEdition::with('course')->find($this->course_edition_id) : null,
            'applicantAge' => $applicantAge,
            'isMinorApplicant' => $applicantAge !== null && $applicantAge < 18,
            'existingStudentAttachments' => $existingStudentAttachments,
            'confirmingEnrollment' => $this->confirmingEnrollmentId ? Enrollment::with(['student', 'edition.course'])->find($this->confirmingEnrollmentId) : null,
            'rejectingEnrollment' => $this->rejectingEnrollmentId ? Enrollment::with(['student', 'edition.course'])->find($this->rejectingEnrollmentId) : null,
            'attachingEnrollment' => $this->attachingEnrollmentId ? Enrollment::with(['student.attachments', 'edition.course'])->find($this->attachingEnrollmentId) : null,
            'reopeningEnrollment' => $this->reopeningEnrollmentId ? Enrollment::with(['student.attachments', 'edition.course'])->find($this->reopeningEnrollmentId) : null,
            'attachingStudent' => $this->attachingStudentId ? Student::with('attachments')->find($this->attachingStudentId) : null,
            'rejectionReasons' => Picklist::optionsFor('enrollment_rejection_reason'),
            'fullCapacityDecisions' => Picklist::optionsFor('full_capacity_decision'),
            'documentTypes' => config('cecap.document_types'),
            'idTypes' => Picklist::optionsFor('id_type'),
            'genders' => Picklist::optionsFor('gender'),
            'educationLevels' => Picklist::optionsFor('education_level'),
            'provinces' => CrProvince::where('is_active', true)->orderBy('code')->get(),
            'cantons' => $this->province_id ? CrCanton::where('province_id', $this->province_id)->orderBy('name')->get() : collect(),
            'districts' => $this->canton_id ? CrDistrict::where('canton_id', $this->canton_id)->orderBy('name')->get() : collect(),
        ]);
    }
}
