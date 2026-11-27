<?php

namespace App\Models;

use App\Models\Concerns\PreventsPhysicalDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Enrollment extends Model
{
    use PreventsPhysicalDeletion;

    public const DEFAULT_REENROLLMENT_REASON = 'Curso anteriormente sin aprobar';

    protected $fillable = [
        'enrollment_code',
        'student_id',
        'course_edition_id',
        'source',
        'status',
        'provisional_expires_at',
        'confirmed_at',
        'confirmed_by',
        'required_documents_snapshot',
        'submitted_documents',
        'documents_complete',
        'exception_authorized',
        'exception_reason',
        'exception_commitment_date',
        'exception_authorized_by',
        'exception_authorized_at',
        'reenrollment_authorized',
        'reenrollment_reason',
        'reenrollment_authorized_by',
        'reenrollment_authorized_at',
        'rejection_reason_code',
        'rejection_notes',
        'rejected_at',
        'rejected_by',
        'capacity_decision_code',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'provisional_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'required_documents_snapshot' => 'array',
            'submitted_documents' => 'array',
            'documents_complete' => 'boolean',
            'exception_authorized' => 'boolean',
            'exception_commitment_date' => 'date',
            'exception_authorized_at' => 'datetime',
            'reenrollment_authorized' => 'boolean',
            'reenrollment_authorized_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(CourseEdition::class, 'course_edition_id');
    }

    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function exceptionAuthorizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exception_authorized_by');
    }

    public function reenrollmentAuthorizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reenrollment_authorized_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getRejectionReasonLabelAttribute(): ?string
    {
        if (! $this->rejection_reason_code) {
            return null;
        }

        return Picklist::labelFor('enrollment_rejection_reason', $this->rejection_reason_code);
    }

    public function getCapacityDecisionLabelAttribute(): ?string
    {
        if (! $this->capacity_decision_code) {
            return null;
        }

        return Picklist::labelFor('full_capacity_decision', $this->capacity_decision_code);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->where('is_current', true)->orderByDesc('session_date');
    }

    public function gradeRecords(): HasMany
    {
        return $this->hasMany(GradeRecord::class)->where('is_current', true)->orderBy('id');
    }

    public function feeCharges(): HasMany
    {
        return $this->hasMany(FeeCharge::class)->orderBy('due_date');
    }

    public function scholarships(): HasMany
    {
        return $this->hasMany(Scholarship::class)->latest();
    }

    public function graduationRecord(): HasOne
    {
        return $this->hasOne(GraduationRecord::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(RecordAttachment::class, 'attachable')->latest();
    }

    public function missingDocuments(): array
    {
        $required = $this->required_documents_snapshot ?? [];
        $submitted = $this->submitted_documents ?? [];

        return array_values(array_diff($required, $submitted));
    }

    /**
     * Nombres legibles en español de los documentos faltantes según el checklist del curso.
     *
     * @return list<string>
     */
    public function missingDocumentLabels(): array
    {
        $labels = config('cecap.document_types', []);

        return array_values(array_map(
            fn (string $key): string => (string) ($labels[$key] ?? $key),
            $this->missingDocuments()
        ));
    }

    public function calculateFinalGrade(): float
    {
        $grades = $this->gradeRecords()->get();
        if ($grades->isEmpty()) {
            return 0.0;
        }

        $totalWeight = (float) $grades->sum('weight_percent');
        if ($totalWeight <= 0) {
            return round((float) $grades->avg('score'), 2);
        }

        $weighted = $grades->sum(fn (GradeRecord $g) => ((float) $g->score) * ((float) $g->weight_percent));

        return round($weighted / $totalWeight, 2);
    }

    public function calculateAttendancePercent(): float
    {
        $records = $this->attendanceRecords()->get();
        if ($records->isEmpty()) {
            return 100.0;
        }

        $attended = $records->whereIn('status', ['presente', 'tardia', 'justificada'])->count();

        return round(($attended / $records->count()) * 100, 2);
    }

    public function pendingBalance(): float
    {
        return round((float) $this->feeCharges()
            ->whereIn('status', ['pendiente', 'parcial'])
            ->get()
            ->sum(fn (FeeCharge $c) => max(0, (float) $c->net_amount - (float) $c->paid_amount)), 2);
    }

    /**
     * Determina si esta matrícula corresponde a un curso, programa o materia sin aprobar
     * (por estado explícito reprobada, nota final/materia menor a la mínima, asistencia insuficiente o cohorte finalizada sin graduación).
     */
    public function isUnapproved(): bool
    {
        if (in_array($this->status, ['reprobada', 'no_aprobada'], true)) {
            return true;
        }

        if (in_array($this->status, ['graduada', 'rechazada', 'preinscrita', 'documentacion_pendiente', 'lista_espera'], true)) {
            return false;
        }

        $this->loadMissing(['edition.course', 'gradeRecords.subject', 'attendanceRecords']);
        $course = $this->edition?->course;
        $minGrade = (float) ($course?->min_passing_grade ?? 70);
        $minAttendance = (float) ($course?->min_attendance_percent ?? 80);

        $grades = $this->gradeRecords;
        if ($grades->isNotEmpty()) {
            if ($this->calculateFinalGrade() < $minGrade) {
                return true;
            }

            // Verificar si alguna materia individual quedó sin aprobar
            $bySubject = $grades->whereNotNull('course_subject_id')->groupBy('course_subject_id');
            foreach ($bySubject as $subjectGrades) {
                $totalWeight = (float) $subjectGrades->sum('weight_percent');
                $avg = $totalWeight > 0
                    ? round((float) $subjectGrades->sum(fn (GradeRecord $g) => ((float) $g->score) * ((float) $g->weight_percent)) / $totalWeight, 2)
                    : round((float) $subjectGrades->avg('score'), 2);
                if ($avg < $minGrade) {
                    return true;
                }
            }
        }

        $attendance = $this->attendanceRecords;
        if ($attendance->isNotEmpty() && $this->calculateAttendancePercent() < $minAttendance) {
            return true;
        }

        if ($this->edition && $this->edition->status === 'finalizada') {
            return true;
        }

        return false;
    }

    public function unapprovedSummary(): string
    {
        if (in_array($this->status, ['reprobada', 'no_aprobada'], true)) {
            return 'estado Reprobada / Sin aprobar';
        }

        $this->loadMissing(['edition.course', 'gradeRecords.subject', 'attendanceRecords']);
        $course = $this->edition?->course;
        $minGrade = (float) ($course?->min_passing_grade ?? 70);
        $minAttendance = (float) ($course?->min_attendance_percent ?? 80);

        $reasons = [];
        $grades = $this->gradeRecords;
        if ($grades->isNotEmpty()) {
            $finalGrade = $this->calculateFinalGrade();
            if ($finalGrade < $minGrade) {
                $reasons[] = sprintf('nota final %.2f/%.2f', $finalGrade, $minGrade);
            }
            $bySubject = $grades->whereNotNull('course_subject_id')->groupBy('course_subject_id');
            foreach ($bySubject as $subjectGrades) {
                $totalWeight = (float) $subjectGrades->sum('weight_percent');
                $avg = $totalWeight > 0
                    ? round((float) $subjectGrades->sum(fn (GradeRecord $g) => ((float) $g->score) * ((float) $g->weight_percent)) / $totalWeight, 2)
                    : round((float) $subjectGrades->avg('score'), 2);
                if ($avg < $minGrade) {
                    $subName = $subjectGrades->first()?->subject?->name ?? 'materia';
                    $reasons[] = sprintf('materia %s sin aprobar (%.2f/%.2f)', $subName, $avg, $minGrade);
                }
            }
        }

        $attendance = $this->attendanceRecords;
        if ($attendance->isNotEmpty()) {
            $attPct = $this->calculateAttendancePercent();
            if ($attPct < $minAttendance) {
                $reasons[] = sprintf('asistencia %.0f%%/%.0f%%', $attPct, $minAttendance);
            }
        }

        if (empty($reasons) && $this->edition && $this->edition->status === 'finalizada') {
            $reasons[] = 'cohorte finalizada sin aprobar';
        }

        return empty($reasons) ? 'curso anteriormente sin aprobar' : implode('; ', $reasons);
    }

    public static function nextCode(): string
    {
        $year = now()->format('Y');
        $count = static::query()->whereYear('created_at', $year)->count() + 1;

        return sprintf('MAT-%s-%05d', $year, $count);
    }
}
