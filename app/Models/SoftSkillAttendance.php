<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoftSkillAttendance extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'soft_skill_week_id',
        'student_id',
        'status',
        'attended_date',
        'schedule_notes',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'attended_date' => 'date',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            'presente' => 'Presente',
            'justificada' => 'Justificada',
            'ausente' => 'Ausente',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function week(): BelongsTo
    {
        return $this->belongsTo(SoftSkillWeek::class, 'soft_skill_week_id');
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
