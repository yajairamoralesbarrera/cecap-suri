<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edition_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_edition_id')->constrained('course_editions')->restrictOnDelete();
            $table->foreignId('course_subject_id')->nullable()->constrained('course_subjects')->restrictOnDelete();
            $table->string('name', 120);
            $table->decimal('weight_percent', 5, 2);
            $table->date('due_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_edition_id', 'course_subject_id', 'is_active'], 'edition_evals_scope_idx');
        });

        Schema::table('grade_records', function (Blueprint $table) {
            $table->foreignId('edition_evaluation_id')
                ->nullable()
                ->after('course_subject_id')
                ->constrained('edition_evaluations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grade_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edition_evaluation_id');
        });

        Schema::dropIfExists('edition_evaluations');
    }
};
