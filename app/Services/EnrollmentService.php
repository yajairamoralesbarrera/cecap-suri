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
        if ($enrollment->source !== 'en_linea' || ! in_array($enrollment->status, ['documentacion_pendiente', 'preinscrita', 'lista_espera'], true)) {
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
            $newStatus = $oldStatus === 'lista_espera'
                ? 'lista_espera'
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
        ?string $capacityDecisionNotes = null
    ): Enrollment {
        $edition->loadMissing('course');
        if (! $authorizeException && ($eduError = $edition->course?->educationLevelRequirementError($student->education_level))) {
            throw ValidationException::withMessages([
                'education_level' => $eduError.' Si Dirección autoriza una excepción, marque la casilla de excepción e indique motivo y fecha compromiso.',
            ]);
        }

        $isFull = $edition->availableSeats() <= 0;
        $decisionRecord = null;
        if ($isFull) {
            if (! filled($capacityDecisionCode)) {
                throw ValidationException::withMessages([
                    'full_capacity_decision' => sprintf(
                        'Cupo máximo alcanzado para esta edición (%d / %d cupos ocupados). El funcionario debe seleccionar una decisión del picklist para continuar.',
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
                    'course_edition_id' => 'Decisión seleccionada: Reubicar en otra edición. Seleccione otra edición con cupos disponibles en el campo "Edición a Matricular".',
                ]);
            }

            if ($capacityDecisionCode === 'autorizar_sobrecupo' && ! filled($capacityDecisionNotes) && ! filled($exceptionReason)) {
                throw ValidationException::withMessages([
                    'full_capacity_notes' => 'Para autorizar un sobrecupo excepcional debe indicar la justificación u observación de auditoría.',
                ]);
            }
        }

        $this->scheduleConflicts->assertStudentHasNoScheduleConflict($student, $edition);

        $requiredDocs = $edition->course->required_documents ?? ['cedula_dimex'];
        $submitted = array_values(array_unique($submittedDocuments));
        $missing = array_values(array_diff($requiredDocs, $submitted));
        $docsComplete = count($missing) === 0;

        $skipDocBlock = $isFull && in_array($capacityDecisionCode, ['lista_espera', 'rechazar_cupo_lleno'], true);
        if (! $skipDocBlock && $confirmImmediately && ! $docsComplete && ! $authorizeException) {
            $missingLabels = $this->documentLabels($missing);
            throw ValidationException::withMessages([
                'documents' => 'Matrícula presencial bloqueada: faltan los siguientes documentos obligatorios: '.implode(', ', $missingLabels).'. Debe completarlos o autorizar una excepción con motivo y fecha compromiso.',
            ]);
        }

        if ($authorizeException && (! filled($exceptionReason) || ! filled($exceptionCommitmentDate))) {
            throw ValidationException::withMessages([
                'exception_reason' => 'Para autorizar una excepción documental debe indicar el motivo y la fecha compromiso.',
            ]);
        }

        $cleanCapacityNotes = filled($capacityDecisionNotes) ? trim((string) $capacityDecisionNotes) : null;

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
            $decisionRecord
        ): Enrollment {
            $lockedEdition = CourseEdition::whereKey($edition->id)->lockForUpdate()->firstOrFail();
            $lockedIsFull = $lockedEdition->availableSeats() <= 0;

            if ($lockedIsFull && ! filled($capacityDecisionCode)) {
                throw ValidationException::withMessages([
                    'full_capacity_decision' => sprintf(
                        'Cupo máximo alcanzado para esta edición (%d / %d cupos ocupados). El funcionario debe seleccionar una decisión del picklist para continuar.',
                        $lockedEdition->activeEnrollmentsCount(),
                        (int) $lockedEdition->max_capacity
                    ),
                ]);
            }

            $effectiveConfirm = $confirmImmediately;
            $status = $confirmImmediately
                ? 'confirmada'
                : ($docsComplete ? 'preinscrita' : 'documentacion_pendiente');
            $rejectionReasonCode = null;
            $rejectedAt = null;
            $rejectedBy = null;

            if ($lockedIsFull) {
                if ($capacityDecisionCode === 'lista_espera') {
                    $status = 'lista_espera';
                    $effectiveConfirm = false;
                } elseif ($capacityDecisionCode === 'rechazar_cupo_lleno') {
                    $status = 'rechazada';
                    $effectiveConfirm = false;
                    $rejectionReasonCode = 'cupo_agotado';
                    $rejectedAt = now();
                    $rejectedBy = Auth::id();
                }
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
                'exception_authorized' => $authorizeException,
                'exception_reason' => $authorizeException ? $exceptionReason : null,
                'exception_commitment_date' => $authorizeException ? $exceptionCommitmentDate : null,
                'exception_authorized_by' => $authorizeException ? Auth::id() : null,
                'exception_authorized_at' => $authorizeException ? now() : null,
                'rejection_reason_code' => $rejectionReasonCode,
                'rejection_notes' => $rejectionReasonCode ? $cleanCapacityNotes : null,
                'rejected_at' => $rejectedAt,
                'rejected_by' => $rejectedBy,
                'capacity_decision_code' => $lockedIsFull ? $capacityDecisionCode : null,
                'notes' => $notes ?: $cleanCapacityNotes,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            if ($effectiveConfirm) {
                $this->financeService->generateFeePlanForEnrollment($enrollment);
            }

            $lifecycleType = match (true) {
                $lockedIsFull && $capacityDecisionCode === 'lista_espera' => 'lista_espera_presencial',
                $lockedIsFull && $capacityDecisionCode === 'rechazar_cupo_lleno' => 'solicitud_rechazada',
                $lockedIsFull && $capacityDecisionCode === 'autorizar_sobrecupo' => 'sobrecupo_autorizado',
                $effectiveConfirm => 'matricula_confirmada',
                default => 'solicitud_presencial',
            };

            $lifecycleDesc = $lockedIsFull && $decisionRecord
                ? sprintf('Decisión presencial por cupo lleno (%s)%s', $decisionRecord->label, $cleanCapacityNotes ? ': '.$cleanCapacityNotes : '.')
                : ($authorizeException
                    ? sprintf('Matrícula confirmada por excepción documental (compromiso: %s). Motivo: %s', $exceptionCommitmentDate, $exceptionReason)
                    : sprintf('Matrícula presencial registrada en estado %s para %s.', $status, $edition->name));

            StudentLifecycleEvent::create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'event_type' => $lifecycleType,
                'from_status' => null,
                'to_status' => $status,
                'description' => $lifecycleDesc,
                'metadata' => [
                    'exception_authorized' => $authorizeException,
                    'capacity_decision_code' => $lockedIsFull ? $capacityDecisionCode : null,
                ],
                'recorded_by' => Auth::id(),
                'created_at' => now(),
            ]);

            $auditEvent = match (true) {
                $lockedIsFull && $capacityDecisionCode === 'lista_espera' => 'enrollment.presencial_waitlisted',
                $lockedIsFull && $capacityDecisionCode === 'autorizar_sobrecupo' => 'enrollment.presencial_overcapacity_authorized',
                $lockedIsFull && $capacityDecisionCode === 'rechazar_cupo_lleno' => 'enrollment.rejected',
                $authorizeException => 'enrollment.confirmed_with_exception',
                default => 'enrollment.presencial_created',
            };

            AuditService::log(
                event: $auditEvent,
                module: 'admisiones',
                auditable: $enrollment,
                newValues: $enrollment->only(['enrollment_code', 'status', 'documents_complete', 'exception_authorized', 'exception_commitment_date', 'capacity_decision_code', 'rejection_reason_code']),
                reason: $cleanCapacityNotes ?: ($authorizeException ? $exceptionReason : ($decisionRecord?->label))
            );

            return $enrollment->fresh(['student', 'edition.course']);
        });

        // Si el funcionario decidió rechazar por cupo lleno, notificar por correo a la solicitante
        if ($enrollment->status === 'rechazada' && filled($enrollment->student?->email)) {
            try {
                Mail::to($enrollment->student->email)->send(new EnrollmentRejectedMail(
                    enrollment: $enrollment,
                    reasonLabel: Picklist::labelFor('enrollment_rejection_reason', 'cupo_agotado'),
                    additionalNotes: $cleanCapacityNotes,
                    settings: InstitutionSetting::current()
                ));
            } catch (\Throwable $e) {
                Log::warning('No se pudo enviar correo de rechazo por cupo lleno', [
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
                    'No hay cupos disponibles en esta edición (%d / %d) para promover desde Lista de Espera. Libere un cupo, amplíe la capacidad de la edición o autorice una excepción con motivo y fecha compromiso.',
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
