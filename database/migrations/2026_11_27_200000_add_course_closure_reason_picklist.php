<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['code' => 'cupo_minimo_no_alcanzado', 'label' => 'Cupo mínimo no alcanzado', 'sort_order' => 10],
            ['code' => 'decision_institucional', 'label' => 'Decisión institucional / Dirección Académica', 'sort_order' => 20],
            ['code' => 'reprogramacion_calendario', 'label' => 'Reprogramación de calendario académico', 'sort_order' => 30],
            ['code' => 'indisponibilidad_docente', 'label' => 'Indisponibilidad de profesora / docente', 'sort_order' => 40],
            ['code' => 'actualizacion_curricular', 'label' => 'Actualización curricular del curso o programa', 'sort_order' => 50],
            ['code' => 'fuerza_mayor', 'label' => 'Fuerza mayor / situación operativa', 'sort_order' => 60],
            ['code' => 'otro_motivo_cierre', 'label' => 'Otro motivo institucional', 'sort_order' => 70],
        ];

        $now = now();
        foreach ($items as $item) {
            $exists = DB::table('picklists')
                ->where('group_key', 'course_closure_reason')
                ->where('code', $item['code'])
                ->exists();

            if (! $exists) {
                DB::table('picklists')->insert([
                    'group_key' => 'course_closure_reason',
                    'code' => $item['code'],
                    'label' => $item['label'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Respetamos política de no borrado físico de catálogos en producción.
    }
};
