<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoftSkillWeek extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'week_start_date',
        'week_end_date',
        'label',
        'has_session',
        'no_session_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => 'date',
            'week_end_date' => 'date',
            'has_session' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SoftSkillAttendance::class, 'soft_skill_week_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRangeLabelAttribute(): string
    {
        $from = $this->week_start_date?->format('d/m/Y') ?? '—';
        $to = $this->week_end_date?->format('d/m/Y') ?? '—';

        return "Del {$from} al {$to}";
    }

    public function getDisplayTitleAttribute(): string
    {
        return filled($this->label)
            ? trim((string) $this->label).' ('.$this->range_label.')'
            : 'Semana '.$this->range_label;
    }
}
