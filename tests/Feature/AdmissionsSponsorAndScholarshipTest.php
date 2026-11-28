<?php

use App\Livewire\ActivitiesPage;
use App\Livewire\AdmissionsPage;
use App\Livewire\QueriesPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\FeeCharge;
use App\Models\Payer;
use App\Models\Scholarship;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopParticipant;
use App\Services\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('allows assigning sponsor payer and scholarship percentage in Admissions Edición Controlada and syncs Finance and Activities without double-discounting', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    // IMAS should be preloaded as an active payer (or create if test DB started fresh without migration seed)
    $imas = Payer::whereRaw('LOWER(full_name) LIKE ?', ['%imas%'])->first();
    if (! $imas) {
        $imas = Payer::create([
            'payer_type' => 'institucion_publica',
            'full_name' => 'IMAS — Instituto Mixto de Ayuda Social',
            'is_active' => true,
        ]);
    }

    $cat = AcademicCategory::create(['code' => 'CAT-BEC', 'name' => 'Técnicos', 'created_by' => $admin->id]);
    $shift = AcademicShift::create(['code' => 'MAN-BEC', 'name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '12:00']);
    $mod = AcademicModality::create(['code' => 'PRES-BEC', 'name' => 'Presencial']);

    $course = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'EST-101',
        'name' => 'Estética Integral',
        'program_type' => 'tecnico',
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);

    $edition = CourseEdition::create([
        'course_id' => $course->id,
        'code' => 'EST-2026-I',
        'name' => 'Cohorte Estética I',
        'shift_id' => $shift->id,
        'modality_id' => $mod->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-07-31',
        'max_capacity' => 20,
        'enrollment_fee' => 20000,
        'monthly_fee' => 30000,
        'installments_count' => 2,
        'status' => 'abierta',
        'created_by' => $admin->id,
    ]);

    $student = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Mariela',
        'last_name' => 'Solís',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-1456-0987',
        'email' => 'mariela.solis@cecap.or.cr',
        'phone' => '8888-1122',
        'gender' => 'femenino',
        'birth_date' => '1997-04-10',
        'education_level' => 'bachillerato_secundaria',
        'status' => 'activo',
        'created_by' => $admin->id,
    ]);

    // Confirm enrollment first so fee plan exists (matricula 20,000 + 2 mensualidades 30,000)
    $enrollment = app(EnrollmentService::class)->createPresencialEnrollment(
        student: $student,
        edition: $edition,
        submittedDocuments: ['cedula_dimex'],
        confirmImmediately: true
    );

    expect($student->fresh()->hasImasSponsor())->toBeFalse();
    expect(FeeCharge::where('enrollment_id', $enrollment->id)->count())->toBe(3);

    // 1. Edición Controlada en Directorio de Alumnas: asignar IMAS + 50% de beca
    Livewire::test(AdmissionsPage::class)
        ->call('setTab', 'students')
        ->call('editStudent', $student->id)
        ->set('sponsor_payer_id', $imas->id)
        ->set('scholarship_percent', 50)
        ->set('scholarship_notes', 'Oficio IMAS-2026-099')
        ->set('edit_reason', 'Resolución IMAS otorgando 50% de beca')
        ->call('saveStudentOnly')
        ->assertHasNoErrors();

    $student->refresh();
    expect($student->sponsor_payer_id)->toBe($imas->id);
    expect((float) $student->scholarship_percent)->toBe(50.0);
    expect($student->scholarship_notes)->toBe('Oficio IMAS-2026-099');
    expect($student->hasImasSponsor())->toBeTrue();

    $matriculaCharge = FeeCharge::where('enrollment_id', $enrollment->id)->where('charge_type', 'matricula')->firstOrFail();
    expect((float) $matriculaCharge->gross_amount)->toBe(20000.0);
    expect((float) $matriculaCharge->scholarship_discount)->toBe(10000.0);
    expect((float) $matriculaCharge->net_amount)->toBe(10000.0);
    expect($matriculaCharge->payer_id)->toBe($imas->id);

    // 2. Actualizar beca a 100% desde Edición Controlada: no debe duplicar ni alterar gross_amount
    Livewire::test(AdmissionsPage::class)
        ->call('setTab', 'students')
        ->call('editStudent', $student->id)
        ->set('scholarship_percent', 100)
        ->set('edit_reason', 'Ampliación de beca IMAS al 100%')
        ->call('saveStudentOnly')
        ->assertHasNoErrors();

    $matriculaCharge->refresh();
    expect((float) $matriculaCharge->gross_amount)->toBe(20000.0);
    expect((float) $matriculaCharge->scholarship_discount)->toBe(20000.0);
    expect((float) $matriculaCharge->net_amount)->toBe(0.0);
    expect($matriculaCharge->status)->toBe('exonerado');
    expect(Scholarship::where('enrollment_id', $enrollment->id)->where('status', 'activa')->count())->toBe(1);

    // 3. Al inscribirla en un taller con costo, Actividades asigna automáticamente método IMAS
    $workshop = Workshop::create([
        'code' => 'TALL-BEC-01',
        'title' => 'Seminario de Emprendimiento',
        'workshop_type' => 'seminario',
        'facilitator_name' => 'Facilitadora Prueba',
        'event_date' => '2026-05-15',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'cost' => 12000,
        'soft_skills_absence_credits' => 1,
        'status' => 'planificado',
        'created_by' => $admin->id,
    ]);

    Livewire::test(ActivitiesPage::class)
        ->call('selectWorkshop', $workshop->id)
        ->set('participant_student_id', $student->id)
        ->assertSet('participant_is_imas', true)
        ->assertSet('participant_payment_method', 'imas')
        ->call('addParticipant')
        ->assertHasNoErrors();

    $participant = WorkshopParticipant::where('workshop_id', $workshop->id)->where('student_id', $student->id)->firstOrFail();
    expect($participant->payment_method)->toBe('imas');
    expect($participant->payment_status)->toBe('cubierto_imas');
    expect((float) $participant->amount_charged)->toBe(12000.0);

    // 4. Verificar que Expediente Alumnas muestra Pagador / Patrocinante y % de Beca
    Livewire::test(QueriesPage::class)
        ->call('selectStudent', $student->id)
        ->assertSee('IMAS')
        ->assertSee('100.00%')
        ->assertSee('Oficio IMAS-2026-099');
});

it('allows editing sponsor payer and scholarship percentage from Cola de Admisiones without creating premature scholarships before confirmation', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    $imas = Payer::whereRaw('LOWER(full_name) LIKE ?', ['%imas%'])->firstOrFail();
    $cat = AcademicCategory::create(['code' => 'CAT-COLA', 'name' => 'Cortos', 'created_by' => $admin->id]);
    $shift = AcademicShift::create(['code' => 'TAR-COLA', 'name' => 'Tarde', 'start_time' => '13:00', 'end_time' => '17:00']);
    $mod = AcademicModality::create(['code' => 'PRES-COLA', 'name' => 'Presencial']);

    $course = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'PAN-101',
        'name' => 'Panadería Artesanal',
        'program_type' => 'curso_corto',
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);

    $edition = CourseEdition::create([
        'course_id' => $course->id,
        'code' => 'PAN-2026-I',
        'name' => 'Cohorte Panadería I',
        'shift_id' => $shift->id,
        'modality_id' => $mod->id,
        'start_date' => '2026-04-01',
        'end_date' => '2026-08-31',
        'max_capacity' => 20,
        'enrollment_fee' => 10000,
        'monthly_fee' => 20000,
        'installments_count' => 1,
        'status' => 'abierta',
        'created_by' => $admin->id,
    ]);

    $student = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Karla',
        'last_name' => 'Vargas',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '2-0654-0321',
        'email' => 'karla.vargas@cecap.or.cr',
        'phone' => '8888-3344',
        'gender' => 'femenino',
        'birth_date' => '1999-08-20',
        'education_level' => 'bachillerato_secundaria',
        'status' => 'activo',
        'created_by' => $admin->id,
    ]);

    // Preinscrita sin confirmar aún
    $enrollment = Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $student->id,
        'course_edition_id' => $edition->id,
        'status' => 'preinscrita',
        'source' => 'portal_publico',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'documents_complete' => true,
        'created_by' => $admin->id,
    ]);

    Livewire::test(AdmissionsPage::class)
        ->call('startEditEnrollment', $enrollment->id)
        ->set('edit_enr_sponsor_payer_id', $imas->id)
        ->set('edit_enr_scholarship_percent', 75)
        ->set('edit_enr_scholarship_notes', 'Beca IMAS 75%')
        ->set('edit_enr_reason', 'Carga inicial de patrocinante y beca en Cola de Admisiones')
        ->call('saveEnrollmentEdit')
        ->assertHasNoErrors();

    $student->refresh();
    expect($student->sponsor_payer_id)->toBe($imas->id);
    expect((float) $student->scholarship_percent)->toBe(75.0);
    // No debe crear beca prematura antes de confirmar
    expect(Scholarship::where('enrollment_id', $enrollment->id)->count())->toBe(0);

    // Al confirmar la matrícula, generateFeePlanForEnrollment aplica automáticamente el 75% y el pagador IMAS una sola vez
    app(EnrollmentService::class)->confirmEnrollment($enrollment->fresh(), ['cedula_dimex']);

    expect(Scholarship::where('enrollment_id', $enrollment->id)->where('status', 'activa')->count())->toBe(1);
    $monthly = FeeCharge::where('enrollment_id', $enrollment->id)->where('charge_type', 'mensualidad')->firstOrFail();
    expect((float) $monthly->gross_amount)->toBe(20000.0);
    expect((float) $monthly->scholarship_discount)->toBe(15000.0);
    expect((float) $monthly->net_amount)->toBe(5000.0);
    expect($monthly->payer_id)->toBe($imas->id);
});
