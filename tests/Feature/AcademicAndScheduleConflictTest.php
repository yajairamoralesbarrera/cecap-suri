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

    // Programa es por módulo: bloquea si no tiene módulos, sugiere MOD-01, pide solo cédula por defecto (con atestados opcional)
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->set('course_category_id', $cat->id)
        ->set('course_code', 'PROG-01')
        ->set('course_name', 'Programa de Emprendimiento')
        ->set('course_program_type', 'programa')
        ->assertSet('technical_subjects.0.code', 'MOD-01')
        ->assertSet('course_required_documents', ['cedula_dimex'])
        ->call('createCourse')
        ->assertHasErrors('technical_subjects')
        ->set('technical_subjects', [
            ['code' => 'MOD-01', 'name' => 'Módulo I: Modelo de Negocio', 'hours' => 24, 'sessions_count' => 6],
            ['code' => 'MOD-02', 'name' => 'Módulo II: Costos y Ventas', 'hours' => 24, 'sessions_count' => 6],
        ])
        ->call('createCourse')
        ->assertHasNoErrors();

    $programa = Course::where('code', 'PROG-01')->firstOrFail();
    expect($programa->program_type)->toBe('programa');
    expect($programa->required_documents)->toBe(['cedula_dimex']);
    expect($programa->subjects()->pluck('code')->all())->toBe(['MOD-01', 'MOD-02']);
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

    // Crear una edición abierta para verificar sincronización automática de capacidad al editar el curso
    $openEdition = CourseEdition::create([
        'course_id' => $course->id,
        'code' => 'ED-SYNC-01',
        'name' => 'Cohorte Abierta',
        'shift_id' => AcademicShift::firstOrFail()->id,
        'modality_id' => AcademicModality::firstOrFail()->id,
        'start_date' => '2026-06-01',
        'end_date' => '2026-08-31',
        'max_capacity' => 20,
        'status' => 'abierta',
        'is_public_enrollment_open' => true,
        'created_by' => $admin->id,
    ]);

    // Editar características del curso: el código debe permanecer inmutable y sincronizar capacidad en ediciones abiertas
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('editCourse', $course->id)
        ->assertSet('editingCourseId', $course->id)
        ->set('course_code', 'INTENTO-CAMBIO-999')
        ->set('course_name', 'Ofimática Intermedia')
        ->set('course_default_max_capacity', 28)
        ->set('course_duration_hours', 48)
        ->set('course_description', 'Perfil actualizado del curso')
        ->call('createCourse')
        ->assertHasNoErrors()
        ->assertSet('editingCourseId', null);

    expect(Course::count())->toBe(1);
    $course->refresh();
    $openEdition->refresh();
    expect($course->code)->toBe('CUR-EDIT-01');
    expect($course->name)->toBe('Ofimática Intermedia');
    expect($course->default_max_capacity)->toBe(28);
    expect($openEdition->max_capacity)->toBe(28);
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
        ->set('edit_ed_code', 'INTENTO-CAMBIO-ED')
        ->set('edit_ed_max_capacity', 12)
        ->set('edit_ed_name', 'Cohorte 1 Actualizada')
        ->call('saveEditionEdit')
        ->assertHasNoErrors()
        ->assertSet('editingEditionId', null)
        ->call('toggleEditionPublicOpen', $edition->id);

    expect(CourseEdition::count())->toBe(1);
    $edition->refresh();
    expect($edition->code)->toBe('ED-ADMIN-01');
    expect($edition->max_capacity)->toBe(12);
    expect($edition->name)->toBe('Cohorte 1 Actualizada');
    expect($edition->is_public_enrollment_open)->toBeFalse();
    expect(\App\Models\AuditLog::where('event', 'academic.edition_updated')->where('auditable_id', (string) $edition->id)->exists())->toBeTrue();
    expect(\App\Models\AuditLog::where('event', 'academic.edition_public_toggled')->where('auditable_id', (string) $edition->id)->exists())->toBeTrue();
});

it('duplicates courses with incremental codes and copied/editable/duplicable subjects, and allows inline subject edit and duplication', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $cat = AcademicCategory::create(['code' => 'CAT-DUP', 'name' => 'Gastronomía', 'created_by' => $admin->id]);
    $source = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'TEC-PAN-001',
        'name' => 'Técnico en Panadería',
        'program_type' => 'curso_tecnico',
        'required_education_level' => 'noveno_aprobado',
        'default_max_capacity' => 20,
        'duration_hours' => 120,
        'duration_months' => 4,
        'base_enrollment_fee' => 15000,
        'base_monthly_fee' => 30000,
        'required_documents' => ['cedula_dimex', 'atestado_academico'],
        'created_by' => $admin->id,
    ]);
    $sub1 = $source->subjects()->create([
        'code' => 'MAT-01',
        'name' => 'Masas Básicas',
        'hours' => 40,
        'sessions_count' => 10,
        'order_index' => 1,
        'is_mandatory' => true,
        'is_active' => true,
    ]);

    // 1. Duplicar curso dejando campos y materias iguales (código incremental automático TEC-PAN-002)
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('startDuplicateCourse', $source->id)
        ->assertSet('duplicatingCourseId', $source->id)
        ->assertSet('dup_code', 'TEC-PAN-002')
        ->assertSet('dup_name', 'Técnico en Panadería')
        ->assertCount('dup_subjects', 1)
        ->call('saveDuplicatedCourse')
        ->assertHasNoErrors()
        ->assertSet('duplicatingCourseId', null);

    $dup1 = Course::where('code', 'TEC-PAN-002')->firstOrFail();
    expect($dup1->name)->toBe('Técnico en Panadería');
    expect($dup1->subjects()->count())->toBe(1);
    expect($dup1->subjects()->firstOrFail()->code)->toBe('MAT-01');
    expect(\App\Models\AuditLog::where('event', 'academic.course_duplicated')->where('auditable_id', (string) $dup1->id)->exists())->toBeTrue();

    // 2. Duplicar nuevamente el curso original: debe sugerir TEC-PAN-003, permitir editar nombre y duplicar materia con código incremental MAT-02
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('startDuplicateCourse', $source->id)
        ->assertSet('dup_code', 'TEC-PAN-003')
        ->set('dup_name', 'Técnico en Panadería Avanzada')
        ->set('dup_default_max_capacity', 18)
        ->call('duplicateDupSubjectRow', 0)
        ->assertCount('dup_subjects', 2)
        ->assertSet('dup_subjects.1.code', 'MAT-02')
        ->set('dup_subjects.1.name', 'Masas Laminadas')
        ->call('saveDuplicatedCourse')
        ->assertHasNoErrors();

    $dup2 = Course::where('code', 'TEC-PAN-003')->firstOrFail();
    expect($dup2->name)->toBe('Técnico en Panadería Avanzada');
    expect($dup2->default_max_capacity)->toBe(18);
    expect($dup2->subjects()->pluck('code')->all())->toBe(['MAT-01', 'MAT-02']);

    // 3. Dentro de "+ Materias", editar materia manteniendo código y duplicar materia con código incremental
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('openCourseSubjects', $source->id)
        ->call('startEditSubject', $sub1->id)
        ->set('edit_sub_name', 'Masas Artesanales')
        ->set('edit_sub_hours', 48)
        ->set('edit_sub_sessions', 12)
        ->call('saveSubjectEdit')
        ->assertHasNoErrors()
        ->call('startDuplicateSubject', $sub1->id)
        ->assertSet('dup_sub_code', 'MAT-02')
        ->assertSet('dup_sub_name', 'Masas Artesanales')
        ->set('dup_sub_name', 'Masas Dulces')
        ->call('saveDuplicatedSubject')
        ->assertHasNoErrors();

    $sub1->refresh();
    expect($sub1->code)->toBe('MAT-01');
    expect($sub1->name)->toBe('Masas Artesanales');
    expect($sub1->hours)->toBe(48);
    expect($source->subjects()->where('code', 'MAT-02')->firstOrFail()->name)->toBe('Masas Dulces');
    expect(\App\Models\AuditLog::where('event', 'academic.subject_updated')->where('auditable_id', (string) $sub1->id)->exists())->toBeTrue();
    expect(\App\Models\AuditLog::where('event', 'academic.subject_duplicated')->exists())->toBeTrue();
});

it('allows logical deletion of courses only when 0 students are enrolled, and supports manual institutional closure with student email notification', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $cat = AcademicCategory::create(['code' => 'CAT-CLS', 'name' => 'Cierre', 'created_by' => $admin->id]);

    $emptyCourse = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'VACIO-01',
        'name' => 'Curso Vacío',
        'program_type' => 'curso_corto',
        'required_education_level' => 'primaria_completa',
        'default_max_capacity' => 15,
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);

    $enrolledCourse = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'ACTIVO-01',
        'name' => 'Curso Con Alumnas',
        'program_type' => 'curso_corto',
        'required_education_level' => 'primaria_completa',
        'default_max_capacity' => 15,
        'required_documents' => ['cedula_dimex'],
        'created_by' => $admin->id,
    ]);

    $edition = CourseEdition::create([
        'course_id' => $enrolledCourse->id,
        'code' => 'ED-ACTIVO-01',
        'name' => 'Cohorte Única',
        'shift_id' => AcademicShift::firstOrFail()->id,
        'modality_id' => AcademicModality::firstOrFail()->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-09-30',
        'max_capacity' => 15,
        'status' => 'abierta',
        'is_public_enrollment_open' => true,
        'created_by' => $admin->id,
    ]);

    $student = \App\Models\Student::create([
        'student_code' => \App\Models\Student::nextCode(),
        'first_name' => 'Silvia',
        'last_name' => 'Gómez',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-5500-0001',
        'email' => 'silvia.gomez@correo.cr',
        'phone' => '8800-5501',
        'gender' => 'femenino',
    ]);

    \App\Models\Enrollment::create([
        'enrollment_code' => \App\Models\Enrollment::nextCode(),
        'student_id' => $student->id,
        'course_edition_id' => $edition->id,
        'source' => 'presencial',
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'documents_complete' => true,
        'created_by' => $admin->id,
    ]);

    // 1. Curso sin alumnas inscritas SÍ se puede eliminar (archivado lógico auditado, desaparece de la lista)
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('startDeleteCourse', $emptyCourse->id)
        ->assertSet('deletingCourseId', $emptyCourse->id)
        ->set('course_delete_reason', 'Curso creado de prueba sin matrícula')
        ->call('confirmDeleteCourse')
        ->assertHasNoErrors()
        ->assertDontSee('Curso Vacío');

    $emptyCourse->refresh();
    expect($emptyCourse->archived_at)->not->toBeNull();
    expect($emptyCourse->is_active)->toBeFalse();
    expect(\App\Models\AuditLog::where('event', 'academic.course_deleted_empty')->where('auditable_id', (string) $emptyCourse->id)->exists())->toBeTrue();

    // 2. Curso CON alumnas inscritas NO se puede eliminar
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('startDeleteCourse', $enrolledCourse->id)
        ->assertHasErrors('course_delete_reason')
        ->assertSet('deletingCourseId', null);

    expect($enrolledCourse->fresh()->archived_at)->toBeNull();

    // 3. Cerrar Curso en catálogo exige picklist y NO envía correos a alumnas (las notificaciones van al cerrar cada cohorte)
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('startCloseCourse', $enrolledCourse->id)
        ->assertSet('closingCourseId', $enrolledCourse->id)
        ->call('confirmCloseCourse')
        ->assertHasErrors('course_closure_reason_code')
        ->set('course_closure_reason_code', 'actualizacion_curricular')
        ->set('course_closure_reason', 'Renovación de malla para próximo año')
        ->call('confirmCloseCourse')
        ->assertHasNoErrors()
        ->assertSet('closingCourseId', null);

    $enrolledCourse->refresh();
    $edition->refresh();
    expect($enrolledCourse->isClosed())->toBeTrue();
    expect($enrolledCourse->closure_reason)->toContain('Actualización curricular del curso o programa');
    expect($enrolledCourse->is_active)->toBeFalse();
    expect($edition->is_public_enrollment_open)->toBeFalse();
    expect($edition->status)->toBe('abierta');
    expect(\App\Models\AuditLog::where('event', 'academic.course_closed_institutional')->where('auditable_id', (string) $enrolledCourse->id)->exists())->toBeTrue();
    \Illuminate\Support\Facades\Mail::assertNothingSent();

    // 4. Cerrar Cohorte exige picklist cuando es cierre/cancelación y SÍ envía correo a la alumna de esa cohorte con horario y oficinas
    $cohortComponent = Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('startCloseEdition', $edition->id)
        ->assertSet('closingEditionId', $edition->id);

    expect($cohortComponent->get('edition_closure_message'))->toContain('oferta académica y laboral');
    expect($cohortComponent->get('edition_closure_message'))->toContain('oficinas del Centro de Capacitación');

    $cohortComponent
        ->call('confirmCloseEdition')
        ->assertHasErrors('edition_closure_reason_code')
        ->set('edition_closure_reason_code', 'reprogramacion_calendario')
        ->set('edition_closure_notes', 'Se abrirá nuevamente en agosto')
        ->set('edition_closure_notify_students', true)
        ->call('confirmCloseEdition')
        ->assertHasNoErrors()
        ->assertSet('closingEditionId', null);

    $edition->refresh();
    expect($edition->status)->toBe('cancelada');
    expect($edition->closed_at)->not->toBeNull();
    expect($edition->closure_reason)->toContain('Reprogramación de calendario académico');
    expect(\App\Models\AuditLog::where('event', 'academic.edition_closed')->where('auditable_id', (string) $edition->id)->exists())->toBeTrue();

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\CourseClosedInstitutionalMail::class, function ($mail) {
        $rendered = $mail->render();

        return $mail->hasTo('silvia.gomez@correo.cr')
            && str_contains($rendered, 'oferta académica y laboral')
            && str_contains($rendered, 'oficinas del Centro de Capacitación')
            && str_contains($rendered, 'Horario de atención');
    });

    // 5. Reabrir la cohorte cerrada la devuelve a estado abierta
    Livewire::actingAs($admin)
        ->test(AcademicPage::class)
        ->call('reopenEdition', $edition->id)
        ->assertHasNoErrors();

    expect($edition->fresh()->status)->toBe('abierta');
    expect($edition->fresh()->closed_at)->toBeNull();
});




