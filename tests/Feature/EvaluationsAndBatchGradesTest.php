<?php

use App\Livewire\TrackingPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\EditionEvaluation;
use App\Models\Enrollment;
use App\Models\GradeRecord;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('configures evaluations with percentage cap (100%) first and then grades all students in batch per evaluation with audited rectification', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    $cat = AcademicCategory::create(['code' => 'CAT-EV', 'name' => 'Belleza', 'created_by' => $admin->id]);
    $shift = AcademicShift::create(['code' => 'TAR-EV', 'name' => 'Tarde', 'start_time' => '13:00', 'end_time' => '17:00']);
    $mod = AcademicModality::create(['code' => 'PRES-EV', 'name' => 'Presencial']);

    $course = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'BEL-101',
        'name' => 'Estética Integral',
        'program_type' => 'tecnico',
        'required_documents' => ['cedula_dimex'],
        'min_passing_grade' => 70,
        'min_attendance_percent' => 80,
        'created_by' => $admin->id,
    ]);

    $edition = CourseEdition::create([
        'course_id' => $course->id,
        'code' => 'BEL-2026-A',
        'name' => 'Cohorte Estética 2026',
        'shift_id' => $shift->id,
        'modality_id' => $mod->id,
        'start_date' => '2026-03-01',
        'end_date' => '2026-07-31',
        'max_capacity' => 20,
        'status' => 'abierta',
        'created_by' => $admin->id,
    ]);

    $st1 = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Sofía',
        'last_name' => 'Méndez',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-5111-1111',
        'email' => 'sofia.mendez@cecap.or.cr',
        'phone' => '8811-1111',
        'gender' => 'femenino',
        'created_by' => $admin->id,
    ]);
    $st2 = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Valeria',
        'last_name' => 'Castro',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-5222-2222',
        'email' => 'valeria.castro@cecap.or.cr',
        'phone' => '8822-2222',
        'gender' => 'femenino',
        'created_by' => $admin->id,
    ]);

    $enr1 = Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $st1->id,
        'course_edition_id' => $edition->id,
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);
    $enr2 = Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $st2->id,
        'course_edition_id' => $edition->id,
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);

    // Paso 1: Crear evaluación 1 (40%) y evaluación 2 (60%)
    $component = Livewire::test(TrackingPage::class)
        ->set('tab', 'grades')
        ->set('selected_edition_id', $edition->id)
        ->set('eval_name', 'Examen Parcial 1')
        ->set('eval_weight', 40)
        ->call('createEvaluation')
        ->assertHasNoErrors()
        ->set('eval_name', 'Proyecto Final')
        ->set('eval_weight', 60)
        ->call('createEvaluation')
        ->assertHasNoErrors();

    // Intentar agregar una 3ra evaluación (10%) que excede 100% debe fallar
    $component
        ->set('eval_name', 'Quiz Extra')
        ->set('eval_weight', 10)
        ->call('createEvaluation')
        ->assertHasErrors('eval_weight');

    $eval1 = EditionEvaluation::where('name', 'Examen Parcial 1')->firstOrFail();
    $eval2 = EditionEvaluation::where('name', 'Proyecto Final')->firstOrFail();

    // Paso 2: Seleccionar Examen Parcial 1 -> lista a ambas alumnas y guarda notas en bloque
    $component
        ->call('selectEvaluation', $eval1->id)
        ->assertSee('Sofía Méndez')
        ->assertSee('Valeria Castro')
        ->set("evaluation_scores.{$enr1->id}", '90')
        ->set("evaluation_scores.{$enr2->id}", '80')
        ->call('saveEvaluationGrades')
        ->assertHasNoErrors();

    // Paso 2: Cargar notas de Proyecto Final para ambas alumnas
    $component
        ->call('selectEvaluation', $eval2->id)
        ->set("evaluation_scores.{$enr1->id}", '100')
        ->set("evaluation_scores.{$enr2->id}", '70')
        ->call('saveEvaluationGrades')
        ->assertHasNoErrors();

    // Sofía: 90*0.4 + 100*0.6 = 96.0; Valeria: 80*0.4 + 70*0.6 = 74.0
    expect($enr1->fresh()->calculateFinalGrade())->toBe(96.0);
    expect($enr2->fresh()->calculateFinalGrade())->toBe(74.0);

    // Rectificación auditada conserva edition_evaluation_id y actualiza versión a v2
    $valeriaGrade = GradeRecord::where('enrollment_id', $enr2->id)
        ->where('edition_evaluation_id', $eval2->id)
        ->where('is_current', true)
        ->firstOrFail();

    $component
        ->call('startRectifyGrade', $valeriaGrade->id)
        ->set('rectify_score', 85)
        ->set('rectify_reason_code', 'error_digitacion')
        ->set('rectify_notes', 'Corrección tras revisión de rúbrica')
        ->call('submitGradeRectification')
        ->assertHasNoErrors();

    $updatedGrade = GradeRecord::where('enrollment_id', $enr2->id)
        ->where('edition_evaluation_id', $eval2->id)
        ->where('is_current', true)
        ->firstOrFail();
    expect($updatedGrade->version)->toBe(2);
    expect((float) $updatedGrade->score)->toBe(85.0);
    // Valeria actualizada: 80*0.4 + 85*0.6 = 83.0
    expect($enr2->fresh()->calculateFinalGrade())->toBe(83.0);
});
