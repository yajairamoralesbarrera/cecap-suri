<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->unsignedSmallInteger('soft_skills_absence_credits')
                ->default(0)
                ->after('max_capacity');
            $table->decimal('cost', 12, 2)
                ->default(0)
                ->after('soft_skills_absence_credits');
        });

        Schema::table('workshop_participants', function (Blueprint $table) {
            $table->decimal('amount_charged', 12, 2)
                ->default(0)
                ->after('attended');
            $table->string('payment_method', 40)
                ->nullable()
                ->after('amount_charged');
            $table->string('payment_status', 30)
                ->default('exento')
                ->after('payment_method');
            $table->string('payment_reference', 120)
                ->nullable()
                ->after('payment_status');
        });

        Schema::create('soft_skill_weeks', function (Blueprint $table) {
            $table->id();
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->string('label', 140)->nullable();
            $table->boolean('has_session')->default(true)->index();
            $table->string('no_session_reason', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['week_start_date', 'week_end_date'], 'soft_skill_weeks_dates_idx');
        });

        Schema::create('soft_skill_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('soft_skill_week_id')->constrained('soft_skill_weeks')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('status', 30); // presente, ausente, justificada
            $table->date('attended_date')->nullable();
            $table->string('schedule_notes', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['soft_skill_week_id', 'student_id'], 'soft_skill_att_week_student_uniq');
        });

        // Asegurar método de cancelación IMAS en catálogo de métodos de pago
        if (Schema::hasTable('picklists')) {
            $exists = DB::table('picklists')
                ->where('group_key', 'payment_method')
                ->where('code', 'imas')
                ->exists();

            if (! $exists) {
                DB::table('picklists')->insert([
                    'group_key' => 'payment_method',
                    'code' => 'imas',
                    'label' => 'IMAS (Patrocinador)',
                    'sort_order' => 60,
                    'is_active' => true,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('soft_skill_attendances');
        Schema::dropIfExists('soft_skill_weeks');

        Schema::table('workshop_participants', function (Blueprint $table) {
            $table->dropColumn(['amount_charged', 'payment_method', 'payment_status', 'payment_reference']);
        });

        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn(['soft_skills_absence_credits', 'cost']);
        });
    }
};
