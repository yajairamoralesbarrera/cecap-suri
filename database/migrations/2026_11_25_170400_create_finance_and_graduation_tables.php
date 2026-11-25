<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payers', function (Blueprint $table) {
            $table->id();
            $table->string('payer_type', 40)->default('alumna'); // alumna, familiar, empresa, ong, institucion_publica
            $table->string('full_name', 160);
            $table->string('identification_number', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->restrictOnDelete();
            $table->string('name', 140);
            $table->string('discount_type', 30)->default('porcentaje'); // porcentaje, monto_fijo
            $table->decimal('discount_value', 12, 2);
            $table->text('reason')->nullable();
            $table->string('status', 30)->default('activa'); // activa, revocada
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('fee_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->restrictOnDelete();
            $table->string('concept', 180);
            $table->string('charge_type', 40)->default('mensualidad'); // matricula, mensualidad, materiales, derecho_graduacion, otro
            $table->unsignedSmallInteger('installment_number')->nullable();
            $table->date('due_date');
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('scholarship_discount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('pendiente')->index(); // pendiente, parcial, pagado, exonerado, anulado
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });

        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 40)->unique(); // REC-YYYY-NNNNN
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('payers')->restrictOnDelete();
            $table->string('payment_method', 40)->default('efectivo');
            $table->string('reference_number', 100)->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->timestamp('paid_at')->useCurrent();
            $table->string('status', 30)->default('emitido')->index(); // emitido, reversado
            $table->foreignId('reversed_by_receipt_id')->nullable()->constrained('payment_receipts')->restrictOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_receipt_id')->constrained('payment_receipts')->restrictOnDelete();
            $table->foreignId('fee_charge_id')->constrained('fee_charges')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('financial_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_code', 50)->unique();
            $table->foreignId('student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('fee_charge_id')->nullable()->constrained('fee_charges')->restrictOnDelete();
            $table->foreignId('payment_receipt_id')->nullable()->constrained('payment_receipts')->restrictOnDelete();
            $table->string('entry_type', 40)->index(); // cargo, beca, pago, reverso
            $table->decimal('amount', 12, 2);
            $table->text('description');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('diploma_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 140);
            $table->string('program_type', 40)->nullable();
            $table->string('header_title', 200)->default('Certificado de Aprovechamiento');
            $table->text('body_template');
            $table->string('primary_color', 20)->default('#0F4C4C');
            $table->string('accent_color', 20)->default('#C87D32');
            $table->string('border_style', 30)->default('classic');
            $table->string('signatory_one_name', 140)->default('Dirección General');
            $table->string('signatory_one_title', 140)->default('Directora General CeCap SURÍ');
            $table->string('signatory_two_name', 140)->nullable();
            $table->string('signatory_two_title', 140)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('graduation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('diploma_template_id')->nullable()->constrained('diploma_templates')->restrictOnDelete();
            $table->decimal('final_grade', 5, 2);
            $table->decimal('attendance_percent', 5, 2);
            $table->boolean('is_financially_solvent')->default(true);
            $table->boolean('solvency_exception_authorized')->default(false);
            $table->text('solvency_exception_reason')->nullable();
            $table->foreignId('solvency_exception_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('tomo', 30);
            $table->string('folio', 30);
            $table->string('asiento', 30);
            $table->string('act_number', 50)->unique();
            $table->date('graduation_date');
            $table->uuid('verification_token')->unique();
            $table->string('status', 30)->default('emitido')->index();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['tomo', 'folio', 'asiento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graduation_records');
        Schema::dropIfExists('diploma_templates');
        Schema::dropIfExists('financial_ledger_entries');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('fee_charges');
        Schema::dropIfExists('scholarships');
        Schema::dropIfExists('payers');
    }
};
