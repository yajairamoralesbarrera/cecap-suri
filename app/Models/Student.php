<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Student extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'user_id',
        'student_code',
        'first_name',
        'last_name',
        'second_last_name',
        'identification_type',
        'identification_number',
        'email',
        'phone',
        'secondary_phone',
        'gender',
        'birth_date',
        'education_level',
        'province_id',
        'canton_id',
        'district_id',
        'address_detail',
        'emergency_contact_name',
        'emergency_contact_phone',
        'guardian_full_name',
        'guardian_identification_number',
        'guardian_relationship',
        'guardian_phone',
        'guardian_email',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(CrProvince::class, 'province_id');
    }

    public function canton(): BelongsTo
    {
        return $this->belongsTo(CrCanton::class, 'canton_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(CrDistrict::class, 'district_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->latest();
    }

    public function feeCharges(): HasMany
    {
        return $this->hasMany(FeeCharge::class)->orderBy('due_date');
    }

    public function paymentReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class)->latest('paid_at');
    }

    public function softSkills(): HasMany
    {
        return $this->hasMany(SoftSkillRecord::class)->where('is_archived', false)->latest('evaluated_on');
    }

    public function graduations(): HasMany
    {
        return $this->hasMany(GraduationRecord::class)->latest('graduation_date');
    }

    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(StudentLifecycleEvent::class)->latest('created_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RecordAttachment::class, 'attachable')->latest();
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
            $this->second_last_name,
        ])));
    }

    public function getTotalPendingBalanceAttribute(): float
    {
        return (float) $this->feeCharges()
            ->whereIn('status', ['pendiente', 'parcial'])
            ->get()
            ->sum(fn (FeeCharge $c) => max(0, (float) $c->net_amount - (float) $c->paid_amount));
    }

    /** @var list<string> */
    public const CONFIRMED_ENROLLMENT_STATUSES = ['confirmada', 'en_curso', 'egresada', 'graduada'];

    /** @var list<string> */
    public const ACTIVE_COURSE_ENROLLMENT_STATUSES = [
        'preinscrita',
        'documentacion_pendiente',
        'lista_espera',
        'confirmada',
        'en_curso',
    ];

    public function scopeConfirmedDirectory($query)
    {
        return $query->whereHas('enrollments', fn ($q) => $q->whereIn('status', self::CONFIRMED_ENROLLMENT_STATUSES));
    }

    public function isMinor(): bool
    {
        return $this->birth_date !== null && $this->birth_date->age < 18;
    }

    public static function ageFromBirthDate(?string $birthDate): ?int
    {
        $clean = trim((string) $birthDate);
        if ($clean === '') {
            return null;
        }

        try {
            $date = \Carbon\Carbon::parse($clean);

            return $date->isFuture() ? null : $date->age;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isBirthDateMinor(?string $birthDate): bool
    {
        $age = self::ageFromBirthDate($birthDate);

        return $age !== null && $age < 18;
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

    public function activeEnrollmentInCourse(int $courseId): ?Enrollment
    {
        return $this->enrollments()
            ->whereIn('status', self::ACTIVE_COURSE_ENROLLMENT_STATUSES)
            ->whereHas('edition', fn ($q) => $q->where('course_id', $courseId))
            ->with('edition.course')
            ->first();
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('EST-%s-%05d', $year, $count);
    }
}
