<?php

use App\Livewire\ActivitiesPage;
use App\Livewire\TrackingPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\Payer;
use App\Models\Scholarship;
use App\Models\SoftSkillWeek;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopParticipant;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('tracks weekly Soft Skills attendance across all enrolled students with courses, filters, no-session weeks, workshop absence credits, workshop cost, and automatic IMAS payment method', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    $cat = AcademicCategory::create(['code' => 'CAT-HB', 'name' => 'Formación Integral', 'created_by' => $admin->id]);
    $shift = AcademicShift::create(['code' => 'MAN-HB', 'name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '12:00']);
    $mod = AcademicModality::create(['code' => 'PRES-HB', 'name' => 'Presencial']);

    $courseA = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'COST-101',
        'name' => 'Costura Creativa',
        'program_type' => 'tecnico',
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);
    $courseB = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'COMP-101',
        'name' => 'Ofimática Práctica',
        'program_type' => 'curso_corto',
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);

    $edA = CourseEdition::create([
        'course_id' => $courseA->id,
        'code' => 'COST-2026-A',
        'name' => 'Cohorte Costura I',
        'shift_id' => $shift->id,
        'modality_id' => $mod->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-07-31',
        'max_capacity' => 20,
        'status' => 'abierta',
        'created_by' => $admin->id,
    ]);
    $edB = CourseEdition::create([
        'course_id' => $courseB->id,
        'code' => 'COMP-2026-A',
        'name' => 'Cohorte Ofimática I',
        'shift_id' => $shift->id,
        'modality_id' => $mod->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-07-31',
        'max_capacity' => 20,
        'status' => 'abierta',
        'created_by' => $admin->id,
    ]);

    // Alumna 1 cursa AMBOS cursos y tiene patrocinador IMAS
    $stImas = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Yajaira',
        'last_name' => 'Morales',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-6111-2222',
        'email' => 'yajaira.hb@cecap.or.cr',
        'phone' => '8811-0001',
        'gender' => 'femenino',
        'created_by' => $admin->id,
    ]);
    // Alumna 2 cursa solo Ofimática y NO tiene IMAS
    $stRegular = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Gabriela',
        'last_name' => 'Solís',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-6333-4444',
        'email' => 'gabriela.hb@cecap.or.cr',
        'phone' => '8811-0002',
        'gender' => 'femenino',
        'created_by' => $admin->id,
    ]);

    $enrImasA = Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $stImas->id,
        'course_edition_id' => $edA->id,
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);
    Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $stImas->id,
        'course_edition_id' => $edB->id,
        'status' => 'en_curso',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);
    Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $stRegular->id,
        'course_edition_id' => $edB->id,
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);

    // Patrocinador IMAS para Alumna 1
    $imasPayer = Payer::create([
        'payer_type' => 'institucion_publica',
        'full_name' => 'IMAS — Instituto Mixto de Ayuda Social',
        'identification_number' => '3-007-045678',
        'is_active' => true,
    ]);
    Scholarship::create([
        'enrollment_id' => $enrImasA->id,
        'payer_id' => $imasPayer->id,
        'name' => 'Beca Patrocinio IMAS',
        'discount_type' => 'percentage',
        'discount_value' => 100,
        'status' => 'activa',
        'approved_by' => $admin->id,
    ]);

    expect($stImas->hasImasSponsor())->toBeTrue();
    expect($stRegular->hasImasSponsor())->toBeFalse();

    // 1. Crear Semanas 1 y 2 con sesión, y Semana 3 SIN Habilidades Blandas
    $tracking = Livewire::test(TrackingPage::class)
        ->set('tab', 'soft_skills')
        ->set('ss_week_start', '2026-04-06')
        ->set('ss_week_end', '2026-04-12')
        ->set('ss_week_label', 'Semana 1 — Comunicación')
        ->set('ss_week_no_session', false)
        ->call('createSoftSkillWeek')
        ->assertHasNoErrors();

    $week1 = SoftSkillWeek::where('label', 'Semana 1 — Comunicación')->firstOrFail();

    // Verifica que lista a TODAS las alumnas inscritas y muestra los DOS cursos de Yajaira
    $tracking
        ->assertSee('Yajaira Morales')
        ->assertSee('Gabriela Solís')
        ->assertSee('Costura Creativa')
        ->assertSee('Ofimática Práctica');

    // Filtro por curso Costura Creativa solo muestra a Yajaira
    $tracking->set('ss_filter_course_id', $courseA->id);
    expect($tracking->viewData('softSkillRoster')->count())->toBe(1);
    expect($tracking->viewData('softSkillRoster')->first()['student']->id)->toBe($stImas->id);
    $tracking->call('clearSoftSkillFilters');

    // Registrar asistencia Semana 1: Yajaira ausente, Gabriela presente
    $tracking
        ->call('setSoftSkillStatus', $stImas->id, 'ausente')
        ->call('setSoftSkillStatus', $stRegular->id, 'presente')
        ->set("ss_schedule_notes.{$stRegular->id}", 'Miércoles Tarde')
        ->call('saveSoftSkillAttendance')
        ->assertHasNoErrors();

    // Semana 2: Yajaira ausente otra vez (2 ausencias brutas)
    $tracking
        ->set('ss_week_start', '2026-04-13')
        ->set('ss_week_end', '2026-04-19')
        ->set('ss_week_label', 'Semana 2 — Trabajo en Equipo')
        ->set('ss_week_no_session', false)
        ->call('createSoftSkillWeek')
        ->call('setSoftSkillStatus', $stImas->id, 'ausente')
        ->call('saveSoftSkillAttendance')
        ->assertHasNoErrors();

    // Semana 3: marcada SIN Habilidades Blandas -> bloquea pase de asistencia y NO suma ausencias
    $tracking
        ->set('ss_week_start', '2026-04-20')
        ->set('ss_week_end', '2026-04-26')
        ->set('ss_week_label', 'Semana 3 — Receso')
        ->set('ss_week_no_session', true)
        ->set('ss_week_no_session_reason', 'Feriado institucional')
        ->call('createSoftSkillWeek')
        ->assertHasNoErrors()
        ->call('saveSoftSkillAttendance')
        ->assertHasErrors('selected_ss_week_id');

    $summaryBeforeWorkshop = $stImas->fresh()->softSkillsAttendanceSummary();
    expect($summaryBeforeWorkshop['gross_absences'])->toBe(2);
    expect($summaryBeforeWorkshop['workshop_credits'])->toBe(0);
    expect($summaryBeforeWorkshop['net_absences'])->toBe(2);

    // 2. Crear Seminario con costo ₡15,000 (activando ¿Tiene costo?) y equivalencia = 2 ausencias en Habilidades Blandas
    $activities = Livewire::test(ActivitiesPage::class)
        ->set('title', 'Seminario de Auditoría y Liderazgo')
        ->set('workshop_type', 'charla')
        ->set('facilitator_name', 'Mariam Díaz')
        ->set('event_date', '2026-04-25')
        ->set('start_time', '09:00')
        ->set('end_time', '12:00')
        ->set('max_capacity', 40)
        ->set('has_cost', true)
        ->set('cost', 15000)
        ->set('soft_skills_absence_credits', 2)
        ->call('createWorkshop')
        ->assertHasNoErrors();

    $workshop = Workshop::where('title', 'Seminario de Auditoría y Liderazgo')->firstOrFail();
    expect((float) $workshop->cost)->toBe(15000.0);
    expect($workshop->soft_skills_absence_credits)->toBe(2);

    // Inscribir a Yajaira (IMAS) -> automáticamente asigna método de cancelación IMAS
    $activities
        ->set('participant_student_id', $stImas->id)
        ->assertSet('participant_is_imas', true)
        ->assertSet('participant_payment_method', 'imas')
        ->call('addParticipant')
        ->assertHasNoErrors();

    $pImas = WorkshopParticipant::where('workshop_id', $workshop->id)
        ->where('student_id', $stImas->id)
        ->firstOrFail();
    expect($pImas->payment_method)->toBe('imas');
    expect($pImas->payment_status)->toBe('cubierto_imas');
    expect((float) $pImas->amount_charged)->toBe(15000.0);

    // Inscribir a Gabriela (sin IMAS) pagando por SINPE Móvil
    $activities
        ->set('participant_student_id', $stRegular->id)
        ->assertSet('participant_is_imas', false)
        ->set('participant_payment_method', 'sinpe_movil')
        ->set('participant_payment_reference', 'SINPE-998877')
        ->call('addParticipant')
        ->assertHasNoErrors();

    $pRegular = WorkshopParticipant::where('workshop_id', $workshop->id)
        ->where('student_id', $stRegular->id)
        ->firstOrFail();
    expect($pRegular->payment_method)->toBe('sinpe_movil');
    expect($pRegular->payment_status)->toBe('pagado');

    // 3. Verificar que el seminario compensó automáticamente las 2 ausencias en Habilidades Blandas de Yajaira
    $summaryAfterWorkshop = $stImas->fresh()->softSkillsAttendanceSummary();
    expect($summaryAfterWorkshop['gross_absences'])->toBe(2);
    expect($summaryAfterWorkshop['workshop_credits'])->toBe(2);
    expect($summaryAfterWorkshop['net_absences'])->toBe(0);
    expect($summaryAfterWorkshop['credited_workshops'])->toContain('Seminario de Auditoría y Liderazgo (+2)');

    // 4. Consulta de Asistencia de Habilidades Blandas (sub-modo en Consulta de Asistencias) + Excel (.xlsx)
    $ssReport = Livewire::test(TrackingPage::class)
        ->call('openSoftSkillsReport')
        ->assertSet('tab', 'attendance_report')
        ->assertSet('report_mode', 'soft_skills')
        ->set('ss_report_course_id', $courseA->id);

    expect($ssReport->viewData('ssReportByStudent')->count())->toBe(1);
    expect($ssReport->viewData('ssReportRecords')->count())->toBe(2);

    $xlsxResp = $this->actingAs($admin)->get(route('seguimiento.soft-skills.excel', [
        'course_id' => $courseA->id,
    ]));
    $xlsxResp->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmp = tempnam(sys_get_temp_dir(), 'ss_xlsx_');
    file_put_contents($tmp, $xlsxResp->getContent());
    $zip = new ZipArchive();
    expect($zip->open($tmp))->toBeTrue();
    $sheet1 = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    @unlink($tmp);

    expect($sheet1)->toContain('Yajaira Morales');
    expect($sheet1)->toContain('Seminario de Auditoría y Liderazgo (+2)');
});
