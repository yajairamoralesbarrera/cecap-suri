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

    // Permitir regresar / cargar los documentos faltantes sobre la MISMA solicitud sin duplicarla
    $component
        ->call('openPendingUploadForm')
        ->assertSet('showPendingUploadForm', true)
        ->set('pending_file_cedula', UploadedFile::fake()->create('cedula.pdf', 120, 'application/pdf'))
        ->set('pending_file_atestado', UploadedFile::fake()->create('atestado.pdf', 150, 'application/pdf'))
        ->call('uploadMissingDocuments')
        ->assertHasNoErrors()
        ->assertSet('showPendingUploadForm', false)
        ->assertSet('successStatus', 'preinscrita')
        ->assertSet('missingDocumentLabels', []);

    expect(Enrollment::count())->toBe(1);
    $enrollment->refresh();
    expect($enrollment->status)->toBe('preinscrita');
    expect($enrollment->documents_complete)->toBeTrue();
    expect($enrollment->missingDocuments())->toBe([]);
    expect(AuditLog::where('event', 'enrollment.online_documents_uploaded')->where('auditable_id', (string) $enrollment->id)->exists())->toBeTrue();

    // La Planilla PDF firmada debe funcionar incluso detrás de proxy reverso HTTPS con Host distinto
    $proxyHeaders = ['Host' => 'preview.cecap-proxy.test', 'X-Forwarded-Proto' => 'https'];
    $this->withHeaders($proxyHeaders)->get($signedUrl.'&vista=html')
        ->assertOk()
        ->assertSee($enrollment->enrollment_code)
        ->assertSee('[X] Atestados Académicos (Títulos / Notas)');
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

    // Vista previa HTML (evita volcado binario en el visor) y descarga binaria segura con ?descargar=1
    $this->get('/adjuntos/'.$v2->uuid.'?vista=html')
        ->assertOk()
        ->assertSee('data:application/pdf;base64,')
        ->assertSee('cedula_v2.pdf');

    $this->withHeaders(['Accept' => 'text/html'])
        ->get('/adjuntos/'.$v2->uuid)
        ->assertOk()
        ->assertSee('cedula_v2.pdf');

    $this->get('/adjuntos/'.$v2->uuid.'?descargar=1')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Archivar y restaurar adjunto sin borrado físico desde Directorio de Alumnas
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('tab', 'students')
        ->call('archiveAttachment', $v2->id)
        ->call('toggleStudentArchivedAttachments', $student->id)
        ->assertSee('Restaurar')
        ->call('restoreAttachment', $v2->id);

    expect($v2->fresh()->status)->toBe('activo');
    expect(AuditLog::where('event', 'attachment.restored')->where('auditable_id', (string) $v2->id)->exists())->toBeTrue();
});

it('routes full editions to online waitlist on /inscripcion, enforces presencial full-capacity picklist decisions, and supports audited rejection with seat release', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

    $edition = createSampleEdition('curso_corto');
    $edition->update(['max_capacity' => 1]);

    // 1ra solicitante en línea ocupa el único cupo disponible (1/1)
    Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->assertSet('isWaitlistMode', false)
        ->set('gender', 'femenino')
        ->set('identification_number', '1-7000-0001')
        ->set('first_name', 'Andrea')
        ->set('last_name', 'Castro')
        ->set('email', 'andrea.castro@correo.cr')
        ->set('phone', '8800-7001')
        ->call('submit')
        ->assertHasNoErrors();

    $firstEnrollment = Enrollment::firstOrFail();
    expect($edition->fresh()->availableSeats())->toBe(0);

    // 2da solicitante en línea ve aviso de Lista de Espera en Línea y se registra en lista_espera sin consumir cupo activo
    $waitlistComp = Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->assertSet('isWaitlistMode', true)
        ->assertSee('Lista de Espera en Línea')
        ->set('gender', 'femenino')
        ->set('identification_number', '1-7000-0002')
        ->set('first_name', 'Lucía')
        ->set('last_name', 'Méndez')
        ->set('email', 'lucia.mendez@correo.cr')
        ->set('phone', '8800-7002')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('successStatus', 'lista_espera')
        ->assertSee('LISTA DE ESPERA EN LÍNEA');

    $waitlistedOnline = Enrollment::where('id', '!=', $firstEnrollment->id)->firstOrFail();
    expect($waitlistedOnline->status)->toBe('lista_espera');
    expect($waitlistedOnline->capacity_decision_code)->toBe('lista_espera');
    expect($edition->fresh()->activeEnrollmentsCount())->toBe(1);
    expect($edition->fresh()->waitlistCount())->toBe(1);
    expect(AuditLog::where('event', 'enrollment.waitlisted_online')->where('auditable_id', (string) $waitlistedOnline->id)->exists())->toBeTrue();

    // En /admisiones (presencial), al elegir edición llena sin decisión del picklist debe bloquearse
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('course_edition_id', $edition->id)
        ->assertSet('selectedEditionIsFull', true)
        ->set('identification_number', '1-7000-0003')
        ->set('first_name', 'Valeria')
        ->set('last_name', 'Solís')
        ->set('email', 'valeria.solis@correo.cr')
        ->set('phone', '8800-7003')
        ->set('gender', 'femenino')
        ->set('education_level', 'bachillerato_secundaria')
        ->set('submitted_documents', ['cedula_dimex'])
        ->call('createPresencialEnrollment')
        ->assertHasErrors('full_capacity_decision');

    // Presencial con decisión del picklist = lista_espera
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('full_capacity_decision', 'lista_espera')
        ->set('identification_number', '1-7000-0003')
        ->set('first_name', 'Valeria')
        ->set('last_name', 'Solís')
        ->set('email', 'valeria.solis@correo.cr')
        ->set('phone', '8800-7003')
        ->set('gender', 'femenino')
        ->set('education_level', 'bachillerato_secundaria')
        ->set('submitted_documents', ['cedula_dimex'])
        ->call('createPresencialEnrollment')
        ->assertHasNoErrors();

    $waitlistedPresencial = Enrollment::whereHas('student', fn ($q) => $q->where('identification_number', '1-7000-0003'))->firstOrFail();
    expect($waitlistedPresencial->status)->toBe('lista_espera');
    expect($waitlistedPresencial->feeCharges()->count())->toBe(0);
    expect($edition->fresh()->waitlistCount())->toBe(2);

    // Presencial con decisión del picklist = rechazar_cupo_lleno guarda rechazada y envía correo
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('full_capacity_decision', 'rechazar_cupo_lleno')
        ->set('full_capacity_notes', 'Se invita a próxima convocatoria de agosto.')
        ->set('identification_number', '1-7000-0004')
        ->set('first_name', 'Camila')
        ->set('last_name', 'Rojas')
        ->set('email', 'camila.rojas@correo.cr')
        ->set('phone', '8800-7004')
        ->set('gender', 'femenino')
        ->set('education_level', 'bachillerato_secundaria')
        ->set('submitted_documents', ['cedula_dimex'])
        ->call('createPresencialEnrollment')
        ->assertHasNoErrors();

    $rejectedFull = Enrollment::whereHas('student', fn ($q) => $q->where('identification_number', '1-7000-0004'))->firstOrFail();
    expect($rejectedFull->status)->toBe('rechazada');
    expect($rejectedFull->rejection_reason_code)->toBe('cupo_agotado');
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EnrollmentRejectedMail::class, fn ($mail) => $mail->hasTo('camila.rojas@correo.cr'));

    // Intentar confirmar una solicitud en lista_espera mientras el cupo sigue lleno sin excepción debe bloquearse
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->call('startConfirmEnrollment', $waitlistedOnline->id)
        ->set('confirm_docs', ['cedula_dimex'])
        ->call('submitConfirmation')
        ->assertHasErrors('documents');

    // Rechazar la 1ra solicitud desde /admisiones exige motivo del picklist, envía correo y libera el cupo
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->call('startRejectEnrollment', $firstEnrollment->id)
        ->call('submitRejection')
        ->assertHasErrors('rejection_reason_code')
        ->set('rejection_reason_code', 'documentacion_incompleta')
        ->set('rejection_notes', 'Favor presentar cédula vigente en la próxima convocatoria.')
        ->call('submitRejection')
        ->assertHasNoErrors();

    $firstEnrollment->refresh();
    expect($firstEnrollment->status)->toBe('rechazada');
    expect($firstEnrollment->rejection_reason_code)->toBe('documentacion_incompleta');
    expect($firstEnrollment->rejected_at)->not->toBeNull();
    expect(AuditLog::where('event', 'enrollment.rejected')->where('auditable_id', (string) $firstEnrollment->id)->exists())->toBeTrue();

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EnrollmentRejectedMail::class, function ($mail) {
        return $mail->hasTo('andrea.castro@correo.cr')
            && str_contains($mail->reasonLabel, 'Documentación incompleta');
    });

    // Al liberarse el cupo (1/1 disponible), ahora sí se puede confirmar a Lucía desde la lista de espera
    expect($edition->fresh()->availableSeats())->toBe(1);
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->call('startConfirmEnrollment', $waitlistedOnline->id)
        ->set('confirm_docs', ['cedula_dimex'])
        ->call('submitConfirmation')
        ->assertHasNoErrors();

    expect($waitlistedOnline->fresh()->status)->toBe('confirmada');
    expect($edition->fresh()->availableSeats())->toBe(0);

    // Presencial con decisión del picklist = autorizar_sobrecupo exige justificación y luego permite sobrecupo auditado
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('full_capacity_decision', 'autorizar_sobrecupo')
        ->set('full_capacity_notes', '')
        ->set('identification_number', '1-7000-0005')
        ->set('first_name', 'Natalia')
        ->set('last_name', 'Quesada')
        ->set('email', 'natalia.quesada@correo.cr')
        ->set('phone', '8800-7005')
        ->set('gender', 'femenino')
        ->set('education_level', 'bachillerato_secundaria')
        ->set('submitted_documents', ['cedula_dimex'])
        ->call('createPresencialEnrollment')
        ->assertHasErrors('full_capacity_notes')
        ->set('full_capacity_notes', 'Autorizado por Dirección Académica por beca institucional.')
        ->call('createPresencialEnrollment')
        ->assertHasNoErrors();

    $overcap = Enrollment::whereHas('student', fn ($q) => $q->where('identification_number', '1-7000-0005'))->firstOrFail();
    expect($overcap->status)->toBe('confirmada');
    expect($overcap->capacity_decision_code)->toBe('autorizar_sobrecupo');
    expect(AuditLog::where('event', 'enrollment.presencial_overcapacity_authorized')->where('auditable_id', (string) $overcap->id)->exists())->toBeTrue();

    // Planilla PDF en modo HTML de una inscripción en lista_espera muestra el aviso correspondiente
    $signedWaitlistUrl = URL::temporarySignedRoute('inscripcion.planilla.publica', now()->addDays(7), ['enrollment' => $waitlistedPresencial->id], false);
    $this->get($signedWaitlistUrl.'&vista=html')
        ->assertOk()
        ->assertSee('LISTA DE ESPERA');
});

it('allows presencial officer to check availability first and directly enroll, waitlist, or reject with picklist and email even when seats are available', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $edition = createSampleEdition('curso_corto'); // 20 cupos disponibles

    // Acción presencial directa: crear en Lista de Espera
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('tab', 'enroll')
        ->set('course_edition_id', $edition->id)
        ->assertSet('selectedEditionIsFull', false)
        ->assertSee('CUPO DISPONIBLE')
        ->set('presencial_action', 'lista_espera')
        ->set('identification_number', '1-8000-0001')
        ->set('first_name', 'Rebeca')
        ->set('last_name', 'Vega')
        ->set('email', 'rebeca.vega@correo.cr')
        ->set('phone', '8800-8001')
        ->set('gender', 'femenino')
        ->call('createPresencialEnrollment')
        ->assertHasNoErrors()
        ->assertSet('tab', 'enroll')
        ->assertSet('course_edition_id', null)
        ->assertSet('identification_number', '')
        ->assertSet('first_name', '')
        ->assertSet('last_name', '')
        ->assertSet('email', '')
        ->assertSet('phone', '');

    $wait = Enrollment::whereHas('student', fn ($q) => $q->where('identification_number', '1-8000-0001'))->firstOrFail();
    expect($wait->status)->toBe('lista_espera');
    expect($edition->fresh()->availableSeats())->toBe(20);

    // Acción presencial directa: rechazar con picklist y correo (bloquea si falta motivo)
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('tab', 'enroll')
        ->set('course_edition_id', $edition->id)
        ->set('presencial_action', 'rechazar')
        ->set('identification_number', '1-8000-0002')
        ->set('first_name', 'Mónica')
        ->set('last_name', 'Salas')
        ->set('email', 'monica.salas@correo.cr')
        ->set('phone', '8800-8002')
        ->set('gender', 'femenino')
        ->call('createPresencialEnrollment')
        ->assertHasErrors('presencial_rejection_reason_code')
        ->set('presencial_rejection_reason_code', 'nivel_academico_insuficiente')
        ->set('presencial_rejection_notes', 'Se recomienda iniciar en nivel básico.')
        ->call('createPresencialEnrollment')
        ->assertHasNoErrors();

    $rej = Enrollment::whereHas('student', fn ($q) => $q->where('identification_number', '1-8000-0002'))->firstOrFail();
    expect($rej->status)->toBe('rechazada');
    expect($rej->rejection_reason_code)->toBe('nivel_academico_insuficiente');
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EnrollmentRejectedMail::class, fn ($mail) => $mail->hasTo('monica.salas@correo.cr'));
});

it('supports inline attachment uploads in /admisiones, audited reopening of rejected enrollments with capacity check, student directory attachment sync, and returning-applicant lookup on /inscripcion', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $edition = createSampleEdition('curso_tecnico'); // requires cedula_dimex + atestado_academico
    $edition->update(['max_capacity' => 1]);

    // 1. Solicitante se preinscribe en línea solo con cédula (le falta atestado) -> documentacion_pendiente
    Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('gender', 'femenino')
        ->set('identification_number', '1-9000-0001')
        ->set('first_name', 'Gabriela')
        ->set('last_name', 'Mora')
        ->set('email', 'gabriela.mora@correo.cr')
        ->set('phone', '8800-9001')
        ->set('education_level', 'noveno_aprobado')
        ->set('file_cedula', UploadedFile::fake()->create('cedula.pdf', 100, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors();

    $enr = Enrollment::firstOrFail();
    expect($enr->status)->toBe('documentacion_pendiente');
    expect($enr->documents_complete)->toBeFalse();

    // 2. Retomar solicitud en /inscripcion en una sesión nueva usando ÚNICAMENTE cédula (incluso sin guiones)
    Livewire::test(PublicEnrollmentPage::class)
        ->call('toggleResumeLookup')
        ->set('lookup_identification', '190000001')
        ->call('resumePendingEnrollment')
        ->assertHasNoErrors()
        ->assertSet('createdEnrollmentId', $enr->id)
        ->assertSet('showPendingUploadForm', true)
        ->set('pending_file_atestado', UploadedFile::fake()->create('atestado.pdf', 120, 'application/pdf'))
        ->call('uploadMissingDocuments')
        ->assertHasNoErrors();

    expect(Enrollment::count())->toBe(1);
    expect($enr->fresh()->status)->toBe('preinscrita');
    expect($enr->fresh()->documents_complete)->toBeTrue();

    // 3. Rechazar la solicitud por documentación incompleta y luego subir adjunto + reabrirla en /admisiones
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->call('startRejectEnrollment', $enr->id)
        ->set('rejection_reason_code', 'documentacion_incompleta')
        ->call('submitRejection')
        ->assertHasNoErrors();

    expect($enr->fresh()->status)->toBe('rechazada');

    // Reabrir con motivo y subiendo un nuevo PDF de respaldo (con cupo disponible -> vuelve a preinscrita sin duplicar)
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->call('startReopenEnrollment', $enr->id)
        ->set('reopen_reason', 'Presentó certificación oficial del MEP en recepción')
        ->set('reopen_attachment_doc_type', 'atestado_academico')
        ->set('reopen_attachment_file', UploadedFile::fake()->create('titulo_mep.pdf', 140, 'application/pdf'))
        ->call('submitReopenEnrollment')
        ->assertHasNoErrors();

    expect(Enrollment::count())->toBe(1);
    expect($enr->fresh()->status)->toBe('preinscrita');
    expect(AuditLog::where('event', 'enrollment.reopened')->where('auditable_id', (string) $enr->id)->exists())->toBeTrue();
    expect(AuditLog::where('event', 'enrollment.documents_attached')->where('auditable_id', (string) $enr->id)->exists())->toBeTrue();

    // 4. Crear una 2da solicitud rechazada mientras el único cupo (1/1) está ocupado: al reabrir sin sobrecupo debe entrar a lista_espera
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('tab', 'enroll')
        ->set('course_edition_id', $edition->id)
        ->set('full_capacity_decision', 'rechazar_cupo_lleno')
        ->set('identification_number', '1-9000-0002')
        ->set('first_name', 'Karina')
        ->set('last_name', 'Ulloa')
        ->set('email', 'karina.ulloa@correo.cr')
        ->set('phone', '8800-9002')
        ->set('gender', 'femenino')
        ->set('education_level', 'bachillerato_secundaria')
        ->set('submitted_documents', ['cedula_dimex'])
        ->call('createPresencialEnrollment')
        ->assertHasNoErrors();

    $enr2 = Enrollment::whereHas('student', fn ($q) => $q->where('identification_number', '1-9000-0002'))->firstOrFail();
    expect($enr2->status)->toBe('rechazada');

    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->call('startReopenEnrollment', $enr2->id)
        ->set('reopen_reason', 'Solicitante pidió quedar en lista de espera')
        ->call('submitReopenEnrollment')
        ->assertHasNoErrors();

    expect($enr2->fresh()->status)->toBe('lista_espera');

    // 5. Subir el atestado faltante de Karina desde Directorio de Alumnas (+ Adjunto) actualiza automáticamente el checklist de su solicitud en lista_espera
    expect($enr2->fresh()->documents_complete)->toBeFalse();
    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->set('tab', 'students')
        ->call('startAttachStudent', $enr2->student_id)
        ->set('student_attachment_doc_type', 'atestado_academico')
        ->set('student_attachment_file', UploadedFile::fake()->create('atestado_karina.pdf', 110, 'application/pdf'))
        ->call('uploadStudentAttachment')
        ->assertHasNoErrors();

    expect($enr2->fresh()->documents_complete)->toBeTrue();
    expect($enr2->fresh()->status)->toBe('lista_espera');

    // 6. Confirmar con excepción (sin atestado) y luego subir el atestado faltante desde Adjuntos -> cambia a Completo y muestra Excepción cumplida
    $edition2 = createSampleEdition('curso_tecnico');
    $student3 = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Lorena',
        'last_name' => 'Pacheco',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-9000-0003',
        'email' => 'lorena.pacheco@correo.cr',
        'phone' => '8800-9003',
        'gender' => 'femenino',
        'education_level' => 'bachillerato_secundaria',
    ]);
    $enrExc = app(EnrollmentService::class)->createPresencialEnrollment(
        student: $student3,
        edition: $edition2,
        submittedDocuments: ['cedula_dimex'],
        confirmImmediately: true,
        authorizeException: true,
        exceptionReason: 'Título pendiente del MEP',
        exceptionCommitmentDate: now()->addDays(10)->toDateString()
    );
    expect($enrExc->documents_complete)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(\App\Livewire\AdmissionsPage::class)
        ->call('startAttachEnrollment', $enrExc->id)
        ->set('enr_attachment_doc_type', 'atestado_academico')
        ->set('enr_attachment_file', UploadedFile::fake()->create('titulo_lorena.pdf', 110, 'application/pdf'))
        ->call('uploadEnrollmentAttachment')
        ->assertHasNoErrors()
        ->assertSee('Excepción documental cumplida');

    expect($enrExc->fresh()->documents_complete)->toBeTrue();
    expect($enrExc->fresh()->status)->toBe('confirmada');
});




