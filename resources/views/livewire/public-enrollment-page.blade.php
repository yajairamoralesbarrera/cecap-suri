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

    @if($exitedForm)
        <div class="cecap-card border-brand-200 bg-white text-center py-10 px-6 space-y-4">
            <span class="cecap-badge bg-brand-100 text-brand-900">SESIÓN DE INSCRIPCIÓN FINALIZADA</span>
            <h2 class="text-xl font-bold text-brand-950">Has salido de la Inscripción en Línea</h2>
            <p class="text-sm text-slate-600 max-w-xl mx-auto">
                Gracias por tu interés en <strong>{{ $settings->institution_name }}</strong>. Por tu seguridad, los datos ingresados han sido limpiados de esta pantalla.
            </p>
            <p class="text-xs text-slate-500">
                Horario de atención presencial: <strong>{{ $settings->attention_hours }}</strong> · Tel: {{ $settings->phone }}
            </p>
            <div class="flex flex-wrap justify-center gap-3 pt-2">
                <button type="button" wire:click="returnToForm" class="cecap-btn-primary">Volver a Inscripción en Línea</button>
                <a href="{{ route('login') }}" class="cecap-btn-secondary">Ingresar al Sistema</a>
            </div>
        </div>
    @elseif($successCode)
        <div class="cecap-card {{ $successStatus === 'lista_espera' ? 'border-indigo-300 bg-indigo-50/70' : 'border-emerald-300 bg-emerald-50/70' }} space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-2">
                    @if($successStatus === 'lista_espera')
                        <span class="cecap-badge bg-indigo-700 text-white">LISTA DE ESPERA EN LÍNEA</span>
                    @else
                        <span class="cecap-badge bg-emerald-700 text-white">PREINSCRIPCIÓN REGISTRADA</span>
                    @endif
                    <div class="rounded-xl border-2 {{ $successStatus === 'lista_espera' ? 'border-indigo-400 bg-white' : 'border-emerald-500 bg-white' }} px-4 py-3">
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Su Número de Solicitud es:</div>
                        <div class="mt-0.5 font-mono text-2xl sm:text-3xl font-extrabold {{ $successStatus === 'lista_espera' ? 'text-indigo-950' : 'text-emerald-900' }}">{{ $successCode }}</div>
                        <p class="text-xs text-slate-600 mt-1">Anote o guarde este <strong>Número de Solicitud</strong> para presentarlo en las oficinas del Centro de Capacitación o consultar su trámite.</p>
                    </div>
                    <p class="text-sm {{ $successStatus === 'lista_espera' ? 'text-indigo-900' : 'text-emerald-900' }}">
                        Estado actual:
                        <strong>
                            {{ $successStatus === 'lista_espera' ? 'Lista de Espera en Línea (Cupo máximo alcanzado)' : ($successStatus === 'documentacion_pendiente' ? 'Documentación Pendiente' : 'Preinscrita (Provisional)') }}
                        </strong>
                        · Válida hasta: <strong>{{ $provisionalExpiresAt }}</strong>.
                    </p>
                </div>
            </div>

            @if($successStatus === 'lista_espera')
                <div class="rounded-xl border border-indigo-300 bg-white px-4 py-3 text-sm text-indigo-950">
                    <strong>Importante (Lista de Espera — Solicitud N.º {{ $successCode }}):</strong> El grupo seleccionado ya completó su matrícula máxima. Su solicitud <strong>N.º {{ $successCode }}</strong> quedó registrada en orden de ingreso y Secretaría le contactará por correo o teléfono si se libera un cupo o se habilita una nueva cohorte.
                </div>
            @endif

            @if($uploadFeedback)
                <div class="rounded-xl border border-emerald-300 bg-white px-4 py-3 text-sm font-semibold text-emerald-900">
                    {{ $uploadFeedback }}
                </div>
            @endif

            @if(! empty($missingDocumentLabels))
                <div class="rounded-xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-950 space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="font-bold">Documentos faltantes por presentar (Solicitud N.º {{ $successCode }}):</div>
                            <ul class="list-disc pl-5 space-y-0.5 font-medium">
                                @foreach($missingDocumentLabels as $docLabel)
                                    <li>{{ $docLabel }}</li>
                                @endforeach
                            </ul>
                            <p class="text-xs text-rose-800 pt-1">Para su solicitud <strong>N.º {{ $successCode }}</strong>, puede cargarlos ahora mismo o presentarlos en Secretaría/Admisiones antes del <strong>{{ $provisionalExpiresAt }}</strong>.</p>
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
                <div class="font-bold">Siguiente paso obligatorio — Solicitud N.º {{ $successCode }} (plazo de {{ $settings->online_enrollment_provisional_days }} días):</div>
                <p>{{ $settings->online_enrollment_notice }}</p>
                <p class="text-xs font-semibold pt-1">Indique su Número de Solicitud <strong>{{ $successCode }}</strong> al acudir a las oficinas · Horario de atención: {{ $settings->attention_hours }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                @if($signedPlanillaUrl)
                    <button type="button"
                            data-pdf-modal-url="{{ $signedPlanillaUrl }}"
                            data-pdf-modal-title="Planilla de Inscripción {{ $successCode }}"
                            class="cecap-btn-primary">
                        Ver / Imprimir Planilla de Inscripción PDF
                    </button>
                    @if(! empty($planillaPdfDataUri))
                        <a href="{{ $planillaPdfDataUri }}"
                           download="Planilla-{{ $successCode }}.pdf"
                           class="cecap-btn-secondary">
                            Descargar Planilla PDF
                        </a>
                    @else
                        <button type="button"
                                data-pdf-download-url="{{ $signedPlanillaUrl }}&descargar=1"
                                data-pdf-filename="Planilla-{{ $successCode }}.pdf"
                                class="cecap-btn-secondary">
                            Descargar Planilla PDF
                        </button>
                    @endif
                @endif
                @if(! empty($missingDocumentLabels) && ! $showPendingUploadForm)
                    <button type="button" wire:click="openPendingUploadForm" class="cecap-btn-secondary">
                        Cargar Atestados / Documentos Faltantes
                    </button>
                @endif
                <button type="button" wire:click="resetForm" class="cecap-btn-secondary">Nueva Preinscripción</button>
                <a href="https://proyectosuri.org/" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                    Salir
                </a>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-amber-300 bg-amber-50/80 p-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="text-sm font-bold text-amber-950">¿Ya te preinscribiste y te faltan documentos o atestados por subir?</div>
                    <p class="text-xs text-amber-900">Ingresa únicamente tu número de cédula o DIMEX para adjuntar los archivos faltantes sobre tu misma preinscripción sin duplicarla.</p>
                </div>
                <button type="button" wire:click="toggleResumeLookup" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">
                    {{ $showResumeLookup ? 'Ocultar' : 'Completar documentos faltantes' }}
                </button>
            </div>

            @if($showResumeLookup)
                <form wire:submit="resumePendingEnrollment" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end pt-2 border-t border-amber-200">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Número de Cédula / DIMEX *</label>
                        <input wire:model="lookup_identification" type="text" required class="cecap-input" placeholder="Ej. 1-1234-5678">
                    </div>
                    <div>
                        <button type="submit" class="cecap-btn-primary w-full text-xs sm:text-sm">Buscar por Cédula</button>
                    </div>
                </form>
                @error('lookup_identification')
                    <div class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-800">{{ $message }}</div>
                @enderror

                @if(! empty($lookupMatches))
                    <div class="rounded-xl border border-amber-300 bg-white p-3 space-y-2">
                        <div class="text-xs font-bold text-amber-950">Encontramos varias solicitudes asociadas a tu cédula. Selecciona a cuál deseas subir los documentos:</div>
                        <div class="space-y-2">
                            @foreach($lookupMatches as $match)
                                <div wire:key="lookup-match-{{ $match['id'] }}" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs">
                                    <div>
                                        <span class="font-mono font-bold text-brand-950">{{ $match['code'] }}</span>
                                        — <span class="font-semibold text-slate-800">{{ $match['course'] }}</span>
                                        <span class="text-slate-500">({{ $match['cohort'] }})</span>
                                        @if($match['missing'] !== '')
                                            <div class="text-rose-700 font-medium">Faltante: {{ $match['missing'] }}</div>
                                        @endif
                                    </div>
                                    <button type="button" wire:click="selectLookupEnrollment({{ $match['id'] }})" class="cecap-btn-primary !min-h-8 !px-3 !py-1 text-xs">
                                        Seleccionar y Subir
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

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

            {{-- 1. Selección de Curso/Cohorte --}}
            <div class="space-y-3">
                <h2 class="text-base font-bold text-brand-950 border-b border-slate-200 pb-2">1. Seleccione la Cohorte / Curso de su Interés</h2>
                <div>
                    <label for="pub_edition" class="block text-sm font-semibold text-slate-700 mb-1">Oferta Académica Disponible *</label>
                    <select id="pub_edition" wire:model.live="course_edition_id" required class="cecap-input">
                        <option value="">-- Seleccione un curso o programa abierto --</option>
                        @foreach($editions as $ed)
                            <option value="{{ $ed->id }}">
                                {{ $ed->course?->name }} [{{ $ed->code }}] ({{ $ed->course?->program_type_label }}) — {{ $ed->shift?->name }} · {{ $ed->modality?->name }} · Cupos: {{ $ed->availableSeats() }}
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
                            <div class="mt-1 text-xs font-bold {{ $availSeats > 0 ? 'text-emerald-700' : 'text-indigo-700' }}">
                                {{ $availSeats > 0 ? "Cupos disponibles: {$availSeats} de {$maxSeats}" : "CUPO LLENO (0 de {$maxSeats}) · Lista de Espera en Línea" }}
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

                @if($isWaitlistMode)
                    <div role="status" class="rounded-xl border-2 border-indigo-300 bg-indigo-50 p-4 text-sm font-semibold text-indigo-950">
                        <div class="font-bold text-indigo-900">Lista de Espera en Línea</div>
                        <p class="mt-1 font-normal">{{ $waitlistNotice }}</p>
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
                            <input id="pub_id_num" wire:model.blur="identification_number" type="text" required class="cecap-input" placeholder="1-1234-5678">
                            <button type="button" wire:click="autofillByIdentification" class="cecap-btn-secondary !px-3 text-xs shrink-0">Buscar</button>
                        </div>
                        @error('identification_number') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                    </div>
                </div>

                @if($duplicateCourseBlocked)
                    <div role="alert" class="rounded-xl border-2 border-rose-400 bg-rose-50 p-4 text-sm font-semibold text-rose-900">
                        {{ $duplicateCourseMessage }}
                    </div>
                @endif

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
                        <label for="pub_birth_date" class="block text-sm font-semibold text-slate-700 mb-1">Fecha de nacimiento *</label>
                        <input id="pub_birth_date" wire:model.live="birth_date" type="date" required max="{{ now()->subDay()->toDateString() }}" class="cecap-input">
                        @if($applicantAge !== null)
                            <div class="mt-1 text-xs font-semibold {{ $isMinorApplicant ? 'text-amber-800' : 'text-slate-600' }}">
                                Edad: {{ $applicantAge }} años {{ $isMinorApplicant ? '— Menor de edad (requiere representante legal)' : '' }}
                            </div>
                        @endif
                        @error('birth_date') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="pub_email" class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico (Obligatorio) *</label>
                        <input id="pub_email"
                               wire:model="email"
                               type="email"
                               required
                               pattern="[^\s@]+@[^\s@]+\.[A-Za-z]{2,24}"
                               title="Ingrese un correo electrónico válido con terminación correcta (ej. nombre@dominio.com, .net, .org, .cr)"
                               class="cecap-input"
                               placeholder="alumna@correo.com">
                        @error('email') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
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

                @if($isMinorApplicant)
                    <div class="rounded-2xl border-2 border-amber-400 bg-amber-50/90 p-4 space-y-3">
                        <div>
                            <div class="text-sm font-bold text-amber-950">Datos del Representante Legal (Obligatorio por ser menor de edad — {{ $applicantAge }} años)</div>
                            <p class="text-xs text-amber-900">Al ser menor de 18 años, debe registrar los datos completos de la madre, padre o encargado/a legal responsable.</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div>
                                <label for="pub_guardian_name" class="block text-xs font-semibold text-slate-700 mb-1">Nombre completo del representante *</label>
                                <input id="pub_guardian_name" wire:model="guardian_full_name" type="text" required class="cecap-input" placeholder="Nombre y apellidos">
                                @error('guardian_full_name') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="pub_guardian_id" class="block text-xs font-semibold text-slate-700 mb-1">Cédula / DIMEX del representante *</label>
                                <input id="pub_guardian_id" wire:model="guardian_identification_number" type="text" required class="cecap-input" placeholder="1-1234-5678">
                                @error('guardian_identification_number') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="pub_guardian_rel" class="block text-xs font-semibold text-slate-700 mb-1">Parentesco *</label>
                                <select id="pub_guardian_rel" wire:model="guardian_relationship" required class="cecap-input">
                                    <option value="Madre">Madre</option>
                                    <option value="Padre">Padre</option>
                                    <option value="Encargado/a Legal">Encargado/a Legal</option>
                                    <option value="Tutor/a Legal">Tutor/a Legal</option>
                                </select>
                                @error('guardian_relationship') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label for="pub_guardian_phone" class="block text-xs font-semibold text-slate-700 mb-1">Teléfono del representante *</label>
                                <input id="pub_guardian_phone" wire:model="guardian_phone" type="tel" required class="cecap-input" placeholder="8888-8888">
                                @error('guardian_phone') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="pub_guardian_email" class="block text-xs font-semibold text-slate-700 mb-1">Correo electrónico del representante (opcional)</label>
                                <input id="pub_guardian_email" wire:model="guardian_email" type="email" pattern="[^\s@]+@[^\s@]+\.[A-Za-z]{2,24}" class="cecap-input" placeholder="representante@correo.com">
                                @error('guardian_email') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                @endif

                @if($educationBlocked)
                    <div role="alert" class="rounded-xl border-2 border-rose-300 bg-rose-50 p-4 text-sm font-semibold text-rose-900">
                        {{ $educationBlockedMessage }}
                    </div>
                @endif
            </div>

            {{-- 3. Adjuntos Privados (PDF/JPG/PNG/WebP máx 25MB) --}}
            <div class="space-y-3">
                <h2 class="text-base font-bold text-brand-950 border-b border-slate-200 pb-2">3. Documentos de Respaldo (Adjuntos Privados — Máx. 25 MB)</h2>
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
                <p class="text-xs text-slate-500">Al enviar, se indicará su Número de Solicitud y se generará su Planilla de Inscripción PDF descargable e imprimible (enlace seguro válido por {{ $settings->online_enrollment_provisional_days }} días).</p>
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <a href="https://proyectosuri.org/" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                        Salir
                    </a>
                    <button type="submit"
                            @disabled($genderBlocked || $educationBlocked || $duplicateCourseBlocked)
                            wire:loading.attr="disabled"
                            wire:target="file_cedula,file_atestado,submit"
                            class="cecap-btn-primary shrink-0">
                        <span wire:loading.remove wire:target="file_cedula,file_atestado,submit">
                            {{ $isWaitlistMode ? 'Registrarme en Lista de Espera' : 'Enviar Preinscripción Provisional' }}
                        </span>
                        <span wire:loading wire:target="file_cedula,file_atestado">Subiendo documentos...</span>
                        <span wire:loading wire:target="submit">Enviando solicitud...</span>
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
