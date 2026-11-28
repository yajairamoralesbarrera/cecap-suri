<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EditionEvaluation extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'course_edition_id',
        'course_subject_id',
        'name',
        'weight_percent',
        'due_date',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'weight_percent' => 'decimal:2',
            'due_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'course_edition_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class, 'course_subject_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function gradeRecords(): HasMany
    {
        return $this->hasMany(GradeRecord::class, 'edition_evaluation_id')->where('is_current', true);
    }
}
