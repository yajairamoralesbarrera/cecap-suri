<div class="space-y-6">
    @if($feedback)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ $feedback }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('directory')" class="{{ $tab === 'directory' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Directorio de Colaboradores ({{ $allStaffMembers->count() }})</button>
        <button type="button" wire:click="setTab('contracts')" class="{{ $tab === 'contracts' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Contratos CR ({{ $contracts->count() }})</button>
        <button type="button" wire:click="setTab('payments')" class="{{ $tab === 'payments' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Pagos y Comprobantes CR ({{ $payments->count() }})</button>
    </div>

    {{-- TAB: DIRECTORIO DE COLABORADORES --}}
    @if($tab === 'directory')
        <div class="space-y-6">
            @if(auth()->user()->hasEffectivePermission('personal.manage'))
                <form wire:submit="createStaff" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Registrar Colaborador(a) / Profesora (Correo, Identificación y Atestados Obligatorios para Docentes)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Perfil de Colaborador(a) *</label>
                            <select wire:model.live="staff_role_type" class="cecap-input">
                                <option value="teacher">Profesora (Atestados Obligatorios)</option>
                                <option value="both">Docente + Administrativo (Atestados Obligatorios)</option>
                                <option value="administrative">Administrativo</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Régimen Legal CR *</label>
                            <select wire:model="hiring_regime" class="cecap-input">
                                <option value="planilla_laboral">Planilla Laboral (CCSS / BP / Renta / Aguinaldo)</option>
                                <option value="servicios_profesionales">Servicios Profesionales (Hacienda 50d + IVA)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Tipo de Identificación *</label>
                            <select wire:model.live="identification_type" class="cecap-input">
                                @foreach($idTypes as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('identification_type') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Cédula / DIMEX *</label>
                            <input wire:model="identification_number" type="text" required class="cecap-input" placeholder="{{ \App\Rules\ValidIdentificationNumber::placeholderFor($identification_type) }}">
                            <div class="mt-1 text-[11px] text-slate-500">{{ \App\Rules\ValidIdentificationNumber::hintFor($identification_type) }}</div>
                            @error('identification_number') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
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
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Fecha de Nacimiento *</label>
                            <input wire:model="birth_date" type="date" required max="{{ now()->subDay()->toDateString() }}" class="cecap-input">
                            @error('birth_date') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
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
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Grado Académico</label>
                            <input wire:model="academic_degree" type="text" class="cecap-input" placeholder="Ej. Técnico Medio, Bachillerato, Licenciatura">
                            @error('academic_degree') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                        </div>

                        <div class="md:col-span-3 rounded-xl border border-slate-200 bg-white p-4 space-y-2">
                            <div class="text-sm font-semibold text-slate-700">Especialidades <span class="text-xs font-normal text-slate-500">(puede indicar hasta 3 especialidades)</span></div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Especialidad 1 (Principal)</label>
                                    <input wire:model="specialty_1" type="text" class="cecap-input" placeholder="Ej. Gastronomía">
                                    @error('specialty_1') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Especialidad 2 (Opcional)</label>
                                    <input wire:model="specialty_2" type="text" class="cecap-input" placeholder="Ej. Contabilidad">
                                    @error('specialty_2') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1">Especialidad 3 (Opcional)</label>
                                    <input wire:model="specialty_3" type="text" class="cecap-input" placeholder="Ej. Corte y Confección">
                                    @error('specialty_3') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">
                                    Documento de Identificación *
                                    <span class="block text-[11px] font-normal text-slate-500">Cédula / DIMEX / Pasaporte (PDF/Imagen máx 250MB)</span>
                                </label>
                                <input wire:model="identification_file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                <div wire:loading wire:target="identification_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo documento...</div>
                                @if($identification_file)
                                    <div wire:loading.remove wire:target="identification_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $identification_file->getClientOriginalName() }}</div>
                                @endif
                                @error('identification_file') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">
                                    Atestados Profesionales
                                    @if(in_array($staff_role_type, ['teacher', 'both'], true))
                                        <span class="text-rose-700">* OBLIGATORIO</span>
                                    @else
                                        <span class="text-slate-500 font-normal">(opcional)</span>
                                    @endif
                                    <span class="block text-[11px] font-normal text-slate-500">Puede seleccionar uno o varios archivos (PDF/Imagen máx 250MB c/u)</span>
                                </label>
                                <input wire:model="credentials_files" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                <div wire:loading wire:target="credentials_files" class="mt-1 text-xs font-semibold text-brand-800">Subiendo atestados...</div>
                                @if(!empty($credentials_files))
                                    <div wire:loading.remove wire:target="credentials_files" class="mt-1 space-y-0.5 text-xs font-semibold text-emerald-700">
                                        @foreach($credentials_files as $cf)
                                            <div>✓ {{ $cf->getClientOriginalName() }}</div>
                                        @endforeach
                                    </div>
                                @endif
                                @error('credentials_file') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                @error('credentials_files') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                @error('credentials_files.*') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">
                                    Contrato Existente / Ya Firmado <span class="text-slate-500 font-normal">(opcional)</span>
                                    <span class="block text-[11px] font-normal text-slate-500">Archivo firmado o vigente (PDF/Imagen máx 250MB)</span>
                                </label>
                                <input wire:model="signed_contract_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                <div wire:loading wire:target="signed_contract_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo contrato...</div>
                                @if($signed_contract_file)
                                    <div wire:loading.remove wire:target="signed_contract_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $signed_contract_file->getClientOriginalName() }}</div>
                                @endif
                                @error('signed_contract_file') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="identification_file,credentials_file,credentials_files,signed_contract_file,createStaff" class="cecap-btn-primary disabled:opacity-60">
                        <span wire:loading.remove wire:target="identification_file,credentials_file,credentials_files,signed_contract_file,createStaff">Guardar Colaboradora</span>
                        <span wire:loading wire:target="identification_file,credentials_file,credentials_files,signed_contract_file,createStaff">Procesando documentos...</span>
                    </button>
                </form>
            @endif

            <div class="cecap-card space-y-4 overflow-x-auto">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-slate-900">Expedientes de Colaboradores</h2>
                    <span class="text-xs font-semibold text-slate-500">Mostrando {{ $staffMembers->count() }} de {{ $allStaffMembers->count() }} colaboradores</span>
                </div>

                {{-- Consulta por nombre, tipo y régimen --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar por nombre, cédula, código o especialidad</label>
                        <input wire:model.live.debounce.300ms="search" type="text" class="cecap-input" placeholder="Ej. Yajaira, PER-2026, 801720968, Gastronomía...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo</label>
                        <select wire:model.live="filter_role_type" class="cecap-input">
                            <option value="">Todos los tipos</option>
                            @foreach($roleTypeLabels as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Régimen CR</label>
                        <select wire:model.live="filter_regime" class="cecap-input">
                            <option value="">Todos los regímenes</option>
                            <option value="planilla_laboral">Planilla Laboral CR</option>
                            <option value="servicios_profesionales">Servicios Profesionales CR</option>
                        </select>
                    </div>
                    @if(trim($search) !== '' || $filter_role_type !== '' || $filter_regime !== '')
                        <div class="md:col-span-4 flex justify-end">
                            <button type="button" wire:click="clearDirectoryFilters" class="cecap-btn-secondary !min-h-8 !px-3 !py-1 text-xs">Limpiar filtros</button>
                        </div>
                    @endif
                </div>

                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Código</th>
                            <th class="py-2 pr-3">Colaboradora / Identificación</th>
                            <th class="py-2 pr-3">Tipo</th>
                            <th class="py-2 pr-3">Régimen CR</th>
                            <th class="py-2 pr-3">Contacto</th>
                            <th class="py-2">Documentos del Expediente</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($staffMembers as $st)
                            <tr wire:key="staff-row-{{ $st->id }}" class="{{ ($attachingCredentialsStaffId === $st->id || $editingStaffId === $st->id) ? 'bg-slate-50/80' : '' }}">
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold whitespace-nowrap">
                                    {{ $st->staff_code }}
                                    @if($st->status !== 'activo')
                                        <div class="mt-1"><span class="cecap-badge bg-rose-100 text-rose-800">Inactivo</span></div>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 font-semibold">
                                    {{ $st->full_name }}
                                    <div class="text-xs font-normal text-slate-500">{{ $idTypes[$st->identification_type] ?? $st->identification_type }}: <span class="font-mono">{{ $st->identification_number }}</span></div>
                                    @if($st->birth_date)
                                        <div class="text-[11px] font-normal text-slate-500">Nacimiento: {{ $st->birth_date->format('d/m/Y') }} ({{ $st->birth_date->age }} años)</div>
                                    @endif
                                    @if(auth()->user()->hasEffectivePermission('personal.manage'))
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <button type="button" wire:click="startEditStaff({{ $st->id }})" class="inline-flex min-h-7 items-center justify-center rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-950 hover:bg-amber-100 cursor-pointer">
                                                Editar
                                            </button>
                                            <button type="button" wire:click="startAttachCredentials({{ $st->id }})" class="inline-flex min-h-7 items-center justify-center rounded-lg border border-brand-300 bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-900 hover:bg-brand-100 cursor-pointer">
                                                + Agregar Atestados
                                            </button>
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 text-xs space-y-1">
                                    <div>
                                        <span class="cecap-badge {{ $st->staff_role_type === 'teacher' ? 'bg-brand-100 text-brand-900' : ($st->staff_role_type === 'both' ? 'bg-indigo-100 text-indigo-900' : 'bg-slate-200 text-slate-800') }}">
                                            {{ $st->staff_role_type_label }}
                                        </span>
                                    </div>
                                    @if($st->academic_degree)
                                        <div class="text-slate-700"><span class="font-semibold text-slate-500">Grado:</span> {{ $st->academic_degree }}</div>
                                    @endif
                                    @if(!empty($st->specialties_list))
                                        <div class="flex flex-wrap gap-1 pt-0.5">
                                            @foreach($st->specialties_list as $sp)
                                                <span class="inline-flex items-center rounded-md bg-slate-100 border border-slate-200 px-1.5 py-0.5 text-[11px] font-medium text-slate-700">{{ $sp }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 text-xs">
                                    <span class="inline-flex items-center rounded-lg px-2 py-1 font-semibold {{ $st->hiring_regime === 'planilla_laboral' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : 'bg-amber-50 text-amber-950 border border-amber-200' }}">
                                        {{ $st->hiring_regime_label }}
                                    </span>
                                </td>
                                <td class="py-2.5 pr-3 text-xs">{{ $st->email }}<div>{{ $st->phone }}</div></td>
                                <td class="py-2.5 text-xs space-y-0.5">
                                    @forelse($st->attachments->where('status', 'activo') as $att)
                                        <div>
                                            <a href="{{ route('attachments.download', $att->uuid) }}" class="text-brand-800 underline font-medium">{{ $att->document_type_label }}: {{ $att->original_name }} (v{{ $att->version }})</a>
                                        </div>
                                    @empty
                                        <span class="text-slate-400">Sin adjuntos</span>
                                    @endforelse
                                </td>
                            </tr>
                            @if($editingStaffId === $st->id)
                                <tr wire:key="staff-edit-{{ $st->id }}">
                                    <td colspan="6" class="py-3 px-1">
                                        <form wire:submit="saveStaffEdit" class="rounded-2xl border-2 border-amber-400 bg-amber-50/70 p-4 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="text-sm font-bold text-amber-950">Editar Expediente de Colaborador(a) — {{ $st->full_name }} ({{ $st->staff_code }})</div>
                                                <button type="button" wire:click="cancelEditStaff" class="text-xs font-semibold text-slate-600 hover:text-slate-900 underline">Cancelar</button>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Perfil de Colaborador(a) *</label>
                                                    <select wire:model.live="edit_staff_role_type" class="cecap-input bg-white">
                                                        @foreach($roleTypeLabels as $code => $label)
                                                            <option value="{{ $code }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Régimen Legal CR *</label>
                                                    <select wire:model="edit_hiring_regime" class="cecap-input bg-white">
                                                        <option value="planilla_laboral">Planilla Laboral CR</option>
                                                        <option value="servicios_profesionales">Servicios Profesionales CR</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Estado *</label>
                                                    <select wire:model="edit_status" class="cecap-input bg-white">
                                                        <option value="activo">Activo</option>
                                                        <option value="inactivo">Inactivo</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de Identificación *</label>
                                                    <select wire:model.live="edit_identification_type" class="cecap-input bg-white">
                                                        @foreach($idTypes as $code => $label)
                                                            <option value="{{ $code }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('edit_identification_type') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Cédula / DIMEX *</label>
                                                    <input wire:model="edit_identification_number" type="text" required class="cecap-input bg-white" placeholder="{{ \App\Rules\ValidIdentificationNumber::placeholderFor($edit_identification_type) }}">
                                                    @error('edit_identification_number') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Fecha de Nacimiento *</label>
                                                    <input wire:model="edit_birth_date" type="date" required max="{{ now()->subDay()->toDateString() }}" class="cecap-input bg-white">
                                                    @error('edit_birth_date') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                                                    <input wire:model="edit_first_name" type="text" required class="cecap-input bg-white">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Primer Apellido *</label>
                                                    <input wire:model="edit_last_name" type="text" required class="cecap-input bg-white">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Segundo Apellido</label>
                                                    <input wire:model="edit_second_last_name" type="text" class="cecap-input bg-white">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Correo Electrónico *</label>
                                                    <input wire:model="edit_email" type="email" required class="cecap-input bg-white">
                                                    @error('edit_email') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Teléfono *</label>
                                                    <input wire:model="edit_phone" type="tel" required class="cecap-input bg-white">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Grado Académico</label>
                                                    <input wire:model="edit_academic_degree" type="text" class="cecap-input bg-white" placeholder="Ej. Técnico Medio, Bachillerato, Licenciatura">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Especialidad 1 (Principal)</label>
                                                    <input wire:model="edit_specialty_1" type="text" class="cecap-input bg-white">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Especialidad 2 (Opcional)</label>
                                                    <input wire:model="edit_specialty_2" type="text" class="cecap-input bg-white">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Especialidad 3 (Opcional)</label>
                                                    <input wire:model="edit_specialty_3" type="text" class="cecap-input bg-white">
                                                </div>
                                                <div class="md:col-span-3">
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Observaciones / Notas del Expediente</label>
                                                    <input wire:model="edit_notes" type="text" class="cecap-input bg-white" placeholder="Notas internas opcionales">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 rounded-xl border border-amber-200 bg-white/80 p-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Actualizar Identificación <span class="font-normal text-slate-500">(opcional)</span></label>
                                                    <input wire:model="edit_identification_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input bg-white">
                                                    @if($edit_identification_file)
                                                        <div class="mt-1 text-xs font-semibold text-emerald-700">✓ {{ $edit_identification_file->getClientOriginalName() }}</div>
                                                    @endif
                                                    @error('edit_identification_file') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Agregar Atestados <span class="font-normal text-slate-500">(uno o varios, sin reemplazar)</span></label>
                                                    <input wire:model="edit_credentials_files" type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input bg-white">
                                                    @if(!empty($edit_credentials_files))
                                                        <div class="mt-1 space-y-0.5 text-xs font-semibold text-emerald-700">
                                                            @foreach($edit_credentials_files as $ecf)
                                                                <div>✓ {{ $ecf->getClientOriginalName() }}</div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    @error('edit_credentials_files') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                    @error('edit_credentials_files.*') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Adjuntar Contrato Firmado <span class="font-normal text-slate-500">(opcional)</span></label>
                                                    <input wire:model="edit_signed_contract_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input bg-white">
                                                    @if($edit_signed_contract_file)
                                                        <div class="mt-1 text-xs font-semibold text-emerald-700">✓ {{ $edit_signed_contract_file->getClientOriginalName() }}</div>
                                                    @endif
                                                    @error('edit_signed_contract_file') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" wire:loading.attr="disabled" wire:target="edit_identification_file,edit_credentials_files,edit_signed_contract_file,saveStaffEdit" class="cecap-btn-primary !min-h-9 !px-4 !py-1.5 text-xs disabled:opacity-60">
                                                    <span wire:loading.remove wire:target="edit_identification_file,edit_credentials_files,edit_signed_contract_file,saveStaffEdit">Guardar Cambios del Expediente</span>
                                                    <span wire:loading wire:target="edit_identification_file,edit_credentials_files,edit_signed_contract_file,saveStaffEdit">Guardando...</span>
                                                </button>
                                                <button type="button" wire:click="cancelEditStaff" class="cecap-btn-secondary !min-h-9 !px-4 !py-1.5 text-xs">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                            @if($attachingCredentialsStaffId === $st->id)
                                <tr wire:key="staff-credentials-{{ $st->id }}">
                                    <td colspan="6" class="py-3 px-1">
                                        <form wire:submit="uploadExtraCredentials" class="rounded-2xl border-2 border-brand-400 bg-brand-50/70 p-4 space-y-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="text-sm font-bold text-brand-950">Agregar Atestados Profesionales — {{ $st->full_name }} ({{ $st->staff_code }})</div>
                                                <button type="button" wire:click="cancelAttachCredentials" class="text-xs font-semibold text-slate-600 hover:text-slate-900 underline">Cancelar</button>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Atestados (puede seleccionar uno o varios — PDF/Imagen máx 250MB c/u) *</label>
                                                    <input wire:model="extra_credentials_files" type="file" multiple required accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input bg-white">
                                                    <div wire:loading wire:target="extra_credentials_files" class="mt-1 text-xs font-semibold text-brand-800">Subiendo atestados...</div>
                                                    @if(!empty($extra_credentials_files))
                                                        <div wire:loading.remove wire:target="extra_credentials_files" class="mt-1 space-y-0.5 text-xs font-semibold text-emerald-700">
                                                            @foreach($extra_credentials_files as $ef)
                                                                <div>✓ {{ $ef->getClientOriginalName() }}</div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    @error('extra_credentials_files') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                    @error('extra_credentials_files.*') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Descripción / Detalle (opcional)</label>
                                                    <input wire:model="extra_credentials_notes" type="text" class="cecap-input bg-white" placeholder="Ej. Título Licenciatura, Certificación INA, Colegiatura">
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" wire:loading.attr="disabled" wire:target="extra_credentials_files,uploadExtraCredentials" class="cecap-btn-primary !min-h-9 !px-4 !py-1.5 text-xs disabled:opacity-60">
                                                    <span wire:loading.remove wire:target="extra_credentials_files,uploadExtraCredentials">Guardar Atestados en Expediente</span>
                                                    <span wire:loading wire:target="extra_credentials_files,uploadExtraCredentials">Subiendo archivos...</span>
                                                </button>
                                                <button type="button" wire:click="cancelAttachCredentials" class="cecap-btn-secondary !min-h-9 !px-4 !py-1.5 text-xs">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">No se encontraron colaboradores con los criterios seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: CONTRATOS CR --}}
    @if($tab === 'contracts')
        <div class="space-y-6">
            @if(auth()->user()->hasEffectivePermission('personal.manage'))
                <form wire:submit="createContract" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Emitir o Registrar Contrato (Separación Legal CR: Planilla vs Servicios Profesionales)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Colaboradora *</label>
                            <select wire:model="contract_staff_id" required class="cecap-input">
                                <option value="">-- Seleccione --</option>
                                @foreach($allStaffMembers as $m)<option value="{{ $m->id }}">{{ $m->full_name }} ({{ $m->staff_role_type_label }})</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Régimen Legal CR *</label>
                            <select wire:model.live="contract_regime" class="cecap-input">
                                <option value="planilla_laboral">Planilla Laboral (CCSS/BP/Renta/Aguinaldo/Vacaciones)</option>
                                <option value="servicios_profesionales">Servicios Profesionales (Factura Hacienda 50d + IVA)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Modalidad de Documento *</label>
                            <select wire:model.live="contract_mode" class="cecap-input">
                                <option value="editable">Contrato Editable en Plataforma (PDF generado)</option>
                                <option value="pdf_adjunto">Subir Contrato Existente o Ya Firmado (PDF / Imagen)</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Título del Contrato *</label>
                            <input wire:model="contract_title" type="text" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Monto Bruto / Tarifa (CRC) *</label>
                            <input wire:model="contract_gross" type="number" step="0.01" min="1" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Inicio *</label>
                            <input wire:model="contract_start_date" type="date" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Fin (Opcional)</label>
                            <input wire:model="contract_end_date" type="date" class="cecap-input">
                        </div>
                        @if($contract_regime === 'planilla_laboral')
                            <div class="grid grid-cols-3 gap-2">
                                <div><label class="block text-xs font-semibold mb-1">CCSS %</label><input wire:model="contract_ccss_rate" type="number" step="0.01" class="cecap-input"></div>
                                <div><label class="block text-xs font-semibold mb-1">BP %</label><input wire:model="contract_bp_rate" type="number" step="0.01" class="cecap-input"></div>
                                <div><label class="block text-xs font-semibold mb-1">Renta %</label><input wire:model="contract_income_tax_rate" type="number" step="0.01" class="cecap-input"></div>
                            </div>
                        @else
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">IVA Servicios CR (%)</label>
                                <input wire:model="contract_iva_rate" type="number" step="0.01" class="cecap-input">
                            </div>
                        @endif
                    </div>

                    @if($contract_mode === 'editable')
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Cláusulas Editables del Contrato</label>
                                <textarea wire:model="contract_body" rows="3" class="cecap-input"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Adjuntar Copia Ya Firmada (opcional — PDF/JPG/PNG/WebP máx 250MB)</label>
                                <input wire:model="contract_pdf_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                <div wire:loading wire:target="contract_pdf_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo contrato...</div>
                                @if($contract_pdf_file)
                                    <div wire:loading.remove wire:target="contract_pdf_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $contract_pdf_file->getClientOriginalName() }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Archivo del Contrato Existente / Ya Firmado (PDF o Imagen máx 250MB) *</label>
                            <input wire:model="contract_pdf_file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                            <div wire:loading wire:target="contract_pdf_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo contrato...</div>
                            @if($contract_pdf_file)
                                <div wire:loading.remove wire:target="contract_pdf_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $contract_pdf_file->getClientOriginalName() }}</div>
                            @endif
                            @error('contract_pdf_file') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <button type="submit" wire:loading.attr="disabled" wire:target="contract_pdf_file,createContract" class="cecap-btn-primary disabled:opacity-60">
                        <span wire:loading.remove wire:target="contract_pdf_file,createContract">Guardar Contrato</span>
                        <span wire:loading wire:target="contract_pdf_file,createContract">Procesando archivo...</span>
                    </button>
                </form>
            @endif

            <div class="cecap-card overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Contratos Vigentes</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Contrato</th><th class="py-2 pr-3">Colaboradora</th><th class="py-2 pr-3">Régimen CR</th><th class="py-2 pr-3">Bruto</th><th class="py-2">Documentos</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($contracts as $ct)
                            <tr wire:key="contract-row-{{ $ct->id }}" class="{{ $attachingContractId === $ct->id ? 'bg-slate-50/80' : '' }}">
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $ct->contract_number }}</td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $ct->staffMember?->full_name }}<div class="text-xs font-normal text-slate-500">{{ $ct->title }}</div></td>
                                <td class="py-2.5 pr-3 text-xs">{{ $ct->hiring_regime === 'planilla_laboral' ? 'Planilla Laboral' : 'Servicios Profesionales' }}</td>
                                <td class="py-2.5 pr-3 font-bold">₡{{ number_format((float) $ct->gross_salary_or_rate, 2) }}</td>
                                <td class="py-2.5">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if($ct->contract_mode === 'editable')
                                            <button type="button" data-pdf-modal-url="{{ route('pdf.contrato', $ct) }}" data-pdf-modal-title="Contrato {{ $ct->contract_number }}" class="cecap-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">Contrato PDF</button>
                                        @endif
                                        @if($ct->attachment)
                                            <a href="{{ route('attachments.download', $ct->attachment->uuid) }}" class="inline-flex min-h-8 items-center justify-center rounded-xl border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-900 hover:bg-emerald-100">Ver Contrato Firmado (v{{ $ct->attachment->version }})</a>
                                        @endif
                                        @if(auth()->user()->hasEffectivePermission('personal.manage'))
                                            <button type="button" wire:click="startAttachContractFile({{ $ct->id }})" class="inline-flex min-h-8 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-950 hover:bg-amber-100 cursor-pointer">
                                                {{ $ct->attachment ? 'Actualizar Firmado' : 'Subir Contrato Firmado' }}
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @if($attachingContractId === $ct->id)
                                <tr wire:key="contract-attach-{{ $ct->id }}">
                                    <td colspan="5" class="py-3 px-1">
                                        <form wire:submit="uploadSignedContractFile" class="rounded-2xl border-2 border-amber-400 bg-amber-50/80 p-4 space-y-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h3 class="text-sm font-bold text-amber-950">Subir Archivo de Contrato Firmado — {{ $ct->contract_number }} ({{ $ct->staffMember?->full_name }})</h3>
                                                    <p class="text-xs text-amber-900">Se almacena de forma privada y versionada con SHA-256 (sin borrar versiones anteriores).</p>
                                                </div>
                                                <button type="button" wire:click="cancelAttachContractFile" class="cecap-btn-secondary !min-h-8 !px-3 !py-1 text-xs">Cancelar</button>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Archivo de Contrato Firmado (PDF/Imagen máx 250MB) *</label>
                                                    <input wire:model="existing_contract_signed_file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                                    <div wire:loading wire:target="existing_contract_signed_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo archivo...</div>
                                                    @if($existing_contract_signed_file)
                                                        <div wire:loading.remove wire:target="existing_contract_signed_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $existing_contract_signed_file->getClientOriginalName() }}</div>
                                                    @endif
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Observación (opcional)</label>
                                                    <input wire:model="existing_contract_signed_notes" type="text" class="cecap-input" placeholder="Ej. Contrato escaneado con firmas autógrafas">
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" wire:loading.attr="disabled" wire:target="existing_contract_signed_file,uploadSignedContractFile" class="cecap-btn-primary text-xs sm:text-sm disabled:opacity-60">
                                                    <span wire:loading.remove wire:target="existing_contract_signed_file,uploadSignedContractFile">Guardar Contrato Firmado</span>
                                                    <span wire:loading wire:target="existing_contract_signed_file,uploadSignedContractFile">Subiendo...</span>
                                                </button>
                                                <button type="button" wire:click="cancelAttachContractFile" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Sin contratos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: PAGOS CR --}}
    @if($tab === 'payments')
        <div class="space-y-6">
            @if(auth()->user()->hasEffectivePermission('personal.payroll.manage'))
                <form wire:submit="recordPayment" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Registrar Pago — Planilla Laboral CR vs Servicios Profesionales</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Contrato *</label>
                            <select wire:model.live="pay_contract_id" required class="cecap-input">
                                <option value="">-- Seleccione contrato --</option>
                                @foreach($contracts as $ct)
                                    <option value="{{ $ct->id }}">{{ $ct->contract_number }} — {{ $ct->staffMember?->full_name }} ({{ $ct->hiring_regime }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Período *</label>
                            <input wire:model="pay_period_label" type="text" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Fecha Pago *</label>
                            <input wire:model="pay_date" type="date" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Monto Bruto (CRC) *</label>
                            <input wire:model="pay_gross_amount" type="number" step="0.01" min="1" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Método</label>
                            <select wire:model="pay_method" class="cecap-input">
                                @foreach($paymentMethods as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Referencia Bancaria</label>
                            <input wire:model="pay_reference" type="text" class="cecap-input">
                        </div>
                        @if($selectedPayContract && $selectedPayContract->hiring_regime === 'servicios_profesionales')
                            <div class="md:col-span-3">
                                <label class="block text-sm font-semibold text-amber-950 mb-1">Clave Numérica Factura Electrónica Hacienda CR (Exactamente 50 dígitos) *</label>
                                <input wire:model="pay_hacienda_key" type="text" maxlength="50" required class="cecap-input font-mono" placeholder="50625112600310100000000100001010000000001123456789">
                            </div>
                        @endif
                    </div>
                    <button type="submit" class="cecap-btn-primary">Calcular y Emitir Comprobante PDF</button>
                </form>
            @endif

            <div class="cecap-card overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Comprobantes Emitidos</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Comprobante</th><th class="py-2 pr-3">Colaboradora</th><th class="py-2 pr-3">Régimen</th><th class="py-2 pr-3">Bruto / Neto</th><th class="py-2">PDF</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($payments as $pm)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $pm->voucher_number }}<div class="text-slate-400">{{ $pm->payment_date?->format('d/m/Y') }}</div></td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $pm->staffMember?->full_name }}</td>
                                <td class="py-2.5 pr-3 text-xs">{{ $pm->hiring_regime === 'planilla_laboral' ? 'Planilla (CCSS/BP/Renta)' : 'Serv. Prof. (Hacienda+IVA)' }}</td>
                                <td class="py-2.5 pr-3 text-xs">Bruto: ₡{{ number_format((float) $pm->gross_amount, 2) }}<div class="font-bold text-brand-950">Neto: ₡{{ number_format((float) $pm->net_amount, 2) }}</div></td>
                                <td class="py-2.5">
                                    <button type="button" data-pdf-modal-url="{{ route('pdf.comprobante-personal', $pm) }}" data-pdf-modal-title="Comprobante {{ $pm->voucher_number }}" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Comprobante PDF</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Sin pagos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
