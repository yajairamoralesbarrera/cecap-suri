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
