<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('sponsor_payer_id')
                ->nullable()
                ->after('education_level')
                ->constrained('payers')
                ->nullOnDelete();
            $table->decimal('scholarship_percent', 5, 2)
                ->default(0)
                ->after('sponsor_payer_id');
            $table->string('scholarship_notes', 255)
                ->nullable()
                ->after('scholarship_percent');
        });

        // Asegurar patrocinador IMAS disponible de inmediato en el catálogo de pagadores
        if (Schema::hasTable('payers')) {
            $imasExists = DB::table('payers')
                ->whereRaw('LOWER(full_name) LIKE ?', ['%imas%'])
                ->exists();

            if (! $imasExists) {
                DB::table('payers')->insert([
                    'payer_type' => 'institucion_publica',
                    'full_name' => 'IMAS — Instituto Mixto de Ayuda Social',
                    'identification_number' => '3-007-045678',
                    'notes' => 'Patrocinador institucional público Costa Rica (IMAS)',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sponsor_payer_id');
            $table->dropColumn(['scholarship_percent', 'scholarship_notes']);
        });
    }
};
