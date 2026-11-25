<?php

use App\Exceptions\PhysicalDeletionForbiddenException;
use App\Models\AuditLog;
use App\Models\FinancialLedgerEntry;
use App\Models\Student;
use App\Models\StudentLifecycleEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('blocks Eloquent physical deletion via PreventsPhysicalDeletion trait', function () {
    $student = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Ana',
        'last_name' => 'Solano',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-2222-3333',
        'email' => 'ana.solano@ejemplo.cr',
        'phone' => '8800-1111',
        'gender' => 'femenino',
    ]);

    expect(fn () => $student->delete())->toThrow(PhysicalDeletionForbiddenException::class);
    expect(fn () => $student->forceDelete())->toThrow(PhysicalDeletionForbiddenException::class);
});

it('blocks raw SQL DELETE on business tables via PostgreSQL BEFORE DELETE trigger', function () {
    $user = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

    expect(fn () => DB::table('users')->where('id', $user->id)->delete())
        ->toThrow(QueryException::class);
});

it('enforces append-only immutability on audit_logs, financial_ledger_entries, and student_lifecycle_events', function () {
    $user = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();
    $student = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Lucía',
        'last_name' => 'Arias',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-3333-4444',
        'email' => 'lucia.arias@ejemplo.cr',
        'phone' => '8800-2222',
        'gender' => 'femenino',
    ]);

    $audit = AuditLog::create([
        'user_id' => $user->id,
        'actor_name' => $user->name,
        'event' => 'test.event',
        'module' => 'seguridad',
        'created_at' => now(),
    ]);

    $ledger = FinancialLedgerEntry::create([
        'entry_code' => FinancialLedgerEntry::nextEntryCode(),
        'student_id' => $student->id,
        'entry_type' => 'cargo',
        'amount' => 15000,
        'description' => 'Cargo prueba inmutable',
        'recorded_by' => $user->id,
        'created_at' => now(),
    ]);

    $lifecycle = StudentLifecycleEvent::create([
        'student_id' => $student->id,
        'event_type' => 'alta',
        'description' => 'Evento prueba inmutable',
        'recorded_by' => $user->id,
        'created_at' => now(),
    ]);

    expect(fn () => DB::table('audit_logs')->where('id', $audit->id)->update(['event' => 'tampered']))->toThrow(QueryException::class);
    expect(fn () => DB::table('audit_logs')->where('id', $audit->id)->delete())->toThrow(QueryException::class);

    expect(fn () => DB::table('financial_ledger_entries')->where('id', $ledger->id)->update(['amount' => 0]))->toThrow(QueryException::class);
    expect(fn () => DB::table('financial_ledger_entries')->where('id', $ledger->id)->delete())->toThrow(QueryException::class);

    expect(fn () => DB::table('student_lifecycle_events')->where('id', $lifecycle->id)->update(['description' => 'tampered']))->toThrow(QueryException::class);
    expect(fn () => DB::table('student_lifecycle_events')->where('id', $lifecycle->id)->delete())->toThrow(QueryException::class);
});
