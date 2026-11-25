<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workshop extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'code',
        'title',
        'workshop_type',
        'description',
        'facilitator_name',
        'event_date',
        'start_time',
        'end_time',
        'location',
        'max_capacity',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(WorkshopParticipant::class)->latest();
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('TAL-%s-%04d', $year, $count);
    }
}
