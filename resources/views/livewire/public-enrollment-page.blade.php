<div class="space-y-6">
    <div class="rounded-2xl bg-gradient-to-r from-brand-950 via-brand-900 to-brand-800 p-6 text-white shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-flex rounded-full bg-accent-500/25 px-3 py-1 text-xs font-semibold text-accent-400">Inscripción en Línea Provisional · {{ $settings->online_enrollment_provisional_days }} días</span>
                <h1 class="mt-2 text-2xl font-bold">{{ $settings->institution_name }}</h1>
                <p class="mt-1 text-sm text-brand-100 max-w-3xl">{{ $settings->online_enrollment_notice }}</p>
            </div>
            <div class="rounded-xl bg-white/10 p-4 text-xs space-y-1 md:max-w-xs shrink-0">
                <div class="font-bold text-accent-400 uppercase">Horario de Atención Presencial</div>
                <p class="text-slate-100">{{ $settings->attention_hours }}</p>
                <p class="text-slate-200 pt-1">Tel: {{ $settings->phone }}</p>
            </div>
        </div>
    </div>

    @if($successCode)
        <div class="cecap-card border-emerald-300 bg-emerald-50/70 space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="cecap-badge bg-emerald-700 text-white">PREINSCRIPCIÓN REGISTRADA</span>
                    <h2 class="mt-2 text-xl font-bold text-emerald-950">Código de Solicitud: {{ $successCode }}</h2>
                    <p class="text-sm text-emerald-900 mt-1">
                        Estado actual: <strong>{{ $successStatus === 'documentacion_pendiente' ? 'Documentación Pendiente' : 'Preinscrita (Provisional)' }}</strong>
                        · Válida hasta: <strong>{{ $provisionalExpiresAt }}</strong>.
                    </p>
                </div>
            </div>

            @if($uploadFeedback)
                <div class="rounded-xl border border-emerald-300 bg-white px-4 py-3 text-sm font-semibold text-emerald-900">
                    {{ $uploadFeedback }}
                </div>
            @endif

            @if(! empty($missingDocumentLabels))
                <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-950 space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="font-bold">Documentos faltantes por presentar:</div>
                            <ul class="list-disc pl-5 space-y-0.5 font-medium">
                                @foreach($missingDocumentLabels as $docLabel)
                                    <li>{{ $docLabel }}</li>
                                @endforeach
                            </ul>
                            <p class="text-xs text-rose-800 pt-1">Puede cargarlos ahora mismo en esta solicitud o presentarlos en Secretaría/Admisiones antes del <strong>{{ $provisionalExpiresAt }}</strong>.</p>
                        </div>
                        @if(! $showPendingUploadForm)
                            <button type="button" wire:click="openPendingUploadForm" class="cecap-btn-primary !min-h-10 text-xs shrink-0">
                                Cargar documentos faltantes ahora
                            </button>
                        @endif
                    </div>

                    @if($showPendingUploadForm)
                        <form wire:submit="uploadMissingDocuments" class="rounded-xl border border-rose-200 bg-white p-4 space-y-4 text-slate-900">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2">
                                <div class="text-sm font-bold text-brand-950">Adjuntar documentos pendientes a la solicitud {{ $successCode }}</div>
                                <button type="button" wire:click="closePendingUploadForm" class="cecap-btn-secondary !min-h-8 !px-3 text-xs">Cancelar</button>
                            </div>

                            @if($errors->any())
                                <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-800">
                                    <ul class="list-disc pl-4 space-y-1">
                                        @foreach($errors->all() as $err)
                                            <li>{{ $err }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @if(in_array('cedula_dimex', $missingDocumentKeys, true))
                                    <div>
                                        <label for="pending_file_cedula" class="block text-sm font-semibold text-slate-700 mb-1">Copia de Cédula / DIMEX (PDF/JPG/PNG/WebP)</label>
                                        <input id="pending_file_cedula" wire:model="pending_file_cedula" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                        <div wire:loading wire:target="pending_file_cedula" class="mt-1 text-xs font-semibold text-amber-700">Subiendo archivo, espere un momento...</div>
                                        @if($pending_file_cedula)
                                            <div wire:loading.remove wire:target="pending_file_cedula" class="mt-1 text-xs font-semibold text-emerald-700">✓ Archivo listo: {{ $pending_file_cedula->getClientOriginalName() }}</div>
                                        @endif
                                        @error('pending_file_cedula') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                                @if(in_array('atestado_academico', $missingDocumentKeys, true))
                                    <div>
                                        <label for="pending_file_atestado" class="block text-sm font-semibold text-slate-700 mb-1">Atestados Académicos (PDF/JPG/PNG/WebP)</label>
                                        <input id="pending_file_atestado" wire:model="pending_file_atestado" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                        <div wire:loading wire:target="pending_file_atestado" class="mt-1 text-xs font-semibold text-amber-700">Subiendo archivo, espere un momento...</div>
                                        @if($pending_file_atestado)
                                            <div wire:loading.remove wire:target="pending_file_atestado" class="mt-1 text-xs font-semibold text-emerald-700">✓ Archivo listo: {{ $pending_file_atestado->getClientOriginalName() }}</div>
                                        @endif
                                        @error('pending_file_atestado') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="submit"
                                        wire:loading.attr="disabled"
                                        wire:target="pending_file_cedula,pending_file_atestado,uploadMissingDocuments"
                                        class="cecap-btn-primary text-xs">
                                    <span wire:loading.remove wire:target="pending_file_cedula,pending_file_atestado,uploadMissingDocuments">Guardar Documentos en esta Solicitud</span>
                                    <span wire:loading wire:target="pending_file_cedula,pending_file_atestado">Subiendo documentos...</span>
                                    <span wire:loading wire:target="uploadMissingDocuments">Guardando...</span>
                                </button>
                                <button type="button" wire:click="closePendingUploadForm" class="cecap-btn-secondary text-xs">Volver al resumen</button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif

            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 space-y-1">
                <div class="font-bold">Siguiente paso obligatorio (plazo de {{ $settings->online_enrollment_provisional_days }} días):</div>
                <p>{{ $settings->online_enrollment_notice }}</p>
                <p class="text-xs font-semibold pt-1">Horario de atención: {{ $settings->attention_hours }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if($signedPlanillaUrl)
                    <button type="button"
                            data-pdf-modal-url="{{ $signedPlanillaUrl }}"
                            data-pdf-modal-title="Planilla de Inscripción {{ $successCode }}"
                            class="cecap-btn-primary">
                        Ver / Imprimir Planilla de Inscripción PDF
                    </button>
                    <a href="{{ $signedPlanillaUrl }}&descargar=1"
                       data-pdf-download-url="{{ $signedPlanillaUrl }}&descargar=1"
                       data-pdf-filename="Planilla-{{ $successCode }}.pdf"
                       class="cecap-btn-secondary">
                        Descargar Planilla PDF
                    </a>
                @endif
                @if(! empty($missingDocumentLabels) && ! $showPendingUploadForm)
                    <button type="button" wire:click="openPendingUploadForm" class="cecap-btn-secondary">
                        Cargar Atestados / Documentos Faltantes
                    </button>
                @endif
                <button type="button" wire:click="resetForm" class="cecap-btn-secondary">Nueva Preinscripción</button>
            </div>
        </div>
    @else
        <form wire:submit="submit" class="cecap-card space-y-6">
            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 1. Selección de Curso/Edición --}}
            <div class="space-y-3">
                <h2 class="text-base font-bold text-brand-950 border-b border-slate-200 pb-2">1. Seleccione la Edición / Curso de su Interés</h2>
                <div>
                    <label for="pub_edition" class="block text-sm font-semibold text-slate-700 mb-1">Oferta Académica Disponible *</label>
                    <select id="pub_edition" wire:model.live="course_edition_id" required class="cecap-input">
                        <option value="">-- Seleccione un curso o programa abierto --</option>
                        @foreach($editions as $ed)
                            <option value="{{ $ed->id }}">
                                {{ $ed->course?->name }} ({{ $ed->course?->program_type_label }}) — {{ $ed->shift?->name }} · {{ $ed->modality?->name }} · Cupos: {{ $ed->availableSeats() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($selectedEdition)
                    @php
                        $availSeats = $selectedEdition->availableSeats();
                        $maxSeats = (int) $selectedEdition->max_capacity;
                    @endphp
                    <div class="rounded-xl border border-brand-100 bg-brand-50 p-4 text-sm grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase text-brand-800">Tipo, Cupo y Requisito</div>
                            <div class="font-bold text-brand-950">{{ $selectedEdition->course?->program_type_label }}</div>
                            <div class="text-xs text-slate-600">Nivel previo: {{ $educationLevels[$selectedEdition->course?->required_education_level] ?? $selectedEdition->course?->required_education_level }}</div>
                            <div class="mt-1 text-xs font-bold {{ $availSeats > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $availSeats > 0 ? "Cupos disponibles: {$availSeats} de {$maxSeats}" : "CUPO AGOTADO (0 de {$maxSeats})" }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase text-brand-800">Inversión Referencial</div>
                            <div class="font-bold text-brand-950">Matrícula: ₡{{ number_format((float) $selectedEdition->enrollment_fee, 0) }}</div>
                            <div class="text-xs text-slate-600">Mensualidad: ₡{{ number_format((float) $selectedEdition->monthly_fee, 0) }} ({{ $selectedEdition->installments_count }} cuotas)</div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase text-brand-800">Checklist Mínimo del Curso</div>
                            <ul class="text-xs text-slate-700 list-disc pl-4 mt-1">
                                @foreach($requiredDocs as $docKey)
                                    <li>{{ $documentTypes[$docKey] ?? $docKey }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @if($capacityBlocked)
                    <div role="alert" class="rounded-xl border-2 border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900">
                        {{ $capacityBlockedMessage }}
                    </div>
                @endif
            </div>

            {{-- 2. Datos Personales (Género Femenino + Correo Obligatorio + Cascada CR) --}}
            <div class="space-y-4">
                <h2 class="text-base font-bold text-brand-950 border-b border-slate-200 pb-2">2. Datos Personales de la Solicitante</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="pub_gender" class="block text-sm font-semibold text-slate-700 mb-1">Género *</label>
                        <select id="pub_gender" wire:model.live="gender" class="cecap-input">
                            @foreach($genders as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="pub_id_type" class="block text-sm font-semibold text-slate-700 mb-1">Tipo de identificación *</label>
                        <select id="pub_id_type" wire:model="identification_type" class="cecap-input">
                            @foreach($idTypes as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="pub_id_num" class="block text-sm font-semibold text-slate-700 mb-1">Cédula / DIMEX *</label>
                        <div class="flex gap-2">
                            <input id="pub_id_num" wire:model="identification_number" type="text" required class="cecap-input" placeholder="1-1234-5678">
                            <button type="button" wire:click="autofillByIdentification" class="cecap-btn-secondary !px-3 text-xs shrink-0">Buscar</button>
                        </div>
                    </div>
                </div>

                @if($genderBlocked)
                    <div role="alert" class="rounded-xl border-2 border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900">
                        {{ $genderBlockedMessage }}
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="pub_first_name" class="block text-sm font-semibold text-slate-700 mb-1">Nombre *</label>
                        <input id="pub_first_name" wire:model="first_name" type="text" required class="cecap-input">
                    </div>
                    <div>
                        <label for="pub_last_name" class="block text-sm font-semibold text-slate-700 mb-1">Primer apellido *</label>
                        <input id="pub_last_name" wire:model="last_name" type="text" required class="cecap-input">
                    </div>
                    <div>
                        <label for="pub_second_last_name" class="block text-sm font-semibold text-slate-700 mb-1">Segundo apellido</label>
                        <input id="pub_second_last_name" wire:model="second_last_name" type="text" class="cecap-input">
                    </div>
                    <div>
                        <label for="pub_email" class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico (Obligatorio) *</label>
                        <input id="pub_email" wire:model="email" type="email" required class="cecap-input" placeholder="alumna@correo.cr">
                    </div>
                    <div>
                        <label for="pub_phone" class="block text-sm font-semibold text-slate-700 mb-1">Teléfono celular / WhatsApp *</label>
                        <input id="pub_phone" wire:model="phone" type="tel" required class="cecap-input" placeholder="8888-8888">
                    </div>
                    <div>
                        <label for="pub_edu" class="block text-sm font-semibold text-slate-700 mb-1">Nivel académico alcanzado *</label>
                        <select id="pub_edu" wire:model.live="education_level" class="cecap-input">
                            @foreach($educationLevels as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="pub_prov" class="block text-sm font-semibold text-slate-700 mb-1">Provincia (Costa Rica)</label>
                        <select id="pub_prov" wire:model.live="province_id" class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($provinces as $prov)
                                <option value="{{ $prov->id }}">{{ $prov->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="pub_canton" class="block text-sm font-semibold text-slate-700 mb-1">Cantón</label>
                        <select id="pub_canton" wire:model.live="canton_id" class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($cantons as $can)
                                <option value="{{ $can->id }}">{{ $can->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="pub_district" class="block text-sm font-semibold text-slate-700 mb-1">Distrito</label>
                        <select id="pub_district" wire:model="district_id" class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($districts as $dist)
                                <option value="{{ $dist->id }}">{{ $dist->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($educationBlocked)
                    <div role="alert" class="rounded-xl border-2 border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900">
                        {{ $educationBlockedMessage }}
                    </div>
                @endif
            </div>

            {{-- 3. Adjuntos Privados (PDF/JPG/PNG/WebP máx 10MB) --}}
            <div class="space-y-3">
                <h2 class="text-base font-bold text-brand-950 border-b border-slate-200 pb-2">3. Documentos de Respaldo (Adjuntos Privados — Máx. 10 MB)</h2>
                <p class="text-xs text-slate-600">Formatos permitidos: PDF, JPG, PNG o WebP. Si aún no cuenta con todos los documentos, su solicitud ingresará como <strong>Documentación Pendiente</strong> y podrá entregarlos presencialmente.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="file_cedula" class="block text-sm font-semibold text-slate-700 mb-1">Copia de Cédula / DIMEX</label>
                        <input id="file_cedula" wire:model="file_cedula" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                        <div wire:loading wire:target="file_cedula" class="mt-1 text-xs font-semibold text-amber-700">Subiendo archivo, espere un momento...</div>
                        @if($file_cedula)
                            <div wire:loading.remove wire:target="file_cedula" class="mt-1 text-xs font-semibold text-emerald-700">✓ Archivo listo: {{ $file_cedula->getClientOriginalName() }}</div>
                        @endif
                        @error('file_cedula') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                    </div>
                    @if(in_array('atestado_academico', $requiredDocs, true))
                        <div>
                            <label for="file_atestado" class="block text-sm font-semibold text-slate-700 mb-1">Atestados Académicos (Requerido para Curso Técnico)</label>
                            <input id="file_atestado" wire:model="file_atestado" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                            <div wire:loading wire:target="file_atestado" class="mt-1 text-xs font-semibold text-amber-700">Subiendo archivo, espere un momento...</div>
                            @if($file_atestado)
                                <div wire:loading.remove wire:target="file_atestado" class="mt-1 text-xs font-semibold text-emerald-700">✓ Archivo listo: {{ $file_atestado->getClientOriginalName() }}</div>
                            @endif
                            @error('file_atestado') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-t border-slate-200 pt-4">
                <p class="text-xs text-slate-500">Al enviar, se generará su Planilla de Inscripción PDF descargable e imprimible (enlace seguro válido por {{ $settings->online_enrollment_provisional_days }} días).</p>
                <button type="submit"
                        @disabled($genderBlocked || $educationBlocked || $capacityBlocked)
                        wire:loading.attr="disabled"
                        wire:target="file_cedula,file_atestado,submit"
                        class="cecap-btn-primary shrink-0">
                    <span wire:loading.remove wire:target="file_cedula,file_atestado,submit">Enviar Preinscripción Provisional</span>
                    <span wire:loading wire:target="file_cedula,file_atestado">Subiendo documentos...</span>
                    <span wire:loading wire:target="submit">Enviando solicitud...</span>
                </button>
            </div>
        </form>
    @endif
</div>
