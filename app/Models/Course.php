<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'academic_category_id',
        'code',
        'name',
        'program_type',
        'description',
        'required_education_level',
        'default_max_capacity',
        'duration_hours',
        'duration_months',
        'base_enrollment_fee',
        'base_monthly_fee',
        'required_documents',
        'min_passing_grade',
        'min_attendance_percent',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'required_documents' => 'array',
            'base_enrollment_fee' => 'decimal:2',
            'base_monthly_fee' => 'decimal:2',
            'min_passing_grade' => 'decimal:2',
            'min_attendance_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public static function defaultRequiredDocumentsFor(string $programType): array
    {
        if ($programType === 'curso_tecnico') {
            return ['cedula_dimex', 'atestado_academico'];
        }

        return ['cedula_dimex'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AcademicCategory::class, 'academic_category_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(CourseSubject::class)->orderBy('order_index');
    }

    public function editions(): HasMany
    {
        return $this->hasMany(CourseEdition::class);
    }

    public function getProgramTypeLabelAttribute(): string
    {
        return config('cecap.program_types.'.$this->program_type, $this->program_type);
    }
}
