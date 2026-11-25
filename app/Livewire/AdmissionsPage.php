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

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();
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
        $this->editingStudentId = $student->id;
        $this->tab = 'enroll';
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
            'course_edition_id' => ['required', 'integer', 'exists:course_editions,id'],
            'attachment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
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
                exceptionCommitmentDate: $this->exception_commitment_date ?: null
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reset(['attachment_file', 'authorize_exception', 'exception_reason', 'exception_commitment_date']);
        $this->feedback = sprintf('Matrícula %s registrada correctamente (%s).', $enrollment->enrollment_code, $enrollment->status);
        $this->tab = 'queue';
    }

    public function startConfirmEnrollment(int $enrollmentId): void
    {
        $enr = Enrollment::findOrFail($enrollmentId);
        $this->confirmingEnrollmentId = $enr->id;
        $this->confirm_docs = $enr->submitted_documents ?? [];
        $this->confirm_exception = false;
        $this->confirm_exception_reason = '';
        $this->confirm_exception_date = now()->addDays(15)->toDateString();
    }

    public function cancelConfirmEnrollment(): void
    {
        $this->confirmingEnrollmentId = null;
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
        $this->feedback = 'Adjunto archivado de forma segura (sin borrado físico).';
    }

    public function render()
    {
        return view('livewire.admissions-page', [
            'enrollments' => Enrollment::with(['student.attachments', 'edition.course'])->latest()->limit(60)->get(),
            'students' => Student::withCount('enrollments')->latest()->limit(60)->get(),
            'editions' => CourseEdition::with('course')->whereIn('status', ['abierta', 'planificada', 'en_curso'])->orderByDesc('id')->get(),
            'confirmingEnrollment' => $this->confirmingEnrollmentId ? Enrollment::with(['student', 'edition.course'])->find($this->confirmingEnrollmentId) : null,
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
