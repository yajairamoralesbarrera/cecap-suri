<?php

namespace App\Services;

use App\Models\CourseEdition;
use App\Models\EditionSchedule;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

class ScheduleConflictService
{
    public function assertScheduleHasNoConflicts(
        int $dayOfWeek,
        int $startMinute,
        int $endMinute,
        string $startDate,
        string $endDate,
        ?int $classroomId,
        ?int $teacherId,
        ?int $ignoreScheduleId = null
    ): void {
        if ($endMinute <= $startMinute) {
            throw ValidationException::withMessages([
                'schedule' => 'La hora de finalización debe ser posterior a la hora de inicio.',
            ]);
        }

        if ($endDate < $startDate) {
            throw ValidationException::withMessages([
                'schedule' => 'La fecha de finalización debe ser mayor o igual a la fecha de inicio.',
            ]);
        }

        if ($classroomId) {
            $classroomConflict = EditionSchedule::query()
                ->where('is_active', true)
                ->where('classroom_id', $classroomId)
                ->where('day_of_week', $dayOfWeek)
                ->when($ignoreScheduleId, fn ($q) => $q->where('id', '!=', $ignoreScheduleId))
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->where('start_minute', '<', $endMinute)
                ->where('end_minute', '>', $startMinute)
                ->with('edition.course')
                ->first();

            if ($classroomConflict) {
                throw ValidationException::withMessages([
                    'schedule' => sprintf(
                        'Choque de aula detectado con "%s" (%s).',
                        $classroomConflict->edition?->name ?? 'otra cohorte',
                        $classroomConflict->formatted_slot
                    ),
                ]);
            }
        }

        if ($teacherId) {
            $teacherConflict = EditionSchedule::query()
                ->where('is_active', true)
                ->where('teacher_id', $teacherId)
                ->where('day_of_week', $dayOfWeek)
                ->when($ignoreScheduleId, fn ($q) => $q->where('id', '!=', $ignoreScheduleId))
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->where('start_minute', '<', $endMinute)
                ->where('end_minute', '>', $startMinute)
                ->with('edition.course')
                ->first();

            if ($teacherConflict) {
                throw ValidationException::withMessages([
                    'schedule' => sprintf(
                        'Choque de profesora detectado con "%s" (%s).',
                        $teacherConflict->edition?->name ?? 'otra cohorte',
                        $teacherConflict->formatted_slot
                    ),
                ]);
            }
        }
    }

    public function assertStudentHasNoScheduleConflict(Student $student, CourseEdition $targetEdition): void
    {
        $targetSchedules = $targetEdition->schedules()->get();
        if ($targetSchedules->isEmpty()) {
            return;
        }

        $activeEditionIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('course_edition_id', '!=', $targetEdition->id)
            ->whereIn('status', ['preinscrita', 'documentacion_pendiente', 'confirmada', 'en_curso'])
            ->pluck('course_edition_id');

        if ($activeEditionIds->isEmpty()) {
            return;
        }

        $existingSchedules = EditionSchedule::query()
            ->whereIn('course_edition_id', $activeEditionIds)
            ->where('is_active', true)
            ->with('edition')
            ->get();

        foreach ($targetSchedules as $target) {
            foreach ($existingSchedules as $existing) {
                $sameDay = $target->day_of_week === $existing->day_of_week;
                $datesOverlap = $target->start_date <= $existing->end_date && $target->end_date >= $existing->start_date;
                $timesOverlap = $target->start_minute < $existing->end_minute && $target->end_minute > $existing->start_minute;

                if ($sameDay && $datesOverlap && $timesOverlap) {
                    throw ValidationException::withMessages([
                        'course_edition_id' => sprintf(
                            'Choque de horario para la alumna: coincide con "%s" (%s).',
                            $existing->edition?->name ?? 'curso inscrito',
                            $existing->formatted_slot
                        ),
                    ]);
                }
            }
        }
    }
}
