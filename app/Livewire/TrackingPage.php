<?php

namespace App\Livewire;

use App\Models\AttendanceRecord;
use App\Models\CourseEdition;
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

    public array $attendance_statuses = [];

    public string $attendance_rectification_reason = '';

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
        $this->loadAttendanceForDate();
    }

    public function updatedSessionDate(): void
    {
        $this->loadAttendanceForDate();
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
                    'enrollment_id' => $enr->id,
                    'student_id' => $enr->student_id,
                    'session_date' => $this->session_date,
                    'status' => $status,
                    'version' => $nextVersion,
                    'is_current' => true,
                    'rectification_reason' => $nextVersion > 1 ? ($this->attendance_rectification_reason ?: 'Actualización de asistencia en sesión') : null,
                    'recorded_by' => Auth::id(),
                ]);

                AuditService::log('tracking.attendance_recorded', 'seguimiento', $record, null, ['status' => $status, 'version' => $nextVersion]);
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

        return view('livewire.tracking-page', [
            'editions' => $editions,
            'selectedEdition' => $selectedEdition,
            'editionEnrollments' => $editionEnrollments,
            'rectificationReasons' => Picklist::optionsFor('grade_rectification_reason'),
            'students' => Student::orderBy('first_name')->limit(100)->get(),
            'softSkills' => SoftSkillRecord::with(['student', 'evaluator'])->where('is_archived', false)->latest('evaluated_on')->limit(50)->get(),
            'recentGrades' => GradeRecord::with(['student', 'enrollment.edition', 'subject'])->where('is_current', true)->latest()->limit(40)->get(),
        ]);
    }
}
