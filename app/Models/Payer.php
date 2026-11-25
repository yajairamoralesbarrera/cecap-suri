<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;

class Payer extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'payer_type',
        'full_name',
        'identification_number',
        'email',
        'phone',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
