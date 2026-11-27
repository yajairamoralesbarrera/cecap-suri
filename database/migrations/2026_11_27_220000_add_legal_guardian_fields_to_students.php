<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'guardian_full_name')) {
                $table->string('guardian_full_name', 160)->nullable()->after('emergency_contact_phone');
            }
            if (! Schema::hasColumn('students', 'guardian_identification_number')) {
                $table->string('guardian_identification_number', 40)->nullable()->after('guardian_full_name');
            }
            if (! Schema::hasColumn('students', 'guardian_relationship')) {
                $table->string('guardian_relationship', 60)->nullable()->after('guardian_identification_number');
            }
            if (! Schema::hasColumn('students', 'guardian_phone')) {
                $table->string('guardian_phone', 30)->nullable()->after('guardian_relationship');
            }
            if (! Schema::hasColumn('students', 'guardian_email')) {
                $table->string('guardian_email', 255)->nullable()->after('guardian_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            foreach (['guardian_email', 'guardian_phone', 'guardian_relationship', 'guardian_identification_number', 'guardian_full_name'] as $col) {
                if (Schema::hasColumn('students', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
