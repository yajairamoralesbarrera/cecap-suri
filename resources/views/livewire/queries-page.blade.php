<div class="space-y-6">
    <div class="cecap-card space-y-3">
        <h2 class="text-base font-bold text-brand-950">Expediente de Alumnas</h2>
        <input wire:model.live.debounce.250ms="search" type="search" class="cecap-input" placeholder="Buscar alumna por nombre, Cédula/DIMEX, correo, código de matrícula (MAT-...) o recibo (REC-...)...">
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="cecap-card space-y-2">
            <h3 class="text-sm font-bold text-slate-800">Alumnas ({{ $matches->count() }})</h3>
            <div class="divide-y divide-slate-100">
                @forelse($matches as $m)
                    <button type="button"
                            wire:click="selectStudent({{ $m->id }})"
                            class="w-full text-left py-2.5 px-2 rounded-xl transition cursor-pointer {{ $selected_student_id === $m->id ? 'bg-brand-50 border border-brand-200' : 'hover:bg-slate-50' }}">
                        <div class="font-bold text-sm text-slate-900">{{ $m->full_name }}</div>
                        <div class="text-xs text-slate-500">{{ $m->identification_number }} · {{ $m->student_code }}</div>
                    </button>
                @empty
                    <p class="py-4 text-xs text-slate-500 text-center">No se encontraron expedientes.</p>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-2 space-y-4">
            @if($selected)
                <div class="cecap-card space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 pb-3">
                        <div class="space-y-0.5">
                            <h3 class="text-lg font-bold text-brand-950">{{ $selected->full_name }}</h3>
                            <p class="text-xs text-slate-600">{{ $selected->student_code }} · {{ $selected->identification_number }} · {{ $selected->email }} · {{ $selected->phone }}</p>
                            @if($selected->birth_date)
                                <p class="text-xs text-slate-600">
                                    Fecha de nacimiento: <strong>{{ $selected->birth_date->format('d/m/Y') }}</strong> ({{ $selected->birth_date->age }} años{{ $selected->isMinor() ? ' · Menor de edad' : '' }})
                                </p>
                            @endif
                            <p class="text-xs text-slate-500">{{ collect([$selected->province?->name, $selected->canton?->name, $selected->district?->name])->filter()->implode(', ') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="cecap-badge {{ $selected->total_pending_balance > 0 ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900' }}">
                                Saldo pendiente: ₡{{ number_format($selected->total_pending_balance, 2) }}
                            </span>
                            <button type="button" data-pdf-modal-url="{{ route('pdf.habilidades', $selected) }}" data-pdf-modal-title="Constancia Habilidades Blandas" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Habilidades PDF</button>
                        </div>
                    </div>

                    @if($selected->guardian_full_name)
                        <div class="rounded-xl border border-amber-200 bg-amber-50/70 px-3 py-2 text-xs text-amber-950">
                            <div class="font-bold">Representante Legal{{ $selected->guardian_relationship ? ' ('.$selected->guardian_relationship.')' : '' }}</div>
                            <div>
                                {{ $selected->guardian_full_name }}
                                @if($selected->guardian_identification_number) · Cédula/DIMEX: {{ $selected->guardian_identification_number }} @endif
                                @if($selected->guardian_phone) · Tel: {{ $selected->guardian_phone }} @endif
                                @if($selected->guardian_email) · {{ $selected->guardian_email }} @endif
                            </div>
                        </div>
                    @endif

                    <div class="space-y-2">
                        <h4 class="text-sm font-bold text-slate-800">Matrículas y Rendimiento</h4>
                        @forelse($selected->enrollments as $enr)
                            <div class="rounded-xl border border-slate-200 p-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div>
                                    <div class="font-bold text-slate-900">{{ $enr->enrollment_code }} — {{ $enr->edition?->course?->name }}</div>
                                    <div class="text-slate-600">Estado: {{ strtoupper($enr->status) }} · Nota: {{ number_format($enr->calculateFinalGrade(), 1) }} · Asistencia: {{ number_format($enr->calculateAttendancePercent(), 0) }}%</div>
                                </div>
                                <button type="button" data-pdf-modal-url="{{ route('pdf.planilla', $enr) }}" data-pdf-modal-title="Planilla {{ $enr->enrollment_code }}" class="cecap-btn-primary !min-h-9 !px-3 text-xs">Planilla PDF</button>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500">Sin matrículas.</p>
                        @endforelse
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <h4 class="text-sm font-bold text-slate-800">Recibos de Pago</h4>
                            @forelse($selected->paymentReceipts as $rec)
                                <div class="rounded-lg bg-slate-50 p-2.5 flex items-center justify-between text-xs">
                                    <span><strong>{{ $rec->receipt_number }}</strong> · ₡{{ number_format((float) $rec->total_amount, 2) }}</span>
                                    <button type="button" data-pdf-modal-url="{{ route('pdf.recibo', $rec) }}" data-pdf-modal-title="Recibo {{ $rec->receipt_number }}" class="text-brand-800 font-semibold underline cursor-pointer">PDF</button>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500">Sin recibos.</p>
                            @endforelse
                        </div>

                        <div class="space-y-2">
                            <h4 class="text-sm font-bold text-slate-800">Diplomas y Adjuntos Privados</h4>
                            @foreach($selected->graduations as $grad)
                                <div class="rounded-lg bg-emerald-50 p-2.5 flex items-center justify-between text-xs">
                                    <span><strong>{{ $grad->act_number }}</strong> (T:{{ $grad->tomo }} F:{{ $grad->folio }} A:{{ $grad->asiento }})</span>
                                    <button type="button" data-pdf-modal-url="{{ route('pdf.diploma', $grad) }}" data-pdf-modal-title="Diploma {{ $grad->act_number }}" class="text-emerald-800 font-semibold underline cursor-pointer">Diploma</button>
                                </div>
                            @endforeach
                            @foreach($selected->attachments->where('status', 'activo') as $att)
                                <div class="rounded-lg bg-slate-50 p-2.5 flex items-center justify-between text-xs">
                                    <span>{{ $att->document_type_label }} v{{ $att->version }}</span>
                                    <a href="{{ route('attachments.download', $att->uuid) }}" class="text-brand-800 font-semibold underline">Descargar</a>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    @if($selected->lifecycleEvents->isNotEmpty())
                        <div class="space-y-1.5 border-t border-slate-200 pt-3">
                            <h4 class="text-sm font-bold text-slate-800">Bitácora Inmutable del Ciclo de Vida</h4>
                            @foreach($selected->lifecycleEvents->take(8) as $ev)
                                <div class="text-xs text-slate-600">
                                    <span class="font-mono text-slate-400">{{ $ev->created_at?->format('d/m/Y H:i') }}</span> — {{ $ev->description }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @else
                <div class="cecap-card py-12 text-center text-sm text-slate-500">
                    Seleccione una alumna de la lista para visualizar su expediente completo.
                </div>
            @endif
        </div>
    </div>
</div>
