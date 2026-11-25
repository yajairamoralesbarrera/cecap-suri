<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrProvince extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function cantons(): HasMany
    {
        return $this->hasMany(CrCanton::class, 'province_id');
    }
}
