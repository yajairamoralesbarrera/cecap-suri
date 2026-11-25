<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = ['code', 'name', 'capacity', 'location', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
