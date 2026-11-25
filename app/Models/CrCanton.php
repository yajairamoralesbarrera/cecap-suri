<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrCanton extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = ['province_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(CrProvince::class, 'province_id');
    }

    public function districts(): HasMany
    {
        return $this->hasMany(CrDistrict::class, 'canton_id');
    }
}
