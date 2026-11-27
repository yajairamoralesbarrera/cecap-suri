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
        <button type="button" wire:click="setTab('directory')" class="{{ $tab === 'directory' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Directorio y Atestados ({{ $staffMembers->count() }})</button>
        <button type="button" wire:click="setTab('contracts')" class="{{ $tab === 'contracts' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Contratos CR ({{ $contracts->count() }})</button>
        <button type="button" wire:click="setTab('payments')" class="{{ $tab === 'payments' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Pagos y Comprobantes CR ({{ $payments->count() }})</button>
    </div>

    {{-- TAB: DIRECTORIO Y ATESTADOS --}}
    @if($tab === 'directory')
        <div class="space-y-6">
            @if(auth()->user()->hasEffectivePermission('personal.manage'))
                <form wire:submit="createStaff" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Registrar Personal / Profesora (Correo y Atestados Obligatorios para Docentes)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Perfil de Personal *</label>
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
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Especialidad / Grado</label>
                            <input wire:model="specialty" type="text" class="cecap-input" placeholder="Ej. Contabilidad / Gastronomía">
                        </div>

                        <div class="md:col-span-3 grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">
                                    Documento de Identificación *
                                    <span class="block text-[11px] font-normal text-slate-500">Cédula / DIMEX / Pasaporte (PDF/Imagen máx 25MB)</span>
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
                                    <span class="block text-[11px] font-normal text-slate-500">Títulos / certificaciones (PDF/Imagen máx 25MB)</span>
                                </label>
                                <input wire:model="credentials_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                <div wire:loading wire:target="credentials_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo atestados...</div>
                                @if($credentials_file)
                                    <div wire:loading.remove wire:target="credentials_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $credentials_file->getClientOriginalName() }}</div>
                                @endif
                                @error('credentials_file') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">
                                    Contrato Existente / Ya Firmado <span class="text-slate-500 font-normal">(opcional)</span>
                                    <span class="block text-[11px] font-normal text-slate-500">Archivo firmado o vigente (PDF/Imagen máx 25MB)</span>
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
                    <button type="submit" wire:loading.attr="disabled" wire:target="identification_file,credentials_file,signed_contract_file,createStaff" class="cecap-btn-primary disabled:opacity-60">
                        <span wire:loading.remove wire:target="identification_file,credentials_file,signed_contract_file,createStaff">Guardar Colaboradora</span>
                        <span wire:loading wire:target="identification_file,credentials_file,signed_contract_file,createStaff">Procesando documentos...</span>
                    </button>
                </form>
            @endif

            <div class="cecap-card overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Expedientes de Personal</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Colaboradora / Identificación</th><th class="py-2 pr-3">Tipo / Régimen CR</th><th class="py-2 pr-3">Contacto</th><th class="py-2">Documentos del Expediente</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($staffMembers as $st)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $st->staff_code }}</td>
                                <td class="py-2.5 pr-3 font-semibold">
                                    {{ $st->full_name }}
                                    <div class="text-xs font-normal text-slate-500">{{ $idTypes[$st->identification_type] ?? $st->identification_type }}: <span class="font-mono">{{ $st->identification_number }}</span></div>
                                    @if($st->birth_date)
                                        <div class="text-[11px] font-normal text-slate-500">Nacimiento: {{ $st->birth_date->format('d/m/Y') }} ({{ $st->birth_date->age }} años)</div>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 text-xs">
                                    <span class="cecap-badge bg-brand-100 text-brand-900">{{ $st->staff_role_type }}</span>
                                    <div class="mt-1 font-semibold">{{ $st->hiring_regime === 'planilla_laboral' ? 'Planilla Laboral CR' : 'Servicios Profesionales CR' }}</div>
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
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Sin personal registrado.</td></tr>
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
                                @foreach($staffMembers as $m)<option value="{{ $m->id }}">{{ $m->full_name }} ({{ $m->staff_role_type }})</option>@endforeach
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
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Adjuntar Copia Ya Firmada (opcional — PDF/JPG/PNG/WebP máx 25MB)</label>
                                <input wire:model="contract_pdf_file" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                                <div wire:loading wire:target="contract_pdf_file" class="mt-1 text-xs font-semibold text-brand-800">Subiendo contrato...</div>
                                @if($contract_pdf_file)
                                    <div wire:loading.remove wire:target="contract_pdf_file" class="mt-1 text-xs font-semibold text-emerald-700">✓ Listo: {{ $contract_pdf_file->getClientOriginalName() }}</div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Archivo del Contrato Existente / Ya Firmado (PDF o Imagen máx 25MB) *</label>
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
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Archivo de Contrato Firmado (PDF/Imagen máx 25MB) *</label>
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
