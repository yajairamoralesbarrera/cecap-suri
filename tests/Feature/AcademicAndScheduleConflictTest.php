<?php

use App\Livewire\AcademicPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\EditionSchedule;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('requires mandatory subjects when creating a Technical course and sets Atestados only for curso_tecnico', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $cat = AcademicCategory::create(['code' => 'CAT-01', 'name' => 'Tecnología', 'created_by' => $admin->id]);

    expect(Course::defaultRequiredDocumentsFor('curso_corto'))->toBe(['cedula_dimex']);
    expect(Course::defaultRequiredDocumentsFor('programa'))->toBe(['cedula_dimex']);
    expect(Course::defaultRequiredDocumentsFor('curso_tecnico'))->toBe(['cedula_dimex', 'atestado_academico']);

    // Intentar crear curso técnico sin materias debe fallar
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('course_category_id', $cat->id)
        ->set('course_code', 'TEC-01')
        ->set('course_name', 'Técnico Contable')
        ->set('course_program_type', 'curso_tecnico')
        ->set('technical_subjects', [['code' => 'MAT-01', 'name' => '', 'hours' => 40]])
        ->call('createCourse')
        ->assertHasErrors('technical_subjects');

    // Con al menos una materia se crea correctamente
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('course_category_id', $cat->id)
        ->set('course_code', 'TEC-01')
        ->set('course_name', 'Técnico Contable')
        ->set('course_program_type', 'curso_tecnico')
        ->set('course_default_max_capacity', 22)
        ->set('technical_subjects', [['code' => 'MAT-01', 'name' => 'Contabilidad I', 'hours' => 60]])
        ->call('createCourse')
        ->assertHasNoErrors();

    $course = Course::where('code', 'TEC-01')->firstOrFail();
    expect($course->subjects()->count())->toBe(1);
    expect($course->default_max_capacity)->toBe(22);

    // Al seleccionar el curso en Edición, autollena max_capacity desde default_max_capacity
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('ed_course_id', $course->id)
        ->assertSet('ed_max_capacity', 22);
});

it('blocks overlapping classroom and teacher schedules at PostgreSQL GiST level', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $teacher = User::create([
        'name' => 'Profe Uno',
        'email' => 'profe1@cecap.or.cr',
        'identification_number' => '1-6000-0001',
        'phone' => '8800-9900',
        'password' => Hash::make('ProfePass2026!'),
        'must_change_password' => false,
    ]);
    $teacher->assignRole('profesora');

    $cat = AcademicCategory::create(['code' => 'CAT-G', 'name' => 'Gastronomía', 'created_by' => $admin->id]);
    $course = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'CUR-01',
        'name' => 'Cocina Saludable',
        'program_type' => 'curso_corto',
        'required_education_level' => 'primaria_completa',
        'default_max_capacity' => 20,
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);
    $shift = AcademicShift::firstOrFail();
    $mod = AcademicModality::firstOrFail();
    $room1 = Classroom::create(['code' => 'A-1', 'name' => 'Aula 1', 'capacity' => 20]);
    $room2 = Classroom::create(['code' => 'A-2', 'name' => 'Aula 2', 'capacity' => 20]);

    $ed1 = CourseEdition::create([
        'course_id' => $course->id, 'code' => 'ED-1', 'name' => 'Edición 1',
        'shift_id' => $shift->id, 'modality_id' => $mod->id, 'classroom_id' => $room1->id,
        'teacher_id' => $teacher->id, 'start_date' => '2026-06-01', 'end_date' => '2026-08-31',
        'max_capacity' => 20, 'created_by' => $admin->id,
    ]);
    $ed2 = CourseEdition::create([
        'course_id' => $course->id, 'code' => 'ED-2', 'name' => 'Edición 2',
        'shift_id' => $shift->id, 'modality_id' => $mod->id, 'classroom_id' => $room1->id,
        'teacher_id' => $teacher->id, 'start_date' => '2026-06-01', 'end_date' => '2026-08-31',
        'max_capacity' => 20, 'created_by' => $admin->id,
    ]);

    EditionSchedule::create([
        'course_edition_id' => $ed1->id, 'classroom_id' => $room1->id, 'teacher_id' => $teacher->id,
        'day_of_week' => 1, 'start_minute' => 480, 'end_minute' => 600,
        'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'is_active' => true,
    ]);

    // Choque de aula directo en PostgreSQL EXCLUDE USING gist
    expect(fn () => EditionSchedule::create([
        'course_edition_id' => $ed2->id, 'classroom_id' => $room1->id, 'teacher_id' => null,
        'day_of_week' => 1, 'start_minute' => 540, 'end_minute' => 660,
        'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'is_active' => true,
    ]))->toThrow(QueryException::class);

    // Choque de profesora en otra aula distinto directo en PostgreSQL EXCLUDE USING gist
    expect(fn () => EditionSchedule::create([
        'course_edition_id' => $ed2->id, 'classroom_id' => $room2->id, 'teacher_id' => $teacher->id,
        'day_of_week' => 1, 'start_minute' => 540, 'end_minute' => 660,
        'start_date' => '2026-06-01', 'end_date' => '2026-08-31', 'is_active' => true,
    ]))->toThrow(QueryException::class);
});

it('strictly scopes coordinador_categoria to assigned user_academic_categories', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $catA = AcademicCategory::create(['code' => 'CAT-A', 'name' => 'Área Permitida', 'created_by' => $admin->id]);
    $catB = AcademicCategory::create(['code' => 'CAT-B', 'name' => 'Área Restringida', 'created_by' => $admin->id]);

    $coord = User::create([
        'name' => 'Coord A',
        'email' => 'coorda@cecap.or.cr',
        'identification_number' => '1-6111-2222',
        'phone' => '8811-2233',
        'password' => Hash::make('CoordPass2026!'),
        'must_change_password' => false,
    ]);
    $coord->assignRole('coordinador_categoria');
    $coord->academicCategories()->attach($catA->id, ['is_active' => true, 'assigned_by' => $admin->id]);

    Livewire::actingAs($coord)
        ->test(AcademicPage::class)
        ->set('course_category_id', $catB->id)
        ->set('course_code', 'BLOCK-01')
        ->set('course_name', 'Curso Fuera de Alcance')
        ->set('course_program_type', 'curso_corto')
        ->call('createCourse')
        ->assertHasErrors('course_category_id');
});

it('allows audited editing of course attributes, adding subjects with sessions, and toggling active status', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $cat = AcademicCategory::create(['code' => 'CAT-ADM', 'name' => 'Administración', 'created_by' => $admin->id]);
    $course = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'CUR-EDIT-01',
        'name' => 'Ofimática Básica',
        'program_type' => 'curso_corto',
        'required_education_level' => 'primaria_completa',
        'default_max_capacity' => 20,
        'duration_hours' => 30,
        'duration_months' => 1,
        'base_enrollment_fee' => 10000,
        'base_monthly_fee' => 20000,
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);

    // No debe permitir cambiar a curso_tecnico si tiene 0 materias
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('editCourse', $course->id)
        ->set('course_program_type', 'curso_tecnico')
        ->call('createCourse')
        ->assertHasErrors('course_program_type');

    // Editar características del curso y auditar
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('editCourse', $course->id)
        ->assertSet('editingCourseId', $course->id)
        ->set('course_name', 'Ofimática Intermedia')
        ->set('course_default_max_capacity', 28)
        ->set('course_duration_hours', 48)
        ->set('course_description', 'Perfil actualizado del curso')
        ->call('createCourse')
        ->assertHasNoErrors()
        ->assertSet('editingCourseId', null);

    $course->refresh();
    expect($course->name)->toBe('Ofimática Intermedia');
    expect($course->default_max_capacity)->toBe(28);
    expect($course->duration_hours)->toBe(48);
    expect($course->description)->toBe('Perfil actualizado del curso');
    expect(\App\Models\AuditLog::where('event', 'academic.course_updated')->where('auditable_id', (string) $course->id)->exists())->toBeTrue();

    // Agregar materia con sesiones y luego inactivar curso sin borrado físico
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('openCourseSubjects', $course->id)
        ->set('new_sub_code', 'MAT-01')
        ->set('new_sub_name', 'Hojas de Cálculo')
        ->set('new_sub_hours', 24)
        ->set('new_sub_sessions', 8)
        ->call('addSubjectToCourse')
        ->assertHasNoErrors()
        ->call('toggleCourseActive', $course->id);

    $course->refresh();
    expect($course->subjects()->where('code', 'MAT-01')->firstOrFail()->sessions_count)->toBe(8);
    expect($course->is_active)->toBeFalse();
    expect(\App\Models\AuditLog::where('event', 'academic.course_toggled')->where('auditable_id', (string) $course->id)->exists())->toBeTrue();
});

it('allows audited inline editing of course editions without duplicating and toggling public enrollment visibility', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $cat = AcademicCategory::create(['code' => 'CAT-ED', 'name' => 'Ediciones', 'created_by' => $admin->id]);
    $course = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'CUR-ED-01',
        'name' => 'Administración',
        'program_type' => 'curso_corto',
        'required_education_level' => 'primaria_completa',
        'default_max_capacity' => 25,
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);
    $edition = CourseEdition::create([
        'course_id' => $course->id,
        'code' => 'ED-ADMIN-01',
        'name' => 'Cohorte 1',
        'shift_id' => AcademicShift::firstOrFail()->id,
        'modality_id' => AcademicModality::firstOrFail()->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-08-31',
        'max_capacity' => 25,
        'is_public_enrollment_open' => true,
        'created_by' => $admin->id,
    ]);

    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('editEdition', $edition->id)
        ->assertSet('editingEditionId', $edition->id)
        ->set('edit_ed_max_capacity', 12)
        ->set('edit_ed_name', 'Cohorte 1 Actualizada')
        ->call('saveEditionEdit')
        ->assertHasNoErrors()
        ->assertSet('editingEditionId', null)
        ->call('toggleEditionPublicOpen', $edition->id);

    expect(CourseEdition::count())->toBe(1);
    $edition->refresh();
    expect($edition->max_capacity)->toBe(12);
    expect($edition->name)->toBe('Cohorte 1 Actualizada');
    expect($edition->is_public_enrollment_open)->toBeFalse();
    expect(\App\Models\AuditLog::where('event', 'academic.edition_updated')->where('auditable_id', (string) $edition->id)->exists())->toBeTrue();
    expect(\App\Models\AuditLog::where('event', 'academic.edition_public_toggled')->where('auditable_id', (string) $edition->id)->exists())->toBeTrue();
});


