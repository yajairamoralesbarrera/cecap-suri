<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name')->default('CeCap — SURÍ Proyecto Educativo');
            $table->string('short_name', 80)->default('CeCap SURÍ');
            $table->string('legal_id', 60)->default('3-002-000000');
            $table->string('country', 80)->default('Costa Rica');
            $table->string('phone', 40)->default('+506 2200-0000');
            $table->string('email')->default('info@cecap.or.cr');
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->string('theme_color', 20)->default('#0F4C4C');
            $table->string('accent_color', 20)->default('#C87D32');
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('online_enrollment_provisional_days')->default(7);
            $table->text('online_enrollment_notice');
            $table->text('attention_hours');
            $table->text('gender_policy_message');
            $table->boolean('require_financial_solvency_for_graduation')->default(true);
            $table->jsonb('enabled_modules');
            $table->string('receipt_prefix', 15)->default('REC');
            $table->text('diploma_footer_text')->nullable();
            $table->string('director_name')->default('Dirección General CeCap SURÍ');
            $table->string('director_title')->default('Directora General');
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('picklists', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 80)->index();
            $table->string('code', 80);
            $table->string('label', 160);
            $table->foreignId('parent_id')->nullable()->constrained('picklists')->restrictOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['group_key', 'code']);
        });

        Schema::create('cr_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cr_cantons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained('cr_provinces')->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['province_id', 'code']);
        });

        Schema::create('cr_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canton_id')->constrained('cr_cantons')->restrictOnDelete();
            $table->string('code', 10);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['canton_id', 'code']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('event', 100)->index();
            $table->string('module', 80)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('record_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('document_type', 60)->index();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('status', 30)->default('activo');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_attachments');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cr_districts');
        Schema::dropIfExists('cr_cantons');
        Schema::dropIfExists('cr_provinces');
        Schema::dropIfExists('picklists');
        Schema::dropIfExists('institution_settings');
    }
};
