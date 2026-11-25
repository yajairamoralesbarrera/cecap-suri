<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class StaffMember extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'user_id',
        'staff_code',
        'staff_role_type',
        'first_name',
        'last_name',
        'second_last_name',
        'identification_type',
        'identification_number',
        'email',
        'phone',
        'gender',
        'specialty',
        'academic_degree',
        'hiring_regime',
        'status',
        'notes',
        'created_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(StaffContract::class)->latest('start_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StaffPayment::class)->latest('payment_date');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RecordAttachment::class, 'attachable')->latest();
    }

    public function requiresProfessionalCredentials(): bool
    {
        return in_array($this->staff_role_type, ['teacher', 'both'], true);
    }

    public function hasActiveProfessionalCredentials(): bool
    {
        return $this->attachments()
            ->where('document_type', 'atestado_profesional')
            ->where('status', 'activo')
            ->exists();
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
            $this->second_last_name,
        ])));
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('PER-%s-%04d', $year, $count);
    }
}
