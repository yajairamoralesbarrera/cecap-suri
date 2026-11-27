<?php

use App\Livewire\StaffPage;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\GradeRecord;
use App\Models\SoftSkillRecord;
use App\Models\StaffContract;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use App\Services\GraduationService;
use App\Services\StaffPayrollService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
});

it('handles finance scholarships preserving gross amount, REC receipts, audited reversals, and graduation with QR verification', function () {
    $superAdmin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $coord = User::create([
        'name' => 'Coordinadora',
        'email' => 'coord@cecap.or.cr',
        'identification_number' => '1-7000-0001',
        'phone' => '8800-7001',
        'password' => Hash::make('CoordPass2026!'),
        'must_change_password' => false,
    ]);
    $coord->assignRole('coordinacion_academica');

    $this->actingAs($superAdmin);

    $cat = AcademicCategory::create(['code' => 'CAT-FIN', 'name' => 'Finanzas Test', 'created_by' => $superAdmin->id]);
    $course = Course::create([
        'academic_category_id' => $cat->id, 'code' => 'FIN-01', 'name' => 'Emprendimiento',
        'program_type' => 'curso_corto', 'required_education_level' => 'primaria_completa',
        'default_max_capacity' => 20, 'base_enrollment_fee' => 20000, 'base_monthly_fee' => 40000,
        'duration_months' => 1, 'required_documents' => ['cedula_dimex'], 'created_by' => $superAdmin->id,
    ]);
    $edition = CourseEdition::create([
        'course_id' => $course->id, 'code' => 'ED-FIN-1', 'name' => 'Edición Finanzas',
        'shift_id' => AcademicShift::firstOrFail()->id, 'modality_id' => AcademicModality::firstOrFail()->id,
        'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
        'max_capacity' => 20, 'enrollment_fee' => 20000, 'monthly_fee' => 40000, 'installments_count' => 1,
        'status' => 'abierta', 'created_by' => $superAdmin->id,
    ]);
    $student = Student::create([
        'student_code' => Student::nextCode(), 'first_name' => 'Sofía', 'last_name' => 'Salas',
        'identification_type' => 'cedula_nacional', 'identification_number' => '1-5000-6000',
        'email' => 'sofia.salas@correo.cr', 'phone' => '8855-6677', 'gender' => 'femenino',
        'education_level' => 'bachillerato_secundaria',
    ]);

    $enrollment = app(EnrollmentService::class)->createPresencialEnrollment($student, $edition, ['cedula_dimex'], true);
    $finance = app(FinanceService::class);

    // Aplicar beca 50% SIN alterar monto bruto
    $finance->applyScholarship($enrollment, 'Beca 50%', 'porcentaje', 50);
    $charges = $enrollment->feeCharges()->orderBy('id')->get();
    expect((float) $charges[0]->gross_amount)->toBe(20000.0);
    expect((float) $charges[0]->scholarship_discount)->toBe(10000.0);
    expect((float) $charges[0]->net_amount)->toBe(10000.0);

    // Abono parcial + múltiple en un recibo REC-YYYY-NNNNN
    $receipt = $finance->recordPayment($student, [
        $charges[0]->id => 10000,
        $charges[1]->id => 10000,
    ], 'sinpe_movil', null, $enrollment->id, 'SINPE-12345');

    expect($receipt->receipt_number)->toStartWith('REC-'.now()->format('Y').'-');
    $this->get(route('pdf.recibo', $receipt))->assertOk()->assertHeader('content-type', 'application/pdf');

    // Reverso auditado restaura saldos
    $reversal = $finance->reverseReceipt($receipt, 'Error de digitación en monto');
    expect($receipt->fresh()->status)->toBe('reversado');
    expect((float) $charges[0]->fresh()->paid_amount)->toBe(0.0);
    expect((float) $reversal->total_amount)->toBe(-20000.0);

    // Registrar nota y habilidad blanda
    GradeRecord::create([
        'enrollment_id' => $enrollment->id, 'student_id' => $student->id,
        'evaluation_name' => 'Proyecto Final', 'score' => 92, 'weight_percent' => 100,
        'version' => 1, 'is_current' => true, 'recorded_by' => $superAdmin->id,
    ]);
    SoftSkillRecord::create([
        'student_id' => $student->id, 'enrollment_id' => $enrollment->id,
        'skill_name' => 'Liderazgo', 'level' => 'destacado', 'score' => 95,
        'evaluated_on' => now()->toDateString(), 'evaluated_by' => $superAdmin->id,
    ]);
    $this->get(route('pdf.habilidades', $student))->assertOk()->assertHeader('content-type', 'application/pdf');

    // Graduación con saldo pendiente bloquea excepción a Coordinación Académica (solo Dirección/SuperAdmin)
    $gradService = app(GraduationService::class);
    expect(fn () => $gradService->issueGraduation($enrollment, 'I', '01', '001', now()->toDateString(), null, true, 'Excepción', $coord))
        ->toThrow(ValidationException::class);

    // SuperAdmin / Dirección SÍ autoriza excepción de solvencia y emite Acta + Diploma QR
    $grad = $gradService->issueGraduation($enrollment, 'I', '01', '001', now()->toDateString(), null, true, 'Arreglo de pago aprobado por Dirección', $superAdmin);
    expect($grad->solvency_exception_authorized)->toBeTrue();

    $this->get(route('pdf.acta', $grad))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('pdf.diploma', $grad))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('diploma.verify', $grad->verification_token))
        ->assertOk()
        ->assertSee('CERTIFICADO AUTÉNTICO Y VERIFICADO')
        ->assertSee($student->full_name);
});

it('enforces Costa Rica staff legal separation (Planilla vs Servicios Profesionales 50-digit Hacienda key) and mandatory teacher credentials', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $this->actingAs($admin);

    // Profesora SIN atestados profesionales ni documento de identificación debe fallar
    Livewire::test(StaffPage::class)
        ->set('staff_role_type', 'teacher')
        ->set('first_name', 'Karla')
        ->set('last_name', 'Ureña')
        ->set('identification_number', '1-9000-1111')
        ->set('birth_date', '1988-06-15')
        ->set('email', 'karla.urena@cecap.or.cr')
        ->set('phone', '8899-0011')
        ->call('createStaff')
        ->assertHasErrors(['credentials_file', 'identification_file']);

    // Profesora CON fecha de nacimiento, documento de identificación, atestados y contrato firmado pasa
    Livewire::test(StaffPage::class)
        ->set('staff_role_type', 'teacher')
        ->set('first_name', 'Karla')
        ->set('last_name', 'Ureña')
        ->set('identification_number', '1-9000-1111')
        ->set('birth_date', '1988-06-15')
        ->set('email', 'karla.urena@cecap.or.cr')
        ->set('phone', '8899-0011')
        ->set('academic_degree', 'Licenciatura en Educación Técnica')
        ->set('specialty_1', 'Gastronomía')
        ->set('specialty_2', 'Contabilidad')
        ->set('specialty_3', 'Corte y Confección')
        ->set('identification_file', UploadedFile::fake()->create('cedula_karla.pdf', 100, 'application/pdf'))
        ->set('credentials_files', [
            UploadedFile::fake()->create('titulo_docente.pdf', 150, 'application/pdf'),
            UploadedFile::fake()->create('certificacion_ina.pdf', 120, 'application/pdf'),
        ])
        ->set('signed_contract_file', UploadedFile::fake()->create('contrato_vigente_karla.pdf', 180, 'application/pdf'))
        ->call('createStaff')
        ->assertHasNoErrors();

    $member = StaffMember::where('identification_number', '1-9000-1111')->firstOrFail();
    expect($member->birth_date?->toDateString())->toBe('1988-06-15');
    expect($member->staff_role_type_label)->toBe('Profesora');
    expect($member->hiring_regime_label)->toBe('Planilla Laboral CR');
    expect($member->academic_degree)->toBe('Licenciatura en Educación Técnica');
    expect($member->specialties)->toBe(['Gastronomía', 'Contabilidad', 'Corte y Confección']);
    expect($member->specialties_list)->toBe(['Gastronomía', 'Contabilidad', 'Corte y Confección']);
    expect($member->specialty)->toBe('Gastronomía · Contabilidad · Corte y Confección');
    expect($member->attachments()->where('document_type', 'cedula_dimex')->where('status', 'activo')->count())->toBe(1);
    expect($member->attachments()->where('document_type', 'atestado_profesional')->where('status', 'activo')->count())->toBe(2);
    expect($member->attachments()->where('document_type', 'contrato_pdf')->where('status', 'activo')->count())->toBe(1);

    // Agregar atestado adicional inline sin reemplazar los 2 anteriores
    Livewire::test(StaffPage::class)
        ->call('startAttachCredentials', $member->id)
        ->set('extra_credentials_files', [
            UploadedFile::fake()->create('colegiatura.pdf', 90, 'application/pdf'),
        ])
        ->set('extra_credentials_notes', 'Colegiatura vigente')
        ->call('uploadExtraCredentials')
        ->assertHasNoErrors();

    expect($member->attachments()->where('document_type', 'atestado_profesional')->where('status', 'activo')->count())->toBe(3);

    // Segundo colaborador Administrativo en Servicios Profesionales para probar consulta por nombre y tipo en español
    $elena = StaffMember::create([
        'staff_code' => StaffMember::nextCode(),
        'staff_role_type' => 'administrative',
        'first_name' => 'Elena',
        'last_name' => 'Salas',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-9000-2222',
        'birth_date' => '1991-04-10',
        'email' => 'elena.salas@cecap.or.cr',
        'phone' => '8877-6655',
        'hiring_regime' => 'servicios_profesionales',
        'status' => 'activo',
        'created_by' => $admin->id,
    ]);

    Livewire::test(StaffPage::class)
        ->assertSee('Profesora')
        ->assertSee('Administrativo')
        ->set('filter_role_type', 'administrative')
        ->assertSee('Elena Salas')
        ->assertDontSee('Karla Ureña')
        ->set('filter_role_type', '')
        ->set('search', 'Karla')
        ->assertSee('Karla Ureña')
        ->assertDontSee('Elena Salas');

    // Edición inline auditada de colaboradora existente (datos + grado + especialidades + atestado adicional)
    Livewire::test(StaffPage::class)
        ->call('startEditStaff', $member->id)
        ->set('edit_staff_role_type', 'both')
        ->set('edit_academic_degree', 'Maestría en Educación Técnica')
        ->set('edit_specialty_1', 'Gastronomía')
        ->set('edit_specialty_2', 'Administración')
        ->set('edit_specialty_3', '')
        ->set('edit_phone', '8800-1122')
        ->set('edit_credentials_files', [
            UploadedFile::fake()->create('maestria.pdf', 110, 'application/pdf'),
        ])
        ->call('saveStaffEdit')
        ->assertHasNoErrors();

    $member->refresh();
    expect($member->staff_role_type)->toBe('both');
    expect($member->staff_role_type_label)->toBe('Docente + Administrativo');
    expect($member->academic_degree)->toBe('Maestría en Educación Técnica');
    expect($member->specialties)->toBe(['Gastronomía', 'Administración']);
    expect($member->phone)->toBe('8800-1122');
    expect($member->attachments()->where('document_type', 'atestado_profesional')->where('status', 'activo')->count())->toBe(4);

    // Cambiar Administrativo sin atestados a Profesora exige adjuntar al menos un atestado
    Livewire::test(StaffPage::class)
        ->call('startEditStaff', $elena->id)
        ->set('edit_staff_role_type', 'teacher')
        ->call('saveStaffEdit')
        ->assertHasErrors('edit_credentials_files');
    $payroll = app(StaffPayrollService::class);

    // 1. Contrato Planilla Laboral CR
    $laborContract = StaffContract::create([
        'staff_member_id' => $member->id, 'contract_number' => StaffContract::nextNumber(),
        'hiring_regime' => 'planilla_laboral', 'contract_mode' => 'editable', 'title' => 'Contrato Planilla',
        'start_date' => now()->toDateString(), 'gross_salary_or_rate' => 500000,
        'ccss_employee_rate' => 10.67, 'banco_popular_rate' => 1.00, 'income_tax_rate' => 0,
        'accrues_aguinaldo' => true, 'accrues_vacations' => true, 'iva_rate' => 0, 'created_by' => $admin->id,
    ]);
    $laborPay = $payroll->recordPayment($laborContract, 'Noviembre 2026', now()->toDateString(), 500000);
    expect((float) $laborPay->ccss_deduction)->toBe(53350.0);
    expect((float) $laborPay->banco_popular_deduction)->toBe(5000.0);
    expect((float) $laborPay->iva_amount)->toBe(0.0);
    expect((float) $laborPay->net_amount)->toBe(441650.0);

    // 2. Contrato Servicios Profesionales CR exige clave Hacienda 50 dígitos + IVA
    $profContract = StaffContract::create([
        'staff_member_id' => $member->id, 'contract_number' => StaffContract::nextNumber(),
        'hiring_regime' => 'servicios_profesionales', 'contract_mode' => 'editable', 'title' => 'Servicios Profesionales',
        'start_date' => now()->toDateString(), 'gross_salary_or_rate' => 300000,
        'ccss_employee_rate' => 0, 'banco_popular_rate' => 0, 'income_tax_rate' => 0,
        'accrues_aguinaldo' => false, 'accrues_vacations' => false, 'iva_rate' => 2.00, 'created_by' => $admin->id,
    ]);

    expect(fn () => $payroll->recordPayment($profContract, 'Noviembre 2026', now()->toDateString(), 300000, 'transferencia', '12345'))
        ->toThrow(ValidationException::class);

    $valid50Key = str_repeat('5', 50);
    $profPay = $payroll->recordPayment($profContract, 'Noviembre 2026', now()->toDateString(), 300000, 'transferencia', $valid50Key);
    expect((float) $profPay->ccss_deduction)->toBe(0.0);
    expect((float) $profPay->iva_amount)->toBe(6000.0);
    expect((float) $profPay->net_amount)->toBe(306000.0);

    $this->get(route('pdf.contrato', $profContract))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('pdf.comprobante-personal', $profPay))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('serves PWA manifest, service worker with PDF bypass, and UTF-8 BOM CSV exports', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('lang', 'es-CR');

    $this->get('/sw.js')
        ->assertOk()
        ->assertSee('application/pdf')
        ->assertSee('/livewire')
        ->assertSee('/finanzas');

    $csvResponse = $this->actingAs($admin)->get('/direccion/exportar/students');
    $csvResponse->assertOk();
    expect(str_starts_with($csvResponse->streamedContent(), "\xEF\xBB\xBF"))->toBeTrue();
});
