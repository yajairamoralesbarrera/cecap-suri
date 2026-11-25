<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCharge extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'enrollment_id',
        'student_id',
        'payer_id',
        'concept',
        'charge_type',
        'installment_number',
        'due_date',
        'gross_amount',
        'scholarship_discount',
        'net_amount',
        'paid_amount',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'gross_amount' => 'decimal:2',
            'scholarship_discount' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function getBalanceAttribute(): float
    {
        return round(max(0, (float) $this->net_amount - (float) $this->paid_amount), 2);
    }

    public function getIsOverdueAttribute(): bool
    {
        return in_array($this->status, ['pendiente', 'parcial'], true)
            && $this->due_date !== null
            && $this->due_date->isPast();
    }
}
