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
        'shift_id',
        'modality_id',
        'classroom_id',
        'teacher_id',
        'day_of_week',
        'start_minute',
        'end_minute',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $schedule): void {
            if ($schedule->course_edition_id && ($schedule->shift_id === null || $schedule->modality_id === null || $schedule->isDirty('course_edition_id'))) {
                $edition = $schedule->relationLoaded('edition') && (int) $schedule->edition?->id === (int) $schedule->course_edition_id
                    ? $schedule->edition
                    : CourseEdition::query()->select(['id', 'shift_id', 'modality_id'])->find($schedule->course_edition_id);

                if ($edition) {
                    $schedule->shift_id = $edition->shift_id;
                    $schedule->modality_id = $edition->modality_id;
                }
            }
        });
    }

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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AcademicShift::class, 'shift_id');
    }

    public function modality(): BelongsTo
    {
        return $this->belongsTo(AcademicModality::class, 'modality_id');
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
