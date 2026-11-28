<?php

namespace App\Livewire;

use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\CourseSubject;
use App\Models\EditionEvaluation;
use App\Models\Enrollment;
use App\Models\GradeRecord;
use App\Models\Picklist;
use App\Models\SoftSkillAttendance;
use App\Models\SoftSkillRecord;
use App\Models\SoftSkillWeek;
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

    // Consulta de Asistencias (por Curso, Materia y Estudiante + Habilidades Blandas)
    public string $report_mode = 'courses'; // courses | soft_skills

    public ?int $report_course_id = null;

    public ?int $report_edition_id = null;

    public string $report_subject_id = '';

    public ?int $report_student_id = null;

    public string $report_student_search = '';

    public string $report_status = '';

    public string $report_date_from = '';

    public string $report_date_to = '';

    public ?int $ss_report_week_id = null;

    public ?int $ss_report_course_id = null;

    public string $ss_report_search = '';

    public string $ss_report_status = '';

    // Paso 1: Plan de Evaluaciones y Porcentajes por Cohorte / Materia
    public ?int $eval_subject_id = null;

    public string $eval_name = '';

    public float|string $eval_weight = 25;

    public string $eval_due_date = '';

    // Paso 2: Carga masiva de notas por evaluación (todas las alumnas del grupo)
    public ?int $selected_evaluation_id = null;

    public array $evaluation_scores = [];

    // Notas individuales (compatibilidad) y rectificación auditada
    public ?int $grade_enrollment_id = null;

    public ?int $grade_subject_id = null;

    public string $grade_evaluation_name = 'Evaluación Parcial 1';

    public float $grade_score = 85;

    public float $grade_weight = 100;

    public ?int $rectifyingGradeId = null;

    public float $rectify_score = 0;

    public string $rectify_reason_code = 'error_digitacion';

    public string $rectify_notes = '';

    // Habilidades Blandas: Asistencia Semanal (cualquier horario)
    public string $ss_week_start = '';

    public string $ss_week_end = '';

    public string $ss_week_label = '';

    public bool $ss_week_no_session = false;

    public string $ss_week_no_session_reason = '';

    public ?int $selected_ss_week_id = null;

    public string $ss_search = '';

    public ?int $ss_filter_course_id = null;

    public string $ss_filter_status = '';

    public array $ss_statuses = [];

    public array $ss_schedule_notes = [];

    // Habilidades Blandas permanentes (Evaluación cualitativa / Constancia PDF)
    public ?int $skill_student_id = null;

    public ?int $skill_enrollment_id = null;

    public string $skill_name = 'Trabajo en Equipo y Comunicación Asertiva';

    public string $skill_level = 'competente';

    public int $skill_score = 90;

    public string $skill_observations = '';

    public function mount(): void
    {
        $this->session_date = now()->toDateString();
        $this->ss_week_start = now()->startOfWeek()->toDateString();
        $this->ss_week_end = now()->endOfWeek()->toDateString();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();

        if ($tab === 'soft_skills' && ! $this->selected_ss_week_id) {
            $latestWeek = SoftSkillWeek::orderByDesc('week_start_date')->orderByDesc('id')->first();
            if ($latestWeek) {
                $this->selectSoftSkillWeek($latestWeek->id);
            }
        }
    }

    public function updatedSelectedEditionId(): void
    {
        $this->attendance_subject_id = null;
        $this->eval_subject_id = null;
        $this->selected_evaluation_id = null;
        $this->evaluation_scores = [];
        $this->rectifyingGradeId = null;
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

    public function setReportMode(string $mode): void
    {
        $this->report_mode = in_array($mode, ['courses', 'soft_skills'], true) ? $mode : 'courses';
    }

    public function openSoftSkillsReport(): void
    {
        $this->tab = 'attendance_report';
        $this->report_mode = 'soft_skills';
        $this->feedback = null;
        $this->resetValidation();
    }

    public function clearSoftSkillReportFilters(): void
    {
        $this->reset([
            'ss_report_week_id',
            'ss_report_course_id',
            'ss_report_search',
            'ss_report_status',
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

    public function createEvaluation(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.grades.manage'), 403);
        $this->resetValidation();

        if (! $this->selected_edition_id) {
            $this->addError('selected_edition_id', 'Seleccione primero una cohorte para definir sus evaluaciones.');

            return;
        }

        $this->validate([
            'eval_name' => ['required', 'string', 'max:120'],
            'eval_weight' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'eval_due_date' => ['nullable', 'date'],
        ], [
            'eval_name.required' => 'Indique el nombre de la evaluación (ej. Examen Parcial 1, Proyecto).',
            'eval_weight.required' => 'Indique el porcentaje que vale esta evaluación.',
            'eval_weight.min' => 'El porcentaje debe ser mayor a 0%.',
            'eval_weight.max' => 'El porcentaje no puede superar 100%.',
        ]);

        $subjectId = $this->eval_subject_id ?: null;
        $currentSum = (float) EditionEvaluation::query()
            ->where('course_edition_id', $this->selected_edition_id)
            ->when(
                $subjectId,
                fn ($q) => $q->where('course_subject_id', $subjectId),
                fn ($q) => $q->whereNull('course_subject_id')
            )
            ->where('is_active', true)
            ->sum('weight_percent');

        $weight = round((float) $this->eval_weight, 2);
        if (($currentSum + $weight) > 100.001) {
            $remaining = max(0, round(100 - $currentSum, 2));
            $this->addError(
                'eval_weight',
                sprintf('La suma de evaluaciones supera el 100%% (ya configurado: %s%%, disponible: %s%%).', number_format($currentSum, 2), number_format($remaining, 2))
            );

            return;
        }

        $evaluation = EditionEvaluation::create([
            'course_edition_id' => $this->selected_edition_id,
            'course_subject_id' => $subjectId,
            'name' => trim($this->eval_name),
            'weight_percent' => $weight,
            'due_date' => $this->eval_due_date !== '' ? $this->eval_due_date : null,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        AuditService::log('tracking.evaluation_created', 'seguimiento', $evaluation, null, $evaluation->only(['course_edition_id', 'course_subject_id', 'name', 'weight_percent', 'due_date']));

        $remainingAfter = max(0, round(100 - ($currentSum + $weight), 2));
        $this->eval_name = '';
        $this->eval_due_date = '';
        $this->eval_weight = $remainingAfter > 0 ? $remainingAfter : 10;

        $this->selectEvaluation($evaluation->id);
        $this->feedback = sprintf('Evaluación "%s" (%s%%) registrada. Ahora puede cargar las notas de todas las alumnas.', $evaluation->name, number_format($weight, 2));
    }

    public function toggleEvaluationActive(int $evaluationId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.grades.manage'), 403);
        $eval = EditionEvaluation::findOrFail($evaluationId);

        if (! $eval->is_active) {
            $currentSum = (float) EditionEvaluation::query()
                ->where('course_edition_id', $eval->course_edition_id)
                ->when(
                    $eval->course_subject_id,
                    fn ($q) => $q->where('course_subject_id', $eval->course_subject_id),
                    fn ($q) => $q->whereNull('course_subject_id')
                )
                ->where('is_active', true)
                ->sum('weight_percent');

            if (($currentSum + (float) $eval->weight_percent) > 100.001) {
                $this->addError('eval_weight', sprintf('No se puede reactivar "%s" (%s%%) porque excedería el 100%% configurado.', $eval->name, number_format((float) $eval->weight_percent, 2)));

                return;
            }
        }

        $next = ! $eval->is_active;
        $eval->update(['is_active' => $next]);

        if (! $next && $this->selected_evaluation_id === $eval->id) {
            $this->selected_evaluation_id = null;
            $this->evaluation_scores = [];
        }

        AuditService::log($next ? 'tracking.evaluation_activated' : 'tracking.evaluation_deactivated', 'seguimiento', $eval);
        $this->feedback = $next
            ? sprintf('Evaluación "%s" reactivada.', $eval->name)
            : sprintf('Evaluación "%s" inactivada (sin borrado físico).', $eval->name);
    }

    public function selectEvaluation(?int $evaluationId): void
    {
        $this->selected_evaluation_id = $evaluationId;
        $this->rectifyingGradeId = null;
        $this->resetValidation();
        $this->loadEvaluationRoster();
    }

    public function updatedSelectedEvaluationId(): void
    {
        $this->rectifyingGradeId = null;
        $this->resetValidation();
        $this->loadEvaluationRoster();
    }

    public function loadEvaluationRoster(): void
    {
        $this->evaluation_scores = [];
        if (! $this->selected_edition_id || ! $this->selected_evaluation_id) {
            return;
        }

        $enrollments = Enrollment::where('course_edition_id', $this->selected_edition_id)
            ->whereIn('status', ['confirmada', 'en_curso', 'finalizada', 'reprobada'])
            ->get();

        $existingGrades = GradeRecord::where('edition_evaluation_id', $this->selected_evaluation_id)
            ->where('is_current', true)
            ->get()
            ->keyBy('enrollment_id');

        foreach ($enrollments as $enr) {
            $existing = $existingGrades->get($enr->id);
            $this->evaluation_scores[$enr->id] = $existing ? (string) ((float) $existing->score) : '';
        }
    }

    public function saveEvaluationGrades(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('seguimiento.grades.manage'), 403);
        $this->resetValidation();

        if (! $this->selected_edition_id || ! $this->selected_evaluation_id) {
            $this->addError('selected_evaluation_id', 'Seleccione una evaluación antes de guardar las notas.');

            return;
        }

        $evaluation = EditionEvaluation::where('course_edition_id', $this->selected_edition_id)
            ->where('is_active', true)
            ->findOrFail($this->selected_evaluation_id);

        $enrollments = Enrollment::where('course_edition_id', $this->selected_edition_id)
            ->whereIn('status', ['confirmada', 'en_curso', 'finalizada', 'reprobada'])
            ->with('student')
            ->get();

        $existingByEnrollment = GradeRecord::where('edition_evaluation_id', $evaluation->id)
            ->where('is_current', true)
            ->get()
            ->keyBy('enrollment_id');

        $toInsert = [];
        foreach ($enrollments as $enr) {
            if ($existingByEnrollment->has($enr->id)) {
                continue;
            }

            $raw = $this->evaluation_scores[$enr->id] ?? '';
            if ($raw === '' || $raw === null) {
                continue;
            }

            if (! is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
                $this->addError(
                    'evaluation_scores.'.$enr->id,
                    sprintf('La nota de %s debe estar entre 0 y 100.', $enr->student?->full_name ?? $enr->enrollment_code)
                );

                return;
            }

            $toInsert[] = [
                'enrollment' => $enr,
                'score' => round((float) $raw, 2),
            ];
        }

        if (empty($toInsert)) {
            $this->addError('selected_evaluation_id', 'Ingrese al menos una nota pendiente (0 a 100) para guardar. Para cambiar notas ya guardadas use el botón Rectificar.');

            return;
        }

        DB::transaction(function () use ($evaluation, $toInsert): void {
            foreach ($toInsert as $item) {
                /** @var Enrollment $enr */
                $enr = $item['enrollment'];
                $grade = GradeRecord::create([
                    'enrollment_id' => $enr->id,
                    'student_id' => $enr->student_id,
                    'course_subject_id' => $evaluation->course_subject_id,
                    'edition_evaluation_id' => $evaluation->id,
                    'evaluation_name' => $evaluation->name,
                    'score' => $item['score'],
                    'weight_percent' => $evaluation->weight_percent,
                    'version' => 1,
                    'is_current' => true,
                    'recorded_by' => Auth::id(),
                ]);

                AuditService::log('tracking.grade_recorded', 'seguimiento', $grade, null, $grade->only(['edition_evaluation_id', 'evaluation_name', 'score', 'weight_percent']));
            }
        });

        $this->loadEvaluationRoster();
        $count = count($toInsert);
        $this->feedback = sprintf(
            '%d %s guardada(s) correctamente para la evaluación "%s" (%s%%).',
            $count,
            $count === 1 ? 'nota' : 'notas',
            $evaluation->name,
            number_format((float) $evaluation->weight_percent, 2)
        );
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
                'edition_evaluation_id' => $oldGrade->edition_evaluation_id,
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
        $this->loadEvaluationRoster();
        $this->feedback = 'Calificación rectificada bajo nueva versión auditada (historial íntegro preservado).';
    }

    private function canManageSoftSkillsAttendance(): bool
    {
        $user = Auth::user();

        return $user->hasEffectivePermission('seguimiento.soft_skills.manage')
            || $user->hasEffectivePermission('seguimiento.attendance.manage');
    }

    public function createSoftSkillWeek(): void
    {
        abort_unless($this->canManageSoftSkillsAttendance(), 403);
        $this->resetValidation();

        $this->validate([
            'ss_week_start' => ['required', 'date'],
            'ss_week_end' => ['required', 'date', 'after_or_equal:ss_week_start'],
            'ss_week_label' => ['nullable', 'string', 'max:140'],
            'ss_week_no_session_reason' => ['nullable', 'string', 'max:255'],
        ], [
            'ss_week_start.required' => 'Indique la fecha Desde de la semana.',
            'ss_week_end.required' => 'Indique la fecha Hasta de la semana.',
            'ss_week_end.after_or_equal' => 'La fecha Hasta debe ser igual o posterior a la fecha Desde.',
        ]);

        $hasSession = ! $this->ss_week_no_session;

        $week = SoftSkillWeek::create([
            'week_start_date' => $this->ss_week_start,
            'week_end_date' => $this->ss_week_end,
            'label' => trim($this->ss_week_label) !== '' ? trim($this->ss_week_label) : null,
            'has_session' => $hasSession,
            'no_session_reason' => ! $hasSession
                ? (trim($this->ss_week_no_session_reason) !== '' ? trim($this->ss_week_no_session_reason) : 'Sin sesión de Habilidades Blandas esta semana')
                : null,
            'created_by' => Auth::id(),
        ]);

        AuditService::log('tracking.soft_skill_week_created', 'seguimiento', $week, null, $week->only([
            'week_start_date', 'week_end_date', 'label', 'has_session', 'no_session_reason',
        ]));

        $this->ss_week_label = '';
        $this->ss_week_no_session = false;
        $this->ss_week_no_session_reason = '';
        $this->selectSoftSkillWeek($week->id);

        $this->feedback = $hasSession
            ? sprintf('Semana %s creada. Ya puede pasar asistencia a todas las alumnas inscritas.', $week->range_label)
            : sprintf('Semana %s marcada SIN Habilidades Blandas (no generará ausencias).', $week->range_label);
    }

    public function toggleSoftSkillWeekHasSession(int $weekId): void
    {
        abort_unless($this->canManageSoftSkillsAttendance(), 403);
        $week = SoftSkillWeek::findOrFail($weekId);
        $next = ! $week->has_session;

        $week->update([
            'has_session' => $next,
            'no_session_reason' => $next ? null : ($week->no_session_reason ?: 'Semana marcada sin Habilidades Blandas'),
        ]);

        AuditService::log('tracking.soft_skill_week_toggled', 'seguimiento', $week, ['has_session' => ! $next], ['has_session' => $next]);
        $this->feedback = $next
            ? sprintf('Semana %s habilitada para pase de asistencia de Habilidades Blandas.', $week->range_label)
            : sprintf('Semana %s marcada SIN Habilidades Blandas (no cuenta como ausencia para ninguna alumna).', $week->range_label);
    }

    public function selectSoftSkillWeek(?int $weekId): void
    {
        $this->selected_ss_week_id = $weekId;
        $this->resetValidation();
        $this->loadSoftSkillWeekAttendance();
    }

    public function updatedSelectedSsWeekId(): void
    {
        $this->resetValidation();
        $this->loadSoftSkillWeekAttendance();
    }

    public function loadSoftSkillWeekAttendance(): void
    {
        $this->ss_statuses = [];
        $this->ss_schedule_notes = [];

        if (! $this->selected_ss_week_id) {
            return;
        }

        $records = SoftSkillAttendance::where('soft_skill_week_id', $this->selected_ss_week_id)->get();
        foreach ($records as $rec) {
            $this->ss_statuses[$rec->student_id] = $rec->status;
            $this->ss_schedule_notes[$rec->student_id] = (string) ($rec->schedule_notes ?? '');
        }
    }

    public function setSoftSkillStatus(int $studentId, string $status): void
    {
        if (! in_array($status, ['presente', 'justificada', 'ausente'], true)) {
            return;
        }

        $this->ss_statuses[$studentId] = $status;
    }

    public function clearSoftSkillFilters(): void
    {
        $this->reset(['ss_search', 'ss_filter_course_id', 'ss_filter_status']);
    }

    public function saveSoftSkillAttendance(): void
    {
        abort_unless($this->canManageSoftSkillsAttendance(), 403);
        $this->resetValidation();

        if (! $this->selected_ss_week_id) {
            $this->addError('selected_ss_week_id', 'Seleccione o cree primero una semana (Desde - Hasta) de Habilidades Blandas.');

            return;
        }

        $week = SoftSkillWeek::findOrFail($this->selected_ss_week_id);
        if (! $week->has_session) {
            $this->addError('selected_ss_week_id', 'Esta semana está marcada SIN Habilidades Blandas. Habilítela primero si desea registrar asistencia.');

            return;
        }

        $savedCount = 0;
        DB::transaction(function () use ($week, &$savedCount): void {
            foreach ($this->ss_statuses as $studentId => $status) {
                if (! in_array($status, ['presente', 'justificada', 'ausente'], true)) {
                    continue;
                }

                $sid = (int) $studentId;
                $scheduleNote = trim((string) ($this->ss_schedule_notes[$sid] ?? ''));

                $record = SoftSkillAttendance::updateOrCreate(
                    [
                        'soft_skill_week_id' => $week->id,
                        'student_id' => $sid,
                    ],
                    [
                        'status' => $status,
                        'schedule_notes' => $scheduleNote !== '' ? $scheduleNote : null,
                        'recorded_by' => Auth::id(),
                    ]
                );

                AuditService::log('tracking.soft_skill_attendance_saved', 'seguimiento', $record, null, [
                    'soft_skill_week_id' => $week->id,
                    'student_id' => $sid,
                    'status' => $status,
                    'schedule_notes' => $scheduleNote !== '' ? $scheduleNote : null,
                ]);
                $savedCount++;
            }
        });

        if ($savedCount === 0) {
            $this->addError('selected_ss_week_id', 'Marque al menos una alumna como Presente, Justificada o Ausente antes de guardar.');

            return;
        }

        $this->loadSoftSkillWeekAttendance();
        $this->feedback = sprintf(
            'Asistencia semanal de Habilidades Blandas guardada para %d alumna(s) (%s).',
            $savedCount,
            $week->range_label
        );
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

        $editionEvaluations = $this->selected_edition_id
            ? EditionEvaluation::with(['subject', 'gradeRecords'])
                ->where('course_edition_id', $this->selected_edition_id)
                ->orderByDesc('is_active')
                ->orderBy('course_subject_id')
                ->orderBy('id')
                ->get()
            : collect();

        $activeEvaluations = $editionEvaluations->where('is_active', true)->values();
        $selectedEvaluation = $this->selected_evaluation_id
            ? $activeEvaluations->firstWhere('id', $this->selected_evaluation_id)
            : null;

        $evaluationGradesByEnrollment = $selectedEvaluation
            ? GradeRecord::where('edition_evaluation_id', $selectedEvaluation->id)
                ->where('is_current', true)
                ->get()
                ->keyBy('enrollment_id')
            : collect();

        $scopeSubjectId = $this->eval_subject_id ?: null;
        $scopeWeightUsed = round((float) $activeEvaluations
            ->filter(fn (EditionEvaluation $ev) => $scopeSubjectId ? ((int) $ev->course_subject_id === (int) $scopeSubjectId) : is_null($ev->course_subject_id))
            ->sum('weight_percent'), 2);
        $scopeWeightRemaining = max(0, round(100 - $scopeWeightUsed, 2));

        $recentGradesQuery = GradeRecord::with(['student', 'enrollment.edition', 'subject', 'evaluation'])
            ->where('is_current', true)
            ->latest();

        if ($this->selected_edition_id) {
            $recentGradesQuery->whereHas('enrollment', fn ($q) => $q->where('course_edition_id', $this->selected_edition_id));
        }

        $softSkillWeeks = SoftSkillWeek::withCount('attendances')
            ->orderByDesc('week_start_date')
            ->orderByDesc('id')
            ->get();

        $selectedSoftSkillWeek = $this->selected_ss_week_id
            ? $softSkillWeeks->firstWhere('id', $this->selected_ss_week_id)
            : null;

        $softSkillCourses = Course::orderBy('name')->get();

        $enrolledStudentsQuery = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->whereIn('status', ['confirmada', 'en_curso', 'finalizada']))
            ->with([
                'enrollments' => fn ($q) => $q->whereIn('status', ['confirmada', 'en_curso', 'finalizada'])->with('edition.course'),
                'softSkillAttendances.week',
                'workshopParticipations.workshop',
            ])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if (trim($this->ss_search) !== '') {
            $term = '%'.mb_strtolower(trim($this->ss_search)).'%';
            $enrolledStudentsQuery->where(function ($q) use ($term) {
                $q->whereRaw("LOWER(TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '') || ' ' || COALESCE(second_last_name, ''))) LIKE ?", [$term])
                    ->orWhereRaw('LOWER(identification_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(student_code) LIKE ?', [$term]);
            });
        }

        if ($this->ss_filter_course_id) {
            $courseFilterId = (int) $this->ss_filter_course_id;
            $enrolledStudentsQuery->whereHas('enrollments', function ($q) use ($courseFilterId) {
                $q->whereIn('status', ['confirmada', 'en_curso', 'finalizada'])
                    ->whereHas('edition', fn ($eq) => $eq->where('course_id', $courseFilterId));
            });
        }

        $softSkillRoster = $enrolledStudentsQuery->get()->map(function (Student $st) {
            $courses = $st->enrollments
                ->map(fn (Enrollment $enr) => trim(($enr->edition?->course?->name ?? 'Curso').($enr->edition?->name ? ' ('.$enr->edition->name.')' : '')))
                ->unique()
                ->values()
                ->all();

            return [
                'student' => $st,
                'courses' => $courses,
                'summary' => $st->softSkillsAttendanceSummary(),
            ];
        });

        if ($this->ss_filter_status !== '') {
            $filterSt = $this->ss_filter_status;
            $softSkillRoster = $softSkillRoster->filter(function ($item) use ($filterSt) {
                $sid = $item['student']->id;
                $currentStatus = $this->ss_statuses[$sid] ?? null;

                return $filterSt === 'sin_registrar'
                    ? $currentStatus === null
                    : $currentStatus === $filterSt;
            })->values();
        }

        $ssReportQuery = SoftSkillAttendance::query()
            ->with([
                'week',
                'recorder',
                'student.enrollments' => fn ($q) => $q->whereIn('status', ['confirmada', 'en_curso', 'finalizada'])->with('edition.course'),
                'student.softSkillAttendances.week',
                'student.workshopParticipations.workshop',
            ])
            ->whereHas('week', fn ($q) => $q->where('has_session', true));

        if ($this->ss_report_week_id) {
            $ssReportQuery->where('soft_skill_week_id', $this->ss_report_week_id);
        }
        if ($this->ss_report_status !== '') {
            $ssReportQuery->where('status', $this->ss_report_status);
        }
        if ($this->ss_report_course_id) {
            $ssCourseId = (int) $this->ss_report_course_id;
            $ssReportQuery->whereHas('student.enrollments', function ($q) use ($ssCourseId) {
                $q->whereIn('status', ['confirmada', 'en_curso', 'finalizada'])
                    ->whereHas('edition', fn ($eq) => $eq->where('course_id', $ssCourseId));
            });
        }
        if (trim($this->ss_report_search) !== '') {
            $term = '%'.mb_strtolower(trim($this->ss_report_search)).'%';
            $ssReportQuery->whereHas('student', function ($q) use ($term) {
                $q->whereRaw("LOWER(TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '') || ' ' || COALESCE(second_last_name, ''))) LIKE ?", [$term])
                    ->orWhereRaw('LOWER(identification_number) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(student_code) LIKE ?', [$term]);
            });
        }

        $ssReportRecords = $ssReportQuery->latest('id')->limit(300)->get()
            ->sortByDesc(fn (SoftSkillAttendance $r) => $r->week?->week_start_date?->toDateString() ?? '')
            ->values();

        $ssReportByStudent = $ssReportRecords
            ->groupBy('student_id')
            ->map(function ($group) {
                /** @var SoftSkillAttendance $first */
                $first = $group->first();
                $st = $first->student;
                $courses = $st
                    ? $st->enrollments
                        ->map(fn (Enrollment $enr) => trim(($enr->edition?->course?->name ?? 'Curso').($enr->edition?->name ? ' ('.$enr->edition->name.')' : '')))
                        ->unique()
                        ->values()
                        ->all()
                    : [];

                return [
                    'student' => $st,
                    'courses' => $courses,
                    'summary' => $st ? $st->softSkillsAttendanceSummary() : [
                        'present' => 0, 'justified' => 0, 'gross_absences' => 0,
                        'workshop_credits' => 0, 'net_absences' => 0, 'credited_workshops' => [],
                    ],
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower((string) ($row['student']?->full_name ?? '')))
            ->values();

        $ssReportSummary = [
            'total' => $ssReportRecords->count(),
            'presente' => $ssReportRecords->where('status', 'presente')->count(),
            'justificada' => $ssReportRecords->where('status', 'justificada')->count(),
            'ausente' => $ssReportRecords->where('status', 'ausente')->count(),
            'students_count' => $ssReportByStudent->count(),
        ];

        return view('livewire.tracking-page', [
            'editions' => $editions,
            'selectedEdition' => $selectedEdition,
            'editionEnrollments' => $editionEnrollments,
            'editionEvaluations' => $editionEvaluations,
            'activeEvaluations' => $activeEvaluations,
            'selectedEvaluation' => $selectedEvaluation,
            'evaluationGradesByEnrollment' => $evaluationGradesByEnrollment,
            'scopeWeightUsed' => $scopeWeightUsed,
            'scopeWeightRemaining' => $scopeWeightRemaining,
            'reportCourses' => $reportCourses,
            'reportEditions' => $reportEditions,
            'reportSubjects' => $reportSubjects,
            'reportRecords' => $reportRecords,
            'reportSummary' => $reportSummary,
            'reportByStudent' => $reportByStudent,
            'attendanceStatusLabels' => AttendanceRecord::statusLabels(),
            'softSkillWeeks' => $softSkillWeeks,
            'selectedSoftSkillWeek' => $selectedSoftSkillWeek,
            'softSkillCourses' => $softSkillCourses,
            'softSkillRoster' => $softSkillRoster,
            'ssReportRecords' => $ssReportRecords,
            'ssReportSummary' => $ssReportSummary,
            'ssReportByStudent' => $ssReportByStudent,
            'rectificationReasons' => Picklist::optionsFor('grade_rectification_reason'),
            'students' => Student::orderBy('first_name')->orderBy('last_name')->limit(200)->get(),
            'softSkills' => SoftSkillRecord::with(['student', 'evaluator'])->where('is_archived', false)->latest('evaluated_on')->limit(50)->get(),
            'recentGrades' => $recentGradesQuery->limit(50)->get(),
        ]);
    }
}
