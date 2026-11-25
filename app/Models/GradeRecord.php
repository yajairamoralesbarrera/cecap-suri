<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeRecord extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'enrollment_id',
        'student_id',
        'course_subject_id',
        'evaluation_name',
        'score',
        'weight_percent',
        'version',
        'is_current',
        'rectification_reason_code',
        'rectification_notes',
        'previous_grade_id',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'weight_percent' => 'decimal:2',
            'is_current' => 'boolean',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class, 'course_subject_id');
    }

    public function previousGrade(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_grade_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
