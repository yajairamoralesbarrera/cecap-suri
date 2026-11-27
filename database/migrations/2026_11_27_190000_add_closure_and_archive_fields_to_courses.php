<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (! Schema::hasColumn('courses', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('courses', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('courses', 'closure_reason')) {
                $table->text('closure_reason')->nullable()->after('closed_by');
            }
            if (! Schema::hasColumn('courses', 'archived_at')) {
                $table->timestamp('archived_at')->nullable()->after('closure_reason');
            }
            if (! Schema::hasColumn('courses', 'archived_by')) {
                $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('courses', 'archive_reason')) {
                $table->text('archive_reason')->nullable()->after('archived_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            foreach (['closed_by', 'archived_by'] as $fk) {
                if (Schema::hasColumn('courses', $fk)) {
                    $table->dropConstrainedForeignId($fk);
                }
            }
            foreach (['closed_at', 'closure_reason', 'archived_at', 'archive_reason'] as $col) {
                if (Schema::hasColumn('courses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
