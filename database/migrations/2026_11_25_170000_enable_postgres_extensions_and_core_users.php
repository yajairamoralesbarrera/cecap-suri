<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pgcrypto');
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('identification_type', 40)->nullable()->after('email');
            $table->string('identification_number', 40)->nullable()->unique()->after('identification_type');
            $table->string('phone', 30)->nullable()->after('identification_number');
            $table->string('gender', 30)->default('femenino')->after('phone');
            $table->boolean('must_change_password')->default(true)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->boolean('uses_custom_permissions')->default(false)->after('password_changed_at');
            $table->boolean('is_active')->default(true)->after('uses_custom_permissions');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->restrictOnDelete();
            $table->text('deactivation_reason')->nullable()->after('deactivated_by');
            $table->foreignId('created_by')->nullable()->after('deactivation_reason')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['deactivated_by']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'identification_type',
                'identification_number',
                'phone',
                'gender',
                'must_change_password',
                'password_changed_at',
                'uses_custom_permissions',
                'is_active',
                'deactivated_at',
                'deactivated_by',
                'deactivation_reason',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
