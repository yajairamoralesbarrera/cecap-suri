<?php

namespace App\Services;

use App\Mail\EnrollmentRejectedMail;
use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\InstitutionSetting;
use App\Models\Picklist;
use App\Models\Student;
use App\Models\StudentLifecycleEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $edition->loadMissing('course');
        $this->assertNotAlreadyEnrolledInCourse($student, $edition);

        if ($eduError = $edition->course?->educationLevelRequirementError($student->education_level)) {
            throw ValidationException::withMessages([
                'education_level' => $eduError,
            ]);
        }

        $this->scheduleConflicts->assertStudentHasNoScheduleConflict($student, $edition);

        return DB::transaction(function () use ($student, $edition, $submittedDocuments, $notes, $settings): Enrollment {
            $lockedEdition = CourseEdition::with('course')->whereKey($edition->id)->lockForUpdate()->firstOrFail();
            $isWaitlist = $lockedEdition->availableSeats() <= 0;

            $requiredDocs = $lockedEdition->course->required_documents ?? ['cedula_dimex'];
            $submitted = array_values(array_unique($submittedDocuments));
            $missing = array_diff($requiredDocs, $submitted);
            $docsComplete = count($missing) === 0;
            $status = $isWaitlist
                ? 'lista_espera'
                : ($docsComplete ? 'preinscrita' : 'documentacion_pendiente');

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
                'capacity_decision_code' => $isWaitlist ? 'lista_espera' : null,
                'notes' => $notes,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            StudentLifecycleEvent::create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'event_type' => $isWaitlist ? 'lista_espera_en_linea' : 'preinscripcion_en_linea',
                'from_status' => null,
                'to_status' => $status,
                'description' => $isWaitlist
                    ? sprintf('Cupo máximo alcanzado (%d/%d). Solicitud en línea registrada en Lista de Espera para %s.', $lockedEdition->activeEnrollmentsCount(), (int) $lockedEdition->max_capacity, $edition->name)
                    : ($docsComplete
                        ? sprintf('Preinscripción en línea provisional (plazo %d días) para %s.', $settings->online_enrollment_provisional_days, $edition->name)
                        : sprintf('Preinscripción en línea registrada en estado Documentación Pendiente para %s.', $edition->name)),
                'metadata' => [
                    'missing_documents' => array_values($missing),
                    'waitlist' => $isWaitlist,
                ],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: $isWaitlist ? 'enrollment.waitlisted_online' : 'enrollment.online_created',
                module: 'admisiones',
                auditable: $enrollment,
                newValues: $enrollment->only(['enrollment_code', 'student_id', 'course_edition_id', 'status', 'documents_complete', 'capacity_decision_code'])
            );

            return $enrollment;
        });
    }

    /**
     * Adjunta documentos faltantes a una preinscripción en línea existente (sin duplicar matrícula).
     *
     * @param  list<string>  $newlySubmittedDocuments
     */
    public function attachOnlinePendingDocuments(Enrollment $enrollment, array $newlySubmittedDocuments): Enrollment
    {
        $allowedStatus = in_array($enrollment->status, ['documentacion_pendiente', 'preinscrita', 'lista_espera'], true)
            || ($enrollment->status === 'confirmada' && ! $enrollment->documents_complete);

        if (! $allowedStatus) {
            throw ValidationException::withMessages([
                'documents' => 'Esta solicitud ya no admite carga pública de documentos.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $newlySubmittedDocuments): Enrollment {
            $oldStatus = $enrollment->status;
            $requiredDocs = $enrollment->required_documents_snapshot ?? ['cedula_dimex'];
            $mergedSubmitted = array_values(array_unique(array_merge(
                $enrollment->submitted_documents ?? [],
                $newlySubmittedDocuments
            )));
            $missing = array_values(array_diff($requiredDocs, $mergedSubmitted));
            $docsComplete = count($missing) === 0;
            $newStatus = in_array($oldStatus, ['lista_espera', 'confirmada'], true)
                ? $oldStatus
                : ($docsComplete ? 'preinscrita' : 'documentacion_pendiente');

            $enrollment->update([
                'submitted_documents' => $mergedSubmitted,
                'documents_complete' => $docsComplete,
                'status' => $newStatus,
                'updated_by' => Auth::id(),
            ]);

            StudentLifecycleEvent::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'event_type' => $docsComplete ? 'documentacion_completada_en_linea' : 'documento_adjuntado_en_linea',
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'description' => $docsComplete
                    ? ($newStatus === 'lista_espera'
                        ? 'Documentación faltante cargada en línea; solicitud continúa en Lista de Espera con expediente completo.'
                        : 'Documentación faltante cargada en línea; solicitud actualizada a Preinscrita (Provisional).')
                    : 'Documento(s) adicional(es) cargados en línea; aún quedan requisitos pendientes.',
                'metadata' => [
                    'added_documents' => array_values($newlySubmittedDocuments),
                    'missing_documents' => $missing,
                ],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: 'enrollment.online_documents_uploaded',
                module: 'admisiones',
                auditable: $enrollment,
                oldValues: ['status' => $oldStatus, 'submitted_documents' => $enrollment->getOriginal('submitted_documents')],
                newValues: ['status' => $newStatus, 'submitted_documents' => $mergedSubmitted, 'documents_complete' => $docsComplete]
            );

            return $enrollment->fresh();
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
        ?string $notes = null,
        ?string $capacityDecisionCode = null,
        ?string $capacityDecisionNotes = null,
        string $presencialAction = 'inscribir',
        ?string $rejectionReasonCode = null,
        ?string $rejectionNotes = null
    ): Enrollment {
        $edition->loadMissing('course');
        $this->assertNotAlreadyEnrolledInCourse($student, $edition);

        $isFull = $edition->availableSeats() <= 0;
        $isDirectRejection = (! $isFull && $presencialAction === 'rechazar')
            || ($isFull && $capacityDecisionCode === 'rechazar_cupo_lleno');
        $isDirectWaitlist = (! $isFull && $presencialAction === 'lista_espera')
            || ($isFull && $capacityDecisionCode === 'lista_espera');

        if (! $isDirectRejection && ! $authorizeException && ($eduError = $edition->course?->educationLevelRequirementError($student->education_level))) {
            throw ValidationException::withMessages([
                'education_level' => $eduError.' Si Dirección autoriza una excepción, marque la casilla de excepción e indique motivo y fecha compromiso.',
            ]);
        }

        $decisionRecord = null;
        $rejectionPicklistRecord = null;

        if ($isFull) {
            if (! filled($capacityDecisionCode)) {
                throw ValidationException::withMessages([
                    'full_capacity_decision' => sprintf(
                        'Cupo máximo alcanzado para esta cohorte (%d / %d cupos ocupados). El funcionario debe seleccionar una decisión del picklist para continuar.',
                        $edition->activeEnrollmentsCount(),
                        (int) $edition->max_capacity
                    ),
                ]);
            }

            $decisionRecord = Picklist::query()
                ->where('group_key', 'full_capacity_decision')
                ->where('code', $capacityDecisionCode)
                ->where('is_active', true)
                ->first();

            if (! $decisionRecord) {
                throw ValidationException::withMessages([
                    'full_capacity_decision' => 'Debe seleccionar una decisión válida del catálogo de cupo lleno.',
                ]);
            }

            if ($capacityDecisionCode === 'trasladar_otra_edicion') {
                throw ValidationException::withMessages([
                    'course_edition_id' => 'Decisión seleccionada: Reubicar en otra cohorte. Seleccione otra cohorte con cupos disponibles en el campo "Curso / Cohorte a Matricular".',
                ]);
            }

            if ($capacityDecisionCode === 'autorizar_sobrecupo' && ! filled($capacityDecisionNotes) && ! filled($exceptionReason)) {
                throw ValidationException::withMessages([
                    'full_capacity_notes' => 'Para autorizar un sobrecupo excepcional debe indicar la justificación u observación de auditoría.',
                ]);
            }
        } elseif ($presencialAction === 'rechazar') {
            if (! filled($rejectionReasonCode)) {
                throw ValidationException::withMessages([
                    'presencial_rejection_reason_code' => 'Debe seleccionar un motivo de rechazo del catálogo (picklist).',
                ]);
            }

            $rejectionPicklistRecord = Picklist::query()
                ->where('group_key', 'enrollment_rejection_reason')
                ->where('code', $rejectionReasonCode)
                ->where('is_active', true)
                ->first();

            if (! $rejectionPicklistRecord) {
                throw ValidationException::withMessages([
                    'presencial_rejection_reason_code' => 'El motivo de rechazo seleccionado no es válido o está inactivo.',
                ]);
            }
        }

        if (! $isDirectRejection) {
            $this->scheduleConflicts->assertStudentHasNoScheduleConflict($student, $edition);
        }

        $requiredDocs = $edition->course->required_documents ?? ['cedula_dimex'];
        $submitted = array_values(array_unique($submittedDocuments));
        $missing = array_values(array_diff($requiredDocs, $submitted));
        $docsComplete = count($missing) === 0;

        $skipDocBlock = $isDirectRejection || $isDirectWaitlist;
        if (! $skipDocBlock && $confirmImmediately && ! $docsComplete && ! $authorizeException) {
            $missingLabels = $this->documentLabels($missing);
            throw ValidationException::withMessages([
                'documents' => 'Matrícula presencial bloqueada: faltan los siguientes documentos obligatorios: '.implode(', ', $missingLabels).'. Debe completarlos o autorizar una excepción con motivo y fecha compromiso.',
            ]);
        }

        if (! $skipDocBlock && $authorizeException && (! filled($exceptionReason) || ! filled($exceptionCommitmentDate))) {
            throw ValidationException::withMessages([
                'exception_reason' => 'Para autorizar una excepción documental debe indicar el motivo y la fecha compromiso.',
            ]);
        }

        $cleanCapacityNotes = filled($capacityDecisionNotes) ? trim((string) $capacityDecisionNotes) : null;
        $cleanRejectionNotes = filled($rejectionNotes) ? trim((string) $rejectionNotes) : null;

        $enrollment = DB::transaction(function () use (
            $student,
            $edition,
            $requiredDocs,
            $submitted,
            $docsComplete,
            $confirmImmediately,
            $authorizeException,
            $exceptionReason,
            $exceptionCommitmentDate,
            $notes,
            $capacityDecisionCode,
            $cleanCapacityNotes,
            $decisionRecord,
            $presencialAction,
            $rejectionReasonCode,
            $cleanRejectionNotes,
            $rejectionPicklistRecord
        ): Enrollment {
            $lockedEdition = CourseEdition::whereKey($edition->id)->lockForUpdate()->firstOrFail();
            $lockedIsFull = $lockedEdition->availableSeats() <= 0;

            if ($lockedIsFull && ! filled($capacityDecisionCode)) {
                throw ValidationException::withMessages([
                    'full_capacity_decision' => sprintf(
                        'Cupo máximo alcanzado para esta cohorte (%d / %d cupos ocupados). El funcionario debe seleccionar una decisión del picklist para continuar.',
                        $lockedEdition->activeEnrollmentsCount(),
                        (int) $lockedEdition->max_capacity
                    ),
                ]);
            }

            $effectiveConfirm = $confirmImmediately;
            $status = $confirmImmediately
                ? 'confirmada'
                : ($docsComplete ? 'preinscrita' : 'documentacion_pendiente');
            $finalRejectionReasonCode = null;
            $finalRejectionNotes = null;
            $rejectedAt = null;
            $rejectedBy = null;
            $finalCapacityDecision = null;

            if ($lockedIsFull) {
                $finalCapacityDecision = $capacityDecisionCode;
                if ($capacityDecisionCode === 'lista_espera') {
                    $status = 'lista_espera';
                    $effectiveConfirm = false;
                } elseif ($capacityDecisionCode === 'rechazar_cupo_lleno') {
                    $status = 'rechazada';
                    $effectiveConfirm = false;
                    $finalRejectionReasonCode = 'cupo_agotado';
                    $finalRejectionNotes = $cleanCapacityNotes;
                    $rejectedAt = now();
                    $rejectedBy = Auth::id();
                }
            } elseif ($presencialAction === 'lista_espera') {
                $status = 'lista_espera';
                $effectiveConfirm = false;
                $finalCapacityDecision = 'lista_espera';
            } elseif ($presencialAction === 'rechazar') {
                $status = 'rechazada';
                $effectiveConfirm = false;
                $finalRejectionReasonCode = $rejectionReasonCode;
                $finalRejectionNotes = $cleanRejectionNotes;
                $rejectedAt = now();
                $rejectedBy = Auth::id();
            }

            $enrollment = Enrollment::create([
                'enrollment_code' => Enrollment::nextCode(),
                'student_id' => $student->id,
                'course_edition_id' => $edition->id,
                'source' => 'presencial',
                'status' => $status,
                'confirmed_at' => $effectiveConfirm ? now() : null,
                'confirmed_by' => $effectiveConfirm ? Auth::id() : null,
                'required_documents_snapshot' => array_values($requiredDocs),
                'submitted_documents' => $submitted,
                'documents_complete' => $docsComplete,
                'exception_authorized' => $effectiveConfirm && $authorizeException,
                'exception_reason' => ($effectiveConfirm && $authorizeException) ? $exceptionReason : null,
                'exception_commitment_date' => ($effectiveConfirm && $authorizeException) ? $exceptionCommitmentDate : null,
                'exception_authorized_by' => ($effectiveConfirm && $authorizeException) ? Auth::id() : null,
                'exception_authorized_at' => ($effectiveConfirm && $authorizeException) ? now() : null,
                'rejection_reason_code' => $finalRejectionReasonCode,
                'rejection_notes' => $finalRejectionNotes,
                'rejected_at' => $rejectedAt,
                'rejected_by' => $rejectedBy,
                'capacity_decision_code' => $finalCapacityDecision,
                'notes' => $notes ?: ($finalRejectionNotes ?: $cleanCapacityNotes),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            if ($effectiveConfirm) {
                $this->financeService->generateFeePlanForEnrollment($enrollment);
            }

            $lifecycleType = match (true) {
                $status === 'lista_espera' => 'lista_espera_presencial',
                $status === 'rechazada' => 'solicitud_rechazada',
                $lockedIsFull && $capacityDecisionCode === 'autorizar_sobrecupo' => 'sobrecupo_autorizado',
                $effectiveConfirm => 'matricula_confirmada',
                default => 'solicitud_presencial',
            };

            $lifecycleDesc = match (true) {
                $lockedIsFull && $decisionRecord !== null => sprintf('Decisión presencial por cupo lleno (%s)%s', $decisionRecord->label, $cleanCapacityNotes ? ': '.$cleanCapacityNotes : '.'),
                $status === 'rechazada' && $rejectionPicklistRecord !== null => sprintf('Solicitud presencial rechazada (%s)%s', $rejectionPicklistRecord->label, $cleanRejectionNotes ? ': '.$cleanRejectionNotes : '.'),
                $status === 'lista_espera' => sprintf('Solicitante registrada presencialmente en Lista de Espera para %s.', $edition->name),
                $effectiveConfirm && $authorizeException => sprintf('Matrícula confirmada por excepción documental (compromiso: %s). Motivo: %s', $exceptionCommitmentDate, $exceptionReason),
                default => sprintf('Matrícula presencial registrada en estado %s para %s.', $status, $edition->name),
            };

            StudentLifecycleEvent::create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'event_type' => $lifecycleType,
                'from_status' => null,
                'to_status' => $status,
                'description' => $lifecycleDesc,
                'metadata' => [
                    'exception_authorized' => $effectiveConfirm && $authorizeException,
                    'capacity_decision_code' => $finalCapacityDecision,
                    'rejection_reason_code' => $finalRejectionReasonCode,
                ],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            $auditEvent = match (true) {
                $status === 'lista_espera' => 'enrollment.presencial_waitlisted',
                $lockedIsFull && $capacityDecisionCode === 'autorizar_sobrecupo' => 'enrollment.presencial_overcapacity_authorized',
                $status === 'rechazada' => 'enrollment.rejected',
                $effectiveConfirm && $authorizeException => 'enrollment.confirmed_with_exception',
                default => 'enrollment.presencial_created',
            };

            AuditService::log(
                event: $auditEvent,
                module: 'admisiones',
                auditable: $enrollment,
                newValues: $enrollment->only(['enrollment_code', 'status', 'documents_complete', 'exception_authorized', 'exception_commitment_date', 'capacity_decision_code', 'rejection_reason_code']),
                reason: $finalRejectionNotes ?: ($cleanCapacityNotes ?: ($authorizeException ? $exceptionReason : ($rejectionPicklistRecord?->label ?: $decisionRecord?->label)))
            );

            return $enrollment->fresh(['student', 'edition.course']);
        });

        // Si el funcionario decidió rechazar (por cupo lleno o acción directa), notificar por correo a la solicitante
        if ($enrollment->status === 'rechazada' && filled($enrollment->student?->email)) {
            $mailReasonCode = $enrollment->rejection_reason_code ?: 'cupo_agotado';
            try {
                Mail::to($enrollment->student->email)->send(new EnrollmentRejectedMail(
                    enrollment: $enrollment,
                    reasonLabel: Picklist::labelFor('enrollment_rejection_reason', $mailReasonCode),
                    additionalNotes: $enrollment->rejection_notes,
                    settings: InstitutionSetting::current()
                ));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar correo de rechazo presencial', [
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $enrollment;
    }

    public function confirmEnrollment(
        Enrollment $enrollment,
        array $submittedDocuments,
        bool $authorizeException = false,
        ?string $exceptionReason = null,
        ?string $exceptionCommitmentDate = null
    ): Enrollment {
        $enrollment->loadMissing('edition');
        if ($enrollment->status === 'lista_espera' && $enrollment->edition && $enrollment->edition->availableSeats() <= 0 && ! $authorizeException) {
            throw ValidationException::withMessages([
                'documents' => sprintf(
                    'No hay cupos disponibles en esta cohorte (%d / %d) para promover desde Lista de Espera. Libere un cupo, amplíe la capacidad de la cohorte o autorice una excepción con motivo y fecha compromiso.',
                    $enrollment->edition->activeEnrollmentsCount(),
                    (int) $enrollment->edition->max_capacity
                ),
            ]);
        }

        $requiredDocs = $enrollment->required_documents_snapshot ?? ['cedula_dimex'];
        $submitted = array_values(array_unique($submittedDocuments));
        $missing = array_values(array_diff($requiredDocs, $submitted));
        $docsComplete = count($missing) === 0;

        if (! $docsComplete && ! $authorizeException) {
            $missingLabels = $this->documentLabels($missing);
            throw ValidationException::withMessages([
                'documents' => 'No se puede confirmar la matrícula: faltan los siguientes documentos obligatorios: '.implode(', ', $missingLabels).'. Autorice una excepción con motivo y fecha compromiso para continuar.',
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

    /**
     * Rechaza una solicitud de inscripción/preinscripción con motivo de picklist,
     * registra auditoría + historial, libera el cupo de la edición y notifica por correo.
     *
     * @return array{enrollment: Enrollment, mail_sent: bool, reason_label: string}
     */
    public function rejectEnrollment(
        Enrollment $enrollment,
        string $reasonCode,
        ?string $additionalNotes = null
    ): array {
        if (! in_array($enrollment->status, ['preinscrita', 'documentacion_pendiente', 'lista_espera'], true)) {
            throw ValidationException::withMessages([
                'rejection_reason_code' => 'Solo se pueden rechazar solicitudes en estado Preinscrita, Documentación Pendiente o Lista de Espera.',
            ]);
        }

        $reason = Picklist::query()
            ->where('group_key', 'enrollment_rejection_reason')
            ->where('code', $reasonCode)
            ->where('is_active', true)
            ->first();

        if (! $reason) {
            throw ValidationException::withMessages([
                'rejection_reason_code' => 'Debe seleccionar un motivo de rechazo válido del catálogo.',
            ]);
        }

        $cleanNotes = filled($additionalNotes) ? trim((string) $additionalNotes) : null;

        $updated = DB::transaction(function () use ($enrollment, $reason, $cleanNotes): Enrollment {
            $oldStatus = $enrollment->status;

            $enrollment->update([
                'status' => 'rechazada',
                'rejection_reason_code' => $reason->code,
                'rejection_notes' => $cleanNotes,
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            StudentLifecycleEvent::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'event_type' => 'solicitud_rechazada',
                'from_status' => $oldStatus,
                'to_status' => 'rechazada',
                'description' => trim(sprintf(
                    'Solicitud rechazada. Motivo: %s.%s',
                    $reason->label,
                    $cleanNotes ? ' Observaciones: '.$cleanNotes : ''
                )),
                'metadata' => [
                    'rejection_reason_code' => $reason->code,
                    'rejection_reason_label' => $reason->label,
                    'rejection_notes' => $cleanNotes,
                ],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: 'enrollment.rejected',
                module: 'admisiones',
                auditable: $enrollment,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => 'rechazada',
                    'rejection_reason_code' => $reason->code,
                    'rejection_reason_label' => $reason->label,
                    'recipient_email' => $enrollment->student?->email,
                ],
                reason: $cleanNotes ? $reason->label.' — '.$cleanNotes : $reason->label
            );

            return $enrollment->fresh(['student', 'edition.course']);
        });

        $mailSent = false;
        $recipientEmail = $updated->student?->email;
        if (filled($recipientEmail)) {
            try {
                Mail::to($recipientEmail)->send(new EnrollmentRejectedMail(
                    enrollment: $updated,
                    reasonLabel: $reason->label,
                    additionalNotes: $cleanNotes,
                    settings: InstitutionSetting::current()
                ));
                $mailSent = true;
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar correo de rechazo de solicitud', [
                    'enrollment_id' => $updated->id,
                    'email' => $recipientEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'enrollment' => $updated,
            'mail_sent' => $mailSent,
            'reason_label' => $reason->label,
        ];
    }

    /**
     * Registra documentos adjuntados por el funcionario sobre una solicitud existente
     * (pendiente, lista de espera, confirmada con excepción o rechazada) sin duplicarla.
     *
     * @param  list<string>  $addedDocumentKeys
     */
    public function attachDocumentsToEnrollment(
        Enrollment $enrollment,
        array $addedDocumentKeys,
        ?string $notes = null
    ): Enrollment {
        return DB::transaction(function () use ($enrollment, $addedDocumentKeys, $notes): Enrollment {
            $enrollment->loadMissing('student.attachments');
            $oldStatus = $enrollment->status;
            $oldSubmitted = $enrollment->submitted_documents ?? [];
            $requiredDocs = $enrollment->required_documents_snapshot ?? ['cedula_dimex'];

            $activeAttachmentTypes = $enrollment->student
                ? $enrollment->student->attachments()
                    ->where('status', 'activo')
                    ->pluck('document_type')
                    ->all()
                : [];

            $mergedSubmitted = array_values(array_unique(array_merge(
                $oldSubmitted,
                $activeAttachmentTypes,
                array_values(array_filter($addedDocumentKeys))
            )));
            $missing = array_values(array_diff($requiredDocs, $mergedSubmitted));
            $docsComplete = count($missing) === 0;

            $newStatus = ($oldStatus === 'documentacion_pendiente' && $docsComplete)
                ? 'preinscrita'
                : $oldStatus;

            $enrollment->update([
                'submitted_documents' => $mergedSubmitted,
                'documents_complete' => $docsComplete,
                'status' => $newStatus,
                'updated_by' => Auth::id(),
            ]);

            $addedLabels = $this->documentLabels($addedDocumentKeys);
            $cleanNotes = filled($notes) ? trim((string) $notes) : null;

            StudentLifecycleEvent::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'event_type' => $docsComplete ? 'documentacion_completada' : 'documento_adjuntado',
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'description' => trim(sprintf(
                    'Documento(s) adjuntado(s) desde Admisiones: %s.%s%s',
                    implode(', ', $addedLabels),
                    $docsComplete ? ' Documentación completa.' : '',
                    $cleanNotes ? ' Nota: '.$cleanNotes : ''
                )),
                'metadata' => [
                    'added_documents' => array_values($addedDocumentKeys),
                    'documents_complete' => $docsComplete,
                    'missing_documents' => $missing,
                ],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: 'enrollment.documents_attached',
                module: 'admisiones',
                auditable: $enrollment,
                oldValues: ['status' => $oldStatus, 'submitted_documents' => $oldSubmitted],
                newValues: ['status' => $newStatus, 'submitted_documents' => $mergedSubmitted, 'documents_complete' => $docsComplete],
                reason: $cleanNotes ?: 'Carga de adjuntos faltantes en expediente'
            );

            return $enrollment->fresh(['student.attachments', 'edition.course']);
        });
    }

    /**
     * Sincroniza los adjuntos activos del expediente de una alumna con todas sus solicitudes
     * incompletas (pendientes, lista de espera, confirmadas con excepción o rechazadas).
     *
     * @param  list<string>  $addedDocumentKeys
     */
    public function syncStudentAttachmentsToEnrollments(
        Student $student,
        array $addedDocumentKeys = [],
        ?string $notes = null
    ): int {
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->where(function ($q) {
                $q->whereIn('status', ['documentacion_pendiente', 'preinscrita', 'lista_espera'])
                    ->orWhere(function ($q2) {
                        $q2->whereIn('status', ['confirmada', 'rechazada'])->where('documents_complete', false);
                    });
            })
            ->get();

        foreach ($enrollments as $enr) {
            $this->attachDocumentsToEnrollment(
                enrollment: $enr,
                addedDocumentKeys: $addedDocumentKeys,
                notes: $notes ?: 'Sincronizado automáticamente desde expediente de alumna'
            );
        }

        return $enrollments->count();
    }

    /**
     * Reabre una solicitud previamente rechazada (sin duplicar la inscripción ni borrar el historial de rechazo),
     * validando el cupo disponible actual de la edición.
     */
    public function reopenRejectedEnrollment(
        Enrollment $enrollment,
        string $reason,
        bool $authorizeOvercapacity = false
    ): Enrollment {
        if ($enrollment->status !== 'rechazada') {
            throw ValidationException::withMessages([
                'reopen_reason' => 'Solo se pueden reabrir solicitudes que se encuentren en estado Rechazada.',
            ]);
        }

        $cleanReason = trim($reason);
        if (mb_strlen($cleanReason) < 5) {
            throw ValidationException::withMessages([
                'reopen_reason' => 'Debe indicar un motivo de reapertura (mínimo 5 caracteres) para auditoría.',
            ]);
        }

        return DB::transaction(function () use ($enrollment, $cleanReason, $authorizeOvercapacity): Enrollment {
            $lockedEdition = CourseEdition::whereKey($enrollment->course_edition_id)->lockForUpdate()->firstOrFail();
            $lockedIsFull = $lockedEdition->availableSeats() <= 0;

            $oldStatus = $enrollment->status;
            $previousReasonCode = $enrollment->rejection_reason_code;

            if ($lockedIsFull && ! $authorizeOvercapacity) {
                $newStatus = 'lista_espera';
                $capacityDecision = 'lista_espera';
            } else {
                $newStatus = $enrollment->documents_complete ? 'preinscrita' : 'documentacion_pendiente';
                $capacityDecision = ($lockedIsFull && $authorizeOvercapacity) ? 'autorizar_sobrecupo' : null;
            }

            $enrollment->update([
                'status' => $newStatus,
                'capacity_decision_code' => $capacityDecision,
                'notes' => trim(($enrollment->notes ? $enrollment->notes.' | ' : '').'Reabierta: '.$cleanReason),
                'updated_by' => Auth::id(),
            ]);

            StudentLifecycleEvent::create([
                'student_id' => $enrollment->student_id,
                'enrollment_id' => $enrollment->id,
                'event_type' => 'solicitud_reabierta',
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'description' => sprintf(
                    'Solicitud reabierta desde estado Rechazada hacia %s. Motivo: %s',
                    strtoupper(str_replace('_', ' ', $newStatus)),
                    $cleanReason
                ),
                'metadata' => [
                    'previous_rejection_reason_code' => $previousReasonCode,
                    'edition_was_full' => $lockedIsFull,
                    'overcapacity_authorized' => $lockedIsFull && $authorizeOvercapacity,
                ],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            AuditService::log(
                event: 'enrollment.reopened',
                module: 'admisiones',
                auditable: $enrollment,
                oldValues: [
                    'status' => $oldStatus,
                    'rejection_reason_code' => $previousReasonCode,
                ],
                newValues: [
                    'status' => $newStatus,
                    'documents_complete' => $enrollment->documents_complete,
                    'capacity_decision_code' => $capacityDecision,
                ],
                reason: $cleanReason
            );

            return $enrollment->fresh(['student.attachments', 'edition.course']);
        });
    }

    protected function assertNotAlreadyEnrolledInCourse(Student $student, CourseEdition $edition): void
    {
        $existing = $student->activeEnrollmentInCourse((int) $edition->course_id);
        if (! $existing) {
            return;
        }

        $courseName = $edition->course?->name ?? 'este curso';
        $cohortName = $existing->edition?->name ?? 'cohorte vigente';
        $statusLabel = strtoupper(str_replace('_', ' ', (string) $existing->status));

        throw ValidationException::withMessages([
            'identification_number' => sprintf(
                'La cédula %s (%s) ya tiene una inscripción activa en el curso "%s" (cohorte %s, solicitud %s, estado: %s). No se permite duplicar la inscripción en el mismo curso.',
                $student->identification_number,
                $student->full_name,
                $courseName,
                $cohortName,
                $existing->enrollment_code,
                $statusLabel
            ),
        ]);
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    protected function documentLabels(array $keys): array
    {
        $labels = config('cecap.document_types', []);

        return array_values(array_map(
            fn (string $key): string => (string) ($labels[$key] ?? $key),
            $keys
        ));
    }
}
