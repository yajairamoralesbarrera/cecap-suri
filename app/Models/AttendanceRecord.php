<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'course_edition_id',
        'enrollment_id',
        'student_id',
        'session_date',
        'status',
        'version',
        'is_current',
        'rectification_reason',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
