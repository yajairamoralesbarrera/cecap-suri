<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('enrollments', 'rejection_reason_code')) {
                $table->string('rejection_reason_code', 60)->nullable()->after('exception_authorized_at');
            }
            if (! Schema::hasColumn('enrollments', 'rejection_notes')) {
                $table->text('rejection_notes')->nullable()->after('rejection_reason_code');
            }
            if (! Schema::hasColumn('enrollments', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejection_notes');
            }
            if (! Schema::hasColumn('enrollments', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->restrictOnDelete();
            }
        });

        // Picklist administrable de motivos de rechazo de preinscripción/admisión
        $reasons = [
            ['documentacion_incompleta', 'Documentación incompleta o no presentada en plazo'],
            ['documentacion_invalida', 'Documentos ilegibles o no cumplen requisitos'],
            ['nivel_academico_insuficiente', 'No cumple con el nivel académico previo requerido'],
            ['cupo_agotado', 'Cupo máximo de la edición agotado'],
            ['plazo_provisional_vencido', 'Vencimiento del plazo provisional sin confirmación presencial'],
            ['perfil_no_elegible', 'No cumple con el perfil de elegibilidad institucional'],
            ['solicitud_duplicada', 'Solicitud duplicada o desistimiento de la solicitante'],
            ['otro_motivo', 'Otro motivo (detallado en observaciones)'],
        ];

        $now = now();
        foreach ($reasons as $idx => [$code, $label]) {
            $exists = DB::table('picklists')
                ->where('group_key', 'enrollment_rejection_reason')
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                DB::table('picklists')->insert([
                    'group_key' => 'enrollment_rejection_reason',
                    'code' => $code,
                    'label' => $label,
                    'sort_order' => ($idx + 1) * 10,
                    'is_active' => true,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('enrollments', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }
            foreach (['rejected_at', 'rejection_notes', 'rejection_reason_code'] as $col) {
                if (Schema::hasColumn('enrollments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
