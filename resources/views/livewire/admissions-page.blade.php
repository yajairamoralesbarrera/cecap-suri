<div class="space-y-6">
    @if($feedback)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ $feedback }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('queue')" class="{{ $tab === 'queue' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Cola de Admisiones ({{ $enrollments->count() }})</button>
        <button type="button" wire:click="setTab('enroll')" class="{{ $tab === 'enroll' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Matrícula Presencial / Nueva Alumna</button>
        <button type="button" wire:click="setTab('students')" class="{{ $tab === 'students' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Directorio de Alumnas ({{ $students->count() }})</button>
    </div>

    {{-- TAB: COLA DE ADMISIONES Y MATRÍCULAS --}}
    @if($tab === 'queue')
        <div class="cecap-card overflow-x-auto">
            <h2 class="text-base font-bold text-slate-900 mb-3">Solicitudes y Matrículas Recientes</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">Código</th>
                        <th class="py-2 pr-3">Alumna</th>
                        <th class="py-2 pr-3">Cohorte</th>
                        <th class="py-2 pr-3">Estado</th>
                        <th class="py-2 pr-3">Checklist</th>
                        <th class="py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($enrollments as $enr)
                        <tr wire:key="enr-row-{{ $enr->id }}" class="{{ ($confirmingEnrollmentId === $enr->id || $rejectingEnrollmentId === $enr->id || $attachingEnrollmentId === $enr->id || $reopeningEnrollmentId === $enr->id) ? 'bg-slate-50/80' : '' }}">
                            <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $enr->enrollment_code }}<div class="text-[11px] text-slate-400">{{ $enr->source }}</div></td>
                            <td class="py-2.5 pr-3 font-semibold text-slate-900">{{ $enr->student?->full_name }}<div class="text-xs text-slate-500">{{ $enr->student?->identification_number }} · {{ $enr->student?->email }}</div></td>
                            <td class="py-2.5 pr-3 text-xs">{{ $enr->edition?->course?->name }}<div class="text-slate-500">{{ $enr->edition?->name }}</div></td>
                            <td class="py-2.5 pr-3">
                                <span class="cecap-badge {{ $enr->status === 'confirmada' ? 'bg-emerald-100 text-emerald-900' : ($enr->status === 'rechazada' ? 'bg-rose-100 text-rose-900' : ($enr->status === 'lista_espera' ? 'bg-indigo-100 text-indigo-900' : ($enr->status === 'documentacion_pendiente' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-800'))) }}">
                                    {{ strtoupper(str_replace('_', ' ', $enr->status)) }}
                                </span>
                                @if($enr->exception_authorized)
                                    @if($enr->documents_complete)
                                        <div class="text-[11px] text-emerald-700 font-semibold mt-0.5">✓ Excepción documental cumplida</div>
                                    @else
                                        <div class="text-[11px] text-amber-700 font-semibold mt-0.5">Excepción hasta {{ $enr->exception_commitment_date?->format('d/m/Y') }}</div>
                                    @endif
                                @endif
                                @if($enr->status === 'rechazada' && $enr->rejection_reason_label)
                                    <div class="text-[11px] text-rose-700 font-medium mt-0.5">Motivo: {{ $enr->rejection_reason_label }}</div>
                                @endif
                                @if($enr->capacity_decision_label && $enr->status !== 'rechazada')
                                    <div class="text-[11px] text-indigo-700 font-medium mt-0.5">Cupo: {{ $enr->capacity_decision_label }}</div>
                                @endif
                            </td>
                            <td class="py-2.5 pr-3 text-xs">
                                @if($enr->documents_complete)
                                    <span class="font-semibold text-emerald-700">Completo</span>
                                @else
                                    <span class="font-semibold text-rose-700">Falta:</span> {{ implode(', ', $enr->missingDocumentLabels()) }}
                                @endif
                            </td>
                            <td class="py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    @if(in_array($enr->status, ['preinscrita', 'documentacion_pendiente', 'lista_espera'], true) && auth()->user()->hasEffectivePermission('admisiones.manage'))
                                        <button type="button" wire:click="startConfirmEnrollment({{ $enr->id }})" class="cecap-btn-primary !min-h-9 !px-3 text-xs">Confirmar</button>
                                        <button type="button" wire:click="startRejectEnrollment({{ $enr->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-rose-300 bg-white px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 cursor-pointer">Rechazar</button>
                                    @endif
                                    @if($enr->status === 'rechazada' && auth()->user()->hasEffectivePermission('admisiones.manage'))
                                        <button type="button" wire:click="startReopenEnrollment({{ $enr->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-emerald-400 bg-emerald-50 px-3 text-xs font-semibold text-emerald-900 hover:bg-emerald-100 cursor-pointer">Reabrir</button>
                                    @endif
                                    @if(auth()->user()->hasEffectivePermission('admisiones.manage'))
                                        <button type="button" wire:click="startAttachEnrollment({{ $enr->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-950 hover:bg-amber-100 cursor-pointer">Adjuntos</button>
                                    @endif
                                    <button type="button"
                                            data-pdf-modal-url="{{ route('pdf.planilla', $enr) }}"
                                            data-pdf-modal-title="Planilla {{ $enr->enrollment_code }}"
                                            class="cecap-btn-secondary !min-h-9 !px-3 text-xs">
                                        Planilla PDF
                                    </button>
                                </div>
                            </td>
                        </tr>

                        @if($confirmingEnrollment && $confirmingEnrollment->id === $enr->id)
                            <tr wire:key="enr-confirm-{{ $enr->id }}">
                                <td colspan="6" class="py-3 px-1">
                                    <div class="rounded-2xl border-2 border-brand-700 bg-brand-50/70 p-4 space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h3 class="text-sm sm:text-base font-bold text-brand-950">Confirmar Matrícula {{ $confirmingEnrollment->enrollment_code }} — {{ $confirmingEnrollment->student?->full_name }}</h3>
                                            <button type="button" wire:click="cancelConfirmEnrollment" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                        </div>

                                        @if($errors->any())
                                            <div class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">
                                                <ul class="list-disc pl-4 space-y-0.5">
                                                    @foreach($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @php $confirmMissing = $confirmingEnrollment->missingDocumentLabels(); @endphp
                                        @if(! empty($confirmMissing))
                                            <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-900">
                                                <strong>Documentos faltantes actuales:</strong> {{ implode(', ', $confirmMissing) }}
                                            </div>
                                        @endif

                                        <div class="space-y-2">
                                            <div class="text-xs font-bold uppercase text-slate-600">Documentos Requeridos (Checklist del Curso)</div>
                                            <div class="flex flex-wrap gap-4 text-sm">
                                                @foreach($confirmingEnrollment->required_documents_snapshot ?? ['cedula_dimex'] as $docKey)
                                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                                        <input type="checkbox" wire:model="confirm_docs" value="{{ $docKey }}" class="h-4 w-4 rounded text-brand-900">
                                                        <span>{{ $documentTypes[$docKey] ?? $docKey }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        @if(auth()->user()->hasEffectivePermission('admisiones.exceptions.authorize'))
                                            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-3">
                                                <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-950 cursor-pointer">
                                                    <input type="checkbox" wire:model.live="confirm_exception" class="h-4 w-4 rounded text-accent-600">
                                                    <span>Autorizar excepción por documentación faltante (queda auditada)</span>
                                                </label>
                                                @if($confirm_exception)
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                        <div>
                                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo de la excepción *</label>
                                                            <input wire:model="confirm_exception_reason" type="text" class="cecap-input" placeholder="Ej. Título en trámite en MEP">
                                                        </div>
                                                        <div>
                                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Fecha compromiso de entrega *</label>
                                                            <input wire:model="confirm_exception_date" type="date" class="cecap-input">
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" wire:click="submitConfirmation" class="cecap-btn-primary text-xs sm:text-sm">Confirmar Matrícula y Generar Plan de Cobro</button>
                                            <button type="button" wire:click="cancelConfirmEnrollment" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        @if($rejectingEnrollment && $rejectingEnrollment->id === $enr->id)
                            <tr wire:key="enr-reject-{{ $enr->id }}">
                                <td colspan="6" class="py-3 px-1">
                                    <div class="rounded-2xl border-2 border-rose-400 bg-rose-50/80 p-4 space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h3 class="text-sm sm:text-base font-bold text-rose-950">Rechazar Solicitud {{ $rejectingEnrollment->enrollment_code }} — {{ $rejectingEnrollment->student?->full_name }}</h3>
                                            <button type="button" wire:click="cancelRejectEnrollment" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                        </div>

                                        @if($errors->any())
                                            <div class="rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs font-medium text-rose-800">
                                                <ul class="list-disc pl-4 space-y-0.5">
                                                    @foreach($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <p class="text-xs sm:text-sm text-slate-700">
                                            Se notificará por correo a <strong>{{ $rejectingEnrollment->student?->email }}</strong> y el cupo de la cohorte quedará libre.
                                        </p>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo de Rechazo (Picklist) *</label>
                                                <select wire:model="rejection_reason_code" required class="cecap-input">
                                                    <option value="">-- Seleccione el motivo de rechazo --</option>
                                                    @foreach($rejectionReasons as $code => $label)
                                                        <option value="{{ $code }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Observaciones adicionales para la solicitante (opcional)</label>
                                                <input wire:model="rejection_notes" type="text" class="cecap-input" placeholder="Ej. Puede postular nuevamente en la cohorte del próximo trimestre">
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" wire:click="submitRejection" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-700 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-rose-800 cursor-pointer">
                                                Confirmar Rechazo y Enviar Correo
                                            </button>
                                            <button type="button" wire:click="cancelRejectEnrollment" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        @if($attachingEnrollment && $attachingEnrollment->id === $enr->id)
                            <tr wire:key="enr-attach-{{ $enr->id }}">
                                <td colspan="6" class="py-3 px-1">
                                    <div class="rounded-2xl border-2 border-amber-400 bg-amber-50/80 p-4 space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <h3 class="text-sm sm:text-base font-bold text-amber-950">Adjuntos de Solicitud {{ $attachingEnrollment->enrollment_code }} — {{ $attachingEnrollment->student?->full_name }}</h3>
                                                <p class="text-xs text-amber-900">Suba los documentos faltantes (PDF, JPG, PNG o WebP hasta 25 MB). El checklist y la Planilla PDF se actualizarán automáticamente.</p>
                                            </div>
                                            <button type="button" wire:click="cancelAttachEnrollment" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cerrar</button>
                                        </div>

                                        @if($errors->any())
                                            <div class="rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs font-medium text-rose-800">
                                                <ul class="list-disc pl-4 space-y-0.5">
                                                    @foreach($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @php
                                            $attachMissing = $attachingEnrollment->missingDocumentLabels();
                                            $attachReqKeys = $attachingEnrollment->required_documents_snapshot ?? ['cedula_dimex'];
                                            $attachSubKeys = $attachingEnrollment->submitted_documents ?? [];
                                        @endphp
                                        <div class="rounded-xl border {{ empty($attachMissing) ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-white text-rose-900' }} px-3 py-2.5 text-xs space-y-2">
                                            <div>
                                                @if(empty($attachMissing))
                                                    <strong>✓ Documentación completa.</strong> Todos los requisitos de esta solicitud están cubiertos. Puede subir una nueva versión si requiere reemplazar algún archivo.
                                                @else
                                                    <strong>Documentos faltantes actuales:</strong> {{ implode(', ', $attachMissing) }}
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach($attachReqKeys as $rk)
                                                    @php $hasDoc = in_array($rk, $attachSubKeys, true); @endphp
                                                    <span class="cecap-badge {{ $hasDoc ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-900' }}">
                                                        {{ $hasDoc ? '✓' : '✗' }} {{ $documentTypes[$rk] ?? $rk }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>

                                        @php $activeAtts = $attachingEnrollment->student?->attachments?->where('status', 'activo') ?? collect(); @endphp
                                        @if($activeAtts->isNotEmpty())
                                            <div class="rounded-xl border border-slate-200 bg-white p-3 space-y-1.5">
                                                <div class="text-[11px] font-bold uppercase text-slate-500">Adjuntos vigentes en el expediente</div>
                                                <div class="flex flex-wrap gap-3 text-xs">
                                                    @foreach($activeAtts as $att)
                                                        <a href="{{ route('attachments.download', $att->uuid) }}" class="inline-flex items-center gap-1 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 font-semibold text-brand-900 hover:bg-brand-100">
                                                            {{ $att->document_type_label }} (v{{ $att->version }})
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <form wire:submit="uploadEnrollmentAttachment" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de Documento *</label>
                                                <select wire:model="enr_attachment_doc_type" class="cecap-input">
                                                    @foreach($documentTypes as $dk => $dl)
                                                        <option value="{{ $dk }}">{{ $dl }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Archivo (PDF/Imagen máx 25 MB) *</label>
                                                <input wire:model="enr_attachment_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                                <div wire:loading wire:target="enr_attachment_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo archivo... espere un momento.</div>
                                                @if($enr_attachment_file)
                                                    <div wire:loading.remove wire:target="enr_attachment_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Archivo listo: {{ $enr_attachment_file->getClientOriginalName() }}</div>
                                                @endif
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Observación (opcional)</label>
                                                <input wire:model="enr_attachment_notes" type="text" class="cecap-input" placeholder="Ej. Entregado en recepción">
                                            </div>
                                            <div class="md:col-span-3 flex flex-wrap gap-2">
                                                <button type="submit" wire:loading.attr="disabled" wire:target="enr_attachment_file,uploadEnrollmentAttachment" class="cecap-btn-primary text-xs sm:text-sm disabled:opacity-60">
                                                    <span wire:loading.remove wire:target="enr_attachment_file,uploadEnrollmentAttachment">Guardar Adjunto y Actualizar Checklist</span>
                                                    <span wire:loading wire:target="enr_attachment_file,uploadEnrollmentAttachment">Procesando archivo...</span>
                                                </button>
                                                <button type="button" wire:click="cancelAttachEnrollment" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endif

                        @if($reopeningEnrollment && $reopeningEnrollment->id === $enr->id)
                            <tr wire:key="enr-reopen-{{ $enr->id }}">
                                <td colspan="6" class="py-3 px-1">
                                    @php
                                        $reopenEd = $reopeningEnrollment->edition;
                                        $reopenAvail = $reopenEd ? $reopenEd->availableSeats() : 0;
                                        $reopenActive = $reopenEd ? $reopenEd->activeEnrollmentsCount() : 0;
                                        $reopenMax = $reopenEd ? (int) $reopenEd->max_capacity : 0;
                                        $reopenMissing = $reopeningEnrollment->missingDocumentLabels();
                                    @endphp
                                    <div class="rounded-2xl border-2 border-emerald-500 bg-emerald-50/70 p-4 space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <h3 class="text-sm sm:text-base font-bold text-emerald-950">Reabrir Solicitud Rechazada {{ $reopeningEnrollment->enrollment_code }} — {{ $reopeningEnrollment->student?->full_name }}</h3>
                                                <p class="text-xs text-emerald-900">Reactiva esta misma solicitud sin duplicarla, conservando el historial previo en auditoría.</p>
                                            </div>
                                            <button type="button" wire:click="cancelReopenEnrollment" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                        </div>

                                        @if($errors->any())
                                            <div class="rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs font-medium text-rose-800">
                                                <ul class="list-disc pl-4 space-y-0.5">
                                                    @foreach($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <div class="rounded-xl border {{ $reopenAvail > 0 ? 'border-emerald-300 bg-white text-emerald-950' : 'border-indigo-300 bg-indigo-50 text-indigo-950' }} px-3 py-2.5 text-xs space-y-1">
                                            <div>
                                                <strong>Disponibilidad actual de {{ $reopenEd?->code }}:</strong>
                                                @if($reopenAvail > 0)
                                                    {{ $reopenAvail }} cupo(s) libre(s) ({{ $reopenActive }} / {{ $reopenMax }} ocupados) — se reabrirá en estado {{ empty($reopenMissing) ? 'PREINSCRITA' : 'DOCUMENTACIÓN PENDIENTE' }}.
                                                @else
                                                    CUPO MÁXIMO ALCANZADO ({{ $reopenActive }} / {{ $reopenMax }} ocupados) — se reabrirá automáticamente en <strong>LISTA DE ESPERA</strong> (a menos que autorice sobrecupo).
                                                @endif
                                            </div>
                                            @if($reopeningEnrollment->rejection_reason_label)
                                                <div class="text-rose-800"><strong>Motivo del rechazo anterior:</strong> {{ $reopeningEnrollment->rejection_reason_label }}</div>
                                            @endif
                                            @if(! empty($reopenMissing))
                                                <div class="text-amber-900"><strong>Documentos aún faltantes:</strong> {{ implode(', ', $reopenMissing) }} (puede subir un archivo abajo al mismo tiempo).</div>
                                            @endif
                                        </div>

                                        <form wire:submit="submitReopenEnrollment" class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo de Reapertura (Obligatorio para Auditoría) *</label>
                                                <input wire:model="reopen_reason" type="text" required class="cecap-input" placeholder="Ej. Solicitante presentó atestado académico faltante">
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Subir documento faltante ahora (opcional)</label>
                                                    <select wire:model="reopen_attachment_doc_type" class="cecap-input">
                                                        @foreach($documentTypes as $dk => $dl)
                                                            <option value="{{ $dk }}">{{ $dl }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Archivo (PDF/Imagen máx 25 MB)</label>
                                                    <input wire:model="reopen_attachment_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                                    <div wire:loading wire:target="reopen_attachment_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo archivo...</div>
                                                    @if($reopen_attachment_file)
                                                        <div wire:loading.remove wire:target="reopen_attachment_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $reopen_attachment_file->getClientOriginalName() }}</div>
                                                    @endif
                                                </div>
                                            </div>

                                            @if($reopenAvail <= 0 && auth()->user()->hasEffectivePermission('admisiones.exceptions.authorize'))
                                                <label class="inline-flex items-center gap-2 text-xs font-semibold text-indigo-950 cursor-pointer">
                                                    <input type="checkbox" wire:model="reopen_authorize_overcapacity" class="h-4 w-4 rounded text-indigo-700">
                                                    <span>Autorizar sobrecupo auditado (reabrir directo a Preinscrita en lugar de Lista de Espera)</span>
                                                </label>
                                            @endif

                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" wire:loading.attr="disabled" wire:target="reopen_attachment_file,submitReopenEnrollment" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-emerald-800 cursor-pointer disabled:opacity-60">
                                                    <span wire:loading.remove wire:target="reopen_attachment_file,submitReopenEnrollment">Reabrir Solicitud</span>
                                                    <span wire:loading wire:target="reopen_attachment_file,submitReopenEnrollment">Procesando...</span>
                                                </button>
                                                <button type="button" wire:click="cancelReopenEnrollment" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">Sin matrículas o preinscripciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- TAB: MATRÍCULA PRESENCIAL --}}
    @if($tab === 'enroll')
        <div class="cecap-card space-y-6">
            <div>
                <h2 class="text-base font-bold text-brand-950">Nueva Matrícula Presencial</h2>
                <p class="text-xs text-slate-600 mt-0.5">Seleccione primero el curso/cohorte para verificar la disponibilidad de cupos y definir la acción antes de ingresar los datos de la solicitante.</p>
            </div>

            {{-- PASO 1: SELECCIÓN DE CURSO / COHORTE, DISPONIBILIDAD Y ACCIÓN --}}
            <div class="rounded-2xl border-2 border-brand-200 bg-brand-50/40 p-4 space-y-4">
                <div class="text-xs font-bold uppercase tracking-wide text-brand-900">1. Selección de Curso / Cohorte, Disponibilidad y Acción</div>

                <div>
                    <label class="block text-sm font-semibold text-slate-800 mb-1">Curso / Cohorte a Matricular *</label>
                    <select wire:model.live="course_edition_id" class="cecap-input">
                        <option value="">-- Seleccione curso y cohorte --</option>
                        @foreach($editions as $ed)
                            @php $avail = $ed->availableSeats(); @endphp
                            <option value="{{ $ed->id }}">
                                {{ $ed->course?->name }} [{{ $ed->code }}] — {{ $ed->name }} ({{ $avail > 0 ? $avail.' cupos disp. de '.$ed->max_capacity : 'LLENO · '.$ed->activeEnrollmentsCount().'/'.$ed->max_capacity }})
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($selectedEdition)
                    @php
                        $edActive = $selectedEdition->activeEnrollmentsCount();
                        $edMax = (int) $selectedEdition->max_capacity;
                        $edAvail = $selectedEdition->availableSeats();
                        $edWait = $selectedEdition->waitlistCount();
                        $edReqEdu = $educationLevels[$selectedEdition->course?->required_education_level] ?? ($selectedEdition->course?->required_education_level ?? 'Sin requisito');
                        $edReqDocs = collect($selectedEdition->course?->required_documents ?? ['cedula_dimex'])
                            ->map(fn ($dk) => $documentTypes[$dk] ?? $dk)
                            ->implode(', ');
                    @endphp

                    {{-- TARJETA DE DISPONIBILIDAD EN VIVO --}}
                    <div class="rounded-xl border p-3.5 {{ $edAvail > 0 ? 'border-emerald-300 bg-emerald-50/80' : 'border-indigo-400 bg-indigo-50' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="cecap-badge {{ $edAvail > 0 ? 'bg-emerald-200 text-emerald-950' : 'bg-rose-200 text-rose-950' }}">
                                    {{ $edAvail > 0 ? 'CUPO DISPONIBLE' : 'CUPO MÁXIMO ALCANZADO' }}
                                </span>
                                <span class="text-sm font-bold {{ $edAvail > 0 ? 'text-emerald-950' : 'text-indigo-950' }}">
                                    {{ $edAvail > 0 ? "{$edAvail} cupo(s) libre(s) ({$edActive} / {$edMax} ocupados)" : "{$edActive} / {$edMax} cupos ocupados" }}
                                </span>
                                @if($edWait > 0)
                                    <span class="cecap-badge bg-indigo-100 text-indigo-900">En lista de espera: {{ $edWait }}</span>
                                @endif
                            </div>
                            <span class="text-xs font-mono text-slate-600">{{ $selectedEdition->code }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-700">
                            <span><strong>Nivel mínimo:</strong> {{ $edReqEdu }}</span>
                            <span><strong>Documentos requeridos:</strong> {{ $edReqDocs }}</span>
                        </div>
                    </div>

                    {{-- ACCIÓN DEL FUNCIONARIO SEGÚN DISPONIBILIDAD --}}
                    @if($selectedEditionIsFull)
                        <div class="rounded-xl border-2 border-indigo-400 bg-white p-4 space-y-3">
                            <div>
                                <div class="text-sm font-bold text-indigo-950">Cohorte Llena ({{ $selectedEditionCapacityInfo }}) — Seleccione la Acción del Funcionario *</div>
                                <p class="text-xs text-indigo-800 mt-0.5">Elija del picklist si registra en Lista de Espera, autoriza sobrecupo, traslada a otra cohorte o rechaza por cupo agotado:</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Acción por Cupo Lleno (Picklist) *</label>
                                    <select wire:model.live="full_capacity_decision" class="cecap-input">
                                        <option value="">-- Seleccione una acción --</option>
                                        @foreach($fullCapacityDecisions as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                                        Observación / Justificación {{ $full_capacity_decision === 'autorizar_sobrecupo' ? '(Obligatoria para sobrecupo) *' : '(opcional)' }}
                                    </label>
                                    <input wire:model="full_capacity_notes" type="text" class="cecap-input" placeholder="Ej. Se coordina cupo adicional o ingreso a lista de espera">
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Acción a Realizar *</label>
                                    <select wire:model.live="presencial_action" class="cecap-input font-semibold">
                                        <option value="inscribir">Inscribir / Matricular en esta cohorte</option>
                                        <option value="lista_espera">Crear en Lista de Espera</option>
                                        <option value="rechazar">Rechazar solicitud y notificar por correo</option>
                                    </select>
                                </div>
                                @if($presencial_action === 'rechazar')
                                    <div>
                                        <label class="block text-xs font-semibold text-rose-800 mb-1">Motivo de Rechazo (Picklist) *</label>
                                        <select wire:model="presencial_rejection_reason_code" class="cecap-input border-rose-300">
                                            <option value="">-- Seleccione el motivo de rechazo --</option>
                                            @foreach($rejectionReasons as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>

                            @if($presencial_action === 'rechazar')
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Observaciones adicionales para el correo de la solicitante (opcional)</label>
                                    <input wire:model="presencial_rejection_notes" type="text" class="cecap-input" placeholder="Ej. Puede postular nuevamente al completar el nivel académico requerido">
                                </div>
                            @elseif($presencial_action === 'lista_espera')
                                <p class="text-xs text-indigo-800 bg-indigo-50 rounded-lg px-3 py-2">
                                    La solicitante se registrará en estado <strong>LISTA DE ESPERA</strong> sin consumir cupo activo ni generar plan de cobro.
                                </p>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            {{-- PASO 2: DATOS DE LA SOLICITANTE --}}
            <div class="space-y-4">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-600">2. Datos de la Solicitante</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Tipo ID *</label>
                        <select wire:model="identification_type" class="cecap-input">
                            @foreach($idTypes as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Cédula / DIMEX *</label>
                        <div class="flex gap-2">
                            <input wire:model="identification_number" type="text" required class="cecap-input" placeholder="1-1234-5678">
                            <button type="button" wire:click="autofillStudent" class="cecap-btn-secondary !px-3 text-xs shrink-0">Autollenar</button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Género (precargado Femenino) *</label>
                        <select wire:model="gender" class="cecap-input">
                            @foreach($genders as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre *</label>
                        <input wire:model="first_name" type="text" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Primer Apellido *</label>
                        <input wire:model="last_name" type="text" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Segundo Apellido</label>
                        <input wire:model="second_last_name" type="text" class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Correo Electrónico (Obligatorio) *</label>
                        <input wire:model="email" type="email" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Teléfono *</label>
                        <input wire:model="phone" type="tel" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nivel Educativo</label>
                        <select wire:model="education_level" class="cecap-input">
                            @foreach($educationLevels as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Provincia</label>
                        <select wire:model.live="province_id" class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($provinces as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Cantón</label>
                        <select wire:model.live="canton_id" class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($cantons as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Distrito</label>
                        <select wire:model="district_id" class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($districts as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- PASO 3: DOCUMENTACIÓN, ADJUNTO Y CONFIRMACIÓN --}}
            @php
                $isRejectAction = (! $selectedEditionIsFull && $presencial_action === 'rechazar')
                    || ($selectedEditionIsFull && $full_capacity_decision === 'rechazar_cupo_lleno');
                $isWaitlistAction = (! $selectedEditionIsFull && $presencial_action === 'lista_espera')
                    || ($selectedEditionIsFull && $full_capacity_decision === 'lista_espera');
                $isOvercapAction = $selectedEditionIsFull && $full_capacity_decision === 'autorizar_sobrecupo';
            @endphp

            <div class="border-t border-slate-200 pt-4 space-y-4">
                <div class="text-xs font-bold uppercase tracking-wide text-slate-600">3. Documentación y Registro</div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Adjunto Privado (PDF/JPG/PNG/WebP máx 25MB)</label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select wire:model="attachment_doc_type" class="cecap-input sm:w-52 shrink-0">
                            <option value="cedula_dimex">Cédula / DIMEX</option>
                            <option value="atestado_academico">Atestado Académico</option>
                        </select>
                        <input wire:model="attachment_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                    </div>
                </div>

                @if(! $isRejectAction && ! $isWaitlistAction)
                    <div class="flex flex-wrap gap-6 text-sm">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="submitted_documents" value="cedula_dimex" class="h-4 w-4 rounded text-brand-900">
                            <span>Cédula / DIMEX presentada</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="submitted_documents" value="atestado_academico" class="h-4 w-4 rounded text-brand-900">
                            <span>Atestados académicos presentados</span>
                        </label>
                        <label class="inline-flex items-center gap-2 cursor-pointer font-semibold text-brand-950">
                            <input type="checkbox" wire:model.live="confirm_immediately" class="h-4 w-4 rounded text-brand-900">
                            <span>Confirmar matrícula inmediatamente</span>
                        </label>
                    </div>

                    @if(auth()->user()->hasEffectivePermission('admisiones.exceptions.authorize'))
                        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 space-y-3">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-amber-950 cursor-pointer">
                                <input type="checkbox" wire:model.live="authorize_exception" class="h-4 w-4 rounded text-accent-600">
                                <span>Autorizar excepción si faltan documentos (motivo + fecha compromiso)</span>
                            </label>
                            @if($authorize_exception)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <input wire:model="exception_reason" type="text" class="cecap-input" placeholder="Motivo formal de excepción">
                                    <input wire:model="exception_commitment_date" type="date" class="cecap-input">
                                </div>
                            @endif
                        </div>
                    @endif
                @endif

                <div class="flex flex-wrap gap-2">
                    @if($isRejectAction)
                        <button type="button" wire:click="createPresencialEnrollment" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-rose-800 cursor-pointer">
                            Registrar Rechazo y Enviar Correo
                        </button>
                    @elseif($isWaitlistAction)
                        <button type="button" wire:click="createPresencialEnrollment" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-800 cursor-pointer">
                            Registrar en Lista de Espera
                        </button>
                    @elseif($isOvercapAction)
                        <button type="button" wire:click="createPresencialEnrollment" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-800 cursor-pointer">
                            Autorizar Sobrecupo y Matricular
                        </button>
                    @else
                        <button type="button" wire:click="createPresencialEnrollment" class="cecap-btn-primary">
                            Registrar Matrícula Presencial
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- TAB: DIRECTORIO ALUMNAS --}}
    @if($tab === 'students')
        <div class="cecap-card overflow-x-auto">
            <h2 class="text-base font-bold text-slate-900 mb-3">Directorio de Alumnas y Adjuntos Privados</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Alumna</th><th class="py-2 pr-3">Contacto</th><th class="py-2 pr-3">Adjuntos</th><th class="py-2">Acciones</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($students as $st)
                        <tr wire:key="student-row-{{ $st->id }}" class="{{ ($editingStudentId === $st->id || $attachingStudentId === $st->id) ? 'bg-slate-50/80' : '' }}">
                            <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $st->student_code }}</td>
                            <td class="py-2.5 pr-3 font-semibold">{{ $st->full_name }}<div class="text-xs text-slate-500">{{ $st->identification_number }}</div></td>
                            <td class="py-2.5 pr-3 text-xs">{{ $st->email }}<div>{{ $st->phone }}</div></td>
                            <td class="py-2.5 pr-3 text-xs space-y-1">
                                @php
                                    $activeStudentAtts = $st->attachments->where('status', 'activo');
                                    $archivedStudentAtts = $st->attachments->where('status', '!=', 'activo');
                                @endphp
                                @forelse($activeStudentAtts as $att)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('attachments.download', $att->uuid) }}" class="text-brand-800 underline font-medium">{{ $att->document_type_label }} v{{ $att->version }}</a>
                                        <button type="button" wire:click="archiveAttachment({{ $att->id }})" class="text-[11px] text-amber-700 hover:underline cursor-pointer">Archivar</button>
                                    </div>
                                @empty
                                    <span class="text-slate-400">Sin adjuntos vigentes</span>
                                @endforelse

                                @if($archivedStudentAtts->isNotEmpty())
                                    <div class="pt-0.5">
                                        <button type="button" wire:click="toggleStudentArchivedAttachments({{ $st->id }})" class="text-[11px] font-semibold text-slate-600 hover:text-brand-800 underline cursor-pointer">
                                            {{ $showingArchivedStudentId === $st->id ? 'Ocultar historial' : 'Ver '.$archivedStudentAtts->count().' archivado(s) / historial' }}
                                        </button>
                                    </div>
                                    @if($showingArchivedStudentId === $st->id)
                                        <div class="mt-1 rounded-lg border border-slate-200 bg-slate-50 p-2 space-y-1">
                                            @foreach($archivedStudentAtts as $arch)
                                                <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                                    <a href="{{ route('attachments.download', $arch->uuid) }}" class="text-slate-700 underline">{{ $arch->document_type_label }} v{{ $arch->version }} ({{ $arch->status }})</a>
                                                    @if(auth()->user()->hasEffectivePermission('admisiones.manage'))
                                                        <button type="button" wire:click="restoreAttachment({{ $arch->id }})" class="font-semibold text-emerald-700 hover:underline cursor-pointer">Restaurar</button>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td class="py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    @if(auth()->user()->hasEffectivePermission('admisiones.manage'))
                                        <button type="button" wire:click="startAttachStudent({{ $st->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-3 text-xs font-semibold text-amber-950 hover:bg-amber-100 cursor-pointer">+ Adjunto</button>
                                    @endif
                                    <button type="button" wire:click="editStudent({{ $st->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Edición Controlada</button>
                                </div>
                            </td>
                        </tr>
                        @if($attachingStudent && $attachingStudent->id === $st->id)
                            <tr wire:key="student-attach-{{ $st->id }}">
                                <td colspan="5" class="py-3 px-1">
                                    <form wire:submit="uploadStudentAttachment" class="rounded-2xl border-2 border-amber-400 bg-amber-50/80 p-4 space-y-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div>
                                                <h3 class="text-sm sm:text-base font-bold text-amber-950">Agregar Adjunto al Expediente — {{ $st->full_name }} ({{ $st->student_code }})</h3>
                                                <p class="text-xs text-amber-900">Se guarda versionado con SHA-256 y actualiza automáticamente el checklist de sus solicitudes abiertas.</p>
                                            </div>
                                            <button type="button" wire:click="cancelAttachStudent" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                        </div>

                                        @if($errors->any())
                                            <div class="rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs text-rose-800">
                                                <ul class="list-disc pl-4 space-y-0.5">
                                                    @foreach($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de Documento *</label>
                                                <select wire:model="student_attachment_doc_type" class="cecap-input">
                                                    @foreach($documentTypes as $dk => $dl)
                                                        <option value="{{ $dk }}">{{ $dl }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Archivo (PDF/Imagen máx 25 MB) *</label>
                                                <input wire:model="student_attachment_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                                <div wire:loading wire:target="student_attachment_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo archivo...</div>
                                                @if($student_attachment_file)
                                                    <div wire:loading.remove wire:target="student_attachment_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $student_attachment_file->getClientOriginalName() }}</div>
                                                @endif
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Observación (opcional)</label>
                                                <input wire:model="student_attachment_notes" type="text" class="cecap-input" placeholder="Ej. Atestado recibido por correo">
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="submit" wire:loading.attr="disabled" wire:target="student_attachment_file,uploadStudentAttachment" class="cecap-btn-primary text-xs sm:text-sm disabled:opacity-60">
                                                <span wire:loading.remove wire:target="student_attachment_file,uploadStudentAttachment">Subir Adjunto al Expediente</span>
                                                <span wire:loading wire:target="student_attachment_file,uploadStudentAttachment">Subiendo...</span>
                                            </button>
                                            <button type="button" wire:click="cancelAttachStudent" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                        @if($editingStudentId === $st->id)
                            <tr wire:key="student-edit-{{ $st->id }}">
                                <td colspan="5" class="py-3 px-1">
                                    <div class="rounded-2xl border-2 border-brand-700 bg-brand-50/60 p-4 space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h3 class="text-sm sm:text-base font-bold text-brand-950">Edición Controlada de Expediente — {{ $st->full_name }} ({{ $st->student_code }})</h3>
                                            <button type="button" wire:click="cancelEditStudent" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                        </div>

                                        @if($errors->any())
                                            <div class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                                <ul class="list-disc pl-4 space-y-0.5">
                                                    @foreach($errors->all() as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                                                <input wire:model="first_name" type="text" required class="cecap-input">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Primer Apellido *</label>
                                                <input wire:model="last_name" type="text" required class="cecap-input">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Segundo Apellido</label>
                                                <input wire:model="second_last_name" type="text" class="cecap-input">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Correo Electrónico (Obligatorio) *</label>
                                                <input wire:model="email" type="email" required class="cecap-input">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Teléfono *</label>
                                                <input wire:model="phone" type="tel" required class="cecap-input">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nivel Educativo</label>
                                                <select wire:model="education_level" class="cecap-input">
                                                    @foreach($educationLevels as $code => $label)
                                                        <option value="{{ $code }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Provincia</label>
                                                <select wire:model.live="province_id" class="cecap-input">
                                                    <option value="">-- Seleccione --</option>
                                                    @foreach($provinces as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Cantón</label>
                                                <select wire:model.live="canton_id" class="cecap-input">
                                                    <option value="">-- Seleccione --</option>
                                                    @foreach($cantons as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Distrito</label>
                                                <select wire:model="district_id" class="cecap-input">
                                                    <option value="">-- Seleccione --</option>
                                                    @foreach($districts as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-3 space-y-2">
                                            <label class="block text-xs sm:text-sm font-semibold text-amber-950">Motivo de la Edición Controlada (Obligatorio para Auditoría) *</label>
                                            <input wire:model="edit_reason" type="text" class="cecap-input" placeholder="Ej. Actualización de correo y teléfono solicitada por la alumna">
                                            <div class="flex flex-wrap gap-2 pt-1">
                                                <button type="button" wire:click="saveStudentOnly" class="cecap-btn-primary text-xs sm:text-sm">Guardar Cambios Auditados</button>
                                                <button type="button" wire:click="cancelEditStudent" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">Sin alumnas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
