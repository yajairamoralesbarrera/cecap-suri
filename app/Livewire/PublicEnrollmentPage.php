<?php

namespace App\Livewire;

use App\Models\CourseEdition;
use App\Models\CrCanton;
use App\Models\CrDistrict;
use App\Models\CrProvince;
use App\Models\InstitutionSetting;
use App\Models\Picklist;
use App\Models\Student;
use App\Services\AttachmentService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
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

    public ?string $successCode = null;

    public ?string $successStatus = null;

    public ?string $signedPlanillaUrl = null;

    public ?string $provisionalExpiresAt = null;

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
            'file_cedula' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'file_atestado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio para enviar su comprobante y planilla.',
            'gender.in' => $settings->gender_policy_message,
        ]);

        $edition = CourseEdition::with('course')->findOrFail($this->course_edition_id);

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

        $this->successCode = $enrollment->enrollment_code;
        $this->successStatus = $enrollment->status;
        $this->provisionalExpiresAt = $enrollment->provisional_expires_at?->format('d/m/Y H:i');
        $this->signedPlanillaUrl = URL::temporarySignedRoute(
            'inscripcion.planilla.publica',
            now()->addDays(max(1, (int) $settings->online_enrollment_provisional_days)),
            ['enrollment' => $enrollment->id]
        );
    }

    public function resetForm(): void
    {
        $this->reset([
            'course_edition_id', 'identification_number', 'first_name', 'last_name',
            'second_last_name', 'email', 'phone', 'birth_date', 'province_id',
            'canton_id', 'district_id', 'address_detail', 'emergency_contact_name',
            'emergency_contact_phone', 'notes', 'file_cedula', 'file_atestado',
            'successCode', 'successStatus', 'signedPlanillaUrl', 'provisionalExpiresAt',
        ]);
        $this->gender = 'femenino';
        $this->genderBlocked = false;
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
