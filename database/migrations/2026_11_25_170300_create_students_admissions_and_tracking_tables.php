<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('student_code', 40)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('second_last_name', 100)->nullable();
            $table->string('identification_type', 40)->default('cedula_nacional');
            $table->string('identification_number', 40)->unique();
            $table->string('email'); // OBLIGATORIO
            $table->string('phone', 30);
            $table->string('secondary_phone', 30)->nullable();
            $table->string('gender', 30)->default('femenino');
            $table->date('birth_date')->nullable();
            $table->string('education_level', 80)->nullable();
            $table->foreignId('province_id')->nullable()->constrained('cr_provinces')->restrictOnDelete();
            $table->foreignId('canton_id')->nullable()->constrained('cr_cantons')->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('cr_districts')->restrictOnDelete();
            $table->text('address_detail')->nullable();
            $table->string('emergency_contact_name', 140)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('activo')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('email');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->string('enrollment_code', 40)->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_edition_id')->constrained('course_editions')->restrictOnDelete();
            $table->string('source', 30)->default('presencial'); // en_linea, presencial
            $table->string('status', 40)->default('preinscrita')->index();
            $table->timestamp('provisional_expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->jsonb('required_documents_snapshot');
            $table->jsonb('submitted_documents');
            $table->boolean('documents_complete')->default(false);
            $table->boolean('exception_authorized')->default(false);
            $table->text('exception_reason')->nullable();
            $table->date('exception_commitment_date')->nullable();
            $table->foreignId('exception_authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('exception_authorized_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'course_edition_id']);
        });

        Schema::create('student_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->string('event_type', 60)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('description');
            $table->jsonb('metadata')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_edition_id')->constrained('course_editions')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('session_date');
            $table->string('status', 30); // presente, ausente, tardia, justificada
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_current')->default(true)->index();
            $table->text('rectification_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['enrollment_id', 'session_date', 'is_current']);
        });

        Schema::create('grade_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('course_subject_id')->nullable()->constrained('course_subjects')->restrictOnDelete();
            $table->string('evaluation_name', 120);
            $table->decimal('score', 5, 2);
            $table->decimal('weight_percent', 5, 2)->default(100.00);
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_current')->default(true)->index();
            $table->string('rectification_reason_code', 80)->nullable();
            $table->text('rectification_notes')->nullable();
            $table->foreignId('previous_grade_id')->nullable()->constrained('grade_records')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['enrollment_id', 'is_current']);
        });

        Schema::create('soft_skill_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->string('skill_name', 120);
            $table->string('level', 40)->default('competente'); // en_desarrollo, competente, destacado
            $table->unsignedSmallInteger('score')->nullable();
            $table->text('observations')->nullable();
            $table->date('evaluated_on');
            $table->foreignId('evaluated_by')->constrained('users')->restrictOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soft_skill_records');
        Schema::dropIfExists('grade_records');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('student_lifecycle_events');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
    }
};
