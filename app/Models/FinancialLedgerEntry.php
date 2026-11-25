<?php

namespace App\Models;

use App\Models\Concerns\ImmutableAppendOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialLedgerEntry extends Model
{
    use ImmutableAppendOnly;

    public $timestamps = false;

    protected $fillable = [
        'entry_code',
        'student_id',
        'enrollment_id',
        'fee_charge_id',
        'payment_receipt_id',
        'entry_type',
        'amount',
        'description',
        'recorded_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public static function nextEntryCode(): string
    {
        return sprintf('LED-%s-%06d', now()->format('Y'), static::query()->count() + 1);
    }
}
