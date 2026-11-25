<?php

namespace Database\Seeders;

use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\CourseSubject;
use App\Models\CrProvince;
use App\Models\EditionSchedule;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder separado EXCLUSIVAMENTE para entornos de demostración o QA manual.
 * NUNCA se ejecuta desde DatabaseSeeder (arranque limpio).
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $superAdmin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

        // Usuarios de prueba por rol
        $demoUsers = [
            ['direccion@cecap.or.cr', 'María Elena Vargas', '1-1010-2020', '8811-1001', 'direccion'],
            ['secretaria@cecap.or.cr', 'Sofía Quesada Mora', '1-1020-3030', '8811-1002', 'secretaria'],
            ['caja@cecap.or.cr', 'Laura Jiménez Solís', '1-1030-4040', '8811-1003', 'administracion_caja'],
            ['coordinacion@cecap.or.cr', 'Patricia Herrera Campos', '1-1040-5050', '8811-1004', 'coordinacion_academica'],
            ['coord.gastronomia@cecap.or.cr', 'Andrea Rojas Chavarría', '1-1050-6060', '8811-1005', 'coordinador_categoria'],
            ['profesora@cecap.or.cr', 'Gabriela Castro Núñez', '1-1060-7070', '8811-1006', 'profesora'],
        ];

        $usersByEmail = [];
        foreach ($demoUsers as [$email, $name, $idNum, $phone, $role]) {
            $u = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'identification_type' => 'cedula_nacional',
                    'identification_number' => $idNum,
                    'phone' => $phone,
                    'gender' => 'femenino',
                    'password' => Hash::make('DemoCeCap2026!'),
                    'must_change_password' => false,
                    'password_changed_at' => now(),
                    'is_active' => true,
                    'created_by' => $superAdmin->id,
                ]
            );
            $u->syncRoles([$role]);
            $usersByEmail[$email] = $u;
        }

        // Categorías
        $catGastro = AcademicCategory::firstOrCreate(['code' => 'CAT-GASTRO'], ['name' => 'Gastronomía y Manipulación', 'description' => 'Panadería, repostería y cocina profesional', 'created_by' => $superAdmin->id]);
        $catTech = AcademicCategory::firstOrCreate(['code' => 'CAT-TECH'], ['name' => 'Tecnología y Administración', 'description' => 'Ofimática, contabilidad y soporte', 'created_by' => $superAdmin->id]);

        // Asignar categoría al coordinador de categoría
        $usersByEmail['coord.gastronomia@cecap.or.cr']->academicCategories()->syncWithoutDetaching([
            $catGastro->id => ['is_active' => true, 'assigned_by' => $superAdmin->id],
        ]);

        // Aulas
        $aula1 = Classroom::firstOrCreate(['code' => 'AULA-101'], ['name' => 'Laboratorio Gastronómico 1', 'capacity' => 20, 'location' => 'Planta Baja']);
        $aula2 = Classroom::firstOrCreate(['code' => 'AULA-201'], ['name' => 'Laboratorio Cómputo 1', 'capacity' => 25, 'location' => 'Segundo Piso']);

        // Cursos (1 Curso Corto + 1 Curso Técnico con materias obligatorias)
        $cursoCorto = Course::firstOrCreate(
            ['code' => 'CUR-PAN-01'],
            [
                'academic_category_id' => $catGastro->id,
                'name' => 'Panadería Artesanal Emprendedora',
                'program_type' => 'curso_corto',
                'description' => 'Técnicas fundamentales de panificación y costeo para emprendedoras.',
                'required_education_level' => 'primaria_completa',
                'default_max_capacity' => 18,
                'duration_hours' => 40,
                'duration_months' => 2,
                'base_enrollment_fee' => 15000,
                'base_monthly_fee' => 25000,
                'required_documents' => Course::defaultRequiredDocumentsFor('curso_corto'),
                'min_passing_grade' => 70,
                'min_attendance_percent' => 80,
                'created_by' => $superAdmin->id,
            ]
        );

        $cursoTecnico = Course::firstOrCreate(
            ['code' => 'TEC-ADM-01'],
            [
                'academic_category_id' => $catTech->id,
                'name' => 'Técnico en Asistencia Administrativa y Contable',
                'program_type' => 'curso_tecnico',
                'description' => 'Formación técnica integral en gestión administrativa, facturación CR y planilla.',
                'required_education_level' => 'noveno_aprobado',
                'default_max_capacity' => 24,
                'duration_hours' => 360,
                'duration_months' => 6,
                'base_enrollment_fee' => 25000,
                'base_monthly_fee' => 45000,
                'required_documents' => Course::defaultRequiredDocumentsFor('curso_tecnico'),
                'min_passing_grade' => 70,
                'min_attendance_percent' => 80,
                'created_by' => $superAdmin->id,
            ]
        );

        CourseSubject::firstOrCreate(['course_id' => $cursoTecnico->id, 'code' => 'MAT-01'], ['name' => 'Ofimática Empresarial', 'hours' => 120, 'order_index' => 1]);
        CourseSubject::firstOrCreate(['course_id' => $cursoTecnico->id, 'code' => 'MAT-02'], ['name' => 'Contabilidad Básica CR', 'hours' => 120, 'order_index' => 2]);
        CourseSubject::firstOrCreate(['course_id' => $cursoTecnico->id, 'code' => 'MAT-03'], ['name' => 'Legislación Laboral y Tributaria', 'hours' => 120, 'order_index' => 3]);

        $matutino = AcademicShift::where('code', 'matutino')->firstOrFail();
        $vespertino = AcademicShift::where('code', 'vespertino')->firstOrFail();
        $presencial = AcademicModality::where('code', 'presencial')->firstOrFail();

        $ed1 = CourseEdition::firstOrCreate(
            ['code' => 'ED-PAN-2026-01'],
            [
                'course_id' => $cursoCorto->id,
                'name' => 'Panadería Artesanal — Cohorte I 2026',
                'shift_id' => $matutino->id,
                'modality_id' => $presencial->id,
                'classroom_id' => $aula1->id,
                'teacher_id' => $usersByEmail['profesora@cecap.or.cr']->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addMonths(2)->toDateString(),
                'max_capacity' => $cursoCorto->default_max_capacity,
                'enrollment_fee' => $cursoCorto->base_enrollment_fee,
                'monthly_fee' => $cursoCorto->base_monthly_fee,
                'installments_count' => $cursoCorto->duration_months,
                'status' => 'abierta',
                'is_public_enrollment_open' => true,
                'created_by' => $superAdmin->id,
            ]
        );

        $ed2 = CourseEdition::firstOrCreate(
            ['code' => 'ED-TEC-ADM-2026-01'],
            [
                'course_id' => $cursoTecnico->id,
                'name' => 'Técnico Administrativo — Cohorte I 2026',
                'shift_id' => $vespertino->id,
                'modality_id' => $presencial->id,
                'classroom_id' => $aula2->id,
                'teacher_id' => $usersByEmail['profesora@cecap.or.cr']->id,
                'start_date' => now()->addDays(15)->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'max_capacity' => $cursoTecnico->default_max_capacity,
                'enrollment_fee' => $cursoTecnico->base_enrollment_fee,
                'monthly_fee' => $cursoTecnico->base_monthly_fee,
                'installments_count' => $cursoTecnico->duration_months,
                'status' => 'abierta',
                'is_public_enrollment_open' => true,
                'created_by' => $superAdmin->id,
            ]
        );

        if ($ed1->schedules()->count() === 0) {
            EditionSchedule::create([
                'course_edition_id' => $ed1->id,
                'classroom_id' => $aula1->id,
                'teacher_id' => $usersByEmail['profesora@cecap.or.cr']->id,
                'day_of_week' => 2,
                'start_minute' => 480,
                'end_minute' => 660,
                'start_date' => $ed1->start_date,
                'end_date' => $ed1->end_date,
            ]);
        }

        if ($ed2->schedules()->count() === 0) {
            EditionSchedule::create([
                'course_edition_id' => $ed2->id,
                'classroom_id' => $aula2->id,
                'teacher_id' => $usersByEmail['profesora@cecap.or.cr']->id,
                'day_of_week' => 2,
                'start_minute' => 840,
                'end_minute' => 1020,
                'start_date' => $ed2->start_date,
                'end_date' => $ed2->end_date,
            ]);
        }

        // Personal Demo
        StaffMember::firstOrCreate(
            ['identification_number' => '1-1060-7070'],
            [
                'user_id' => $usersByEmail['profesora@cecap.or.cr']->id,
                'staff_code' => 'PER-2026-0001',
                'staff_role_type' => 'teacher',
                'first_name' => 'Gabriela',
                'last_name' => 'Castro',
                'second_last_name' => 'Núñez',
                'email' => 'profesora@cecap.or.cr',
                'phone' => '8811-1006',
                'specialty' => 'Gastronomía y Gestión Administrativa',
                'academic_degree' => 'Licenciatura en Administración Educativa',
                'hiring_regime' => 'planilla_laboral',
                'created_by' => $superAdmin->id,
            ]
        );

        // Alumna Demo
        $sj = CrProvince::where('code', '01')->first();
        $student = Student::firstOrCreate(
            ['identification_number' => '1-1555-0888'],
            [
                'student_code' => 'EST-2026-00001',
                'first_name' => 'Valeria',
                'last_name' => 'Méndez',
                'second_last_name' => 'Salas',
                'email' => 'valeria.mendez@ejemplo.cr',
                'phone' => '8700-1122',
                'gender' => 'femenino',
                'education_level' => 'bachillerato_secundaria',
                'province_id' => $sj?->id,
                'created_by' => $superAdmin->id,
            ]
        );

        if ($student->enrollments()->count() === 0) {
            app(EnrollmentService::class)->createPresencialEnrollment(
                student: $student,
                edition: $ed1,
                submittedDocuments: ['cedula_dimex'],
                confirmImmediately: true
            );
        }
    }
}
