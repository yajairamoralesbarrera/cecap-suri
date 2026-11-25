<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;

class DiplomaTemplate extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'name',
        'program_type',
        'header_title',
        'body_template',
        'primary_color',
        'accent_color',
        'border_style',
        'signatory_one_name',
        'signatory_one_title',
        'signatory_two_name',
        'signatory_two_title',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
