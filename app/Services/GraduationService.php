<?php

namespace App\Services;

use App\Models\DiplomaTemplate;
use App\Models\Enrollment;
use App\Models\GraduationRecord;
use App\Models\InstitutionSetting;
use App\Models\StudentLifecycleEvent;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GraduationService
{
    public function evaluateEligibility(Enrollment $enrollment): array
    {
        $enrollment->loadMissing('edition.course');
        $course = $enrollment->edition->course;
        $settings = InstitutionSetting::current();

        $finalGrade = $enrollment->calculateFinalGrade();
        $attendance = $enrollment->calculateAttendancePercent();
        $balance = $enrollment->pendingBalance();

        $gradePassed = $finalGrade >= (float) $course->min_passing_grade;
        $attendancePassed = $attendance >= (float) $course->min_attendance_percent;
        $isSolvent = $balance <= 0.00;
        $solvencyRequired = (bool) $settings->require_financial_solvency_for_graduation;
        $solvencyPassed = ! $solvencyRequired || $isSolvent;

        return [
            'final_grade' => $finalGrade,
            'min_grade' => (float) $course->min_passing_grade,
            'grade_passed' => $gradePassed,
            'attendance_percent' => $attendance,
            'min_attendance' => (float) $course->min_attendance_percent,
            'attendance_passed' => $attendancePassed,
            'pending_balance' => $balance,
            'is_solvent' => $isSolvent,
            'solvency_required' => $solvencyRequired,
            'solvency_passed' => $solvencyPassed,
            'eligible' => $gradePassed && $attendancePassed && $solvencyPassed,
            'needs_solvency_exception' => $gradePassed && $attendancePassed && ! $solvencyPassed,
        ];
    }

    public function issueGraduation(
        Enrollment $enrollment,
        string $tomo,
        string $folio,
        string $asiento,
        string $graduationDate,
        ?int $templateId = null,
        bool $solvencyException = false,
        ?string $solvencyExceptionReason = null,
        ?User $actor = null
    ): GraduationRecord {
        $actor ??= Auth::user();
        $eval = $this->evaluateEligibility($enrollment);

        if (! $eval['grade_passed'] || ! $eval['attendance_passed']) {
            throw ValidationException::withMessages([
                'eligibility' => sprintf(
                    'La alumna no cumple requisitos académicos (Nota %.2f/%.2f, Asistencia %.2f%%/%.2f%%).',
                    $eval['final_grade'],
                    $eval['min_grade'],
                    $eval['attendance_percent'],
                    $eval['min_attendance']
                ),
            ]);
        }

        if (! $eval['solvency_passed']) {
            if (! $solvencyException) {
                throw ValidationException::withMessages([
                    'solvency' => sprintf(
                        'La alumna mantiene un saldo pendiente de ₡%s. Se requiere solvencia financiera o excepción exclusiva de Dirección.',
                        number_format($eval['pending_balance'], 2)
                    ),
                ]);
            }

            if (! $actor || ! $actor->hasAnyRole(['direccion', 'super_admin'])) {
                throw ValidationException::withMessages([
                    'solvency' => 'Solo Dirección o Super Administrador puede autorizar excepciones de solvencia para graduación.',
                ]);
            }

            if (trim((string) $solvencyExceptionReason) === '') {
                throw ValidationException::withMessages([
                    'solvency_exception_reason' => 'Debe indicar el motivo formal para la excepción de solvencia.',
                ]);
            }
        }

        $duplicate = GraduationRecord::query()
            ->where('tomo', trim($tomo))
            ->where('folio', trim($folio))
            ->where('asiento', trim($asiento))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'asiento' => sprintf('Ya existe un acta registrada en Tomo %s, Folio %s, Asiento %s.', $tomo, $folio, $asiento),
            ]);
        }

        return DB::transaction(function () use (
            $enrollment,
            $eval,
            $tomo,
            $folio,
            $asiento,
            $graduationDate,
            $templateId,
            $solvencyException,
            $solvencyExceptionReason,
            $actor
        ): GraduationRecord {
            $template = $templateId
                ? DiplomaTemplate::find($templateId)
                : DiplomaTemplate::query()->where('is_default', true)->first();

            $record = GraduationRecord::create([
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'diploma_template_id' => $template?->id,
                'final_grade' => $eval['final_grade'],
                'attendance_percent' => $eval['attendance_percent'],
                'is_financially_solvent' => $eval['is_solvent'],
                'solvency_exception_authorized' => $solvencyException && ! $eval['is_solvent'],
                'solvency_exception_reason' => $solvencyException ? $solvencyExceptionReason : null,
                'solvency_exception_by' => $solvencyException ? $actor?->id : null,
                'tomo' => trim($tomo),
                'folio' => trim($folio),
                'asiento' => trim($asiento),
                'act_number' => GraduationRecord::nextActNumber(),
                'graduation_date' => $graduationDate,
                'status' => 'emitido',
                'issued_by' => $actor?->id ?? 1,
            ]);

            $oldStatus = $enrollment->status;
            $enrollment->update(['status' => 'graduada']);
            $enrollment->student->update(['status' => 'graduada']);

            StudentLifecycleEvent::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'event_type' => 'graduacion_emitida',
                'from_status' => $oldStatus,
                'to_status' => 'graduada',
                'description' => sprintf('Acta %s (Tomo %s, Folio %s, Asiento %s) emitida.', $record->act_number, $record->tomo, $record->folio, $record->asiento),
                'metadata' => ['solvency_exception' => $record->solvency_exception_authorized],
                'recorded_by' => $actor?->id,
                'created_at' => now(),
            ]);

            AuditService::log(
                event: 'graduation.issued',
                module: 'graduacion',
                auditable: $record,
                newValues: $record->only(['act_number', 'tomo', 'folio', 'asiento', 'verification_token', 'solvency_exception_authorized']),
                reason: $solvencyExceptionReason
            );

            return $record;
        });
    }

    public function generateVerificationQrSvg(string $url, int $size = 140): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );

        return (new Writer($renderer))->writeString($url);
    }
}
