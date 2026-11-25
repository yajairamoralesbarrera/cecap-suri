<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPayment extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'voucher_number',
        'staff_member_id',
        'staff_contract_id',
        'hiring_regime',
        'period_label',
        'payment_date',
        'gross_amount',
        'ccss_deduction',
        'banco_popular_deduction',
        'income_tax_deduction',
        'aguinaldo_provision',
        'vacation_provision',
        'iva_amount',
        'hacienda_electronic_invoice_key',
        'net_amount',
        'payment_method',
        'reference_number',
        'status',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'gross_amount' => 'decimal:2',
            'ccss_deduction' => 'decimal:2',
            'banco_popular_deduction' => 'decimal:2',
            'income_tax_deduction' => 'decimal:2',
            'aguinaldo_provision' => 'decimal:2',
            'vacation_provision' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(StaffContract::class, 'staff_contract_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function nextVoucherNumber(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('COM-%s-%05d', $year, $count);
    }
}
