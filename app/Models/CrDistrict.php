<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrDistrict extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = ['canton_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function canton(): BelongsTo
    {
        return $this->belongsTo(CrCanton::class, 'canton_id');
    }
}
