<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopParticipant extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'workshop_id',
        'student_id',
        'participant_name',
        'identification_number',
        'email',
        'phone',
        'attended',
        'amount_charged',
        'payment_method',
        'payment_status',
        'payment_reference',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'amount_charged' => 'decimal:2',
        ];
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
