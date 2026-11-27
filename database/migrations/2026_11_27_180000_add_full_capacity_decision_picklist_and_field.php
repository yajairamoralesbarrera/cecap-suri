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
            if (! Schema::hasColumn('enrollments', 'capacity_decision_code')) {
                $table->string('capacity_decision_code', 60)->nullable()->after('rejected_by');
            }
        });

        $decisions = [
            ['lista_espera', 'Ingresar en Lista de Espera'],
            ['autorizar_sobrecupo', 'Autorizar Sobrecupo Excepcional (Auditado)'],
            ['trasladar_otra_edicion', 'Reubicar en otra edición con cupo disponible'],
            ['rechazar_cupo_lleno', 'No matricular — Cupo agotado (notificar por correo)'],
        ];

        $now = now();
        foreach ($decisions as $idx => [$code, $label]) {
            $exists = DB::table('picklists')
                ->where('group_key', 'full_capacity_decision')
                ->where('code', $code)
                ->exists();

            if (! $exists) {
                DB::table('picklists')->insert([
                    'group_key' => 'full_capacity_decision',
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
            if (Schema::hasColumn('enrollments', 'capacity_decision_code')) {
                $table->dropColumn('capacity_decision_code');
            }
        });
    }
};
