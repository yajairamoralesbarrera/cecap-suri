<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoftSkillRecord extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'student_id',
        'enrollment_id',
        'skill_name',
        'level',
        'score',
        'observations',
        'evaluated_on',
        'evaluated_by',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'evaluated_on' => 'date',
            'is_archived' => 'boolean',
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

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
