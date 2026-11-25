<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('staff_code', 40)->unique();
            $table->string('staff_role_type', 30)->default('teacher')->index(); // teacher, administrative, both
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('second_last_name', 100)->nullable();
            $table->string('identification_type', 40)->default('cedula_nacional');
            $table->string('identification_number', 40)->unique();
            $table->string('email'); // OBLIGATORIO
            $table->string('phone', 30);
            $table->string('gender', 30)->default('femenino');
            $table->string('specialty', 140)->nullable();
            $table->string('academic_degree', 140)->nullable();
            $table->string('hiring_regime', 40)->default('planilla_laboral'); // planilla_laboral, servicios_profesionales
            $table->string('status', 30)->default('activo')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $table->string('contract_number', 50)->unique();
            $table->string('hiring_regime', 40); // planilla_laboral, servicios_profesionales
            $table->string('contract_mode', 30)->default('editable'); // editable, pdf_adjunto
            $table->string('title', 180);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('gross_salary_or_rate', 12, 2);
            $table->string('rate_unit', 30)->default('mensual');
            $table->decimal('ccss_employee_rate', 5, 2)->default(10.67);
            $table->decimal('banco_popular_rate', 5, 2)->default(1.00);
            $table->decimal('income_tax_rate', 5, 2)->default(0.00);
            $table->boolean('accrues_aguinaldo')->default(true);
            $table->boolean('accrues_vacations')->default(true);
            $table->decimal('iva_rate', 5, 2)->default(2.00);
            $table->longText('contract_body')->nullable();
            $table->foreignId('attachment_id')->nullable()->constrained('record_attachments')->restrictOnDelete();
            $table->string('status', 30)->default('vigente')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_payments', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number', 50)->unique(); // COM-YYYY-NNNNN
            $table->foreignId('staff_member_id')->constrained('staff_members')->restrictOnDelete();
            $table->foreignId('staff_contract_id')->constrained('staff_contracts')->restrictOnDelete();
            $table->string('hiring_regime', 40); // planilla_laboral, servicios_profesionales
            $table->string('period_label', 100);
            $table->date('payment_date');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('ccss_deduction', 12, 2)->default(0);
            $table->decimal('banco_popular_deduction', 12, 2)->default(0);
            $table->decimal('income_tax_deduction', 12, 2)->default(0);
            $table->decimal('aguinaldo_provision', 12, 2)->default(0);
            $table->decimal('vacation_provision', 12, 2)->default(0);
            $table->decimal('iva_amount', 12, 2)->default(0);
            $table->char('hacienda_electronic_invoice_key', 50)->nullable();
            $table->decimal('net_amount', 12, 2);
            $table->string('payment_method', 40)->default('transferencia');
            $table->string('reference_number', 100)->nullable();
            $table->string('status', 30)->default('pagado')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title', 180);
            $table->string('workshop_type', 40)->default('taller');
            $table->text('description')->nullable();
            $table->string('facilitator_name', 140);
            $table->date('event_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('location', 160)->nullable();
            $table->unsignedSmallInteger('max_capacity')->default(40);
            $table->string('status', 30)->default('programado')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('workshop_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->string('participant_name', 160);
            $table->string('identification_number', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('attended')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('institutional_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title', 180);
            $table->string('category', 80)->index();
            $table->text('description')->nullable();
            $table->string('visibility', 30)->default('interno'); // publico, alumnas, interno, direccion
            $table->unsignedSmallInteger('current_version')->default(1);
            $table->foreignId('current_attachment_id')->nullable()->constrained('record_attachments')->restrictOnDelete();
            $table->string('status', 30)->default('vigente')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institutional_documents');
        Schema::dropIfExists('workshop_participants');
        Schema::dropIfExists('workshops');
        Schema::dropIfExists('staff_payments');
        Schema::dropIfExists('staff_contracts');
        Schema::dropIfExists('staff_members');
    }
};
