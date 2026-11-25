<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;

class AcademicShift extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = ['code', 'name', 'start_time', 'end_time', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
