<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Picklist extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'group_key',
        'code',
        'label',
        'parent_id',
        'sort_order',
        'is_active',
        'is_system',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public static function optionsFor(string $groupKey): array
    {
        return static::query()
            ->where('group_key', $groupKey)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label', 'code')
            ->all();
    }
}
