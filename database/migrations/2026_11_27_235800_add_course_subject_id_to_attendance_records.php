<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('course_subject_id')
                ->nullable()
                ->after('course_edition_id')
                ->constrained('course_subjects')
                ->restrictOnDelete();

            $table->index(
                ['enrollment_id', 'course_subject_id', 'session_date', 'is_current'],
                'attendance_enr_sub_date_curr_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_enr_sub_date_curr_idx');
            $table->dropConstrainedForeignId('course_subject_id');
        });
    }
};
