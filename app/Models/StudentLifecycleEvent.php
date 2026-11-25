<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLifecycleEvent extends Model
{
    use ImmutableAppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'enrollment_id',
        'event_type',
        'from_status',
        'to_status',
        'description',
        'metadata',
        'recorded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
