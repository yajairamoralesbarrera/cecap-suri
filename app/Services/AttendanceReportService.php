<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\CourseEdition;
use App\Models\User;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    public function accessibleEditionsFor(User $user): Collection
    {
        $query = CourseEdition::query()->with(['course.subjects'])->orderByDesc('id');

        if ($user->hasRole('profesora') && ! $user->hasAnyRole(['super_admin', 'direccion', 'coordinacion_academica', 'coordinador_categoria'])) {
            $query->where('teacher_id', $user->id);
        } elseif ($user->isCategoryScopedCoordinator()) {
            $query->whereHas('course', fn ($q) => $q->whereIn('academic_category_id', $user->activeAcademicCategoryIds()));
        }

        return $query->get();
    }

    /**
     * @param  array{
     *   course_id?: int|string|null,
     *   edition_id?: int|string|null,
     *   subject_id?: string|int|null,
     *   student_id?: int|string|null,
     *   student_search?: string|null,
     *   status?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   limit?: int|null
     * }  $filters
     * @return array{records: Collection, summary: array<string, int|float>, byStudent: Collection}
     */
    public function query(array $accessibleEditionIds, array $filters = []): array
    {
        $records = collect();

        if (! empty($accessibleEditionIds)) {
            $attQuery = AttendanceRecord::query()
                ->with(['student', 'edition.course', 'subject', 'recorder'])
                ->where('is_current', true)
                ->whereIn('course_edition_id', $accessibleEditionIds);

            $courseId = ! empty($filters['course_id']) ? (int) $filters['course_id'] : null;
            $editionId = ! empty($filters['edition_id']) ? (int) $filters['edition_id'] : null;
            $subjectId = (string) ($filters['subject_id'] ?? '');
            $studentId = ! empty($filters['student_id']) ? (int) $filters['student_id'] : null;
            $studentSearch = trim((string) ($filters['student_search'] ?? ''));
            $status = trim((string) ($filters['status'] ?? ''));
            $dateFrom = trim((string) ($filters['date_from'] ?? ''));
            $dateTo = trim((string) ($filters['date_to'] ?? ''));

            if ($courseId) {
                $attQuery->whereHas('edition', fn ($q) => $q->where('course_id', $courseId));
            }
            if ($editionId) {
                $attQuery->where('course_edition_id', $editionId);
            }
            if ($subjectId === '__general__') {
                $attQuery->whereNull('course_subject_id');
            } elseif ($subjectId !== '') {
                $attQuery->where('course_subject_id', (int) $subjectId);
            }
            if ($studentId) {
                $attQuery->where('student_id', $studentId);
            }
            if ($studentSearch !== '') {
                $term = '%'.mb_strtolower($studentSearch).'%';
                $attQuery->whereHas('student', function ($q) use ($term) {
                    $q->whereRaw("LOWER(TRIM(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '') || ' ' || COALESCE(second_last_name, ''))) LIKE ?", [$term])
                        ->orWhereRaw('LOWER(identification_number) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(student_code) LIKE ?', [$term]);
                });
            }
            if ($status !== '') {
                $attQuery->where('status', $status);
            }
            if ($dateFrom !== '') {
                $attQuery->whereDate('session_date', '>=', $dateFrom);
            }
            if ($dateTo !== '') {
                $attQuery->whereDate('session_date', '<=', $dateTo);
            }

            $attQuery->orderByDesc('session_date')->orderByDesc('id');

            if (! empty($filters['limit'])) {
                $attQuery->limit((int) $filters['limit']);
            }

            $records = $attQuery->get();
        }

        $totalSessions = $records->count();
        $presentCount = $records->where('status', 'presente')->count();
        $lateCount = $records->where('status', 'tardia')->count();
        $excusedCount = $records->where('status', 'justificada')->count();
        $absentCount = $records->where('status', 'ausente')->count();
        $effectiveCount = $presentCount + $lateCount + $excusedCount;

        $summary = [
            'total' => $totalSessions,
            'presente' => $presentCount,
            'tardia' => $lateCount,
            'justificada' => $excusedCount,
            'ausente' => $absentCount,
            'percent' => $totalSessions > 0 ? round(($effectiveCount / $totalSessions) * 100, 1) : 0.0,
        ];

        $byStudent = $records
            ->groupBy(fn (AttendanceRecord $r) => $r->student_id.'-'.$r->course_edition_id.'-'.($r->course_subject_id ?? 0))
            ->map(function (Collection $group) {
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

        return [
            'records' => $records,
            'summary' => $summary,
            'byStudent' => $byStudent,
        ];
    }
}
