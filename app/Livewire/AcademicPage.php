<?php

namespace App\Livewire;

use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\CourseSubject;
use App\Models\EditionSchedule;
use App\Models\Picklist;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ScheduleConflictService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Módulo Académico')]
class AcademicPage extends Component
{
    public string $tab = 'courses';

    public ?string $feedback = null;

    // Categoría
    public string $cat_code = '';

    public string $cat_name = '';

    public string $cat_description = '';

    // Curso + Materias obligatorias para Técnico
    public ?int $course_category_id = null;

    public string $course_code = '';

    public string $course_name = '';

    public string $course_program_type = 'curso_corto';

    public string $course_education_level = 'primaria_completa';

    public int $course_default_max_capacity = 25;

    public int $course_duration_hours = 40;

    public int $course_duration_months = 2;

    public float $course_enrollment_fee = 15000;

    public float $course_monthly_fee = 25000;

    public array $course_required_documents = ['cedula_dimex'];

    public string $course_description = '';

    public array $technical_subjects = [
        ['code' => 'MAT-01', 'name' => '', 'hours' => 40, 'sessions_count' => 10],
    ];

    // Gestión de materias en un curso ya existente
    public ?int $managingSubjectsCourseId = null;

    public string $new_sub_code = '';

    public string $new_sub_name = '';

    public int $new_sub_hours = 40;

    public int $new_sub_sessions = 10;

    // Edición (autollenado desde curso)
    public ?int $ed_course_id = null;

    public string $ed_code = '';

    public string $ed_name = '';

    public ?int $ed_shift_id = null;

    public ?int $ed_modality_id = null;

    public ?int $ed_classroom_id = null;

    public ?int $ed_teacher_id = null;

    public string $ed_start_date = '';

    public string $ed_end_date = '';

    public int $ed_max_capacity = 25;

    public float $ed_enrollment_fee = 0;

    public float $ed_monthly_fee = 0;

    public int $ed_installments_count = 1;

    public bool $ed_public_open = true;

    // Horario anti-choque
    public ?int $sch_edition_id = null;

    public ?int $sch_classroom_id = null;

    public ?int $sch_teacher_id = null;

    public int $sch_day_of_week = 1;

    public string $sch_start_time = '08:00';

    public string $sch_end_time = '11:00';

    // Aula
    public string $room_code = '';

    public string $room_name = '';

    public int $room_capacity = 25;

    public string $room_location = '';

    public function mount(): void
    {
        $this->ed_start_date = now()->addDays(7)->toDateString();
        $this->ed_end_date = now()->addMonths(3)->toDateString();
        $this->ed_shift_id = AcademicShift::where('is_active', true)->value('id');
        $this->ed_modality_id = AcademicModality::where('is_active', true)->value('id');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();
    }

    public function updatedCourseProgramType(string $value): void
    {
        // Atestados requeridos por defecto SOLO en Cursos Técnicos
        $this->course_required_documents = Course::defaultRequiredDocumentsFor($value);
    }

    public function addTechnicalSubjectRow(): void
    {
        $idx = count($this->technical_subjects) + 1;
        $this->technical_subjects[] = ['code' => sprintf('MAT-%02d', $idx), 'name' => '', 'hours' => 40, 'sessions_count' => 10];
    }

    public function removeTechnicalSubjectRow(int $index): void
    {
        if (count($this->technical_subjects) > 1) {
            unset($this->technical_subjects[$index]);
            $this->technical_subjects = array_values($this->technical_subjects);
        }
    }

    public function updatedEdCourseId($value): void
    {
        if (! $value) {
            return;
        }
        $course = Course::find($value);
        if (! $course) {
            return;
        }

        // Autollenado inteligente desde el curso (capacidad máxima por defecto + montos)
        $this->ed_max_capacity = (int) $course->default_max_capacity;
        $this->ed_enrollment_fee = (float) $course->base_enrollment_fee;
        $this->ed_monthly_fee = (float) $course->base_monthly_fee;
        $this->ed_installments_count = max(1, (int) $course->duration_months);
        $this->ed_name = $course->name.' — Cohorte '.now()->format('Y-m');
        $this->ed_code = 'ED-'.$course->code.'-'.strtoupper(substr(md5((string) microtime(true)), 0, 4));
    }

    public function updatedSchEditionId($value): void
    {
        if (! $value) {
            return;
        }
        $edition = CourseEdition::find($value);
        if ($edition) {
            $this->sch_classroom_id = $edition->classroom_id;
            $this->sch_teacher_id = $edition->teacher_id;
        }
    }

    public function createCategory(): void
    {
        $this->authorizeManage();
        if (Auth::user()->isCategoryScopedCoordinator()) {
            $this->addError('cat_name', 'Un Coordinador de Categoría solo puede operar dentro de sus categorías asignadas.');

            return;
        }

        $this->validate([
            'cat_code' => ['required', 'string', 'max:40', 'unique:academic_categories,code'],
            'cat_name' => ['required', 'string', 'max:140'],
        ]);

        $cat = AcademicCategory::create([
            'code' => strtoupper(trim($this->cat_code)),
            'name' => trim($this->cat_name),
            'description' => $this->cat_description ?: null,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        AuditService::log('academic.category_created', 'academico', $cat, null, $cat->only(['code', 'name']));
        $this->reset(['cat_code', 'cat_name', 'cat_description']);
        $this->feedback = 'Categoría académica registrada correctamente.';
    }

    public function createCourse(): void
    {
        $this->authorizeManage();

        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $this->course_category_id, $allowedCategoryIds, true)) {
            $this->addError('course_category_id', 'No tiene autorización sobre la categoría académica seleccionada.');

            return;
        }

        $this->validate([
            'course_category_id' => ['required', 'integer', 'exists:academic_categories,id'],
            'course_code' => ['required', 'string', 'max:40', 'unique:courses,code'],
            'course_name' => ['required', 'string', 'max:180'],
            'course_program_type' => ['required', 'in:curso_corto,programa,curso_tecnico'],
            'course_education_level' => ['required', 'string', 'max:80'],
            'course_default_max_capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'course_duration_hours' => ['required', 'integer', 'min:1'],
            'course_duration_months' => ['required', 'integer', 'min:1', 'max:48'],
            'course_enrollment_fee' => ['required', 'numeric', 'min:0'],
            'course_monthly_fee' => ['required', 'numeric', 'min:0'],
        ]);

        // Materias obligatorias al crear Cursos Técnicos
        $cleanSubjects = collect($this->technical_subjects)
            ->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '')
            ->values();

        if ($this->course_program_type === 'curso_tecnico' && $cleanSubjects->isEmpty()) {
            $this->addError('technical_subjects', 'Los Cursos Técnicos requieren registrar al menos una materia obligatoria al momento de creación.');

            return;
        }

        $docs = ! empty($this->course_required_documents)
            ? array_values(array_unique($this->course_required_documents))
            : Course::defaultRequiredDocumentsFor($this->course_program_type);

        $course = DB::transaction(function () use ($docs, $cleanSubjects): Course {
            $course = Course::create([
                'academic_category_id' => $this->course_category_id,
                'code' => strtoupper(trim($this->course_code)),
                'name' => trim($this->course_name),
                'program_type' => $this->course_program_type,
                'description' => $this->course_description ?: null,
                'required_education_level' => $this->course_education_level,
                'default_max_capacity' => $this->course_default_max_capacity,
                'duration_hours' => $this->course_duration_hours,
                'duration_months' => $this->course_duration_months,
                'base_enrollment_fee' => $this->course_enrollment_fee,
                'base_monthly_fee' => $this->course_monthly_fee,
                'required_documents' => $docs,
                'created_by' => Auth::id(),
            ]);

            foreach ($cleanSubjects as $idx => $sub) {
                CourseSubject::create([
                    'course_id' => $course->id,
                    'code' => strtoupper(trim((string) ($sub['code'] ?: sprintf('MAT-%02d', $idx + 1)))),
                    'name' => trim((string) $sub['name']),
                    'hours' => max(1, (int) ($sub['hours'] ?? 20)),
                    'sessions_count' => max(1, (int) ($sub['sessions_count'] ?? 1)),
                    'order_index' => $idx + 1,
                    'is_mandatory' => true,
                    'is_active' => true,
                ]);
            }

            return $course;
        });

        AuditService::log('academic.course_created', 'academico', $course, null, $course->only(['code', 'name', 'program_type', 'default_max_capacity', 'required_documents']));
        $this->reset(['course_code', 'course_name', 'course_description']);
        $this->technical_subjects = [['code' => 'MAT-01', 'name' => '', 'hours' => 40, 'sessions_count' => 10]];
        $this->feedback = 'Curso registrado exitosamente.';
    }

    public function openCourseSubjects(int $courseId): void
    {
        $course = Course::with('subjects')->findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('course', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->managingSubjectsCourseId = $course->id;
        $nextIdx = $course->subjects->count() + 1;
        $this->new_sub_code = sprintf('MAT-%02d', $nextIdx);
        $this->new_sub_name = '';
        $this->new_sub_hours = 40;
        $this->new_sub_sessions = 10;
        $this->resetValidation();
    }

    public function closeCourseSubjects(): void
    {
        $this->managingSubjectsCourseId = null;
    }

    public function addSubjectToCourse(): void
    {
        $this->authorizeManage();
        if (! $this->managingSubjectsCourseId) {
            return;
        }

        $course = Course::with('subjects')->findOrFail($this->managingSubjectsCourseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('new_sub_name', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->validate([
            'new_sub_code' => ['required', 'string', 'max:40'],
            'new_sub_name' => ['required', 'string', 'max:160'],
            'new_sub_hours' => ['required', 'integer', 'min:1'],
            'new_sub_sessions' => ['required', 'integer', 'min:1'],
        ]);

        $code = strtoupper(trim($this->new_sub_code));
        if (CourseSubject::where('course_id', $course->id)->where('code', $code)->exists()) {
            $this->addError('new_sub_code', "Ya existe una materia con el código {$code} en este curso.");

            return;
        }

        $subject = CourseSubject::create([
            'course_id' => $course->id,
            'code' => $code,
            'name' => trim($this->new_sub_name),
            'hours' => $this->new_sub_hours,
            'sessions_count' => $this->new_sub_sessions,
            'order_index' => $course->subjects->count() + 1,
            'is_mandatory' => true,
            'is_active' => true,
        ]);

        AuditService::log('academic.subject_added', 'academico', $subject, null, $subject->only(['course_id', 'code', 'name', 'hours', 'sessions_count']));

        $nextIdx = $course->subjects()->count() + 1;
        $this->new_sub_code = sprintf('MAT-%02d', $nextIdx);
        $this->new_sub_name = '';
        $this->new_sub_hours = 40;
        $this->new_sub_sessions = 10;
        $this->feedback = sprintf('Materia "%s" agregada al curso %s.', $subject->name, $course->name);
    }

    public function toggleSubjectActive(int $subjectId): void
    {
        $this->authorizeManage();
        $subject = CourseSubject::findOrFail($subjectId);
        $subject->update(['is_active' => ! $subject->is_active]);
        AuditService::log('academic.subject_toggled', 'academico', $subject, null, ['is_active' => $subject->is_active]);
    }

    public function createEdition(): void
    {
        $this->authorizeManage();

        $this->validate([
            'ed_course_id' => ['required', 'integer', 'exists:courses,id'],
            'ed_code' => ['required', 'string', 'max:50', 'unique:course_editions,code'],
            'ed_name' => ['required', 'string', 'max:180'],
            'ed_shift_id' => ['required', 'integer', 'exists:academic_shifts,id'],
            'ed_modality_id' => ['required', 'integer', 'exists:academic_modalities,id'],
            'ed_classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'ed_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'ed_start_date' => ['required', 'date'],
            'ed_end_date' => ['required', 'date', 'after_or_equal:ed_start_date'],
            'ed_max_capacity' => ['required', 'integer', 'min:1'],
            'ed_enrollment_fee' => ['required', 'numeric', 'min:0'],
            'ed_monthly_fee' => ['required', 'numeric', 'min:0'],
            'ed_installments_count' => ['required', 'integer', 'min:1', 'max:48'],
        ]);

        $course = Course::findOrFail($this->ed_course_id);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('ed_course_id', 'No puede crear ediciones fuera de sus categorías asignadas.');

            return;
        }

        $edition = CourseEdition::create([
            'course_id' => $course->id,
            'code' => strtoupper(trim($this->ed_code)),
            'name' => trim($this->ed_name),
            'shift_id' => $this->ed_shift_id,
            'modality_id' => $this->ed_modality_id,
            'classroom_id' => $this->ed_classroom_id ?: null,
            'teacher_id' => $this->ed_teacher_id ?: null,
            'start_date' => $this->ed_start_date,
            'end_date' => $this->ed_end_date,
            'max_capacity' => $this->ed_max_capacity ?: $course->default_max_capacity,
            'enrollment_fee' => $this->ed_enrollment_fee,
            'monthly_fee' => $this->ed_monthly_fee,
            'installments_count' => $this->ed_installments_count,
            'status' => 'abierta',
            'is_public_enrollment_open' => $this->ed_public_open,
            'created_by' => Auth::id(),
        ]);

        AuditService::log('academic.edition_created', 'academico', $edition, null, $edition->only(['code', 'name', 'max_capacity']));
        $this->reset(['ed_code', 'ed_name']);
        $this->feedback = 'Edición creada con capacidad autollenada y lista para matrícula.';
    }

    public function createSchedule(ScheduleConflictService $conflictService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('academico.schedules.manage'), 403);

        $this->validate([
            'sch_edition_id' => ['required', 'integer', 'exists:course_editions,id'],
            'sch_classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'sch_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'sch_day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'sch_start_time' => ['required', 'date_format:H:i'],
            'sch_end_time' => ['required', 'date_format:H:i'],
        ]);

        $edition = CourseEdition::with('course')->findOrFail($this->sch_edition_id);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $edition->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('sch_edition_id', 'No tiene autorización sobre la categoría de esta edición.');

            return;
        }

        $startMin = EditionSchedule::timeToMinutes($this->sch_start_time);
        $endMin = EditionSchedule::timeToMinutes($this->sch_end_time);

        try {
            $conflictService->assertScheduleHasNoConflicts(
                dayOfWeek: $this->sch_day_of_week,
                startMinute: $startMin,
                endMinute: $endMin,
                startDate: $edition->start_date->toDateString(),
                endDate: $edition->end_date->toDateString(),
                classroomId: $this->sch_classroom_id ?: null,
                teacherId: $this->sch_teacher_id ?: null
            );

            $schedule = EditionSchedule::create([
                'course_edition_id' => $edition->id,
                'classroom_id' => $this->sch_classroom_id ?: null,
                'teacher_id' => $this->sch_teacher_id ?: null,
                'day_of_week' => $this->sch_day_of_week,
                'start_minute' => $startMin,
                'end_minute' => $endMin,
                'start_date' => $edition->start_date->toDateString(),
                'end_date' => $edition->end_date->toDateString(),
                'is_active' => true,
            ]);
        } catch (ValidationException $e) {
            $this->addError('schedule', collect($e->errors())->flatten()->first());

            return;
        } catch (QueryException $e) {
            $this->addError('schedule', 'Bloqueo PostgreSQL GiST anti-choque: el aula o profesora ya tiene un horario superpuesto.');

            return;
        }

        AuditService::log('academic.schedule_created', 'academico', $schedule, null, ['slot' => $schedule->formatted_slot]);
        $this->feedback = 'Horario asignado sin choques de aula ni profesora.';
    }

    public function createClassroom(): void
    {
        $this->authorizeManage();
        $this->validate([
            'room_code' => ['required', 'string', 'max:40', 'unique:classrooms,code'],
            'room_name' => ['required', 'string', 'max:120'],
            'room_capacity' => ['required', 'integer', 'min:1'],
        ]);

        $room = Classroom::create([
            'code' => strtoupper(trim($this->room_code)),
            'name' => trim($this->room_name),
            'capacity' => $this->room_capacity,
            'location' => $this->room_location ?: null,
            'is_active' => true,
        ]);

        AuditService::log('academic.classroom_created', 'academico', $room);
        $this->reset(['room_code', 'room_name', 'room_location']);
        $this->feedback = 'Aula registrada correctamente.';
    }

    protected function authorizeManage(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('academico.manage'), 403);
    }

    protected function scopedCategoryIds(): ?array
    {
        $user = Auth::user();
        if ($user->isCategoryScopedCoordinator()) {
            return $user->activeAcademicCategoryIds();
        }

        return null;
    }

    public function render()
    {
        $scopedIds = $this->scopedCategoryIds();

        $categoriesQuery = AcademicCategory::query()->withCount('courses')->orderBy('name');
        $coursesQuery = Course::query()->with(['category', 'subjects'])->orderByDesc('id');
        $editionsQuery = CourseEdition::query()->with(['course.category', 'shift', 'modality', 'classroom', 'teacher', 'schedules'])->orderByDesc('id');
        $schedulesQuery = EditionSchedule::query()->where('is_active', true)->with(['edition.course', 'classroom', 'teacher'])->orderBy('day_of_week')->orderBy('start_minute');

        if ($scopedIds !== null) {
            $categoriesQuery->whereIn('id', $scopedIds);
            $coursesQuery->whereIn('academic_category_id', $scopedIds);
            $editionsQuery->whereHas('course', fn ($q) => $q->whereIn('academic_category_id', $scopedIds));
            $schedulesQuery->whereHas('edition.course', fn ($q) => $q->whereIn('academic_category_id', $scopedIds));
        }

        return view('livewire.academic-page', [
            'isScopedCoordinator' => $scopedIds !== null,
            'categories' => $categoriesQuery->get(),
            'courses' => $coursesQuery->get(),
            'managingCourse' => $this->managingSubjectsCourseId ? Course::with('subjects')->find($this->managingSubjectsCourseId) : null,
            'editions' => $editionsQuery->get(),
            'schedules' => $schedulesQuery->get(),
            'shifts' => AcademicShift::where('is_active', true)->get(),
            'modalities' => AcademicModality::where('is_active', true)->get(),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(),
            'teachers' => User::role('profesora')->where('is_active', true)->orderBy('name')->get(),
            'educationLevels' => Picklist::optionsFor('education_level'),
            'documentTypes' => config('cecap.document_types'),
            'daysOfWeek' => config('cecap.days_of_week'),
        ]);
    }
}
