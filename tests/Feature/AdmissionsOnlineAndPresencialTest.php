<?php

use App\Livewire\PublicEnrollmentPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\InstitutionSetting;
use App\Models\Student;
use App\Models\User;
use App\Services\AttachmentService;
use App\Services\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
});

function createSampleEdition(string $programType = 'curso_tecnico'): CourseEdition
{
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $cat = AcademicCategory::firstOrCreate(['code' => 'CAT-ADM'], ['name' => 'Admisión', 'created_by' => $admin->id]);
    $course = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'CRS-'.uniqid(),
        'name' => $programType === 'curso_tecnico' ? 'Técnico en Contabilidad' : 'Curso Corto Repostería',
        'program_type' => $programType,
        'required_education_level' => 'noveno_aprobado',
        'default_max_capacity' => 20,
        'base_enrollment_fee' => 15000,
        'base_monthly_fee' => 30000,
        'required_documents' => Course::defaultRequiredDocumentsFor($programType),
        'created_by' => $admin->id,
    ]);

    return CourseEdition::create([
        'course_id' => $course->id,
        'code' => 'ED-'.uniqid(),
        'name' => 'Cohorte 2026',
        'shift_id' => AcademicShift::firstOrFail()->id,
        'modality_id' => AcademicModality::firstOrFail()->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
        'max_capacity' => 20,
        'enrollment_fee' => 15000,
        'monthly_fee' => 30000,
        'installments_count' => 2,
        'status' => 'abierta',
        'is_public_enrollment_open' => true,
        'created_by' => $admin->id,
    ]);
}

it('blocks non-female applicants on /inscripcion with configurable gender message and requires email', function () {
    $edition = createSampleEdition('curso_corto');
    $settings = InstitutionSetting::current();

    Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('gender', 'masculino')
        ->assertSet('genderBlocked', true)
        ->assertSee($settings->gender_policy_message)
        ->call('submit')
        ->assertHasErrors('gender');

    // Correo obligatorio
    Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('gender', 'femenino')
        ->set('identification_number', '1-4000-5000')
        ->set('first_name', 'Elena')
        ->set('last_name', 'Chaves')
        ->set('email', '')
        ->set('phone', '8800-4000')
        ->call('submit')
        ->assertHasErrors('email');
});

it('registers incomplete online pre-enrollment as documentacion_pendiente and serves 7-day signed Planilla PDF', function () {
    $edition = createSampleEdition('curso_tecnico');

    $component = Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('gender', 'femenino')
        ->set('identification_number', '1-4111-5222')
        ->set('first_name', 'Mariana')
        ->set('last_name', 'Soto')
        ->set('email', 'mariana.soto@correo.cr')
        ->set('phone', '8811-4455')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Documentos faltantes por presentar')
        ->assertSee('Cédula / DIMEX')
        ->assertSee('Atestados Académicos (Títulos / Notas)');

    $enrollment = Enrollment::firstOrFail();
    expect($enrollment->status)->toBe('documentacion_pendiente');
    expect($enrollment->provisional_expires_at)->not->toBeNull();
    expect($enrollment->missingDocumentLabels())->toBe([
        'Cédula / DIMEX',
        'Atestados Académicos (Títulos / Notas)',
    ]);

    $signedUrl = $component->get('signedPlanillaUrl');
    expect($signedUrl)->toStartWith('/inscripcion/planilla/'.$enrollment->id);

    // Debe funcionar incluso detrás de proxy reverso HTTPS con Host distinto
    $proxyHeaders = ['Host' => 'preview.cecap-proxy.test', 'X-Forwarded-Proto' => 'https'];
    $this->withHeaders($proxyHeaders)->get($signedUrl.'&vista=html')
        ->assertOk()
        ->assertSee($enrollment->enrollment_code)
        ->assertSee('Atestados Académicos (Títulos / Notas)');
    $this->withHeaders($proxyHeaders)->get($signedUrl)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
    $this->withHeaders($proxyHeaders)->get($signedUrl.'&descargar=1')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('blocks online pre-enrollment when applicant education level is below course required_education_level', function () {
    $edition = createSampleEdition('curso_tecnico'); // requires noveno_aprobado (40)

    // Nivel inferior (primaria_completa = 30) debe bloquear en vivo y al enviar
    Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('gender', 'femenino')
        ->set('identification_number', '1-4111-9999')
        ->set('first_name', 'Sofía')
        ->set('last_name', 'Vargas')
        ->set('email', 'sofia.vargas@correo.cr')
        ->set('phone', '8811-9999')
        ->set('education_level', 'primaria_completa')
        ->assertSet('educationBlocked', true)
        ->assertSee('Nivel académico insuficiente para este curso')
        ->assertSee('Tercer Ciclo aprobado (IX año)')
        ->call('submit')
        ->assertHasErrors('education_level');

    expect(Enrollment::count())->toBe(0);

    // Nivel igual al mínimo requerido (noveno_aprobado = 40) debe permitirse
    Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('gender', 'femenino')
        ->set('identification_number', '1-4111-9999')
        ->set('first_name', 'Sofía')
        ->set('last_name', 'Vargas')
        ->set('email', 'sofia.vargas@correo.cr')
        ->set('phone', '8811-9999')
        ->set('education_level', 'noveno_aprobado')
        ->assertSet('educationBlocked', false)
        ->call('submit')
        ->assertHasNoErrors();

    expect(Enrollment::count())->toBe(1);
});

it('blocks presencial confirmation when required docs are missing unless audited exception is authorized', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    $edition = createSampleEdition('curso_tecnico'); // requires cedula_dimex + atestado_academico
    $student = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Paola',
        'last_name' => 'Díaz',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-4222-5333',
        'email' => 'paola.diaz@correo.cr',
        'phone' => '8822-5566',
        'gender' => 'femenino',
        'education_level' => 'bachillerato_secundaria',
    ]);

    $service = app(EnrollmentService::class);

    // Bloqueada sin atestados ni excepción
    expect(fn () => $service->createPresencialEnrollment(
        student: $student,
        edition: $edition,
        submittedDocuments: ['cedula_dimex'],
        confirmImmediately: true,
        authorizeException: false
    ))->toThrow(ValidationException::class);

    // Permitida con excepción auditada (motivo + fecha compromiso)
    $enrollment = $service->createPresencialEnrollment(
        student: $student,
        edition: $edition,
        submittedDocuments: ['cedula_dimex'],
        confirmImmediately: true,
        authorizeException: true,
        exceptionReason: 'Atestado de noveno en trámite ante el MEP',
        exceptionCommitmentDate: now()->addDays(14)->toDateString()
    );

    expect($enrollment->status)->toBe('confirmada');
    expect($enrollment->exception_authorized)->toBeTrue();
    expect($enrollment->feeCharges()->count())->toBeGreaterThan(0);
    expect(AuditLog::where('event', 'enrollment.confirmed_with_exception')->exists())->toBeTrue();
});

it('stores private attachments with SHA-256 versioning and protected download', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    $student = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Daniela',
        'last_name' => 'Mora',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-4333-5444',
        'email' => 'daniela.mora@correo.cr',
        'phone' => '8833-6677',
        'gender' => 'femenino',
    ]);

    $attService = app(AttachmentService::class);
    $v1 = $attService->store($student, UploadedFile::fake()->create('cedula_v1.pdf', 120, 'application/pdf'), 'cedula_dimex');
    $v2 = $attService->store($student, UploadedFile::fake()->create('cedula_v2.pdf', 140, 'application/pdf'), 'cedula_dimex');

    expect($v1->fresh()->is_current)->toBeFalse();
    expect($v1->fresh()->status)->toBe('reemplazado');
    expect($v2->version)->toBe(2);
    expect(strlen($v2->sha256))->toBe(64);

    $this->get('/adjuntos/'.$v2->uuid)->assertOk();
});
