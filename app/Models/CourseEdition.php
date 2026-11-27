<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseEdition extends Model
{
    use PreventsPhysicalDeletion;

    protected $fillable = [
        'course_id',
        'code',
        'name',
        'shift_id',
        'modality_id',
        'classroom_id',
        'teacher_id',
        'start_date',
        'end_date',
        'max_capacity',
        'enrollment_fee',
        'monthly_fee',
        'installments_count',
        'status',
        'is_public_enrollment_open',
        'closed_at',
        'closed_by',
        'closure_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'enrollment_fee' => 'decimal:2',
            'monthly_fee' => 'decimal:2',
            'is_public_enrollment_open' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AcademicShift::class, 'shift_id');
    }

    public function modality(): BelongsTo
    {
        return $this->belongsTo(AcademicModality::class, 'modality_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EditionSchedule::class)->where('is_active', true)->orderBy('day_of_week')->orderBy('start_minute');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollmentsCount(): int
    {
        return $this->enrollments()
            ->whereIn('status', ['preinscrita', 'documentacion_pendiente', 'confirmada', 'en_curso'])
            ->count();
    }

    public function availableSeats(): int
    {
        return max(0, (int) $this->max_capacity - $this->activeEnrollmentsCount());
    }

    public function waitlistCount(): int
    {
        return $this->enrollments()
            ->where('status', 'lista_espera')
            ->count();
    }

    /**
     * Inscripciones activas o en espera asociadas a esta cohorte que deben recibir aviso en un cierre institucional.
     */
    public function activeNotifiableEnrollments()
    {
        return $this->enrollments()
            ->whereIn('status', ['preinscrita', 'documentacion_pendiente', 'lista_espera', 'confirmada', 'en_curso'])
            ->with(['student', 'edition.course'])
            ->get();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['cancelada', 'finalizada'], true) || $this->closed_at !== null;
    }
}
