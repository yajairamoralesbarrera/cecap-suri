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
        'closed_at',
        'closed_by',
        'closure_reason',
        'archived_at',
        'archived_by',
        'archive_reason',
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
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public static function defaultRequiredDocumentsFor(string $programType): array
    {
        if ($programType === 'curso_tecnico') {
            return ['cedula_dimex', 'atestado_academico'];
        }

        return ['cedula_dimex'];
    }

    public static function nextIncrementalCode(string $sourceCode): string
    {
        $clean = strtoupper(trim($sourceCode));
        if ($clean === '') {
            $clean = 'CUR-01';
        }

        if (preg_match('/^(.*?)(\d+)$/', $clean, $matches)) {
            $prefix = $matches[1];
            $numStr = $matches[2];
            $padLen = strlen($numStr);
            $nextNum = (int) $numStr + 1;
        } else {
            $prefix = $clean.'-';
            $padLen = 2;
            $nextNum = 2;
        }

        do {
            $candidate = $prefix.str_pad((string) $nextNum, $padLen, '0', STR_PAD_LEFT);
            $nextNum++;
        } while (self::where('code', $candidate)->exists());

        return $candidate;
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

    public function totalEnrollmentsCount(): int
    {
        return Enrollment::query()
            ->whereHas('edition', fn ($q) => $q->where('course_id', $this->id))
            ->count();
    }

    /**
     * Inscripciones activas o en espera asociadas a las ediciones de este curso que deben ser notificadas en un cierre institucional.
     */
    public function activeNotifiableEnrollments()
    {
        return Enrollment::query()
            ->with(['student', 'edition'])
            ->whereHas('edition', fn ($q) => $q->where('course_id', $this->id))
            ->whereIn('status', ['confirmada', 'preinscrita', 'documentacion_pendiente', 'lista_espera'])
            ->get();
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getProgramTypeLabelAttribute(): string
    {
        return config('cecap.program_types.'.$this->program_type, $this->program_type);
    }

    public function meetsRequiredEducationLevel(?string $studentEducationLevel): bool
    {
        $required = (string) ($this->required_education_level ?? 'sin_requisito');
        if ($required === '' || $required === 'sin_requisito') {
            return true;
        }

        $requiredRank = Picklist::rankFor('education_level', $required);
        if ($requiredRank === null) {
            return true;
        }

        $studentRank = Picklist::rankFor('education_level', $studentEducationLevel);
        if ($studentRank === null) {
            return false;
        }

        return $studentRank >= $requiredRank;
    }

    public function educationLevelRequirementError(?string $studentEducationLevel): ?string
    {
        if ($this->meetsRequiredEducationLevel($studentEducationLevel)) {
            return null;
        }

        $requiredLabel = Picklist::labelFor('education_level', $this->required_education_level);
        $studentLabel = Picklist::labelFor('education_level', $studentEducationLevel);

        return sprintf(
            'Nivel académico insuficiente para este curso: se requiere como mínimo «%s» y el nivel indicado es «%s».',
            $requiredLabel,
            $studentLabel
        );
    }
}
