<?php

use App\Models\Course;
use App\Models\EditionSchedule;
use App\Models\Enrollment;
use App\Models\FeeCharge;
use App\Models\InstitutionSetting;
use App\Models\RecordAttachment;
use App\Models\StaffContract;
use App\Models\StaffMember;
use App\Services\StaffPayrollService;
use Carbon\Carbon;

describe('Unit — Reglas de Dominio CeCap SURÍ v3.0', function () {
    it('converts HH:MM time strings to minutes and back accurately', function () {
        expect(EditionSchedule::timeToMinutes('00:00'))->toBe(0);
        expect(EditionSchedule::timeToMinutes('08:30'))->toBe(510);
        expect(EditionSchedule::timeToMinutes('17:45'))->toBe(1065);
        expect(EditionSchedule::timeToMinutes('23:59'))->toBe(1439);

        expect(EditionSchedule::minutesToTime(0))->toBe('00:00');
        expect(EditionSchedule::minutesToTime(510))->toBe('08:30');
        expect(EditionSchedule::minutesToTime(1065))->toBe('17:45');
        expect(EditionSchedule::minutesToTime(1439))->toBe('23:59');
    });

    it('determines default required documents by program type (Atestados only for curso_tecnico)', function () {
        expect(Course::defaultRequiredDocumentsFor('curso_corto'))->toBe(['cedula_dimex']);
        expect(Course::defaultRequiredDocumentsFor('programa'))->toBe(['cedula_dimex']);
        expect(Course::defaultRequiredDocumentsFor('curso_tecnico'))->toBe(['cedula_dimex', 'atestado_academico']);
    });

    it('calculates Costa Rica Planilla Laboral deductions and employer provisions accurately', function () {
        $service = new StaffPayrollService;
        $contract = new StaffContract([
            'hiring_regime' => 'planilla_laboral',
            'ccss_employee_rate' => 10.67,
            'banco_popular_rate' => 1.00,
            'income_tax_rate' => 10.00,
            'accrues_aguinaldo' => true,
            'accrues_vacations' => true,
            'iva_rate' => 0,
        ]);

        $calc = $service->calculatePaymentBreakdown($contract, 1000000);

        expect($calc['hiring_regime'])->toBe('planilla_laboral');
        expect($calc['gross_amount'])->toBe(1000000.0);
        expect($calc['ccss_deduction'])->toBe(106700.0);
        expect($calc['banco_popular_deduction'])->toBe(10000.0);
        expect($calc['income_tax_deduction'])->toBe(100000.0);
        expect($calc['aguinaldo_provision'])->toBe(83333.33);
        expect($calc['vacation_provision'])->toBe(41667.0);
        expect($calc['iva_amount'])->toBe(0.0);
        expect($calc['net_amount'])->toBe(783300.0);
    });

    it('calculates Costa Rica Servicios Profesionales with IVA and zero social charges', function () {
        $service = new StaffPayrollService;
        $contract = new StaffContract([
            'hiring_regime' => 'servicios_profesionales',
            'ccss_employee_rate' => 10.67,
            'banco_popular_rate' => 1.00,
            'income_tax_rate' => 10.00,
            'accrues_aguinaldo' => true,
            'accrues_vacations' => true,
            'iva_rate' => 2.00,
        ]);

        $calc = $service->calculatePaymentBreakdown($contract, 250000);

        expect($calc['hiring_regime'])->toBe('servicios_profesionales');
        expect($calc['ccss_deduction'])->toBe(0.0);
        expect($calc['banco_popular_deduction'])->toBe(0.0);
        expect($calc['income_tax_deduction'])->toBe(0.0);
        expect($calc['aguinaldo_provision'])->toBe(0.0);
        expect($calc['vacation_provision'])->toBe(0.0);
        expect($calc['iva_amount'])->toBe(5000.0);
        expect($calc['net_amount'])->toBe(255000.0);
    });

    it('computes missing documents diff on Enrollment accurately', function () {
        $enrollment = new Enrollment([
            'required_documents_snapshot' => ['cedula_dimex', 'atestado_academico'],
            'submitted_documents' => ['cedula_dimex'],
        ]);

        expect($enrollment->missingDocuments())->toBe(['atestado_academico']);

        $complete = new Enrollment([
            'required_documents_snapshot' => ['cedula_dimex', 'atestado_academico'],
            'submitted_documents' => ['atestado_academico', 'cedula_dimex'],
        ]);

        expect($complete->missingDocuments())->toBe([]);
    });

    it('computes FeeCharge balance and overdue status accurately', function () {
        $overdue = new FeeCharge([
            'gross_amount' => 30000,
            'scholarship_discount' => 5000,
            'net_amount' => 25000,
            'paid_amount' => 10000,
            'status' => 'parcial',
            'due_date' => Carbon::yesterday(),
        ]);

        expect($overdue->balance)->toBe(15000.0);
        expect($overdue->is_overdue)->toBeTrue();

        $paid = new FeeCharge([
            'gross_amount' => 30000,
            'scholarship_discount' => 0,
            'net_amount' => 30000,
            'paid_amount' => 30000,
            'status' => 'pagado',
            'due_date' => Carbon::yesterday(),
        ]);

        expect($paid->balance)->toBe(0.0);
        expect($paid->is_overdue)->toBeFalse();
    });

    it('identifies teacher and both staff profiles as requiring professional credentials', function () {
        expect((new StaffMember(['staff_role_type' => 'teacher']))->requiresProfessionalCredentials())->toBeTrue();
        expect((new StaffMember(['staff_role_type' => 'both']))->requiresProfessionalCredentials())->toBeTrue();
        expect((new StaffMember(['staff_role_type' => 'administrative']))->requiresProfessionalCredentials())->toBeFalse();
    });

    it('formats human readable attachment sizes and evaluates module toggles cleanly', function () {
        expect((new RecordAttachment(['size_bytes' => 51200]))->size_human)->toBe('50.0 KB');
        expect((new RecordAttachment(['size_bytes' => 2621440]))->size_human)->toBe('2.50 MB');

        $setting = new InstitutionSetting([
            'logo_path' => '/images/logo-suri.png',
            'enabled_modules' => ['academico' => true, 'actividades' => false],
        ]);
        expect($setting->logoUrl())->toBe('/images/logo-suri.png');
        expect($setting->isModuleEnabled('academico'))->toBeTrue();
        expect($setting->isModuleEnabled('actividades'))->toBeFalse();
    });
});
