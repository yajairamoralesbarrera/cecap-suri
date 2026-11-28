<?php

namespace App\Livewire;

use App\Models\AttendanceRecord;
use App\Models\CourseEdition;
use App\Models\CourseSubject;
use App\Models\Enrollment;
use App\Models\GradeRecord;
use App\Models\Picklist;
use App\Models\SoftSkillRecord;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Seguimiento Académico')]
class TrackingPage extends Component
{
    public string $tab = 'attendance';

    public ?string $feedback = null;

    public ?int $selected_edition_id = null;

    // Asistencia móvil versionada
    public string $session_date = '';

    public ?int $attendance_subject_id = null;

    public array $attendance_statuses = [];

    public string $attendance_rectification_reason = '';

    // Consulta de Asistencias (por Curso, Materia y Estudiante)
    public ?int $report_course_id = null;

    public ?int $report_edition_id = null;

    public string $report_subject_id = '';

    public ?int $report_student_id = null;

    public string $report_student_search = '';

    public string $report_status = '';

    public string $report_date_from = '';

    public string $report_date_to = '';

    // Notas y rectificación auditada
    public ?int $grade_enrollment_id = null;

    public ?int $grade_subject_id = null;

    public string $grade_evaluation_name = 'Evaluación Parcial 1';

    public float $grade_score = 85;

    public float $grade_weight = 100;

    public ?int $rectifyingGradeId = null;

    public float $rectify_score = 0;

    public string $rectify_reason_code = 'error_digitacion';

    public string $rectify_notes = '';

    // Habilidades Blandas permanentes
    public ?int $skill_student_id = null;

    public ?int $skill_enrollment_id = null;

    public string $skill_name = 'Trabajo en Equipo y Comunicación Asertiva';

    public string $skill_level = 'competente';

    public int $skill_score = 90;

    public string $skill_observations = '';

    public function mount(): void
    {
        $this->session_date = now()->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();
    }

    public function updatedSelectedEditionId(): void
    {
        $this->attendance_subject_id = null;
        $this->loadAttendanceForDate();
    }

    public function updatedSessionDate(): void
    {
        $this->loadAttendanceForDate();
    }

    public function updatedAttendanceSubjectId(): void
    {
        $this->loadAttendanceForDate();
    }

    public function updatedReportCourseId(): void
    {
        $this->report_edition_id = null;
        $this->report_subject_id = '';
    }

    public function clearAttendanceReportFilters(): void
    {
        $this->reset([
            'report_course_id',
            'report_edition_id',
            'report_subject_id',
            'report_student_id',
            'report_student_search',
            'report_status',
            'report_date_from',
            'report_date_to',
        ]);
    }

    public function loadAttendanceForDate(): void
    {
        $this->attendance_statuses = [];
        if (! $this->selected_edition_id) {
            return;
        }

        $enrollments = Enrollment::where('course_edition_id', $this->selected_edition_id)
            ->whereIn('status', ['confirmada', 'en_curso', 'finalizada'])
            ->get();

        foreach ($enrollments as $enr) {
            $existing = AttendanceRecord::where('enrollment_id', $enr->id)
                ->whereDate('session_date', $this->session_date)
                ->where('is_current', true)
                ->when(
                    $this->attendance_subject_id,
                    fn ($q) => $q->where('course_subject_id', $this->attendance_subject_id),
                    fn ($q) => $q->whereNull('course_subject_id')
                )
                ->first();
            $this->attendance_statuses[$enr->id] = $existing?->status ?? 'presente';
        }
    }

    public function setAttendanceStatus(int $enrollmentId, string $status): void
    {
        $this->attendance_statuses[$enrollmentId] = $status;
    }

    public function saveAttendance(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.attendance.manage'), 403);
        if (! $this->selected_edition_id) {
            $this->addError('selected_edition_id', 'Seleccione una cohorte primero.');

            return;
        }

        $enrollments = Enrollment::where('course_edition_id', $this->selected_edition_id)
            ->whereIn('status', ['confirmada', 'en_curso', 'finalizada'])
            ->get();

        DB::transaction(function () use ($enrollments): void {
            foreach ($enrollments as $enr) {
                $status = $this->attendance_statuses[$enr->id] ?? 'presente';
                $current = AttendanceRecord::where('enrollment_id', $enr->id)
                    ->whereDate('session_date', $this->session_date)
                    ->where('is_current', true)
                    ->when(
                        $this->attendance_subject_id,
                        fn ($q) => $q->where('course_subject_id', $this->attendance_subject_id),
                        fn ($q) => $q->whereNull('course_subject_id')
                    )
                    ->first();

                if ($current && $current->status === $status) {
                    continue;
                }

                $nextVersion = 1;
                if ($current) {
                    $nextVersion = $current->version + 1;
                    $current->update(['is_current' => false]);
                }

                $record = AttendanceRecord::create([
                    'course_edition_id' => $this->selected_edition_id,
                    'course_subject_id' => $this->attendance_subject_id ?: null,
                    'enrollment_id' => $enr->id,
                    'student_id' => $enr->student_id,
                    'session_date' => $this->session_date,
                    'status' => $status,
                    'version' => $nextVersion,
                    'is_current' => true,
                    'rectification_reason' => $nextVersion > 1 ? ($this->attendance_rectification_reason ?: 'Actualización de asistencia en sesión') : null,
                    'recorded_by' => Auth::id(),
                ]);

                AuditService::log('tracking.attendance_recorded', 'seguimiento', $record, null, [
                    'status' => $status,
                    'version' => $nextVersion,
                    'course_subject_id' => $this->attendance_subject_id ?: null,
                ]);
            }
        });

        $this->feedback = 'Asistencia versionada guardada correctamente.';
    }

    public function recordGrade(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.grades.manage'), 403);

        $this->validate([
            'grade_enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'grade_evaluation_name' => ['required', 'string', 'max:120'],
            'grade_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'grade_weight' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $enr = Enrollment::findOrFail($this->grade_enrollment_id);

        $grade = GradeRecord::create([
            'enrollment_id' => $enr->id,
            'student_id' => $enr->student_id,
            'course_subject_id' => $this->grade_subject_id ?: null,
            'evaluation_name' => trim($this->grade_evaluation_name),
            'score' => $this->grade_score,
            'weight_percent' => $this->grade_weight,
            'version' => 1,
            'is_current' => true,
            'recorded_by' => Auth::id(),
        ]);

        AuditService::log('tracking.grade_recorded', 'seguimiento', $grade, null, $grade->only(['evaluation_name', 'score', 'weight_percent']));
        $this->feedback = 'Calificación registrada correctamente.';
    }

    public function markEnrollmentUnapproved(int $enrollmentId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.grades.manage'), 403);

        $enr = Enrollment::with(['student', 'edition.course'])->findOrFail($enrollmentId);
        if ($enr->status === 'graduada') {
            $this->addError('grade_enrollment_id', 'No se puede marcar como sin aprobar una matrícula ya graduada.');

            return;
        }

        $oldStatus = $enr->status;
        $enr->update([
            'status' => 'reprobada',
            'updated_by' => Auth::id(),
        ]);

        \App\Models\StudentLifecycleEvent::create([
            'student_id' => $enr->student_id,
            'enrollment_id' => $enr->id,
            'event_type' => 'curso_sin_aprobar',
            'from_status' => $oldStatus,
            'to_status' => 'reprobada',
            'description' => sprintf('Matrícula %s marcada como Sin Aprobar (Reprobada) en %s. Podrá re-inscribirse posteriormente con autorización.', $enr->enrollment_code, $enr->edition?->course?->name ?? 'el curso'),
            'recorded_by' => Auth::id(),
            'created_at' => now(),
        ]);

        AuditService::log(
            event: 'tracking.enrollment_marked_unapproved',
            module: 'seguimiento',
            auditable: $enr,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'reprobada'],
            reason: Enrollment::DEFAULT_REENROLLMENT_REASON
        );

        $this->feedback = sprintf('Matrícula %s (%s) marcada como Sin Aprobar (Reprobada). La alumna podrá inscribirse nuevamente con autorización.', $enr->enrollment_code, $enr->student?->full_name);
    }

    public function startRectifyGrade(int $gradeId): void
    {
        $grade = GradeRecord::findOrFail($gradeId);
        $this->rectifyingGradeId = $grade->id;
        $this->rectify_score = (float) $grade->score;
        $this->rectify_notes = '';
    }

    public function cancelRectifyGrade(): void
    {
        $this->rectifyingGradeId = null;
    }

    public function submitGradeRectification(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.grades.rectify'), 403);
        if (! $this->rectifyingGradeId) {
            return;
        }

        $this->validate([
            'rectify_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'rectify_reason_code' => ['required', 'string', 'max:80'],
            'rectify_notes' => ['required', 'string', 'min:5'],
        ]);

        $oldGrade = GradeRecord::findOrFail($this->rectifyingGradeId);

        DB::transaction(function () use ($oldGrade): void {
            $oldGrade->update(['is_current' => false]);

            $newGrade = GradeRecord::create([
                'enrollment_id' => $oldGrade->enrollment_id,
                'student_id' => $oldGrade->student_id,
                'course_subject_id' => $oldGrade->course_subject_id,
                'evaluation_name' => $oldGrade->evaluation_name,
                'score' => $this->rectify_score,
                'weight_percent' => $oldGrade->weight_percent,
                'version' => $oldGrade->version + 1,
                'is_current' => true,
                'rectification_reason_code' => $this->rectify_reason_code,
                'rectification_notes' => $this->rectify_notes,
                'previous_grade_id' => $oldGrade->id,
                'recorded_by' => Auth::id(),
            ]);

            AuditService::log(
                event: 'tracking.grade_rectified',
                module: 'seguimiento',
                auditable: $newGrade,
                oldValues: ['score' => (float) $oldGrade->score, 'version' => $oldGrade->version],
                newValues: ['score' => (float) $newGrade->score, 'version' => $newGrade->version, 'reason_code' => $this->rectify_reason_code],
                reason: $this->rectify_notes
            );
        });

        $this->rectifyingGradeId = null;
        $this->feedback = 'Calificación rectificada bajo nueva versión auditada (historial íntegro preservado).';
    }

    public function recordSoftSkill(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.soft_skills.manage'), 403);

        $this->validate([
            'skill_student_id' => ['required', 'integer', 'exists:students,id'],
            'skill_name' => ['required', 'string', 'max:120'],
            'skill_level' => ['required', 'in:en_desarrollo,competente,destacado'],
            'skill_score' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $record = SoftSkillRecord::create([
            'student_id' => $this->skill_student_id,
            'enrollment_id' => $this->skill_enrollment_id ?: null,
            'skill_name' => trim($this->skill_name),
            'level' => $this->skill_level,
            'score' => $this->skill_score,
            'observations' => $this->skill_observations ?: null,
            'evaluated_on' => now()->toDateString(),
            'evaluated_by' => Auth::id(),
        ]);

        AuditService::log('tracking.soft_skill_recorded', 'seguimiento', $record, null, $record->only(['skill_name', 'level', 'score']));
        $this->skill_observations = '';
        $this->feedback = 'Habilidad blanda permanente registrada y disponible en Constancia PDF.';
    }

    public function render()
    {
        $user = Auth::user();
        $editionsQuery = CourseEdition::query()->with(['course.subjects'])->orderByDesc('id');

        if ($user->hasRole('profesora') && ! $user->hasAnyRole(['super_admin', 'direccion', 'coordinacion_academica', 'coordinador_categoria'])) {
            $editionsQuery->where('teacher_id', $user->id);
        } elseif ($user->isCategoryScopedCoordinator()) {
            $editionsQuery->whereHas('course', fn ($q) => $q->whereIn('academic_category_id', $user->activeAcademicCategoryIds()));
        }

        $editions = $editionsQuery->get();
        $selectedEdition = $this->selected_edition_id ? $editions->firstWhere('id', $this->selected_edition_id) : null;

        $editionEnrollments = $this->selected_edition_id
            ? Enrollment::where('course_edition_id', $this->selected_edition_id)
                ->whereIn('status', ['confirmada', 'en_curso', 'finalizada', 'reprobada'])
                ->with(['student', 'gradeRecords.subject', 'attendanceRecords', 'edition.course'])
                ->get()
            : collect();

        $accessibleEditionIds = $editions->pluck('id')->all();
        $reportCourses = $editions->pluck('course')->filter()->unique('id')->sortBy('name')->values();
        $reportEditions = $this->report_course_id
            ? $editions->where('course_id', $this->report_course_id)->values()
            : $editions;

        $reportCourseIds = $this->report_course_id
            ? [$this->report_course_id]
            : $reportCourses->pluck('id')->all();

        $reportSubjects = empty($reportCourseIds)
            ? collect()
            : CourseSubject::with('course')
                ->whereIn('course_id', $reportCourseIds)
                ->orderBy('course_id')
                ->orderBy('code')
                ->orderBy('name')
                ->get();

        $reportRecords = collect();
        if (! empty($accessibleEditionIds)) {
            $attQuery = AttendanceRecord::query()
                ->with(['student', 'edition.course', 'subject', 'recorder'])
                ->where('is_current', true)
                ->whereIn('course_edition_id', $accessibleEditionIds);

            if ($this->report_course_id) {
                $attQuery->whereHas('edition', fn ($q) => $q->where('course_id', $this->report_course_id));
            }
            if ($this->report_edition_id) {
                $attQuery->where('course_edition_id', $this->report_edition_id);
            }
            if ($this->report_subject_id === '__general__') {
                $attQuery->whereNull('course_subject_id');
            } elseif ($this->report_subject_id !== '') {
                $attQuery->where('course_subject_id', (int) $this->report_subject_id);
            }
            if ($this->report_student_id) {
                $attQuery->where('student_id', $this->report_student_id);
            }
            if (trim($this->report_student_search) !== '') {
                $term = '%'.mb_strtolower(trim($this->report_student_search)).'%';
                $attQuery->whereHas('student', function ($q) use ($term) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '') || ' ' || COALESCE(second_last_name, ''))) LIKE ?", [$term])
                        ->orWhereRaw('LOWER(identification_number) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(student_code) LIKE ?', [$term]);
                });
            }
            if ($this->report_status !== '') {
                $attQuery->where('status', $this->report_status);
            }
            if ($this->report_date_from !== '') {
                $attQuery->whereDate('session_date', '>=', $this->report_date_from);
            }
            if ($this->report_date_to !== '') {
                $attQuery->whereDate('session_date', '<=', $this->report_date_to);
            }

            $reportRecords = $attQuery
                ->orderByDesc('session_date')
                ->orderByDesc('id')
                ->limit(300)
                ->get();
        }

        $totalSessions = $reportRecords->count();
        $presentCount = $reportRecords->where('status', 'presente')->count();
        $lateCount = $reportRecords->where('status', 'tardia')->count();
        $excusedCount = $reportRecords->where('status', 'justificada')->count();
        $absentCount = $reportRecords->where('status', 'ausente')->count();
        $effectiveCount = $presentCount + $lateCount + $excusedCount;

        $reportSummary = [
            'total' => $totalSessions,
            'presente' => $presentCount,
            'tardia' => $lateCount,
            'justificada' => $excusedCount,
            'ausente' => $absentCount,
            'percent' => $totalSessions > 0 ? round(($effectiveCount / $totalSessions) * 100, 1) : 0.0,
        ];

        $reportByStudent = $reportRecords
            ->groupBy(fn (AttendanceRecord $r) => $r->student_id.'-'.$r->course_edition_id.'-'.($r->course_subject_id ?? 0))
            ->map(function ($group) {
                /** @var AttendanceRecord $first */
                $first = $group->first();
                $total = $group->count();
                $p = $group->where('status', 'presente')->count();
                $t = $group->where('status', 'tardia')->count();
                $j = $group->where('status', 'justificada')->count();
                $a = $group->where('status', 'ausente')->count();
                $minPct = (float) ($first->edition?->course?->min_attendance_percent ?? 80);
                $pct = $total > 0 ? round((($p + $t + $j) / $total) * 100, 1) : 0.0;

                return [
                    'student' => $first->student,
                    'course_name' => $first->edition?->course?->name ?? '—',
                    'edition_name' => $first->edition?->name ?? '—',
                    'subject_label' => $first->subject ? ($first->subject->code.' — '.$first->subject->name) : 'General del curso',
                    'presente' => $p,
                    'tardia' => $t,
                    'justificada' => $j,
                    'ausente' => $a,
                    'total' => $total,
                    'percent' => $pct,
                    'min_percent' => $minPct,
                    'passed' => $pct >= $minPct,
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower((string) ($row['student']?->full_name ?? '')))
            ->values();

        return view('livewire.tracking-page', [
            'editions' => $editions,
            'selectedEdition' => $selectedEdition,
            'editionEnrollments' => $editionEnrollments,
            'reportCourses' => $reportCourses,
            'reportEditions' => $reportEditions,
            'reportSubjects' => $reportSubjects,
            'reportRecords' => $reportRecords,
            'reportSummary' => $reportSummary,
            'reportByStudent' => $reportByStudent,
            'attendanceStatusLabels' => AttendanceRecord::statusLabels(),
            'rectificationReasons' => Picklist::optionsFor('grade_rectification_reason'),
            'students' => Student::orderBy('first_name')->orderBy('last_name')->limit(200)->get(),
            'softSkills' => SoftSkillRecord::with(['student', 'evaluator'])->where('is_archived', false)->latest('evaluated_on')->limit(50)->get(),
            'recentGrades' => GradeRecord::with(['student', 'enrollment.edition', 'subject'])->where('is_current', true)->latest()->limit(40)->get(),
        ]);
    }
}
