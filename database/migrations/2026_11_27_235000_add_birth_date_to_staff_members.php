<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_members', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_members', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('gender');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_members', function (Blueprint $table) {
            if (Schema::hasColumn('staff_members', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};
