<?php

use App\Livewire\QueriesPage;
use App\Livewire\TrackingPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('records attendance per subject and queries attendance by course, subject, and student', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    $cat = AcademicCategory::create(['code' => 'CAT-ASIST', 'name' => 'Gastronomía', 'created_by' => $admin->id]);
    $shift = AcademicShift::create(['code' => 'MAN-ASIST', 'name' => 'Mañana', 'start_time' => '08:00', 'end_time' => '12:00']);
    $mod = AcademicModality::create(['code' => 'PRES-ASIST', 'name' => 'Presencial']);

    $courseA = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'GAST-101',
        'name' => 'Panadería Artesanal',
        'program_type' => 'tecnico',
        'required_documents' => ['cedula_dimex'],
        'min_attendance_percent' => 80,
        'created_by' => $admin->id,
    ]);
    $courseB = Course::create([
        'academic_category_id' => $cat->id,
        'code' => 'CONT-101',
        'name' => 'Contabilidad Básica',
        'program_type' => 'curso_corto',
        'required_documents' => ['cedula_dimex'],
        'min_attendance_percent' => 80,
        'created_by' => $admin->id,
    ]);

    $subjectMasas = CourseSubject::create([
        'course_id' => $courseA->id,
        'code' => 'PAN-M1',
        'name' => 'Masas Fermentadas',
        'hours' => 20,
    ]);
    $subjectCostos = CourseSubject::create([
        'course_id' => $courseA->id,
        'code' => 'PAN-M2',
        'name' => 'Costos y Recetas',
        'hours' => 15,
    ]);

    $editionA = CourseEdition::create([
        'course_id' => $courseA->id,
        'code' => 'GAST-2026-A',
        'name' => 'Cohorte I 2026',
        'shift_id' => $shift->id,
        'modality_id' => $mod->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
        'max_capacity' => 20,
        'status' => 'abierta',
        'created_by' => $admin->id,
    ]);
    $editionB = CourseEdition::create([
        'course_id' => $courseB->id,
        'code' => 'CONT-2026-A',
        'name' => 'Cohorte Contabilidad',
        'shift_id' => $shift->id,
        'modality_id' => $mod->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-06-30',
        'max_capacity' => 20,
        'status' => 'abierta',
        'created_by' => $admin->id,
    ]);

    $stAna = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Ana',
        'last_name' => 'Ramírez',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-4111-2222',
        'email' => 'ana.ramirez@cecap.or.cr',
        'phone' => '8811-2222',
        'gender' => 'femenino',
        'created_by' => $admin->id,
    ]);
    $stLucia = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Lucía',
        'last_name' => 'Vargas',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-4333-4444',
        'email' => 'lucia.vargas@cecap.or.cr',
        'phone' => '8833-4444',
        'gender' => 'femenino',
        'created_by' => $admin->id,
    ]);

    $enrAnaA = Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $stAna->id,
        'course_edition_id' => $editionA->id,
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);
    $enrLuciaA = Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $stLucia->id,
        'course_edition_id' => $editionA->id,
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);
    $enrLuciaB = Enrollment::create([
        'enrollment_code' => Enrollment::nextCode(),
        'student_id' => $stLucia->id,
        'course_edition_id' => $editionB->id,
        'status' => 'confirmada',
        'required_documents_snapshot' => ['cedula_dimex'],
        'submitted_documents' => ['cedula_dimex'],
        'channel' => 'presencial',
        'created_by' => $admin->id,
    ]);

    // Registrar asistencia para Materia 1 (Masas Fermentadas)
    Livewire::test(TrackingPage::class)
        ->set('selected_edition_id', $editionA->id)
        ->set('attendance_subject_id', $subjectMasas->id)
        ->set('session_date', '2026-03-10')
        ->call('setAttendanceStatus', $enrAnaA->id, 'presente')
        ->call('setAttendanceStatus', $enrLuciaA->id, 'ausente')
        ->call('saveAttendance')
        ->assertHasNoErrors();

    // Registrar asistencia para Materia 2 (Costos y Recetas) el mismo día sin sobrescribir Materia 1
    Livewire::test(TrackingPage::class)
        ->set('selected_edition_id', $editionA->id)
        ->set('attendance_subject_id', $subjectCostos->id)
        ->set('session_date', '2026-03-10')
        ->call('setAttendanceStatus', $enrAnaA->id, 'tardia')
        ->call('setAttendanceStatus', $enrLuciaA->id, 'presente')
        ->call('saveAttendance')
        ->assertHasNoErrors();

    // Registrar asistencia general en Curso B
    Livewire::test(TrackingPage::class)
        ->set('selected_edition_id', $editionB->id)
        ->set('session_date', '2026-03-12')
        ->call('setAttendanceStatus', $enrLuciaB->id, 'justificada')
        ->call('saveAttendance')
        ->assertHasNoErrors();

    expect(AttendanceRecord::where('is_current', true)->count())->toBe(5);

    // 1. Consulta filtrada por Curso (Contabilidad Básica -> solo Lucía Vargas)
    $byCourse = Livewire::test(TrackingPage::class)
        ->set('tab', 'attendance_report')
        ->set('report_course_id', $courseB->id);
    expect($byCourse->viewData('reportRecords')->count())->toBe(1);
    expect($byCourse->viewData('reportRecords')->first()->student_id)->toBe($stLucia->id);

    // 2. Consulta filtrada por Materia (Masas Fermentadas -> 2 registros de esa materia)
    $bySubject = Livewire::test(TrackingPage::class)
        ->set('tab', 'attendance_report')
        ->set('report_course_id', $courseA->id)
        ->set('report_subject_id', (string) $subjectMasas->id);
    expect($bySubject->viewData('reportRecords')->count())->toBe(2);
    expect($bySubject->viewData('reportRecords')->pluck('course_subject_id')->unique()->all())->toBe([$subjectMasas->id]);

    // 3. Consulta filtrada por Estudiante (búsqueda por nombre -> 2 registros de Ana Ramírez)
    $byStudent = Livewire::test(TrackingPage::class)
        ->set('tab', 'attendance_report')
        ->set('report_student_search', 'Ana Ramírez');
    expect($byStudent->viewData('reportRecords')->count())->toBe(2);
    expect($byStudent->viewData('reportRecords')->pluck('student_id')->unique()->all())->toBe([$stAna->id]);

    // 4. Expediente Alumnas muestra el detalle fecha por fecha con la materia
    Livewire::test(QueriesPage::class)
        ->set('selected_student_id', $stAna->id)
        ->assertSee('10/03/2026')
        ->assertSee('Masas Fermentadas')
        ->assertSee('Costos y Recetas');

    // 5. Descarga Excel (.xlsx) del Resumen por Estudiante, Curso y Materia respetando filtros
    $excelResponse = $this->actingAs($admin)->get(route('seguimiento.attendance.excel', [
        'course_id' => $courseA->id,
        'subject_id' => (string) $subjectMasas->id,
    ]));

    $excelResponse->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmpXlsx = tempnam(sys_get_temp_dir(), 'test_xlsx_');
    file_put_contents($tmpXlsx, $excelResponse->getContent());
    $zip = new ZipArchive();
    expect($zip->open($tmpXlsx))->toBeTrue();
    $sheet1 = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $sheet2 = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
    $zip->close();
    @unlink($tmpXlsx);

    expect($sheet1)->toContain('Ana Ramírez');
    expect($sheet1)->toContain('Lucía Vargas');
    expect($sheet1)->toContain('Masas Fermentadas');
    expect($sheet1)->not->toContain('Costos y Recetas');
    expect($sheet2)->toContain('10/03/2026');
});
