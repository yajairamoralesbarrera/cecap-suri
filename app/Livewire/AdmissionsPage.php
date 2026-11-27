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
use App\Services\AttachmentService;
use App\Services\AuditService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\Auth;
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

    public string $education_level = 'bachillerato_secundaria';

    public ?int $province_id = null;

    public ?int $canton_id = null;

    public ?int $district_id = null;

    public string $address_detail = '';

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

    // Decisión del funcionario en matrícula presencial (cupo disponible o cupo máximo alcanzado)
    public string $presencial_action = 'inscribir'; // inscribir | lista_espera | rechazar

    public string $presencial_rejection_reason_code = '';

    public string $presencial_rejection_notes = '';

    public string $full_capacity_decision = '';

    public string $full_capacity_notes = '';

    public bool $selectedEditionIsFull = false;

    public string $selectedEditionCapacityInfo = '';

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
        $id = trim($this->identification_number);
        if ($id === '') {
            return;
        }
        $student = Student::where('identification_number', $id)->first();
        if (! $student) {
            return;
        }
        $this->loadStudentIntoForm($student);
        $this->feedback = 'Datos de la alumna autollenados desde el expediente existente.';
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
        $this->education_level = (string) ($student->education_level ?? 'bachillerato_secundaria');
        $this->province_id = $student->province_id;
        $this->canton_id = $student->canton_id;
        $this->district_id = $student->district_id;
        $this->address_detail = (string) ($student->address_detail ?? '');
    }

    public function cancelEditStudent(): void
    {
        $this->reset([
            'editingStudentId', 'edit_reason', 'identification_number', 'first_name',
            'last_name', 'second_last_name', 'email', 'phone', 'province_id',
            'canton_id', 'district_id', 'address_detail',
        ]);
        $this->gender = 'femenino';
    }

    public function saveStudentOnly(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);

        $rules = [
            'identification_type' => ['required', 'string', 'max:40'],
            'identification_number' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'], // OBLIGATORIO en creación y edición
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'string', 'max:30'],
        ];

        if ($this->editingStudentId) {
            $rules['edit_reason'] = ['required', 'string', 'min:5'];
        }

        $this->validate($rules, [
            'email.required' => 'El correo electrónico es obligatorio en creación y edición de expediente.',
            'edit_reason.required' => 'La edición controlada exige indicar el motivo de la modificación para auditoría.',
        ]);

        if ($this->editingStudentId) {
            $student = Student::findOrFail($this->editingStudentId);
            $old = $student->only(['first_name', 'last_name', 'email', 'phone', 'gender', 'education_level']);
            $student->update([
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name ?: null,
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'gender' => $this->gender,
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail ?: null,
                'updated_by' => Auth::id(),
            ]);

            AuditService::log(
                event: 'student.updated_controlled',
                module: 'admisiones',
                auditable: $student,
                oldValues: $old,
                newValues: $student->only(['first_name', 'last_name', 'email', 'phone', 'gender', 'education_level']),
                reason: $this->edit_reason
            );
            $this->cancelEditStudent();
            $this->feedback = 'Expediente actualizado bajo edición controlada y auditada.';
        }
    }

    public function createPresencialEnrollment(EnrollmentService $enrollmentService, AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('admisiones.manage'), 403);

        $this->validate([
            'identification_type' => ['required', 'string', 'max:40'],
            'identification_number' => ['required', 'string', 'max:40'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'gender' => ['required', 'string', 'max:30'],
            'education_level' => ['required', 'string', 'max:80'],
            'course_edition_id' => ['required', 'integer', 'exists:course_editions,id'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio en Admisiones.',
        ]);

        $student = Student::where('identification_number', trim($this->identification_number))->first();
        if (! $student) {
            $student = Student::create([
                'student_code' => Student::nextCode(),
                'first_name' => trim($this->first_name),
                'last_name' => trim($this->last_name),
                'second_last_name' => $this->second_last_name ?: null,
                'identification_type' => $this->identification_type,
                'identification_number' => trim($this->identification_number),
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'gender' => $this->gender ?: 'femenino',
                'education_level' => $this->education_level,
                'province_id' => $this->province_id,
                'canton_id' => $this->canton_id,
                'district_id' => $this->district_id,
                'address_detail' => $this->address_detail ?: null,
                'status' => 'activo',
                'created_by' => Auth::id(),
            ]);
        } else {
            $student->update([
                'email' => strtolower(trim($this->email)),
                'phone' => trim($this->phone),
                'education_level' => $this->education_level,
                'updated_by' => Auth::id(),
            ]);
        }

        $docs = $this->submitted_documents;
        if ($this->attachment_file) {
            $attachmentService->store($student, $this->attachment_file, $this->attachment_doc_type, 'Adjunto en matrícula presencial');
            if (! in_array($this->attachment_doc_type, $docs, true)) {
                $docs[] = $this->attachment_doc_type;
            }
        }

        $edition = CourseEdition::with('course')->findOrFail($this->course_edition_id);

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
                rejectionNotes: $this->presencial_rejection_notes ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reset([
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
        $this->presencial_action = 'inscribir';
        $this->feedback = match ($enrollment->status) {
            'lista_espera' => sprintf('Solicitud %s registrada en LISTA DE ESPERA.', $enrollment->enrollment_code),
            'rechazada' => sprintf('Solicitud %s registrada como RECHAZADA (%s) y notificada por correo.', $enrollment->enrollment_code, $enrollment->rejection_reason_label ?: 'motivo registrado'),
            default => sprintf('Matrícula %s registrada correctamente (%s).', $enrollment->enrollment_code, strtoupper(str_replace('_', ' ', $enrollment->status))),
        };
        $this->tab = 'queue';
    }

    public function updatedAttachmentFile(): void
    {
        if ($this->attachment_file && $this->attachment_doc_type && ! in_array($this->attachment_doc_type, $this->submitted_documents, true)) {
            $this->submitted_documents[] = $this->attachment_doc_type;
        }
    }

    public function startConfirmEnrollment(int $enrollmentId): void
    {
        $enr = Enrollment::with('student.attachments')->findOrFail($enrollmentId);
        $this->rejectingEnrollmentId = null;
        $this->attachingEnrollmentId = null;
        $this->reopeningEnrollmentId = null;
        $this->confirmingEnrollmentId = $enr->id;
        $activeTypes = $enr->student?->attachments?->where('status', 'activo')->pluck('document_type')->all() ?? [];
        $this->confirm_docs = array_values(array_unique(array_merge($enr->submitted_documents ?? [], $activeTypes)));
        $this->confirm_exception = false;
        $this->confirm_exception_reason = '';
        $this->confirm_exception_date = now()->addDays(15)->toDateString();
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

        if ($this->confirm_exception && ! Auth::user()->hasEffectivePermission('admisiones.exceptions.authorize')) {
            $this->addError('confirm_exception', 'No tiene permiso para autorizar excepciones documentales.');

            return;
        }

        $enrollment = Enrollment::findOrFail($this->confirmingEnrollmentId);

        try {
            $enrollmentService->confirmEnrollment(
                enrollment: $enrollment,
                submittedDocuments: $this->confirm_docs,
                authorizeException: $this->confirm_exception,
                exceptionReason: $this->confirm_exception_reason ?: null,
                exceptionCommitmentDate: $this->confirm_exception_date ?: null
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
        return view('livewire.admissions-page', [
            'enrollments' => Enrollment::with(['student.attachments', 'edition.course'])->latest()->limit(60)->get(),
            'students' => Student::with('attachments')->withCount('enrollments')->latest()->limit(60)->get(),
            'editions' => CourseEdition::with('course')->whereIn('status', ['abierta', 'planificada', 'en_curso'])->orderByDesc('id')->get(),
            'selectedEdition' => $this->course_edition_id ? CourseEdition::with('course')->find($this->course_edition_id) : null,
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
