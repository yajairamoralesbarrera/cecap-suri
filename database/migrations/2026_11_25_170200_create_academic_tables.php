<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 140);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('user_academic_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_category_id')->constrained('academic_categories')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'academic_category_id']);
        });

        Schema::create('academic_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_modalities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_category_id')->constrained('academic_categories')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name', 180);
            $table->string('program_type', 40)->index(); // curso_corto, programa, curso_tecnico
            $table->text('description')->nullable();
            $table->string('required_education_level', 80)->default('primaria_completa');
            $table->unsignedSmallInteger('default_max_capacity')->default(25);
            $table->unsignedInteger('duration_hours')->default(40);
            $table->unsignedSmallInteger('duration_months')->default(1);
            $table->decimal('base_enrollment_fee', 12, 2)->default(0);
            $table->decimal('base_monthly_fee', 12, 2)->default(0);
            $table->jsonb('required_documents');
            $table->decimal('min_passing_grade', 5, 2)->default(70.00);
            $table->decimal('min_attendance_percent', 5, 2)->default(80.00);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('course_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 160);
            $table->unsignedInteger('hours')->default(20);
            $table->unsignedSmallInteger('order_index')->default(1);
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['course_id', 'code']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('capacity')->default(25);
            $table->string('location', 140)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('course_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 180);
            $table->foreignId('shift_id')->constrained('academic_shifts')->restrictOnDelete();
            $table->foreignId('modality_id')->constrained('academic_modalities')->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('max_capacity');
            $table->decimal('enrollment_fee', 12, 2)->default(0);
            $table->decimal('monthly_fee', 12, 2)->default(0);
            $table->unsignedSmallInteger('installments_count')->default(1);
            $table->string('status', 30)->default('abierta')->index();
            $table->boolean('is_public_enrollment_open')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('edition_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_edition_id')->constrained('course_editions')->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('day_of_week'); // 1=Lunes..7=Domingo
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE edition_schedules ADD COLUMN time_slot int4range GENERATED ALWAYS AS (int4range(start_minute, end_minute)) STORED');
            DB::statement("ALTER TABLE edition_schedules ADD COLUMN date_span daterange GENERATED ALWAYS AS (daterange(start_date, end_date, '[]')) STORED");
            DB::statement('ALTER TABLE edition_schedules ADD CONSTRAINT edition_schedules_valid_time CHECK (end_minute > start_minute)');
            DB::statement('ALTER TABLE edition_schedules ADD CONSTRAINT edition_schedules_valid_dates CHECK (end_date >= start_date)');
            DB::statement('
                ALTER TABLE edition_schedules
                ADD CONSTRAINT edition_schedules_no_classroom_overlap
                EXCLUDE USING gist (
                    classroom_id WITH =,
                    day_of_week WITH =,
                    date_span WITH &&,
                    time_slot WITH &&
                ) WHERE (is_active AND classroom_id IS NOT NULL)
            ');
            DB::statement('
                ALTER TABLE edition_schedules
                ADD CONSTRAINT edition_schedules_no_teacher_overlap
                EXCLUDE USING gist (
                    teacher_id WITH =,
                    day_of_week WITH =,
                    date_span WITH &&,
                    time_slot WITH &&
                ) WHERE (is_active AND teacher_id IS NOT NULL)
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_schedules');
        Schema::dropIfExists('course_editions');
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('course_subjects');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('academic_modalities');
        Schema::dropIfExists('academic_shifts');
        Schema::dropIfExists('user_academic_categories');
        Schema::dropIfExists('academic_categories');
    }
};
