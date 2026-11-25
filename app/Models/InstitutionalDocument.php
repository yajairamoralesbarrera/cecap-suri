<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InstitutionalDocument extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'code',
        'title',
        'category',
        'description',
        'visibility',
        'current_version',
        'current_attachment_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function currentAttachment(): BelongsTo
    {
        return $this->belongsTo(RecordAttachment::class, 'current_attachment_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RecordAttachment::class, 'attachable')->latest('version');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('DOC-%s-%04d', $year, $count);
    }
}
