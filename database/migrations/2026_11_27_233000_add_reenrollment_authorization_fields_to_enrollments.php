<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('enrollments', 'reenrollment_authorized')) {
                $table->boolean('reenrollment_authorized')->default(false)->after('exception_authorized_at');
            }
            if (! Schema::hasColumn('enrollments', 'reenrollment_reason')) {
                $table->text('reenrollment_reason')->nullable()->after('reenrollment_authorized');
            }
            if (! Schema::hasColumn('enrollments', 'reenrollment_authorized_by')) {
                $table->foreignId('reenrollment_authorized_by')->nullable()->after('reenrollment_reason')->constrained('users')->restrictOnDelete();
            }
            if (! Schema::hasColumn('enrollments', 'reenrollment_authorized_at')) {
                $table->timestamp('reenrollment_authorized_at')->nullable()->after('reenrollment_authorized_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('enrollments', 'reenrollment_authorized_by')) {
                $table->dropConstrainedForeignId('reenrollment_authorized_by');
            }
            foreach (['reenrollment_authorized_at', 'reenrollment_reason', 'reenrollment_authorized'] as $col) {
                if (Schema::hasColumn('enrollments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
