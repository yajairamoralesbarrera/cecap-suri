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
        <button type="button" wire:click="setTab('cashier')" class="{{ $tab === 'cashier' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Caja y Abonos</button>
        <button type="button" wire:click="setTab('scholarships')" class="{{ $tab === 'scholarships' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Becas ({{ $scholarships->count() }})</button>
        <button type="button" wire:click="setTab('payers')" class="{{ $tab === 'payers' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Pagadores / Patrocinantes ({{ $payers->count() }})</button>
        <button type="button" wire:click="setTab('receipts')" class="{{ $tab === 'receipts' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Recibos y Reversos ({{ $receipts->count() }})</button>
        <button type="button" wire:click="setTab('overdue')" class="{{ $tab === 'overdue' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Morosidad ({{ $overdueCharges->count() }})</button>
    </div>

    {{-- TAB: CAJA --}}
    @if($tab === 'cashier')
        <div class="cecap-card space-y-4">
            <h2 class="text-base font-bold text-brand-950">Caja — Abonos Parciales y Múltiples (Emisión REC-YYYY-NNNNN)</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Alumna *</label>
                    <select wire:model.live="pay_student_id" class="cecap-input">
                        <option value="">-- Seleccione alumna --</option>
                        @foreach($students as $st)<option value="{{ $st->id }}">{{ $st->full_name }} ({{ $st->identification_number }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Pagador / Patrocinante</label>
                    <select wire:model="pay_payer_id" class="cecap-input">
                        <option value="">Propia alumna</option>
                        @foreach($payers as $p)<option value="{{ $p->id }}">{{ $p->full_name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Método de Pago *</label>
                    <select wire:model="pay_method" class="cecap-input">
                        @foreach($paymentMethods as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Referencia / Comprobante</label>
                    <input wire:model="pay_reference" type="text" class="cecap-input" placeholder="N.° SINPE / Transferencia">
                </div>
            </div>

            @if($pay_student_id)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                            <tr><th class="py-2 pr-3">Concepto</th><th class="py-2 pr-3">Vence</th><th class="py-2 pr-3">Bruto</th><th class="py-2 pr-3">Beca</th><th class="py-2 pr-3">Saldo</th><th class="py-2">Abono a Aplicar (CRC)</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($studentCharges as $ch)
                                <tr>
                                    <td class="py-2 pr-3 font-semibold">{{ $ch->concept }}</td>
                                    <td class="py-2 pr-3 text-xs">{{ $ch->due_date?->format('d/m/Y') }}</td>
                                    <td class="py-2 pr-3">₡{{ number_format((float) $ch->gross_amount, 2) }}</td>
                                    <td class="py-2 pr-3 text-emerald-700">-₡{{ number_format((float) $ch->scholarship_discount, 2) }}</td>
                                    <td class="py-2 pr-3 font-bold">₡{{ number_format($ch->balance, 2) }}</td>
                                    <td class="py-2 w-44">
                                        <input wire:model="charge_allocations.{{ $ch->id }}" type="number" step="0.01" min="0" max="{{ $ch->balance }}" class="cecap-input">
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-4 text-center text-slate-500">Esta alumna no posee saldos pendientes.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($studentCharges->isNotEmpty() && auth()->user()->hasEffectivePermission('finanzas.payments.collect'))
                    <button type="button" wire:click="collectPayment" class="cecap-btn-primary">Registrar Pago y Emitir Recibo REC</button>
                @endif
            @endif
        </div>
    @endif

    {{-- TAB: BECAS --}}
    @if($tab === 'scholarships')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if(auth()->user()->hasEffectivePermission('finanzas.scholarships.manage'))
                <form wire:submit="applyScholarship" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Aplicar Beca (Sin Alterar Monto Bruto)</h2>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Matrícula *</label>
                        <select wire:model="schol_enrollment_id" required class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($enrollments as $enr)
                                <option value="{{ $enr->id }}">{{ $enr->enrollment_code }} — {{ $enr->student?->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre de la Beca *</label>
                        <input wire:model="schol_name" type="text" required class="cecap-input">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Tipo *</label>
                            <select wire:model="schol_type" class="cecap-input">
                                <option value="porcentaje">Porcentaje (0-100%)</option>
                                <option value="monto_fijo">Monto Fijo (CRC)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Valor *</label>
                            <input wire:model="schol_value" type="number" step="0.01" min="0" required class="cecap-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Patrocinante</label>
                        <select wire:model="schol_payer_id" class="cecap-input">
                            <option value="">Fondo Propio CeCap</option>
                            @foreach($payers as $p)<option value="{{ $p->id }}">{{ $p->full_name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Justificación</label>
                        <input wire:model="schol_reason" type="text" class="cecap-input">
                    </div>
                    <button type="submit" class="cecap-btn-primary w-full">Aplicar Beca</button>
                </form>
            @endif
            <div class="cecap-card lg:col-span-2 overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Becas Activas</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Matrícula</th><th class="py-2 pr-3">Alumna</th><th class="py-2 pr-3">Beca</th><th class="py-2">Descuento</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($scholarships as $sc)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs">{{ $sc->enrollment?->enrollment_code }}</td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $sc->enrollment?->student?->full_name }}</td>
                                <td class="py-2.5 pr-3">{{ $sc->name }}</td>
                                <td class="py-2.5 font-bold text-emerald-700">{{ $sc->discount_type === 'porcentaje' ? $sc->discount_value.'%' : '₡'.number_format((float) $sc->discount_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin becas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: PAGADORES --}}
    @if($tab === 'payers')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form wire:submit="createPayer" class="cecap-card space-y-4">
                <h2 class="text-base font-bold text-brand-950">Nuevo Pagador / Patrocinante</h2>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Tipo *</label>
                    <select wire:model="payer_type" class="cecap-input">
                        @foreach($payerTypes as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre / Razón Social *</label>
                    <input wire:model="payer_name" type="text" required class="cecap-input">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Cédula Física / Jurídica</label>
                    <input wire:model="payer_id_num" type="text" class="cecap-input">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Correo / Teléfono</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input wire:model="payer_email" type="email" class="cecap-input" placeholder="Correo">
                        <input wire:model="payer_phone" type="tel" class="cecap-input" placeholder="Teléfono">
                    </div>
                </div>
                <button type="submit" class="cecap-btn-primary w-full">Guardar Patrocinante</button>
            </form>
            <div class="cecap-card lg:col-span-2 overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Pagadores y Patrocinantes</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Nombre</th><th class="py-2 pr-3">Tipo</th><th class="py-2">Identificación</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($payers as $p)
                            <tr><td class="py-2.5 pr-3 font-semibold">{{ $p->full_name }}</td><td class="py-2.5 pr-3 text-xs">{{ $payerTypes[$p->payer_type] ?? $p->payer_type }}</td><td class="py-2.5 text-xs">{{ $p->identification_number ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-slate-500">Sin pagadores externos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: RECIBOS Y REVERSOS --}}
    @if($tab === 'receipts')
        <div class="cecap-card overflow-x-auto">
            <h2 class="text-base font-bold text-slate-900 mb-3">Recibos Emitidos (REC-YYYY-NNNNN)</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr><th class="py-2 pr-3">Recibo</th><th class="py-2 pr-3">Alumna</th><th class="py-2 pr-3">Monto</th><th class="py-2 pr-3">Estado</th><th class="py-2">Acciones</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($receipts as $r)
                        <tr wire:key="receipt-row-{{ $r->id }}" class="{{ $reversingReceiptId === $r->id ? 'bg-rose-50/40' : '' }}">
                            <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $r->receipt_number }}<div class="text-[11px] text-slate-400">{{ $r->paid_at?->format('d/m/Y H:i') }}</div></td>
                            <td class="py-2.5 pr-3 font-semibold">{{ $r->student?->full_name }}</td>
                            <td class="py-2.5 pr-3 font-bold">₡{{ number_format((float) $r->total_amount, 2) }}</td>
                            <td class="py-2.5 pr-3"><span class="cecap-badge {{ $r->status === 'emitido' ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-800' }}">{{ strtoupper($r->status) }}</span></td>
                            <td class="py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button"
                                            data-pdf-modal-url="{{ route('pdf.recibo', $r) }}"
                                            data-pdf-modal-title="Recibo {{ $r->receipt_number }}"
                                            class="cecap-btn-secondary !min-h-9 !px-3 text-xs">
                                        Recibo PDF
                                    </button>
                                    @if($r->status === 'emitido' && (float) $r->total_amount > 0 && auth()->user()->hasEffectivePermission('finanzas.payments.reverse'))
                                        <button type="button" wire:click="startReverseReceipt({{ $r->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs text-rose-700">Reversar</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if($reversingReceiptId === $r->id)
                            <tr wire:key="receipt-reverse-{{ $r->id }}">
                                <td colspan="5" class="py-3 px-1">
                                    <div class="rounded-2xl border-2 border-rose-300 bg-rose-50/80 p-4 space-y-3">
                                        <h3 class="text-sm font-bold text-rose-950">Anulación Exclusiva por Reverso Auditado — {{ $r->receipt_number }}</h3>
                                        <p class="text-xs text-rose-800">El recibo original nunca se borra. Se generará un asiento de reverso negativo en el libro mayor append-only.</p>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <input wire:model="reversal_reason" type="text" class="cecap-input flex-1" placeholder="Motivo formal del reverso *">
                                            <button type="button" wire:click="confirmReverseReceipt" class="cecap-btn-primary text-xs sm:text-sm">Aplicar Reverso</button>
                                            <button type="button" wire:click="cancelReverseReceipt" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">Sin recibos emitidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- TAB: MOROSIDAD --}}
    @if($tab === 'overdue')
        <div class="cecap-card overflow-x-auto">
            <h2 class="text-base font-bold text-slate-900 mb-3">Control de Morosidad (Cargos Vencidos)</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr><th class="py-2 pr-3">Alumna</th><th class="py-2 pr-3">Concepto</th><th class="py-2 pr-3">Vencimiento</th><th class="py-2">Saldo Pendiente</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($overdueCharges as $ov)
                        <tr>
                            <td class="py-2.5 pr-3 font-semibold">{{ $ov->student?->full_name }}<div class="text-xs text-slate-500">{{ $ov->student?->phone }}</div></td>
                            <td class="py-2.5 pr-3">{{ $ov->concept }}</td>
                            <td class="py-2.5 pr-3 text-rose-700 font-semibold">{{ $ov->due_date?->format('d/m/Y') }}</td>
                            <td class="py-2.5 font-bold text-rose-800">₡{{ number_format($ov->balance, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-emerald-700 font-medium">Excelente: no hay cargos vencidos en morosidad.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
