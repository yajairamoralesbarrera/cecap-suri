<?php

namespace App\Services;

use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\InstitutionSetting;
use App\Models\Student;
use App\Models\StudentLifecycleEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function __construct(
        protected ScheduleConflictService $scheduleConflicts,
        protected FinanceService $financeService
    ) {}

    public function createOnlinePreEnrollment(
        Student $student,
        CourseEdition $edition,
        array $submittedDocuments = [],
        ?string $notes = null
    ): Enrollment {
        $settings = InstitutionSetting::current();

        if (strtolower((string) $student->gender) !== 'femenino') {
            throw ValidationException::withMessages([
                'gender' => $settings->gender_policy_message,
            ]);
        }

        if ($edition->availableSeats() <= 0) {
            throw ValidationException::withMessages([
                'course_edition_id' => 'Esta edición no cuenta con cupos disponibles.',
            ]);
        }

        $this->scheduleConflicts->assertStudentHasNoScheduleConflict($student, $edition);

        return DB::transaction(function () use ($student, $edition, $submittedDocuments, $notes, $settings): Enrollment {
            $requiredDocs = $edition->course->required_documents ?? CourseEdition::find($edition->id)->course->required_documents ?? ['cedula_dimex'];
            $submitted = array_values(array_unique($submittedDocuments));
            $missing = array_diff($requiredDocs, $submitted);
            $docsComplete = count($missing) === 0;
            $status = $docsComplete ? 'preinscrita' : 'documentacion_pendiente';

            $enrollment = Enrollment::create([
                'enrollment_code' => Enrollment::nextCode(),
                'student_id' => $student->id,
                'course_edition_id' => $edition->id,
                'source' => 'en_linea',
                'status' => $status,
                'provisional_expires_at' => now()->addDays(max(1, (int) $settings->online_enrollment_provisional_days)),
                'required_documents_snapshot' => array_values($requiredDocs),
                'submitted_documents' => $submitted,
                'documents_complete' => $docsComplete,
                'notes' => $notes,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            StudentLifecycleEvent::create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'event_type' => 'preinscripcion_en_linea',
                'from_status' => null,
                'to_status' => $status,
                'description' => $docsComplete
                    ? sprintf('Preinscripción en línea provisional (plazo %d días) para %s.', $settings->online_enrollment_provisional_days, $edition->name)
                    : sprintf('Preinscripción en línea registrada en estado Documentación Pendiente para %s.', $edition->name),
                'metadata' => ['missing_documents' => array_values($missing)],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: 'enrollment.online_created',
                module: 'admisiones',
                auditable: $enrollment,
                newValues: $enrollment->only(['enrollment_code', 'student_id', 'course_edition_id', 'status', 'documents_complete'])
            );

            return $enrollment;
        });
    }

    public function createPresencialEnrollment(
        Student $student,
        CourseEdition $edition,
        array $submittedDocuments = [],
        bool $confirmImmediately = true,
        bool $authorizeException = false,
        ?string $exceptionReason = null,
        ?string $exceptionCommitmentDate = null,
        ?string $notes = null
    ): Enrollment {
        if ($edition->availableSeats() <= 0) {
            throw ValidationException::withMessages([
                'course_edition_id' => 'Esta edición no cuenta con cupos disponibles.',
            ]);
        }

        $this->scheduleConflicts->assertStudentHasNoScheduleConflict($student, $edition);

        $requiredDocs = $edition->course->required_documents ?? ['cedula_dimex'];
        $submitted = array_values(array_unique($submittedDocuments));
        $missing = array_values(array_diff($requiredDocs, $submitted));
        $docsComplete = count($missing) === 0;

        if ($confirmImmediately && ! $docsComplete && ! $authorizeException) {
            throw ValidationException::withMessages([
                'documents' => 'Matrícula presencial bloqueada: faltan documentos obligatorios ('.implode(', ', $missing).'). Debe completarlos o autorizar una excepción con motivo y fecha compromiso.',
            ]);
        }

        if ($authorizeException && (! filled($exceptionReason) || ! filled($exceptionCommitmentDate))) {
            throw ValidationException::withMessages([
                'exception_reason' => 'Para autorizar una excepción documental debe indicar el motivo y la fecha compromiso.',
            ]);
        }

        return DB::transaction(function () use (
            $student,
            $edition,
            $requiredDocs,
            $submitted,
            $docsComplete,
            $confirmImmediately,
            $authorizeException,
            $exceptionReason,
            $exceptionCommitmentDate,
            $notes
        ): Enrollment {
            $status = $confirmImmediately
                ? 'confirmada'
                : ($docsComplete ? 'preinscrita' : 'documentacion_pendiente');

            $enrollment = Enrollment::create([
                'enrollment_code' => Enrollment::nextCode(),
                'student_id' => $student->id,
                'course_edition_id' => $edition->id,
                'source' => 'presencial',
                'status' => $status,
                'confirmed_at' => $confirmImmediately ? now() : null,
                'confirmed_by' => $confirmImmediately ? Auth::id() : null,
                'required_documents_snapshot' => array_values($requiredDocs),
                'submitted_documents' => $submitted,
                'documents_complete' => $docsComplete,
                'exception_authorized' => $authorizeException,
                'exception_reason' => $authorizeException ? $exceptionReason : null,
                'exception_commitment_date' => $authorizeException ? $exceptionCommitmentDate : null,
                'exception_authorized_by' => $authorizeException ? Auth::id() : null,
                'exception_authorized_at' => $authorizeException ? now() : null,
                'notes' => $notes,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            if ($confirmImmediately) {
                $this->financeService->generateFeePlanForEnrollment($enrollment);
            }

            StudentLifecycleEvent::create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'event_type' => $confirmImmediately ? 'matricula_confirmada' : 'solicitud_presencial',
                'from_status' => null,
                'to_status' => $status,
                'description' => $authorizeException
                    ? sprintf('Matrícula confirmada por excepción documental (compromiso: %s). Motivo: %s', $exceptionCommitmentDate, $exceptionReason)
                    : sprintf('Matrícula presencial registrada en estado %s para %s.', $status, $edition->name),
                'metadata' => ['exception_authorized' => $authorizeException],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: $authorizeException ? 'enrollment.confirmed_with_exception' : 'enrollment.presencial_created',
                module: 'admisiones',
                auditable: $enrollment,
                newValues: $enrollment->only(['enrollment_code', 'status', 'documents_complete', 'exception_authorized', 'exception_commitment_date']),
                reason: $authorizeException ? $exceptionReason : null
            );

            return $enrollment;
        });
    }

    public function confirmEnrollment(
        Enrollment $enrollment,
        array $submittedDocuments,
        bool $authorizeException = false,
        ?string $exceptionReason = null,
        ?string $exceptionCommitmentDate = null
    ): Enrollment {
        $requiredDocs = $enrollment->required_documents_snapshot ?? ['cedula_dimex'];
        $submitted = array_values(array_unique($submittedDocuments));
        $missing = array_values(array_diff($requiredDocs, $submitted));
        $docsComplete = count($missing) === 0;

        if (! $docsComplete && ! $authorizeException) {
            throw ValidationException::withMessages([
                'documents' => 'No se puede confirmar la matrícula: faltan documentos obligatorios ('.implode(', ', $missing).'). Autorice una excepción con motivo y fecha compromiso para continuar.',
            ]);
        }

        if ($authorizeException && (! filled($exceptionReason) || ! filled($exceptionCommitmentDate))) {
            throw ValidationException::withMessages([
                'exception_reason' => 'La excepción documental exige motivo y fecha compromiso.',
            ]);
        }

        return DB::transaction(function () use (
            $enrollment,
            $submitted,
            $docsComplete,
            $authorizeException,
            $exceptionReason,
            $exceptionCommitmentDate
        ): Enrollment {
            $oldStatus = $enrollment->status;

            $enrollment->update([
                'status' => 'confirmada',
                'submitted_documents' => $submitted,
                'documents_complete' => $docsComplete,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id(),
                'exception_authorized' => $authorizeException,
                'exception_reason' => $authorizeException ? $exceptionReason : $enrollment->exception_reason,
                'exception_commitment_date' => $authorizeException ? $exceptionCommitmentDate : $enrollment->exception_commitment_date,
                'exception_authorized_by' => $authorizeException ? Auth::id() : $enrollment->exception_authorized_by,
                'exception_authorized_at' => $authorizeException ? now() : $enrollment->exception_authorized_at,
                'updated_by' => Auth::id(),
            ]);

            if ($enrollment->feeCharges()->count() === 0) {
                $this->financeService->generateFeePlanForEnrollment($enrollment);
            }

            StudentLifecycleEvent::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'event_type' => 'matricula_confirmada',
                'from_status' => $oldStatus,
                'to_status' => 'confirmada',
                'description' => $authorizeException
                    ? sprintf('Matrícula confirmada con excepción documental hasta %s. Motivo: %s', $exceptionCommitmentDate, $exceptionReason)
                    : 'Matrícula presencial confirmada con documentación verificada.',
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: $authorizeException ? 'enrollment.confirmed_with_exception' : 'enrollment.confirmed',
                module: 'admisiones',
                auditable: $enrollment,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => 'confirmada', 'documents_complete' => $docsComplete, 'exception_authorized' => $authorizeException],
                reason: $exceptionReason
            );

            return $enrollment->fresh();
        });
    }
}
