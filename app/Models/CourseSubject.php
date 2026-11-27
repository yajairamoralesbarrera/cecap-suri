<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseSubject extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'course_id',
        'code',
        'name',
        'hours',
        'sessions_count',
        'order_index',
        'is_mandatory',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public static function nextIncrementalCode(?int $courseId, string $sourceCode, array $extraUsedCodes = []): string
    {
        $clean = strtoupper(trim($sourceCode));
        if ($clean === '') {
            $clean = 'MAT-01';
        }

        if (preg_match('/^(.*?)(\d+)$/', $clean, $matches)) {
            $prefix = $matches[1];
            $numStr = $matches[2];
            $padLen = strlen($numStr);
            $nextNum = (int) $numStr + 1;
        } else {
            $prefix = $clean.'-';
            $padLen = 2;
            $nextNum = 2;
        }

        $used = array_map(fn ($c) => strtoupper(trim((string) $c)), $extraUsedCodes);
        if ($courseId) {
            $dbCodes = self::where('course_id', $courseId)
                ->pluck('code')
                ->map(fn ($c) => strtoupper(trim((string) $c)))
                ->all();
            $used = array_merge($used, $dbCodes);
        }
        $usedLookup = array_flip($used);

        do {
            $candidate = $prefix.str_pad((string) $nextNum, $padLen, '0', STR_PAD_LEFT);
            $nextNum++;
        } while (isset($usedLookup[$candidate]));

        return $candidate;
    }
}
