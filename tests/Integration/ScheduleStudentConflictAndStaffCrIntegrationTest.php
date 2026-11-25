<?php

use App\Livewire\StaffPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\EditionSchedule;
use App\Models\StaffContract;
use App\Models\StaffMember;
use App\Models\StaffPayment;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
});

it('prevents a student from enrolling in two overlapping course editions', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    $cat = AcademicCategory::create(['code' => 'CAT-SCH', 'name' => 'Horarios', 'created_by' => $admin->id]);
    $course = Course::create([
        'academic_category_id' => $cat->id, 'code' => 'SCH-01', 'name' => 'Curso A',
        'program_type' => 'curso_corto', 'required_education_level' => 'primaria_completa',
        'default_max_capacity' => 20, 'required_documents' => ['cedula_dimex'], 'created_by' => $admin->id,
    ]);

    $room1 = Classroom::create(['code' => 'R1', 'name' => 'Aula R1', 'capacity' => 20]);
    $room2 = Classroom::create(['code' => 'R2', 'name' => 'Aula R2', 'capacity' => 20]);

    $ed1 = CourseEdition::create([
        'course_id' => $course->id, 'code' => 'ED-S1', 'name' => 'Edición S1',
        'shift_id' => AcademicShift::firstOrFail()->id, 'modality_id' => AcademicModality::firstOrFail()->id,
        'classroom_id' => $room1->id, 'start_date' => '2026-07-01', 'end_date' => '2026-09-30',
        'max_capacity' => 20, 'created_by' => $admin->id,
    ]);
    $ed2 = CourseEdition::create([
        'course_id' => $course->id, 'code' => 'ED-S2', 'name' => 'Edición S2',
        'shift_id' => AcademicShift::firstOrFail()->id, 'modality_id' => AcademicModality::firstOrFail()->id,
        'classroom_id' => $room2->id, 'start_date' => '2026-07-15', 'end_date' => '2026-09-30',
        'max_capacity' => 20, 'created_by' => $admin->id,
    ]);

    EditionSchedule::create([
        'course_edition_id' => $ed1->id, 'classroom_id' => $room1->id,
        'day_of_week' => 4, 'start_minute' => 540, 'end_minute' => 660,
        'start_date' => '2026-07-01', 'end_date' => '2026-09-30', 'is_active' => true,
    ]);
    EditionSchedule::create([
        'course_edition_id' => $ed2->id, 'classroom_id' => $room2->id,
        'day_of_week' => 4, 'start_minute' => 600, 'end_minute' => 720,
        'start_date' => '2026-07-15', 'end_date' => '2026-09-30', 'is_active' => true,
    ]);

    $student = Student::create([
        'student_code' => Student::nextCode(), 'first_name' => 'Natalia', 'last_name' => 'Gómez',
        'identification_type' => 'cedula_nacional', 'identification_number' => '1-9900-1122',
        'email' => 'natalia.gomez@correo.cr', 'phone' => '8877-6655', 'gender' => 'femenino',
    ]);

    $service = app(EnrollmentService::class);
    $service->createPresencialEnrollment($student, $ed1, ['cedula_dimex'], true);

    expect(fn () => $service->createPresencialEnrollment($student, $ed2, ['cedula_dimex'], true))
        ->toThrow(ValidationException::class);
});

it('integrates StaffPage editable & uploaded PDF contracts, CR payroll vouchers, and CSV BOM export', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(StaffPage::class)
        ->set('staff_role_type', 'both')
        ->set('first_name', 'Silvia')
        ->set('last_name', 'Cordero')
        ->set('identification_number', '1-7711-8822')
        ->set('email', 'silvia.cordero@cecap.or.cr')
        ->set('phone', '8800-7711')
        ->set('credentials_file', UploadedFile::fake()->create('atestados_silvia.pdf', 180, 'application/pdf'))
        ->call('createStaff')
        ->assertHasNoErrors();

    $staff = StaffMember::where('identification_number', '1-7711-8822')->firstOrFail();

    // Contrato PDF adjunto bajo Servicios Profesionales
    Livewire::actingAs($admin)
        ->test(StaffPage::class)
        ->set('contract_staff_id', $staff->id)
        ->set('contract_regime', 'servicios_profesionales')
        ->set('contract_mode', 'pdf_adjunto')
        ->set('contract_title', 'Contrato Servicios Docentes Especializados')
        ->set('contract_start_date', now()->toDateString())
        ->set('contract_gross', 350000)
        ->set('contract_iva_rate', 2.00)
        ->set('contract_pdf_file', UploadedFile::fake()->create('contrato_firmado.pdf', 200, 'application/pdf'))
        ->call('createContract')
        ->assertHasNoErrors();

    $contract = StaffContract::where('staff_member_id', $staff->id)->firstOrFail();
    expect($contract->attachment_id)->not->toBeNull();

    // Pago con clave Hacienda 50 dígitos
    Livewire::actingAs($admin)
        ->test(StaffPage::class)
        ->set('pay_contract_id', $contract->id)
        ->set('pay_period_label', 'Noviembre 2026')
        ->set('pay_date', now()->toDateString())
        ->set('pay_gross_amount', 350000)
        ->set('pay_hacienda_key', str_repeat('9', 50))
        ->call('recordPayment')
        ->assertHasNoErrors();

    $payment = StaffPayment::where('staff_contract_id', $contract->id)->firstOrFail();
    expect((float) $payment->net_amount)->toBe(357000.0);

    $csv = $this->actingAs($admin)->get('/direccion/exportar/staff_payments')->assertOk()->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF");
    expect($csv)->toContain($payment->voucher_number);
});
