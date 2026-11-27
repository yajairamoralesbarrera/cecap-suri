<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_editions', function (Blueprint $table) {
            if (! Schema::hasColumn('course_editions', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_public_enrollment_open');
            }
            if (! Schema::hasColumn('course_editions', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users');
            }
            if (! Schema::hasColumn('course_editions', 'closure_reason')) {
                $table->text('closure_reason')->nullable()->after('closed_by');
            }
        });

        // Actualizar etiquetas de picklists existentes de "edición" a "cohorte"
        DB::table('picklists')
            ->where('group_key', 'enrollment_rejection_reason')
            ->where('code', 'cupo_agotado')
            ->update(['label' => 'Cupo máximo de la cohorte agotado', 'updated_at' => now()]);

        DB::table('picklists')
            ->where('group_key', 'full_capacity_decision')
            ->where('code', 'trasladar_otra_edicion')
            ->update(['label' => 'Reubicar en otra cohorte con cupo disponible', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('course_editions', function (Blueprint $table) {
            if (Schema::hasColumn('course_editions', 'closed_by')) {
                $table->dropConstrainedForeignId('closed_by');
            }
            if (Schema::hasColumn('course_editions', 'closure_reason')) {
                $table->dropColumn('closure_reason');
            }
            if (Schema::hasColumn('course_editions', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
        });
    }
};
