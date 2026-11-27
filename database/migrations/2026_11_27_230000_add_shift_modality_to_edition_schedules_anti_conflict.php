<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edition_schedules', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('course_edition_id')->constrained('academic_shifts')->restrictOnDelete();
            $table->foreignId('modality_id')->nullable()->after('shift_id')->constrained('academic_modalities')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                UPDATE edition_schedules es
                SET shift_id = ce.shift_id,
                    modality_id = ce.modality_id
                FROM course_editions ce
                WHERE es.course_edition_id = ce.id
            ');

            DB::statement('ALTER TABLE edition_schedules DROP CONSTRAINT IF EXISTS edition_schedules_no_classroom_overlap');
            DB::statement('ALTER TABLE edition_schedules DROP CONSTRAINT IF EXISTS edition_schedules_no_teacher_overlap');

            DB::statement('
                ALTER TABLE edition_schedules
                ADD CONSTRAINT edition_schedules_no_classroom_overlap
                EXCLUDE USING gist (
                    classroom_id WITH =,
                    shift_id WITH =,
                    modality_id WITH =,
                    day_of_week WITH =,
                    date_span WITH &&,
                    time_slot WITH &&
                ) WHERE (is_active AND classroom_id IS NOT NULL AND shift_id IS NOT NULL AND modality_id IS NOT NULL)
            ');

            DB::statement('
                ALTER TABLE edition_schedules
                ADD CONSTRAINT edition_schedules_no_teacher_overlap
                EXCLUDE USING gist (
                    teacher_id WITH =,
                    shift_id WITH =,
                    modality_id WITH =,
                    day_of_week WITH =,
                    date_span WITH &&,
                    time_slot WITH &&
                ) WHERE (is_active AND teacher_id IS NOT NULL AND shift_id IS NOT NULL AND modality_id IS NOT NULL)
            ');
        } else {
            $rows = DB::table('edition_schedules')
                ->join('course_editions', 'course_editions.id', '=', 'edition_schedules.course_edition_id')
                ->select('edition_schedules.id', 'course_editions.shift_id', 'course_editions.modality_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('edition_schedules')
                    ->where('id', $row->id)
                    ->update([
                        'shift_id' => $row->shift_id,
                        'modality_id' => $row->modality_id,
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE edition_schedules DROP CONSTRAINT IF EXISTS edition_schedules_no_classroom_overlap');
            DB::statement('ALTER TABLE edition_schedules DROP CONSTRAINT IF EXISTS edition_schedules_no_teacher_overlap');

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

        Schema::table('edition_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modality_id');
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
