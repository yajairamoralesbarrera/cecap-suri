<?php

namespace App\Livewire;

use App\Mail\CourseClosedInstitutionalMail;
use App\Models\AcademicCategory;
use App\Models\AcademicModality;
use App\Models\AcademicShift;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\CourseSubject;
use App\Models\EditionSchedule;
use App\Models\InstitutionSetting;
use App\Models\Picklist;
use App\Models\StudentLifecycleEvent;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ScheduleConflictService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
    public ?int $editingCourseId = null;

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

    // Duplicación inline de curso (con código incremental y sus materias editables)
    public ?int $duplicatingCourseId = null;

    public ?int $dup_category_id = null;

    public string $dup_code = '';

    public string $dup_name = '';

    public string $dup_program_type = 'curso_corto';

    public string $dup_education_level = 'primaria_completa';

    public int $dup_default_max_capacity = 25;

    public int $dup_duration_hours = 40;

    public int $dup_duration_months = 2;

    public float $dup_enrollment_fee = 15000;

    public float $dup_monthly_fee = 25000;

    public array $dup_required_documents = ['cedula_dimex'];

    public string $dup_description = '';

    public array $dup_subjects = [];

    // Cierre administrativo de curso en catálogo (sin envío de correos a alumnas; las notificaciones se gestionan por cohorte)
    public ?int $closingCourseId = null;

    public string $course_closure_reason_code = '';

    public string $course_closure_reason = '';

    // Eliminación lógica de curso (solo permitida si no tiene alumnas inscritas)
    public ?int $deletingCourseId = null;

    public string $course_delete_reason = '';

    // Gestión de materias en un curso ya existente
    public ?int $managingSubjectsCourseId = null;

    public string $new_sub_code = '';

    public string $new_sub_name = '';

    public int $new_sub_hours = 40;

    public int $new_sub_sessions = 10;

    // Edición y duplicación inline de materias existentes
    public ?int $editingSubjectId = null;

    public string $edit_sub_code = '';

    public string $edit_sub_name = '';

    public int $edit_sub_hours = 40;

    public int $edit_sub_sessions = 10;

    public ?int $duplicatingSubjectId = null;

    public string $dup_sub_code = '';

    public string $dup_sub_name = '';

    public int $dup_sub_hours = 40;

    public int $dup_sub_sessions = 10;

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

    // Edición inline de una edición existente (evita duplicados)
    public ?int $editingEditionId = null;

    public string $edit_ed_code = '';

    public string $edit_ed_name = '';

    public ?int $edit_ed_shift_id = null;

    public ?int $edit_ed_modality_id = null;

    public ?int $edit_ed_classroom_id = null;

    public ?int $edit_ed_teacher_id = null;

    public string $edit_ed_start_date = '';

    public string $edit_ed_end_date = '';

    public int $edit_ed_max_capacity = 25;

    public float $edit_ed_enrollment_fee = 0;

    public float $edit_ed_monthly_fee = 0;

    public int $edit_ed_installments_count = 1;

    public bool $edit_ed_public_open = true;

    // Cierre inline de cohorte (con picklist de motivo y correo institucional a las alumnas de la cohorte)
    public ?int $closingEditionId = null;

    public string $edition_closure_type = 'cancelada';

    public string $edition_closure_reason_code = '';

    public string $edition_closure_notes = '';

    public string $edition_closure_message = 'Por decisión institucional esta cohorte ha sido cerrada. Por favor manténgase pendiente de nuestra nueva oferta académica y próximas convocatorias de CeCap SURÍ.';

    public bool $edition_closure_notify_students = true;

    // Horario anti-choque
    public ?int $sch_edition_id = null;

    public ?int $sch_classroom_id = null;

    public ?int $sch_teacher_id = null;

    public int $sch_day_of_week = 1;

    public string $sch_start_time = '08:00';

    public string $sch_end_time = '11:00';

    // Modificación y eliminación lógica inline de horarios
    public ?int $editingScheduleId = null;

    public ?int $edit_sch_edition_id = null;

    public ?int $edit_sch_classroom_id = null;

    public ?int $edit_sch_teacher_id = null;

    public int $edit_sch_day_of_week = 1;

    public string $edit_sch_start_time = '08:00';

    public string $edit_sch_end_time = '11:00';

    public ?int $deletingScheduleId = null;

    public string $schedule_delete_reason = '';

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
        // Atestados requeridos por defecto SOLO en Cursos Técnicos (en Programa es opcional)
        $this->course_required_documents = Course::defaultRequiredDocumentsFor($value);

        // Ajustar prefijo sugerido (MOD-01 para Programa, MAT-01 para Curso Técnico) si aún está vacío
        if (count($this->technical_subjects) === 1 && trim((string) ($this->technical_subjects[0]['name'] ?? '')) === '') {
            $this->technical_subjects[0]['code'] = $value === 'programa' ? 'MOD-01' : 'MAT-01';
        }
    }

    public function addTechnicalSubjectRow(): void
    {
        $prefix = $this->course_program_type === 'programa' ? 'MOD-' : 'MAT-';
        $usedCodes = array_column($this->technical_subjects, 'code');
        $lastCode = ! empty($usedCodes) ? (string) end($usedCodes) : $prefix.'00';
        $nextCode = CourseSubject::nextIncrementalCode(null, $lastCode, $usedCodes);
        $this->technical_subjects[] = ['code' => $nextCode, 'name' => '', 'hours' => 40, 'sessions_count' => 10];
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

    public function editCourse(int $courseId): void
    {
        $this->authorizeManage();
        $course = Course::findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('course_category_id', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->managingSubjectsCourseId = null;
        $this->duplicatingCourseId = null;
        $this->closingCourseId = null;
        $this->deletingCourseId = null;
        $this->editingCourseId = $course->id;
        $this->course_category_id = $course->academic_category_id;
        $this->course_code = $course->code;
        $this->course_name = $course->name;
        $this->course_program_type = $course->program_type;
        $this->course_education_level = $course->required_education_level;
        $this->course_default_max_capacity = (int) $course->default_max_capacity;
        $this->course_duration_hours = (int) $course->duration_hours;
        $this->course_duration_months = (int) $course->duration_months;
        $this->course_enrollment_fee = (float) $course->base_enrollment_fee;
        $this->course_monthly_fee = (float) $course->base_monthly_fee;
        $this->course_required_documents = $course->required_documents ?? Course::defaultRequiredDocumentsFor($course->program_type);
        $this->course_description = (string) ($course->description ?? '');
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelEditCourse(): void
    {
        $this->editingCourseId = null;
        $this->reset(['course_category_id', 'course_code', 'course_name', 'course_description']);
        $this->course_program_type = 'curso_corto';
        $this->course_education_level = 'primaria_completa';
        $this->course_default_max_capacity = 25;
        $this->course_duration_hours = 40;
        $this->course_duration_months = 2;
        $this->course_enrollment_fee = 15000;
        $this->course_monthly_fee = 25000;
        $this->course_required_documents = ['cedula_dimex'];
        $this->technical_subjects = [['code' => 'MAT-01', 'name' => '', 'hours' => 40, 'sessions_count' => 10]];
        $this->resetValidation();
    }

    public function toggleCourseActive(int $courseId): void
    {
        $this->authorizeManage();
        $course = Course::findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            return;
        }
        $old = ['is_active' => $course->is_active];
        $course->update(['is_active' => ! $course->is_active]);
        AuditService::log('academic.course_toggled', 'academico', $course, $old, ['is_active' => $course->is_active]);
        $this->feedback = $course->is_active ? 'Curso reactivado.' : 'Curso inactivado (sin borrado físico).';
    }

    public function createCourse(): void
    {
        $this->authorizeManage();

        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $this->course_category_id, $allowedCategoryIds, true)) {
            $this->addError('course_category_id', 'No tiene autorización sobre la categoría académica seleccionada.');

            return;
        }

        $codeUniqueRule = $this->editingCourseId
            ? 'unique:courses,code,'.$this->editingCourseId
            : 'unique:courses,code';

        $this->validate([
            'course_category_id' => ['required', 'integer', 'exists:academic_categories,id'],
            'course_code' => ['required', 'string', 'max:40', $codeUniqueRule],
            'course_name' => ['required', 'string', 'max:180'],
            'course_program_type' => ['required', 'in:curso_corto,programa,curso_tecnico'],
            'course_education_level' => ['required', 'string', 'max:80'],
            'course_default_max_capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'course_duration_hours' => ['required', 'integer', 'min:1'],
            'course_duration_months' => ['required', 'integer', 'min:1', 'max:48'],
            'course_enrollment_fee' => ['required', 'numeric', 'min:0'],
            'course_monthly_fee' => ['required', 'numeric', 'min:0'],
        ]);

        $docs = ! empty($this->course_required_documents)
            ? array_values(array_unique($this->course_required_documents))
            : Course::defaultRequiredDocumentsFor($this->course_program_type);

        if ($this->editingCourseId) {
            $course = Course::findOrFail($this->editingCourseId);
            if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
                $this->addError('course_category_id', 'No tiene autorización sobre la categoría de este curso.');

                return;
            }
            $old = $course->only(['academic_category_id', 'code', 'name', 'program_type', 'description', 'required_education_level', 'default_max_capacity', 'duration_hours', 'duration_months', 'base_enrollment_fee', 'base_monthly_fee', 'required_documents']);

            if ($this->course_program_type === 'curso_tecnico' && $course->subjects()->count() === 0) {
                $this->addError('course_program_type', 'Para cambiar este curso a Curso Técnico debe agregar al menos una materia usando "+ Materias".');

                return;
            }

            if ($this->course_program_type === 'programa' && $course->subjects()->count() === 0) {
                $this->addError('course_program_type', 'Para cambiar este registro a Programa debe agregar al menos un módulo usando "+ Módulos".');

                return;
            }

            // El código del curso se mantiene inmutable al editar para conservar integridad y evitar duplicados
            $course->update([
                'academic_category_id' => $this->course_category_id,
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
            ]);

            // Sincronizar capacidad máxima en ediciones abiertas de este curso cuando cambia default_max_capacity
            $syncedEditions = 0;
            if ((int) $old['default_max_capacity'] !== (int) $this->course_default_max_capacity) {
                $openEditions = $course->editions()->whereIn('status', ['abierta', 'planificada'])->get();
                foreach ($openEditions as $openEd) {
                    if ($this->course_default_max_capacity >= $openEd->activeEnrollmentsCount()) {
                        $openEd->update(['max_capacity' => $this->course_default_max_capacity]);
                        $syncedEditions++;
                    }
                }
            }

            AuditService::log('academic.course_updated', 'academico', $course, $old, $course->only(array_keys($old)));
            $this->cancelEditCourse();
            $this->feedback = $syncedEditions > 0
                ? sprintf('Curso %s actualizado con el mismo código (capacidad sincronizada en %d cohorte(s) abierta(s)).', $course->code, $syncedEditions)
                : sprintf('Curso %s actualizado correctamente manteniendo su mismo código (cambio auditado).', $course->code);

            return;
        }

        // Materias obligatorias al crear Cursos Técnicos y Módulos obligatorios al crear Programas
        $cleanSubjects = collect($this->technical_subjects)
            ->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '')
            ->values();

        if ($this->course_program_type === 'curso_tecnico' && $cleanSubjects->isEmpty()) {
            $this->addError('technical_subjects', 'Los Cursos Técnicos requieren registrar al menos una materia obligatoria al momento de creación.');

            return;
        }

        if ($this->course_program_type === 'programa' && $cleanSubjects->isEmpty()) {
            $this->addError('technical_subjects', 'Los Programas son por módulo y requieren registrar al menos un módulo obligatorio al momento de creación.');

            return;
        }

        $unitPrefix = $this->course_program_type === 'programa' ? 'MOD-' : 'MAT-';

        $course = DB::transaction(function () use ($docs, $cleanSubjects, $unitPrefix): Course {
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
                    'code' => strtoupper(trim((string) ($sub['code'] ?: sprintf('%s%02d', $unitPrefix, $idx + 1)))),
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
        $createdType = $this->course_program_type;
        $this->reset(['course_code', 'course_name', 'course_description']);
        $this->technical_subjects = [['code' => $createdType === 'programa' ? 'MOD-01' : 'MAT-01', 'name' => '', 'hours' => 40, 'sessions_count' => 10]];
        $this->feedback = $createdType === 'programa'
            ? 'Programa por módulos registrado exitosamente.'
            : 'Curso registrado exitosamente.';
    }

    public function startDuplicateCourse(int $courseId): void
    {
        $this->authorizeManage();
        $course = Course::with('subjects')->findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('dup_category_id', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->editingCourseId = null;
        $this->managingSubjectsCourseId = null;
        $this->closingCourseId = null;
        $this->deletingCourseId = null;
        $this->duplicatingCourseId = $course->id;
        $this->dup_category_id = $course->academic_category_id;
        $this->dup_code = Course::nextIncrementalCode($course->code);
        $this->dup_name = $course->name;
        $this->dup_program_type = $course->program_type;
        $this->dup_education_level = $course->required_education_level;
        $this->dup_default_max_capacity = (int) $course->default_max_capacity;
        $this->dup_duration_hours = (int) $course->duration_hours;
        $this->dup_duration_months = (int) $course->duration_months;
        $this->dup_enrollment_fee = (float) $course->base_enrollment_fee;
        $this->dup_monthly_fee = (float) $course->base_monthly_fee;
        $this->dup_required_documents = $course->required_documents ?? Course::defaultRequiredDocumentsFor($course->program_type);
        $this->dup_description = (string) ($course->description ?? '');
        $this->dup_subjects = $course->subjects->map(fn ($sub) => [
            'code' => $sub->code,
            'name' => $sub->name,
            'hours' => (int) $sub->hours,
            'sessions_count' => (int) ($sub->sessions_count ?? 1),
        ])->values()->all();

        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelDuplicateCourse(): void
    {
        $this->duplicatingCourseId = null;
        $this->dup_subjects = [];
        $this->resetValidation();
    }

    public function updatedDupProgramType(string $value): void
    {
        $this->dup_required_documents = Course::defaultRequiredDocumentsFor($value);
        if (in_array($value, ['curso_tecnico', 'programa'], true) && empty($this->dup_subjects)) {
            $this->dup_subjects = [['code' => $value === 'programa' ? 'MOD-01' : 'MAT-01', 'name' => '', 'hours' => 40, 'sessions_count' => 10]];
        }
    }

    public function addDupSubjectRow(): void
    {
        $prefix = $this->dup_program_type === 'programa' ? 'MOD-' : 'MAT-';
        $usedCodes = array_column($this->dup_subjects, 'code');
        $lastCode = ! empty($usedCodes) ? (string) end($usedCodes) : $prefix.'00';
        $nextCode = CourseSubject::nextIncrementalCode(null, $lastCode, $usedCodes);
        $this->dup_subjects[] = [
            'code' => $nextCode,
            'name' => '',
            'hours' => 40,
            'sessions_count' => 10,
        ];
    }

    public function duplicateDupSubjectRow(int $index): void
    {
        if (! isset($this->dup_subjects[$index])) {
            return;
        }
        $source = $this->dup_subjects[$index];
        $usedCodes = array_column($this->dup_subjects, 'code');
        $fallback = $this->dup_program_type === 'programa' ? 'MOD-01' : 'MAT-01';
        $nextCode = CourseSubject::nextIncrementalCode(null, (string) ($source['code'] ?? $fallback), $usedCodes);
        $clone = [
            'code' => $nextCode,
            'name' => (string) ($source['name'] ?? ''),
            'hours' => max(1, (int) ($source['hours'] ?? 40)),
            'sessions_count' => max(1, (int) ($source['sessions_count'] ?? 10)),
        ];
        array_splice($this->dup_subjects, $index + 1, 0, [$clone]);
        $this->dup_subjects = array_values($this->dup_subjects);
    }

    public function removeDupSubjectRow(int $index): void
    {
        if (isset($this->dup_subjects[$index])) {
            unset($this->dup_subjects[$index]);
            $this->dup_subjects = array_values($this->dup_subjects);
        }
    }

    public function saveDuplicatedCourse(): void
    {
        $this->authorizeManage();
        if (! $this->duplicatingCourseId) {
            return;
        }

        $source = Course::findOrFail($this->duplicatingCourseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $this->dup_category_id, $allowedCategoryIds, true)) {
            $this->addError('dup_category_id', 'No tiene autorización sobre la categoría académica seleccionada.');

            return;
        }

        $this->dup_code = strtoupper(trim($this->dup_code));

        $this->validate([
            'dup_category_id' => ['required', 'integer', 'exists:academic_categories,id'],
            'dup_code' => ['required', 'string', 'max:40', 'unique:courses,code'],
            'dup_name' => ['required', 'string', 'max:180'],
            'dup_program_type' => ['required', 'in:curso_corto,programa,curso_tecnico'],
            'dup_education_level' => ['required', 'string', 'max:80'],
            'dup_default_max_capacity' => ['required', 'integer', 'min:1', 'max:500'],
            'dup_duration_hours' => ['required', 'integer', 'min:1'],
            'dup_duration_months' => ['required', 'integer', 'min:1', 'max:48'],
            'dup_enrollment_fee' => ['required', 'numeric', 'min:0'],
            'dup_monthly_fee' => ['required', 'numeric', 'min:0'],
        ], [
            'dup_code.unique' => 'Ya existe un curso registrado con ese código. Use el código incremental sugerido u otro único.',
        ]);

        $cleanSubjects = collect($this->dup_subjects)
            ->filter(fn ($row) => trim((string) ($row['name'] ?? '')) !== '')
            ->values();

        if ($this->dup_program_type === 'curso_tecnico' && $cleanSubjects->isEmpty()) {
            $this->addError('dup_subjects', 'Los Cursos Técnicos requieren al menos una materia registrada.');

            return;
        }

        if ($this->dup_program_type === 'programa' && $cleanSubjects->isEmpty()) {
            $this->addError('dup_subjects', 'Los Programas son por módulo y requieren al menos un módulo registrado.');

            return;
        }

        $defaultBase = $this->dup_program_type === 'programa' ? 'MOD-00' : 'MAT-00';

        // Verificar que no haya códigos repetidos dentro del curso/programa duplicado
        $seenSubjectCodes = [];
        foreach ($cleanSubjects as $idx => $sub) {
            $subCode = strtoupper(trim((string) ($sub['code'] ?? '')));
            if ($subCode === '') {
                $subCode = CourseSubject::nextIncrementalCode(null, $defaultBase, $seenSubjectCodes);
            }
            if (in_array($subCode, $seenSubjectCodes, true)) {
                $this->addError('dup_subjects', "El código {$subCode} está repetido en la lista. Ajuste o incremente el código antes de guardar.");

                return;
            }
            $seenSubjectCodes[] = $subCode;
        }

        $docs = ! empty($this->dup_required_documents)
            ? array_values(array_unique($this->dup_required_documents))
            : Course::defaultRequiredDocumentsFor($this->dup_program_type);

        $newCourse = DB::transaction(function () use ($docs, $cleanSubjects): Course {
            $course = Course::create([
                'academic_category_id' => $this->dup_category_id,
                'code' => $this->dup_code,
                'name' => trim($this->dup_name),
                'program_type' => $this->dup_program_type,
                'description' => $this->dup_description ?: null,
                'required_education_level' => $this->dup_education_level,
                'default_max_capacity' => $this->dup_default_max_capacity,
                'duration_hours' => $this->dup_duration_hours,
                'duration_months' => $this->dup_duration_months,
                'base_enrollment_fee' => $this->dup_enrollment_fee,
                'base_monthly_fee' => $this->dup_monthly_fee,
                'required_documents' => $docs,
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);

            $usedCodes = [];
            foreach ($cleanSubjects as $idx => $sub) {
                $subCode = strtoupper(trim((string) ($sub['code'] ?? '')));
                if ($subCode === '') {
                    $subCode = CourseSubject::nextIncrementalCode(null, 'MAT-00', $usedCodes);
                }
                $usedCodes[] = $subCode;

                CourseSubject::create([
                    'course_id' => $course->id,
                    'code' => $subCode,
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

        AuditService::log(
            event: 'academic.course_duplicated',
            module: 'academico',
            auditable: $newCourse,
            oldValues: ['source_course_id' => $source->id, 'source_code' => $source->code],
            newValues: [
                'code' => $newCourse->code,
                'name' => $newCourse->name,
                'subjects_copied' => $cleanSubjects->count(),
            ]
        );

        $copiedCount = $cleanSubjects->count();
        $this->cancelDuplicateCourse();
        $this->feedback = sprintf(
            'Curso duplicado exitosamente con código %s (%d materia(s) incluida(s)).',
            $newCourse->code,
            $copiedCount
        );
    }

    public function startCloseCourse(int $courseId): void
    {
        $this->authorizeManage();
        $course = Course::findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('course_closure_reason', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->editingCourseId = null;
        $this->duplicatingCourseId = null;
        $this->managingSubjectsCourseId = null;
        $this->deletingCourseId = null;
        $this->closingCourseId = $course->id;
        $this->course_closure_reason_code = '';
        $this->course_closure_reason = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelCloseCourse(): void
    {
        $this->closingCourseId = null;
        $this->course_closure_reason_code = '';
        $this->course_closure_reason = '';
        $this->resetValidation();
    }

    public function confirmCloseCourse(): void
    {
        $this->authorizeManage();
        if (! $this->closingCourseId) {
            return;
        }

        $course = Course::with('editions')->findOrFail($this->closingCourseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('course_closure_reason_code', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->validate([
            'course_closure_reason_code' => ['required', 'string', 'max:60'],
            'course_closure_reason' => ['nullable', 'string', 'max:500'],
        ], [
            'course_closure_reason_code.required' => 'Debe seleccionar el motivo del cierre institucional del catálogo (picklist).',
        ]);

        $reasonPicklist = Picklist::query()
            ->where('group_key', 'course_closure_reason')
            ->where('code', $this->course_closure_reason_code)
            ->where('is_active', true)
            ->first();

        if (! $reasonPicklist) {
            $this->addError('course_closure_reason_code', 'Debe seleccionar un motivo de cierre válido del catálogo.');

            return;
        }

        $extraNotes = trim($this->course_closure_reason);
        $cleanReason = $extraNotes !== ''
            ? $reasonPicklist->label.' — '.$extraNotes
            : $reasonPicklist->label;

        $hiddenCohortsCount = DB::transaction(function () use ($course, $cleanReason): int {
            $old = $course->only(['is_active', 'closed_at', 'closure_reason']);
            $course->update([
                'is_active' => false,
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'closure_reason' => $cleanReason,
            ]);

            // Al cerrar el curso en catálogo se desactiva la inscripción pública de cohortes abiertas
            // sin enviar correo a las alumnas; el cierre individual y notificación se gestionan en Cohortes.
            $openCohorts = $course->editions()
                ->whereIn('status', ['abierta', 'planificada', 'en_curso'])
                ->where('is_public_enrollment_open', true)
                ->get();
            foreach ($openCohorts as $ed) {
                $ed->update([
                    'is_public_enrollment_open' => false,
                ]);
            }

            AuditService::log(
                event: 'academic.course_closed_institutional',
                module: 'academico',
                auditable: $course,
                oldValues: $old,
                newValues: [
                    'is_active' => false,
                    'closed_at' => $course->closed_at?->toDateTimeString(),
                    'closure_reason' => $cleanReason,
                    'hidden_public_cohorts' => $openCohorts->count(),
                ],
                reason: $cleanReason
            );

            return $openCohorts->count();
        });

        $courseCode = $course->code;
        $this->cancelCloseCourse();
        $this->feedback = sprintf(
            'Curso %s cerrado en el catálogo (%d cohorte(s) ocultada(s) de matrícula en línea; las notificaciones a alumnas se envían al cerrar cada cohorte).',
            $courseCode,
            $hiddenCohortsCount
        );
    }

    public function reopenClosedCourse(int $courseId): void
    {
        $this->authorizeManage();
        $course = Course::findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            return;
        }

        $old = $course->only(['is_active', 'closed_at', 'closure_reason']);
        $course->update([
            'is_active' => true,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        AuditService::log(
            event: 'academic.course_reopened',
            module: 'academico',
            auditable: $course,
            oldValues: $old,
            newValues: ['is_active' => true, 'closed_at' => null]
        );

        $this->feedback = sprintf('Curso %s reabierto y activo nuevamente.', $course->code);
    }

    public function startDeleteCourse(int $courseId): void
    {
        $this->authorizeManage();
        $course = Course::findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('course_delete_reason', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $enrolledCount = $course->totalEnrollmentsCount();
        if ($enrolledCount > 0) {
            $this->addError(
                'course_delete_reason',
                sprintf(
                    'No se puede eliminar el curso %s porque tiene %d alumna(s) inscrita(s) en sus cohortes. Utilice «Cerrar Curso» para descontinuarlo del catálogo o cierre cada cohorte desde la pestaña Cohortes.',
                    $course->code,
                    $enrolledCount
                )
            );

            return;
        }

        $this->editingCourseId = null;
        $this->duplicatingCourseId = null;
        $this->managingSubjectsCourseId = null;
        $this->closingCourseId = null;
        $this->deletingCourseId = $course->id;
        $this->course_delete_reason = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelDeleteCourse(): void
    {
        $this->deletingCourseId = null;
        $this->course_delete_reason = '';
        $this->resetValidation();
    }

    public function confirmDeleteCourse(): void
    {
        $this->authorizeManage();
        if (! $this->deletingCourseId) {
            return;
        }

        $course = Course::with('editions')->findOrFail($this->deletingCourseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('course_delete_reason', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $enrolledCount = $course->totalEnrollmentsCount();
        if ($enrolledCount > 0) {
            $this->addError(
                'course_delete_reason',
                sprintf(
                    'No se puede eliminar el curso %s porque ya cuenta con %d alumna(s) inscrita(s). Use «Cerrar Curso» en su lugar.',
                    $course->code,
                    $enrolledCount
                )
            );

            return;
        }

        $cleanReason = trim($this->course_delete_reason) !== ''
            ? trim($this->course_delete_reason)
            : 'Eliminado por funcionario (curso sin alumnas inscritas)';

        // Eliminación lógica auditada (conserva la política institucional de cero borrado físico en BD)
        DB::transaction(function () use ($course, $cleanReason): void {
            $course->update([
                'is_active' => false,
                'archived_at' => now(),
                'archived_by' => Auth::id(),
                'archive_reason' => $cleanReason,
            ]);

            foreach ($course->editions as $ed) {
                $ed->update([
                    'status' => 'cancelada',
                    'is_public_enrollment_open' => false,
                ]);
            }

            AuditService::log(
                event: 'academic.course_deleted_empty',
                module: 'academico',
                auditable: $course,
                oldValues: ['is_active' => true, 'archived_at' => null],
                newValues: ['is_active' => false, 'archived_at' => $course->archived_at?->toDateTimeString()],
                reason: $cleanReason
            );
        });

        $courseCode = $course->code;
        $this->cancelDeleteCourse();
        $this->feedback = sprintf('Curso %s eliminado de la oferta (sin alumnas inscritas; registro preservado únicamente en auditoría).', $courseCode);
    }

    public function openCourseSubjects(int $courseId): void
    {
        $course = Course::with('subjects')->findOrFail($courseId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('course', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->editingCourseId = null;
        $this->duplicatingCourseId = null;
        $this->closingCourseId = null;
        $this->deletingCourseId = null;
        $this->editingSubjectId = null;
        $this->duplicatingSubjectId = null;
        $this->managingSubjectsCourseId = $course->id;
        $defaultBase = $course->program_type === 'programa' ? 'MOD-00' : 'MAT-00';
        $lastCode = $course->subjects->last()?->code ?? $defaultBase;
        $this->new_sub_code = CourseSubject::nextIncrementalCode($course->id, $lastCode);
        $this->new_sub_name = '';
        $this->new_sub_hours = 40;
        $this->new_sub_sessions = 10;
        $this->resetValidation();
    }

    public function closeCourseSubjects(): void
    {
        $this->managingSubjectsCourseId = null;
        $this->editingSubjectId = null;
        $this->duplicatingSubjectId = null;
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

        $this->new_sub_code = CourseSubject::nextIncrementalCode($course->id, $subject->code);
        $this->new_sub_name = '';
        $this->new_sub_hours = 40;
        $this->new_sub_sessions = 10;
        $this->feedback = sprintf('Materia "%s" agregada al curso %s.', $subject->name, $course->name);
    }

    public function startEditSubject(int $subjectId): void
    {
        $this->authorizeManage();
        $subject = CourseSubject::with('course')->findOrFail($subjectId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $subject->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('edit_sub_name', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->duplicatingSubjectId = null;
        $this->editingSubjectId = $subject->id;
        $this->edit_sub_code = $subject->code;
        $this->edit_sub_name = $subject->name;
        $this->edit_sub_hours = (int) $subject->hours;
        $this->edit_sub_sessions = (int) ($subject->sessions_count ?? 1);
        $this->resetValidation();
    }

    public function cancelEditSubject(): void
    {
        $this->editingSubjectId = null;
        $this->resetValidation();
    }

    public function saveSubjectEdit(): void
    {
        $this->authorizeManage();
        if (! $this->editingSubjectId) {
            return;
        }

        $subject = CourseSubject::with('course')->findOrFail($this->editingSubjectId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $subject->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('edit_sub_name', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->validate([
            'edit_sub_name' => ['required', 'string', 'max:160'],
            'edit_sub_hours' => ['required', 'integer', 'min:1'],
            'edit_sub_sessions' => ['required', 'integer', 'min:1'],
        ]);

        $old = $subject->only(['code', 'name', 'hours', 'sessions_count']);
        // Código inmutable al editar materia existente
        $subject->update([
            'name' => trim($this->edit_sub_name),
            'hours' => $this->edit_sub_hours,
            'sessions_count' => $this->edit_sub_sessions,
        ]);

        AuditService::log('academic.subject_updated', 'academico', $subject, $old, $subject->only(['code', 'name', 'hours', 'sessions_count']));
        $this->cancelEditSubject();
        $this->feedback = sprintf('Materia %s actualizada manteniendo su mismo código.', $subject->code);
    }

    public function startDuplicateSubject(int $subjectId): void
    {
        $this->authorizeManage();
        $subject = CourseSubject::with('course')->findOrFail($subjectId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $subject->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('dup_sub_name', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->editingSubjectId = null;
        $this->duplicatingSubjectId = $subject->id;
        $this->dup_sub_code = CourseSubject::nextIncrementalCode($subject->course_id, $subject->code);
        $this->dup_sub_name = $subject->name;
        $this->dup_sub_hours = (int) $subject->hours;
        $this->dup_sub_sessions = (int) ($subject->sessions_count ?? 1);
        $this->resetValidation();
    }

    public function cancelDuplicateSubject(): void
    {
        $this->duplicatingSubjectId = null;
        $this->resetValidation();
    }

    public function saveDuplicatedSubject(): void
    {
        $this->authorizeManage();
        if (! $this->duplicatingSubjectId) {
            return;
        }

        $source = CourseSubject::with('course')->findOrFail($this->duplicatingSubjectId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $source->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('dup_sub_name', 'No tiene autorización sobre la categoría de este curso.');

            return;
        }

        $this->validate([
            'dup_sub_code' => ['required', 'string', 'max:40'],
            'dup_sub_name' => ['required', 'string', 'max:160'],
            'dup_sub_hours' => ['required', 'integer', 'min:1'],
            'dup_sub_sessions' => ['required', 'integer', 'min:1'],
        ]);

        $code = strtoupper(trim($this->dup_sub_code));
        if (CourseSubject::where('course_id', $source->course_id)->where('code', $code)->exists()) {
            $this->addError('dup_sub_code', "Ya existe una materia con el código {$code} en este curso.");

            return;
        }

        $nextOrder = (int) CourseSubject::where('course_id', $source->course_id)->max('order_index') + 1;
        $newSubject = CourseSubject::create([
            'course_id' => $source->course_id,
            'code' => $code,
            'name' => trim($this->dup_sub_name),
            'hours' => $this->dup_sub_hours,
            'sessions_count' => $this->dup_sub_sessions,
            'order_index' => $nextOrder,
            'is_mandatory' => $source->is_mandatory,
            'is_active' => true,
        ]);

        AuditService::log(
            event: 'academic.subject_duplicated',
            module: 'academico',
            auditable: $newSubject,
            oldValues: ['source_subject_id' => $source->id, 'source_code' => $source->code],
            newValues: $newSubject->only(['course_id', 'code', 'name', 'hours', 'sessions_count'])
        );

        $this->cancelDuplicateSubject();
        $this->new_sub_code = CourseSubject::nextIncrementalCode($source->course_id, $newSubject->code);
        $this->feedback = sprintf('Materia duplicada con código incremental %s ("%s").', $newSubject->code, $newSubject->name);
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
            $this->addError('ed_course_id', 'No puede crear cohortes fuera de sus categorías asignadas.');

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
        $this->feedback = 'Cohorte creada con capacidad autollenada y lista para matrícula.';
    }

    public function editEdition(int $editionId): void
    {
        $this->authorizeManage();
        $edition = CourseEdition::with('course')->findOrFail($editionId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $edition->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('ed_course_id', 'No tiene autorización sobre la categoría de esta cohorte.');

            return;
        }

        $this->closingEditionId = null;
        $this->editingEditionId = $edition->id;
        $this->edit_ed_code = $edition->code;
        $this->edit_ed_name = $edition->name;
        $this->edit_ed_shift_id = $edition->shift_id;
        $this->edit_ed_modality_id = $edition->modality_id;
        $this->edit_ed_classroom_id = $edition->classroom_id;
        $this->edit_ed_teacher_id = $edition->teacher_id;
        $this->edit_ed_start_date = $edition->start_date?->toDateString() ?? '';
        $this->edit_ed_end_date = $edition->end_date?->toDateString() ?? '';
        $this->edit_ed_max_capacity = (int) $edition->max_capacity;
        $this->edit_ed_enrollment_fee = (float) $edition->enrollment_fee;
        $this->edit_ed_monthly_fee = (float) $edition->monthly_fee;
        $this->edit_ed_installments_count = (int) $edition->installments_count;
        $this->edit_ed_public_open = (bool) $edition->is_public_enrollment_open;
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelEditEdition(): void
    {
        $this->editingEditionId = null;
        $this->resetValidation();
    }

    public function saveEditionEdit(): void
    {
        $this->authorizeManage();
        if (! $this->editingEditionId) {
            return;
        }

        $edition = CourseEdition::with('course')->findOrFail($this->editingEditionId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $edition->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('edit_ed_name', 'No tiene autorización sobre la categoría de esta cohorte.');

            return;
        }

        $this->validate([
            'edit_ed_code' => ['required', 'string', 'max:50', 'unique:course_editions,code,'.$edition->id],
            'edit_ed_name' => ['required', 'string', 'max:180'],
            'edit_ed_shift_id' => ['required', 'integer', 'exists:academic_shifts,id'],
            'edit_ed_modality_id' => ['required', 'integer', 'exists:academic_modalities,id'],
            'edit_ed_classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'edit_ed_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'edit_ed_start_date' => ['required', 'date'],
            'edit_ed_end_date' => ['required', 'date', 'after_or_equal:edit_ed_start_date'],
            'edit_ed_max_capacity' => ['required', 'integer', 'min:1'],
            'edit_ed_enrollment_fee' => ['required', 'numeric', 'min:0'],
            'edit_ed_monthly_fee' => ['required', 'numeric', 'min:0'],
            'edit_ed_installments_count' => ['required', 'integer', 'min:1', 'max:48'],
        ]);

        $activeCount = $edition->activeEnrollmentsCount();
        if ($this->edit_ed_max_capacity < $activeCount) {
            $this->addError('edit_ed_max_capacity', sprintf('La capacidad máxima no puede ser menor a las %d inscripciones activas actuales.', $activeCount));

            return;
        }

        $old = $edition->only(['code', 'name', 'shift_id', 'modality_id', 'classroom_id', 'teacher_id', 'start_date', 'end_date', 'max_capacity', 'enrollment_fee', 'monthly_fee', 'installments_count', 'is_public_enrollment_open']);

        // El código de la cohorte permanece inmutable al editar
        $edition->update([
            'name' => trim($this->edit_ed_name),
            'shift_id' => $this->edit_ed_shift_id,
            'modality_id' => $this->edit_ed_modality_id,
            'classroom_id' => $this->edit_ed_classroom_id ?: null,
            'teacher_id' => $this->edit_ed_teacher_id ?: null,
            'start_date' => $this->edit_ed_start_date,
            'end_date' => $this->edit_ed_end_date,
            'max_capacity' => $this->edit_ed_max_capacity,
            'enrollment_fee' => $this->edit_ed_enrollment_fee,
            'monthly_fee' => $this->edit_ed_monthly_fee,
            'installments_count' => $this->edit_ed_installments_count,
            'is_public_enrollment_open' => $this->edit_ed_public_open,
        ]);

        AuditService::log('academic.edition_updated', 'academico', $edition, $old, $edition->only(array_keys($old)));
        $this->editingEditionId = null;
        $this->feedback = sprintf('Cohorte %s actualizada manteniendo su mismo código (cambio auditado).', $edition->code);
    }

    public function toggleEditionPublicOpen(int $editionId): void
    {
        $this->authorizeManage();
        $edition = CourseEdition::with('course')->findOrFail($editionId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $edition->course->academic_category_id, $allowedCategoryIds, true)) {
            return;
        }

        $old = ['is_public_enrollment_open' => $edition->is_public_enrollment_open];
        $edition->update(['is_public_enrollment_open' => ! $edition->is_public_enrollment_open]);
        AuditService::log('academic.edition_public_toggled', 'academico', $edition, $old, ['is_public_enrollment_open' => $edition->is_public_enrollment_open]);
        $this->feedback = $edition->is_public_enrollment_open
            ? sprintf('Cohorte %s habilitada en /inscripcion.', $edition->code)
            : sprintf('Cohorte %s ocultada de /inscripcion (sin borrado físico).', $edition->code);
    }

    public function startCloseEdition(int $editionId): void
    {
        $this->authorizeManage();
        $edition = CourseEdition::with('course')->findOrFail($editionId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $edition->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('edition_closure_reason_code', 'No tiene autorización sobre la categoría de esta cohorte.');

            return;
        }

        $settings = InstitutionSetting::current();
        $this->editingEditionId = null;
        $this->closingEditionId = $edition->id;
        $this->edition_closure_type = 'cancelada';
        $this->edition_closure_reason_code = '';
        $this->edition_closure_notes = '';
        $this->edition_closure_message = sprintf(
            'Por decisión institucional la cohorte %s del curso/programa %s ha sido cerrada. Le invitamos a mantenerse pendiente de nuestra oferta académica y laboral, y le agradecemos pasar por las oficinas del Centro de Capacitación en nuestro horario de atención: %s.',
            $edition->name,
            $edition->course?->name ?? '',
            $settings->attention_hours ?: 'Lunes a viernes de 8:00 a.m. a 4:00 p.m.'
        );
        $this->edition_closure_notify_students = true;
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelCloseEdition(): void
    {
        $this->closingEditionId = null;
        $this->edition_closure_type = 'cancelada';
        $this->edition_closure_reason_code = '';
        $this->edition_closure_notes = '';
        $this->resetValidation();
    }

    public function confirmCloseEdition(): void
    {
        $this->authorizeManage();
        if (! $this->closingEditionId) {
            return;
        }

        $edition = CourseEdition::with('course')->findOrFail($this->closingEditionId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $edition->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('edition_closure_reason_code', 'No tiene autorización sobre la categoría de esta cohorte.');

            return;
        }

        $notifiableEnrollments = $edition->activeNotifiableEnrollments();
        $isCancel = $this->edition_closure_type === 'cancelada';

        $rules = [
            'edition_closure_type' => ['required', 'in:cancelada,finalizada'],
            'edition_closure_reason_code' => [$isCancel ? 'required' : 'nullable', 'string', 'max:60'],
            'edition_closure_notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($isCancel && $this->edition_closure_notify_students && $notifiableEnrollments->isNotEmpty()) {
            $rules['edition_closure_message'] = ['required', 'string', 'min:5', 'max:1000'];
        }

        $this->validate($rules, [
            'edition_closure_reason_code.required' => 'Debe seleccionar el motivo de cierre de la cohorte del catálogo (picklist).',
            'edition_closure_message.required' => 'Debe indicar el mensaje que se enviará por correo a las alumnas de esta cohorte.',
        ]);

        $reasonLabel = 'Cohorte finalizada satisfactoriamente';
        if ($this->edition_closure_reason_code !== '') {
            $reasonPicklist = Picklist::query()
                ->where('group_key', 'course_closure_reason')
                ->where('code', $this->edition_closure_reason_code)
                ->where('is_active', true)
                ->first();

            if (! $reasonPicklist) {
                $this->addError('edition_closure_reason_code', 'Debe seleccionar un motivo de cierre válido del catálogo.');

                return;
            }
            $reasonLabel = $reasonPicklist->label;
        }

        $extraNotes = trim($this->edition_closure_notes);
        $cleanReason = $extraNotes !== ''
            ? $reasonLabel.' — '.$extraNotes
            : $reasonLabel;
        $cleanMessage = trim($this->edition_closure_message);

        DB::transaction(function () use ($edition, $cleanReason, $notifiableEnrollments, $isCancel): void {
            $old = $edition->only(['status', 'is_public_enrollment_open', 'closed_at', 'closure_reason']);
            $edition->update([
                'status' => $this->edition_closure_type,
                'is_public_enrollment_open' => false,
                'closed_at' => now(),
                'closed_by' => Auth::id(),
                'closure_reason' => $cleanReason,
            ]);

            foreach ($notifiableEnrollments as $enr) {
                StudentLifecycleEvent::create([
                    'student_id' => $enr->student_id,
                    'enrollment_id' => $enr->id,
                    'event_type' => $isCancel ? 'cohorte_cerrada_institucional' : 'cohorte_finalizada',
                    'from_status' => $enr->status,
                    'to_status' => $enr->status,
                    'description' => sprintf(
                        'Cohorte %s (%s) del curso %s %s. Motivo: %s',
                        $edition->name,
                        $edition->code,
                        $edition->course?->name ?? '',
                        $isCancel ? 'cerrada por decisión institucional' : 'marcada como finalizada',
                        $cleanReason
                    ),
                    'metadata' => [
                        'course_id' => $edition->course_id,
                        'course_edition_id' => $edition->id,
                        'edition_code' => $edition->code,
                        'closure_type' => $this->edition_closure_type,
                        'closure_reason' => $cleanReason,
                    ],
                    'recorded_by' => Auth::id(),
                    'created_at' => now(),
                ]);
            }

            AuditService::log(
                event: 'academic.edition_closed',
                module: 'academico',
                auditable: $edition,
                oldValues: $old,
                newValues: [
                    'status' => $this->edition_closure_type,
                    'is_public_enrollment_open' => false,
                    'closed_at' => $edition->closed_at?->toDateTimeString(),
                    'closure_reason' => $cleanReason,
                    'affected_enrollments' => $notifiableEnrollments->count(),
                ],
                reason: $cleanReason
            );
        });

        $emailsSent = 0;
        if ($isCancel && $this->edition_closure_notify_students && $notifiableEnrollments->isNotEmpty()) {
            $settings = InstitutionSetting::current();
            $emailedAddresses = [];
            $course = $edition->course;
            foreach ($notifiableEnrollments as $enr) {
                $email = strtolower(trim((string) ($enr->student?->email ?? '')));
                if ($email === '' || isset($emailedAddresses[$email])) {
                    continue;
                }
                $emailedAddresses[$email] = true;
                try {
                    Mail::to($email)->send(new CourseClosedInstitutionalMail(
                        course: $course,
                        enrollment: $enr,
                        closureReason: $cleanReason,
                        studentMessage: $cleanMessage,
                        settings: $settings
                    ));
                    $emailsSent++;
                } catch (\Throwable $e) {
                    Log::warning('No se pudo enviar correo de cierre institucional de cohorte', [
                        'course_edition_id' => $edition->id,
                        'enrollment_id' => $enr->id,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $code = $edition->code;
        $statusLabel = $isCancel ? 'cerrada/cancelada' : 'finalizada';
        $this->cancelCloseEdition();
        $this->feedback = $isCancel
            ? sprintf('Cohorte %s %s (%d correo(s) enviado(s) a alumnas de esta cohorte; historial preservado en auditoría).', $code, $statusLabel, $emailsSent)
            : sprintf('Cohorte %s marcada como finalizada y retirada de matrícula activa (historial preservado en auditoría).', $code);
    }

    public function reopenEdition(int $editionId): void
    {
        $this->authorizeManage();
        $edition = CourseEdition::with('course')->findOrFail($editionId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $edition->course->academic_category_id, $allowedCategoryIds, true)) {
            return;
        }

        $old = $edition->only(['status', 'is_public_enrollment_open', 'closed_at', 'closure_reason']);
        $edition->update([
            'status' => 'abierta',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        AuditService::log(
            event: 'academic.edition_reopened',
            module: 'academico',
            auditable: $edition,
            oldValues: $old,
            newValues: ['status' => 'abierta', 'closed_at' => null]
        );

        $this->feedback = sprintf('Cohorte %s reabierta y activa nuevamente.', $edition->code);
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
            $this->addError('sch_edition_id', 'No tiene autorización sobre la categoría de esta cohorte.');

            return;
        }

        $startMin = EditionSchedule::timeToMinutes($this->sch_start_time);
        $endMin = EditionSchedule::timeToMinutes($this->sch_end_time);

        if ($endMin <= $startMin) {
            $this->addError('schedule', 'La hora de fin debe ser posterior a la hora de inicio.');

            return;
        }

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

    public function startEditSchedule(int $scheduleId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('academico.schedules.manage'), 403);

        $schedule = EditionSchedule::with('edition.course')->findOrFail($scheduleId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $schedule->edition?->course?->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('schedule', 'No tiene autorización sobre la categoría de este horario.');

            return;
        }

        $this->deletingScheduleId = null;
        $this->editingScheduleId = $schedule->id;
        $this->edit_sch_edition_id = $schedule->course_edition_id;
        $this->edit_sch_classroom_id = $schedule->classroom_id;
        $this->edit_sch_teacher_id = $schedule->teacher_id;
        $this->edit_sch_day_of_week = (int) $schedule->day_of_week;
        $this->edit_sch_start_time = EditionSchedule::minutesToTime((int) $schedule->start_minute);
        $this->edit_sch_end_time = EditionSchedule::minutesToTime((int) $schedule->end_minute);
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelEditSchedule(): void
    {
        $this->editingScheduleId = null;
        $this->resetValidation();
    }

    public function saveScheduleEdit(ScheduleConflictService $conflictService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('academico.schedules.manage'), 403);
        if (! $this->editingScheduleId) {
            return;
        }

        $schedule = EditionSchedule::with('edition.course')->findOrFail($this->editingScheduleId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $schedule->edition?->course?->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('schedule', 'No tiene autorización sobre la categoría de este horario.');

            return;
        }

        $this->validate([
            'edit_sch_edition_id' => ['required', 'integer', 'exists:course_editions,id'],
            'edit_sch_classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'edit_sch_teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'edit_sch_day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'edit_sch_start_time' => ['required', 'date_format:H:i'],
            'edit_sch_end_time' => ['required', 'date_format:H:i'],
        ]);

        $targetEdition = CourseEdition::with('course')->findOrFail($this->edit_sch_edition_id);
        if ($allowedCategoryIds !== null && ! in_array((int) $targetEdition->course->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('edit_sch_edition_id', 'No tiene autorización sobre la categoría de la cohorte seleccionada.');

            return;
        }

        $startMin = EditionSchedule::timeToMinutes($this->edit_sch_start_time);
        $endMin = EditionSchedule::timeToMinutes($this->edit_sch_end_time);

        if ($endMin <= $startMin) {
            $this->addError('schedule', 'La hora de fin debe ser posterior a la hora de inicio.');

            return;
        }

        $old = [
            'course_edition_id' => $schedule->course_edition_id,
            'classroom_id' => $schedule->classroom_id,
            'teacher_id' => $schedule->teacher_id,
            'slot' => $schedule->formatted_slot,
        ];

        try {
            $conflictService->assertScheduleHasNoConflicts(
                dayOfWeek: $this->edit_sch_day_of_week,
                startMinute: $startMin,
                endMinute: $endMin,
                startDate: $targetEdition->start_date->toDateString(),
                endDate: $targetEdition->end_date->toDateString(),
                classroomId: $this->edit_sch_classroom_id ?: null,
                teacherId: $this->edit_sch_teacher_id ?: null,
                ignoreScheduleId: $schedule->id
            );

            $schedule->update([
                'course_edition_id' => $targetEdition->id,
                'classroom_id' => $this->edit_sch_classroom_id ?: null,
                'teacher_id' => $this->edit_sch_teacher_id ?: null,
                'day_of_week' => $this->edit_sch_day_of_week,
                'start_minute' => $startMin,
                'end_minute' => $endMin,
                'start_date' => $targetEdition->start_date->toDateString(),
                'end_date' => $targetEdition->end_date->toDateString(),
            ]);
        } catch (ValidationException $e) {
            $this->addError('schedule', collect($e->errors())->flatten()->first());

            return;
        } catch (QueryException $e) {
            $this->addError('schedule', 'Bloqueo PostgreSQL GiST anti-choque: el aula o profesora ya tiene un horario superpuesto.');

            return;
        }

        $schedule->refresh();
        AuditService::log(
            event: 'academic.schedule_updated',
            module: 'academico',
            auditable: $schedule,
            oldValues: $old,
            newValues: [
                'course_edition_id' => $schedule->course_edition_id,
                'classroom_id' => $schedule->classroom_id,
                'teacher_id' => $schedule->teacher_id,
                'slot' => $schedule->formatted_slot,
            ]
        );

        $this->cancelEditSchedule();
        $this->feedback = sprintf('Horario modificado correctamente (%s) sin choques de aula ni profesora.', $schedule->formatted_slot);
    }

    public function startDeleteSchedule(int $scheduleId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('academico.schedules.manage'), 403);

        $schedule = EditionSchedule::with('edition.course')->findOrFail($scheduleId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $schedule->edition?->course?->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('schedule', 'No tiene autorización sobre la categoría de este horario.');

            return;
        }

        $this->editingScheduleId = null;
        $this->deletingScheduleId = $schedule->id;
        $this->schedule_delete_reason = '';
        $this->resetValidation();
        $this->feedback = null;
    }

    public function cancelDeleteSchedule(): void
    {
        $this->deletingScheduleId = null;
        $this->schedule_delete_reason = '';
        $this->resetValidation();
    }

    public function confirmDeleteSchedule(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('academico.schedules.manage'), 403);
        if (! $this->deletingScheduleId) {
            return;
        }

        $schedule = EditionSchedule::with('edition.course')->findOrFail($this->deletingScheduleId);
        $allowedCategoryIds = $this->scopedCategoryIds();
        if ($allowedCategoryIds !== null && ! in_array((int) $schedule->edition?->course?->academic_category_id, $allowedCategoryIds, true)) {
            $this->addError('schedule', 'No tiene autorización sobre la categoría de este horario.');

            return;
        }

        $slot = $schedule->formatted_slot;
        $reason = trim($this->schedule_delete_reason) ?: 'Horario eliminado/retirado desde Académico';

        // Retiro lógico (is_active=false): libera inmediatamente aula y profesora en PostgreSQL GiST sin borrado físico
        $schedule->update(['is_active' => false]);

        AuditService::log(
            event: 'academic.schedule_deleted',
            module: 'academico',
            auditable: $schedule,
            oldValues: ['is_active' => true, 'slot' => $slot],
            newValues: ['is_active' => false],
            reason: $reason
        );

        $this->cancelDeleteSchedule();
        $this->feedback = sprintf('Horario %s eliminado y bloque liberado en aula/profesora (traza preservada en auditoría).', $slot);
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

        $categoriesQuery = AcademicCategory::query()
            ->withCount(['courses' => fn ($q) => $q->whereNull('archived_at')])
            ->orderBy('name');
        $coursesQuery = Course::query()
            ->whereNull('archived_at')
            ->with(['category', 'subjects'])
            ->orderByDesc('id');
        $editionsQuery = CourseEdition::query()
            ->whereNotIn('status', ['cancelada', 'finalizada'])
            ->whereHas('course', fn ($q) => $q->whereNull('archived_at'))
            ->with(['course.category', 'shift', 'modality', 'classroom', 'teacher', 'schedules'])
            ->orderByDesc('id');
        $closedEditionsQuery = CourseEdition::query()
            ->whereIn('status', ['cancelada', 'finalizada'])
            ->whereHas('course', fn ($q) => $q->whereNull('archived_at'))
            ->with(['course.category', 'shift', 'modality', 'teacher'])
            ->orderByDesc('closed_at')
            ->orderByDesc('id');
        $schedulesQuery = EditionSchedule::query()
            ->where('is_active', true)
            ->whereHas('edition.course', fn ($q) => $q->whereNull('archived_at'))
            ->with(['edition.course', 'classroom', 'teacher'])
            ->orderBy('day_of_week')
            ->orderBy('start_minute');

        if ($scopedIds !== null) {
            $categoriesQuery->whereIn('id', $scopedIds);
            $coursesQuery->whereIn('academic_category_id', $scopedIds);
            $editionsQuery->whereHas('course', fn ($q) => $q->whereIn('academic_category_id', $scopedIds));
            $closedEditionsQuery->whereHas('course', fn ($q) => $q->whereIn('academic_category_id', $scopedIds));
            $schedulesQuery->whereHas('edition.course', fn ($q) => $q->whereIn('academic_category_id', $scopedIds));
        }

        $closingCourse = $this->closingCourseId ? Course::with('editions')->find($this->closingCourseId) : null;
        $closingEdition = $this->closingEditionId ? CourseEdition::with('course')->find($this->closingEditionId) : null;
        $closingEditionNotifiableCount = $closingEdition ? $closingEdition->activeNotifiableEnrollments()->count() : 0;

        return view('livewire.academic-page', [
            'isScopedCoordinator' => $scopedIds !== null,
            'categories' => $categoriesQuery->get(),
            'courses' => $coursesQuery->get(),
            'managingCourse' => $this->managingSubjectsCourseId ? Course::with('subjects')->find($this->managingSubjectsCourseId) : null,
            'closingCourse' => $closingCourse,
            'closingEdition' => $closingEdition,
            'closingEditionNotifiableCount' => $closingEditionNotifiableCount,
            'deletingCourse' => $this->deletingCourseId ? Course::find($this->deletingCourseId) : null,
            'editions' => $editionsQuery->get(),
            'closedEditions' => $closedEditionsQuery->get(),
            'schedules' => $schedulesQuery->get(),
            'shifts' => AcademicShift::where('is_active', true)->get(),
            'modalities' => AcademicModality::where('is_active', true)->get(),
            'classrooms' => Classroom::where('is_active', true)->orderBy('name')->get(),
            'teachers' => User::role('profesora')->where('is_active', true)->orderBy('name')->get(),
            'educationLevels' => Picklist::optionsFor('education_level'),
            'courseClosureReasons' => Picklist::optionsFor('course_closure_reason'),
            'documentTypes' => config('cecap.document_types'),
            'daysOfWeek' => config('cecap.days_of_week'),
        ]);
    }
}
