<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EditionSchedule extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'course_edition_id',
        'classroom_id',
        'teacher_id',
        'day_of_week',
        'start_minute',
        'end_minute',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'course_edition_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public static function timeToMinutes(string $hhmm): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $hhmm));

        return ($hours * 60) + $minutes;
    }

    public static function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function getFormattedSlotAttribute(): string
    {
        $day = config('cecap.days_of_week.'.$this->day_of_week, 'Día '.$this->day_of_week);

        return sprintf('%s %s - %s', $day, self::minutesToTime($this->start_minute), self::minutesToTime($this->end_minute));
    }
}
