<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class RecordAttachment extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'uuid',
        'attachable_type',
        'attachable_id',
        'document_type',
        'original_name',
        'stored_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'version',
        'is_current',
        'status',
        'notes',
        'uploaded_by',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attachment): void {
            if (empty($attachment->uuid)) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return config('cecap.document_types.'.$this->document_type, $this->document_type);
    }

    public function getSizeHumanAttribute(): string
    {
        if ($this->size_bytes >= 1048576) {
            return number_format($this->size_bytes / 1048576, 2).' MB';
        }

        return number_format($this->size_bytes / 1024, 1).' KB';
    }
}
