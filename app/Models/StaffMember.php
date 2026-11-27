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
        'birth_date',
        'specialty',
        'specialties',
        'academic_degree',
        'hiring_regime',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'specialties' => 'array',
        ];
    }

    public function getSpecialtiesListAttribute(): array
    {
        if (is_array($this->specialties) && ! empty($this->specialties)) {
            return array_values(array_slice(array_filter(array_map('trim', $this->specialties)), 0, 3));
        }

        if (filled($this->specialty)) {
            return [trim((string) $this->specialty)];
        }

        return [];
    }

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

    public static function findByIdentification(string $identification): ?self
    {
        $clean = trim($identification);
        if ($clean === '') {
            return null;
        }

        $normalized = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $clean));

        return static::query()
            ->where(function ($q) use ($clean, $normalized): void {
                $q->where('identification_number', $clean);
                if ($normalized !== '') {
                    $q->orWhereRaw("UPPER(REGEXP_REPLACE(identification_number, '[^0-9A-Za-z]', '', 'g')) = ?", [$normalized]);
                }
            })
            ->first();
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('PER-%s-%04d', $year, $count);
    }
}
