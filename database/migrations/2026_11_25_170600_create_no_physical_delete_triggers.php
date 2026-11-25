<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $noDeleteTables = [
        'users',
        'institution_settings',
        'picklists',
        'cr_provinces',
        'cr_cantons',
        'cr_districts',
        'record_attachments',
        'academic_categories',
        'user_academic_categories',
        'academic_shifts',
        'academic_modalities',
        'courses',
        'course_subjects',
        'classrooms',
        'course_editions',
        'edition_schedules',
        'students',
        'enrollments',
        'attendance_records',
        'grade_records',
        'soft_skill_records',
        'payers',
        'scholarships',
        'fee_charges',
        'payment_receipts',
        'payment_allocations',
        'diploma_templates',
        'graduation_records',
        'staff_members',
        'staff_contracts',
        'staff_payments',
        'workshops',
        'workshop_participants',
        'institutional_documents',
    ];

    private array $appendOnlyTables = [
        'audit_logs',
        'financial_ledger_entries',
        'student_lifecycle_events',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION cecap_forbid_physical_delete()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Política estricta CeCap: el borrado físico está prohibido en la tabla %.', TG_TABLE_NAME
                    USING ERRCODE = 'P0001';
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION cecap_forbid_append_only_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Política estricta CeCap: la tabla append-only % es inmutable (no admite % ni borrado físico).', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'P0001';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        foreach ($this->noDeleteTables as $table) {
            DB::unprepared("
                CREATE TRIGGER trg_no_delete_{$table}
                BEFORE DELETE ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION cecap_forbid_physical_delete();
            ");
        }

        foreach ($this->appendOnlyTables as $table) {
            DB::unprepared("
                CREATE TRIGGER trg_append_only_{$table}
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW
                EXECUTE FUNCTION cecap_forbid_append_only_mutation();
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->noDeleteTables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_no_delete_{$table} ON {$table};");
        }

        foreach ($this->appendOnlyTables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_append_only_{$table} ON {$table};");
        }

        DB::unprepared('DROP FUNCTION IF EXISTS cecap_forbid_physical_delete();');
        DB::unprepared('DROP FUNCTION IF EXISTS cecap_forbid_append_only_mutation();');
    }
};
