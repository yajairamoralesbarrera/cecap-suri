<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffContract extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'staff_member_id',
        'contract_number',
        'hiring_regime',
        'contract_mode',
        'title',
        'start_date',
        'end_date',
        'gross_salary_or_rate',
        'rate_unit',
        'ccss_employee_rate',
        'banco_popular_rate',
        'income_tax_rate',
        'accrues_aguinaldo',
        'accrues_vacations',
        'iva_rate',
        'contract_body',
        'attachment_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'gross_salary_or_rate' => 'decimal:2',
            'ccss_employee_rate' => 'decimal:2',
            'banco_popular_rate' => 'decimal:2',
            'income_tax_rate' => 'decimal:2',
            'accrues_aguinaldo' => 'boolean',
            'accrues_vacations' => 'boolean',
            'iva_rate' => 'decimal:2',
        ];
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(RecordAttachment::class, 'attachment_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StaffPayment::class)->latest('payment_date');
    }

    public static function nextNumber(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('CTR-%s-%04d', $year, $count);
    }
}
