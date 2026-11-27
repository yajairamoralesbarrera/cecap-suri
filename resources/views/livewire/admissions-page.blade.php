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
        <button type="button" wire:click="setTab('enroll')" class="{{ $tab === 'enroll' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">{{ $editingStudentId ? 'Editando Expediente' : 'Matrícula Presencial / Nueva Alumna' }}</button>
        <button type="button" wire:click="setTab('students')" class="{{ $tab === 'students' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Directorio de Alumnas ({{ $students->count() }})</button>
    </div>

    {{-- TAB: COLA DE ADMISIONES Y MATRÍCULAS --}}
    @if($tab === 'queue')
        @if($confirmingEnrollment)
            <div class="cecap-card border-brand-700 bg-brand-50/50 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-brand-950">Confirmar Matrícula {{ $confirmingEnrollment->enrollment_code }} — {{ $confirmingEnrollment->student?->full_name }}</h2>
                    <button type="button" wire:click="cancelConfirmEnrollment" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                </div>
                <p class="text-sm text-slate-700">Curso: <strong>{{ $confirmingEnrollment->edition?->course?->name }}</strong> ({{ $confirmingEnrollment->edition?->name }})</p>

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

                <div class="flex gap-2">
                    <button type="button" wire:click="submitConfirmation" class="cecap-btn-primary">Confirmar Matrícula y Generar Plan de Cobro</button>
                </div>
            </div>
        @endif

        @if($rejectingEnrollment)
            <div class="cecap-card border-rose-400 bg-rose-50/70 space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-rose-950">Rechazar Solicitud {{ $rejectingEnrollment->enrollment_code }} — {{ $rejectingEnrollment->student?->full_name }}</h2>
                    <button type="button" wire:click="cancelRejectEnrollment" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                </div>
                <p class="text-sm text-slate-700">
                    Curso: <strong>{{ $rejectingEnrollment->edition?->course?->name }}</strong> ({{ $rejectingEnrollment->edition?->name }}) ·
                    Se notificará por correo a <strong>{{ $rejectingEnrollment->student?->email }}</strong> y el cupo quedará libre.
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
                        <input wire:model="rejection_notes" type="text" class="cecap-input" placeholder="Ej. Puede postular nuevamente en la edición del próximo trimestre">
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="submitRejection" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800 cursor-pointer">
                        Confirmar Rechazo y Enviar Correo
                    </button>
                    <button type="button" wire:click="cancelRejectEnrollment" class="cecap-btn-secondary">Cancelar</button>
                </div>
            </div>
        @endif

        <div class="cecap-card overflow-x-auto">
            <h2 class="text-base font-bold text-slate-900 mb-3">Solicitudes y Matrículas Recientes</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="py-2 pr-3">Código</th>
                        <th class="py-2 pr-3">Alumna</th>
                        <th class="py-2 pr-3">Edición</th>
                        <th class="py-2 pr-3">Estado</th>
                        <th class="py-2 pr-3">Checklist</th>
                        <th class="py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($enrollments as $enr)
                        <tr>
                            <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $enr->enrollment_code }}<div class="text-[11px] text-slate-400">{{ $enr->source }}</div></td>
                            <td class="py-2.5 pr-3 font-semibold text-slate-900">{{ $enr->student?->full_name }}<div class="text-xs text-slate-500">{{ $enr->student?->identification_number }} · {{ $enr->student?->email }}</div></td>
                            <td class="py-2.5 pr-3 text-xs">{{ $enr->edition?->course?->name }}<div class="text-slate-500">{{ $enr->edition?->name }}</div></td>
                            <td class="py-2.5 pr-3">
                                <span class="cecap-badge {{ $enr->status === 'confirmada' ? 'bg-emerald-100 text-emerald-900' : ($enr->status === 'rechazada' ? 'bg-rose-100 text-rose-900' : ($enr->status === 'documentacion_pendiente' ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-800')) }}">
                                    {{ strtoupper(str_replace('_', ' ', $enr->status)) }}
                                </span>
                                @if($enr->exception_authorized)
                                    <div class="text-[11px] text-amber-700 font-semibold mt-0.5">Excepción hasta {{ $enr->exception_commitment_date?->format('d/m/Y') }}</div>
                                @endif
                                @if($enr->status === 'rechazada' && $enr->rejection_reason_label)
                                    <div class="text-[11px] text-rose-700 font-medium mt-0.5">Motivo: {{ $enr->rejection_reason_label }}</div>
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
                                    @if(in_array($enr->status, ['preinscrita', 'documentacion_pendiente'], true) && auth()->user()->hasEffectivePermission('admisiones.manage'))
                                        <button type="button" wire:click="startConfirmEnrollment({{ $enr->id }})" class="cecap-btn-primary !min-h-9 !px-3 text-xs">Confirmar</button>
                                        <button type="button" wire:click="startRejectEnrollment({{ $enr->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-rose-300 bg-white px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50 cursor-pointer">Rechazar</button>
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
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">Sin matrículas o preinscripciones registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- TAB: MATRÍCULA PRESENCIAL / EDICIÓN CONTROLADA --}}
    @if($tab === 'enroll')
        <div class="cecap-card space-y-5">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-base font-bold text-brand-950">{{ $editingStudentId ? 'Edición Controlada de Expediente (Auditada)' : 'Nueva Matrícula Presencial' }}</h2>
                @if($editingStudentId)
                    <button type="button" wire:click="cancelEditStudent" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar Edición</button>
                @endif
            </div>

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

            @if($editingStudentId)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-3">
                    <label class="block text-sm font-semibold text-amber-950">Motivo de la Edición Controlada (Obligatorio para Auditoría) *</label>
                    <input wire:model="edit_reason" type="text" class="cecap-input" placeholder="Ej. Actualización de correo y teléfono solicitada por la alumna">
                    <button type="button" wire:click="saveStudentOnly" class="cecap-btn-primary">Guardar Cambios Auditados</button>
                </div>
            @else
                <div class="border-t border-slate-200 pt-4 space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Edición a Matricular *</label>
                            <select wire:model="course_edition_id" class="cecap-input">
                                <option value="">-- Seleccione edición --</option>
                                @foreach($editions as $ed)
                                    <option value="{{ $ed->id }}">{{ $ed->course?->name }} — {{ $ed->name }} (Cupos: {{ $ed->availableSeats() }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Adjunto Privado (PDF/JPG/PNG/WebP máx 10MB)</label>
                            <div class="flex gap-2">
                                <select wire:model="attachment_doc_type" class="cecap-input w-44 shrink-0">
                                    <option value="cedula_dimex">Cédula / DIMEX</option>
                                    <option value="atestado_academico">Atestado Académico</option>
                                </select>
                                <input wire:model="attachment_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                            </div>
                        </div>
                    </div>

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

                    <button type="button" wire:click="createPresencialEnrollment" class="cecap-btn-primary">Registrar Matrícula Presencial</button>
                </div>
            @endif
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
                        <tr>
                            <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $st->student_code }}</td>
                            <td class="py-2.5 pr-3 font-semibold">{{ $st->full_name }}<div class="text-xs text-slate-500">{{ $st->identification_number }}</div></td>
                            <td class="py-2.5 pr-3 text-xs">{{ $st->email }}<div>{{ $st->phone }}</div></td>
                            <td class="py-2.5 pr-3 text-xs">
                                @foreach($st->attachments->where('status', 'activo') as $att)
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('attachments.download', $att->uuid) }}" class="text-brand-800 underline">{{ $att->document_type_label }} v{{ $att->version }}</a>
                                        <button type="button" wire:click="archiveAttachment({{ $att->id }})" class="text-[11px] text-amber-700 hover:underline cursor-pointer">Archivar</button>
                                    </div>
                                @endforeach
                            </td>
                            <td class="py-2.5">
                                <button type="button" wire:click="editStudent({{ $st->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Edición Controlada</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">Sin alumnas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
