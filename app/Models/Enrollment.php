<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Enrollment extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'enrollment_code',
        'student_id',
        'course_edition_id',
        'source',
        'status',
        'provisional_expires_at',
        'confirmed_at',
        'confirmed_by',
        'required_documents_snapshot',
        'submitted_documents',
        'documents_complete',
        'exception_authorized',
        'exception_reason',
        'exception_commitment_date',
        'exception_authorized_by',
        'exception_authorized_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'provisional_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'required_documents_snapshot' => 'array',
            'submitted_documents' => 'array',
            'documents_complete' => 'boolean',
            'exception_authorized' => 'boolean',
            'exception_commitment_date' => 'date',
            'exception_authorized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'course_edition_id');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function exceptionAuthorizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exception_authorized_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->where('is_current', true)->orderByDesc('session_date');
    }

    public function gradeRecords(): HasMany
    {
        return $this->hasMany(GradeRecord::class)->where('is_current', true)->orderBy('id');
    }

    public function feeCharges(): HasMany
    {
        return $this->hasMany(FeeCharge::class)->orderBy('due_date');
    }

    public function scholarships(): HasMany
    {
        return $this->hasMany(Scholarship::class)->latest();
    }

    public function graduationRecord(): HasOne
    {
        return $this->hasOne(GraduationRecord::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RecordAttachment::class, 'attachable')->latest();
    }

    public function missingDocuments(): array
    {
        $required = $this->required_documents_snapshot ?? [];
        $submitted = $this->submitted_documents ?? [];

        return array_values(array_diff($required, $submitted));
    }

    public function calculateFinalGrade(): float
    {
        $grades = $this->gradeRecords()->get();
        if ($grades->isEmpty()) {
            return 0.0;
        }

        $totalWeight = (float) $grades->sum('weight_percent');
        if ($totalWeight <= 0) {
            return round((float) $grades->avg('score'), 2);
        }

        $weighted = $grades->sum(fn (GradeRecord $g) => ((float) $g->score) * ((float) $g->weight_percent));

        return round($weighted / $totalWeight, 2);
    }

    public function calculateAttendancePercent(): float
    {
        $records = $this->attendanceRecords()->get();
        if ($records->isEmpty()) {
            return 100.0;
        }

        $attended = $records->whereIn('status', ['presente', 'tardia', 'justificada'])->count();

        return round(($attended / $records->count()) * 100, 2);
    }

    public function pendingBalance(): float
    {
        return round((float) $this->feeCharges()
            ->whereIn('status', ['pendiente', 'parcial'])
            ->get()
            ->sum(fn (FeeCharge $c) => max(0, (float) $c->net_amount - (float) $c->paid_amount)), 2);
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('MAT-%s-%05d', $year, $count);
    }
}
