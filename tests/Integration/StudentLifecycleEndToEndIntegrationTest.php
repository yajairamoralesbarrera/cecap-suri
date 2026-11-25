<?php

use App\Livewire\AcademicPage;
use App\Livewire\AdmissionsPage;
use App\Livewire\FinancePage;
use App\Livewire\GraduationPage;
use App\Livewire\PublicEnrollmentPage;
use App\Livewire\TrackingPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\AttendanceRecord;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\FeeCharge;
use App\Models\FinancialLedgerEntry;
use App\Models\GradeRecord;
use App\Models\GraduationRecord;
use App\Models\PaymentReceipt;
use App\Models\SoftSkillRecord;
use App\Models\Student;
use App\Models\StudentLifecycleEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
});

it('completes the full 6-module vertical student lifecycle (Academic -> Online Enrollment -> Presencial Confirmation -> Tracking -> Finance -> Graduation QR)', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

    // 1. ACADÉMICO: Categoría, Aula, Curso Técnico (con 2 materias), Edición (autollenado capacidad) y Horario GiST
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('cat_code', 'CAT-INT')
        ->set('cat_name', 'Administración Integral')
        ->call('createCategory')
        ->assertHasNoErrors();

    $cat = AcademicCategory::where('code', 'CAT-INT')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('room_code', 'AULA-INT-1')
        ->set('room_name', 'Aula Integración 1')
        ->set('room_capacity', 25)
        ->call('createClassroom')
        ->assertHasNoErrors();

    $room = Classroom::where('code', 'AULA-INT-1')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('course_category_id', $cat->id)
        ->set('course_code', 'TEC-INT-01')
        ->set('course_name', 'Técnico en Gestión Contable')
        ->set('course_program_type', 'curso_tecnico')
        ->set('course_default_max_capacity', 18)
        ->set('course_duration_hours', 240)
        ->set('course_duration_months', 2)
        ->set('course_enrollment_fee', 20000)
        ->set('course_monthly_fee', 40000)
        ->set('technical_subjects', [
            ['code' => 'MAT-01', 'name' => 'Contabilidad General', 'hours' => 120],
            ['code' => 'MAT-02', 'name' => 'Tributación Costa Rica', 'hours' => 120],
        ])
        ->call('createCourse')
        ->assertHasNoErrors();

    $course = Course::where('code', 'TEC-INT-01')->firstOrFail();
    expect($course->subjects()->count())->toBe(2);

    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('ed_course_id', $course->id)
        ->assertSet('ed_max_capacity', 18)
        ->set('ed_code', 'ED-INT-2026-01')
        ->set('ed_name', 'Técnico Contable — Cohorte Integración')
        ->set('ed_shift_id', AcademicShift::firstOrFail()->id)
        ->set('ed_modality_id', AcademicModality::firstOrFail()->id)
        ->set('ed_classroom_id', $room->id)
        ->set('ed_start_date', now()->toDateString())
        ->set('ed_end_date', now()->addMonths(2)->toDateString())
        ->call('createEdition')
        ->assertHasNoErrors();

    $edition = CourseEdition::where('code', 'ED-INT-2026-01')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('sch_edition_id', $edition->id)
        ->set('sch_classroom_id', $room->id)
        ->set('sch_day_of_week', 3)
        ->set('sch_start_time', '08:00')
        ->set('sch_end_time', '11:00')
        ->call('createSchedule')
        ->assertHasNoErrors();

    // 2. INSCRIPCIÓN EN LÍNEA: Solo adjunta cédula (falta atestado -> Documentación Pendiente)
    Livewire::test(PublicEnrollmentPage::class)
        ->set('course_edition_id', $edition->id)
        ->set('gender', 'femenino')
        ->set('identification_number', '1-8888-1001')
        ->set('first_name', 'Gabriela')
        ->set('last_name', 'Vargas')
        ->set('second_last_name', 'Rojas')
        ->set('email', 'gabriela.vargas@ejemplo.cr')
        ->set('phone', '8810-2030')
        ->set('file_cedula', UploadedFile::fake()->create('cedula.pdf', 100, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors();

    $student = Student::where('identification_number', '1-8888-1001')->firstOrFail();
    $enrollment = Enrollment::where('student_id', $student->id)->firstOrFail();
    expect($enrollment->status)->toBe('documentacion_pendiente');
    expect($student->attachments()->count())->toBe(1);

    // 3. ADMISIONES: Bloqueada sin excepción; luego confirmada con excepción auditada -> genera cargos y ledger
    Livewire::actingAs($admin)
        ->test(AdmissionsPage::class)
        ->call('startConfirmEnrollment', $enrollment->id)
        ->call('submitConfirmation')
        ->assertHasErrors('documents');

    Livewire::actingAs($admin)
        ->test(AdmissionsPage::class)
        ->call('startConfirmEnrollment', $enrollment->id)
        ->set('confirm_exception', true)
        ->set('confirm_exception_reason', 'Entrega copia certificada en próxima sesión')
        ->set('confirm_exception_date', now()->addDays(10)->toDateString())
        ->call('submitConfirmation')
        ->assertHasNoErrors();

    expect($enrollment->fresh()->status)->toBe('confirmada');
    expect(FeeCharge::where('enrollment_id', $enrollment->id)->count())->toBe(3); // 1 matrícula + 2 mensualidades
    expect(FinancialLedgerEntry::where('enrollment_id', $enrollment->id)->count())->toBe(3);

    // 4. SEGUIMIENTO: Asistencia versionada (v1 -> v2), Calificación + Rectificación auditada, Habilidad Blanda
    Livewire::actingAs($admin)
        ->test(TrackingPage::class)
        ->set('selected_edition_id', $edition->id)
        ->set('session_date', now()->toDateString())
        ->call('setAttendanceStatus', $enrollment->id, 'ausente')
        ->call('saveAttendance')
        ->set('attendance_rectification_reason', 'Presentó comprobante médico')
        ->call('setAttendanceStatus', $enrollment->id, 'justificada')
        ->call('saveAttendance')
        ->assertHasNoErrors();

    expect(AttendanceRecord::where('enrollment_id', $enrollment->id)->count())->toBe(2);
    expect(AttendanceRecord::where('enrollment_id', $enrollment->id)->where('is_current', true)->first()->version)->toBe(2);

    Livewire::actingAs($admin)
        ->test(TrackingPage::class)
        ->set('selected_edition_id', $edition->id)
        ->set('grade_enrollment_id', $enrollment->id)
        ->set('grade_evaluation_name', 'Examen Final Integrado')
        ->set('grade_score', 78)
        ->set('grade_weight', 100)
        ->call('recordGrade')
        ->assertHasNoErrors();

    $gradeV1 = GradeRecord::where('enrollment_id', $enrollment->id)->firstOrFail();

    Livewire::actingAs($admin)
        ->test(TrackingPage::class)
        ->call('startRectifyGrade', $gradeV1->id)
        ->set('rectify_score', 94)
        ->set('rectify_reason_code', 'revision_examen')
        ->set('rectify_notes', 'Revisión aprobada en coordinación académica')
        ->call('submitGradeRectification')
        ->assertHasNoErrors();

    expect($gradeV1->fresh()->is_current)->toBeFalse();
    expect($enrollment->fresh()->calculateFinalGrade())->toBe(94.0);

    Livewire::actingAs($admin)
        ->test(TrackingPage::class)
        ->set('skill_student_id', $student->id)
        ->set('skill_name', 'Ética Profesional')
        ->set('skill_level', 'destacado')
        ->set('skill_score', 98)
        ->call('recordSoftSkill')
        ->assertHasNoErrors();

    expect(SoftSkillRecord::where('student_id', $student->id)->count())->toBe(1);

    // 5. FINANZAS: Beca 25% (sin alterar monto bruto) + Cobro total -> solvencia = ₡0
    Livewire::actingAs($admin)
        ->test(FinancePage::class)
        ->set('schol_enrollment_id', $enrollment->id)
        ->set('schol_name', 'Beca Rendimiento 25%')
        ->set('schol_type', 'porcentaje')
        ->set('schol_value', 25)
        ->call('applyScholarship')
        ->assertHasNoErrors();

    $charges = FeeCharge::where('enrollment_id', $enrollment->id)->orderBy('id')->get();
    expect((float) $charges[0]->gross_amount)->toBe(20000.0); // Bruto intacto
    expect((float) $charges[0]->net_amount)->toBe(15000.0);

    $allocs = [];
    foreach ($charges as $ch) {
        $allocs[$ch->id] = (string) $ch->balance;
    }

    Livewire::actingAs($admin)
        ->test(FinancePage::class)
        ->set('pay_student_id', $student->id)
        ->set('pay_method', 'sinpe_movil')
        ->set('pay_reference', 'SINPE-INT-9988')
        ->set('charge_allocations', $allocs)
        ->call('collectPayment')
        ->assertHasNoErrors();

    expect($enrollment->fresh()->pendingBalance())->toBe(0.0);
    expect(PaymentReceipt::where('student_id', $student->id)->count())->toBe(1);

    // 6. GRADUACIÓN: Elegible automáticamente, emisión de Acta + Diploma QR verificable
    Livewire::actingAs($admin)
        ->test(GraduationPage::class)
        ->set('enrollment_id', $enrollment->id)
        ->set('tomo', 'II')
        ->set('folio', '14')
        ->set('asiento', '007')
        ->set('graduation_date', now()->toDateString())
        ->call('issue')
        ->assertHasNoErrors();

    $grad = GraduationRecord::where('enrollment_id', $enrollment->id)->firstOrFail();
    expect($grad->is_financially_solvent)->toBeTrue();
    expect(StudentLifecycleEvent::where('student_id', $student->id)->count())->toBeGreaterThanOrEqual(3);

    $this->get(route('diploma.verify', $grad->verification_token))
        ->assertOk()
        ->assertSee('CERTIFICADO AUTÉNTICO Y VERIFICADO')
        ->assertSee('Gabriela Vargas Rojas');
});
