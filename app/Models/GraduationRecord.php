<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GraduationRecord extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'enrollment_id',
        'student_id',
        'diploma_template_id',
        'final_grade',
        'attendance_percent',
        'is_financially_solvent',
        'solvency_exception_authorized',
        'solvency_exception_reason',
        'solvency_exception_by',
        'tomo',
        'folio',
        'asiento',
        'act_number',
        'graduation_date',
        'verification_token',
        'status',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'final_grade' => 'decimal:2',
            'attendance_percent' => 'decimal:2',
            'is_financially_solvent' => 'boolean',
            'solvency_exception_authorized' => 'boolean',
            'graduation_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (empty($record->verification_token)) {
                $record->verification_token = (string) Str::uuid();
            }
        });
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DiplomaTemplate::class, 'diploma_template_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function solvencyExceptionAuthorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solvency_exception_by');
    }

    public static function nextActNumber(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('ACTA-%s-%04d', $year, $count);
    }
}
