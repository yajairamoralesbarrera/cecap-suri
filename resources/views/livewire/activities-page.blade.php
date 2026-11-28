<div class="space-y-6">
    @if($feedback)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ $feedback }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @if(auth()->user()->hasEffectivePermission('actividades.manage'))
            <form wire:submit="createWorkshop" class="cecap-card space-y-3">
                <h2 class="text-base font-bold text-brand-950">Nuevo Taller / Actividad Institucional</h2>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Título *</label>
                    <input wire:model="title" type="text" required class="cecap-input" placeholder="Taller de Finanzas Personales">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Tipo</label>
                    <select wire:model="workshop_type" class="cecap-input">
                        @foreach($workshopTypes as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Facilitadora *</label>
                    <input wire:model="facilitator_name" type="text" required class="cecap-input">
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div><label class="block text-xs font-semibold mb-1">Fecha *</label><input wire:model="event_date" type="date" required class="cecap-input"></div>
                    <div><label class="block text-xs font-semibold mb-1">Inicio *</label><input wire:model="start_time" type="time" required class="cecap-input"></div>
                    <div><label class="block text-xs font-semibold mb-1">Fin *</label><input wire:model="end_time" type="time" required class="cecap-input"></div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div><label class="block text-xs font-semibold mb-1">Lugar</label><input wire:model="location" type="text" class="cecap-input"></div>
                    <div><label class="block text-xs font-semibold mb-1">Cupo Máx.</label><input wire:model="max_capacity" type="number" min="1" class="cecap-input"></div>
                    <div><label class="block text-xs font-semibold mb-1">Costo (₡ CRC)</label><input wire:model="cost" type="number" step="0.01" min="0" class="cecap-input" placeholder="0 = Gratuito"></div>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 space-y-1">
                    <label class="block text-xs font-semibold text-emerald-950">Equivalencia en ausencias de Habilidades Blandas</label>
                    <input wire:model="soft_skills_absence_credits" type="number" min="0" max="20" class="cecap-input bg-white">
                    <p class="text-[11px] text-emerald-800">0 = no aplica. Si coloca 1, 2, etc., asistir a este taller/seminario compensa esa cantidad de ausencias en Habilidades Blandas.</p>
                </div>
                <button type="submit" class="cecap-btn-primary w-full">Programar Taller</button>
            </form>
        @endif

        <div class="cecap-card lg:col-span-2 space-y-4">
            <h2 class="text-base font-bold text-slate-900">Agenda de Talleres y Actividades</h2>
            <div class="space-y-2.5">
                @forelse($workshops as $w)
                    <div class="rounded-xl border border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="font-bold text-slate-900">{{ $w->code }} — {{ $w->title }}</div>
                            <div class="text-xs text-slate-600">{{ $w->event_date?->format('d/m/Y') }} · {{ $w->start_time }} - {{ $w->end_time }} · {{ $w->facilitator_name }} · Participantes: {{ $w->participants_count }}/{{ $w->max_capacity }}</div>
                            <div class="flex flex-wrap gap-1.5">
                                @if((float) $w->cost > 0)
                                    <span class="cecap-badge bg-amber-100 text-amber-950">Costo: ₡{{ number_format((float) $w->cost, 2) }}</span>
                                @else
                                    <span class="cecap-badge bg-slate-100 text-slate-700">Gratuito</span>
                                @endif
                                @if((int) $w->soft_skills_absence_credits > 0)
                                    <span class="cecap-badge bg-emerald-100 text-emerald-900">
                                        Equivale a {{ $w->soft_skills_absence_credits }} {{ $w->soft_skills_absence_credits === 1 ? 'ausencia' : 'ausencias' }} en Habilidades Blandas
                                    </span>
                                @endif
                            </div>
                        </div>
                        <button type="button" wire:click="selectWorkshop({{ $w->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Ver Participantes</button>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">Sin talleres programados.</p>
                @endforelse
            </div>

            @if($selectedWorkshop)
                <div class="border-t border-slate-200 pt-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-bold text-brand-950">Participantes — {{ $selectedWorkshop->title }}</h3>
                        @if(auth()->user()->hasEffectivePermission('actividades.manage'))
                            <form wire:submit="updateSelectedWorkshopCredits" class="flex flex-wrap items-center gap-2 text-xs">
                                <label class="font-semibold text-slate-600">Costo ₡:</label>
                                <input wire:model="edit_workshop_cost" type="number" step="0.01" min="0" class="cecap-input !min-h-8 !w-28 !py-1 text-xs">
                                <label class="font-semibold text-slate-600">Ausencias HB:</label>
                                <input wire:model="edit_workshop_credits" type="number" min="0" max="20" class="cecap-input !min-h-8 !w-16 !py-1 text-xs">
                                <button type="submit" class="cecap-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">Guardar</button>
                            </form>
                        @endif
                    </div>
                    @if(auth()->user()->hasEffectivePermission('actividades.manage'))
                        <form wire:submit="addParticipant" class="rounded-xl border border-slate-200 bg-slate-50/70 p-3 space-y-2">
                            @if($participant_is_imas)
                                <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-900">
                                    ✓ Alumna con patrocinador IMAS detectado — Método de cancelación asignado automáticamente: <strong>IMAS (Patrocinador)</strong>.
                                </div>
                            @endif
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                <select wire:model.live="participant_student_id" class="cecap-input bg-white">
                                    <option value="">Alumna registrada (opc.)</option>
                                    @foreach($students as $st)<option value="{{ $st->id }}">{{ $st->full_name }} ({{ $st->identification_number }})</option>@endforeach
                                </select>
                                <input wire:model="participant_name" type="text" required class="cecap-input bg-white" placeholder="Nombre completo *">
                                <input wire:model="participant_id_num" type="text" class="cecap-input bg-white" placeholder="Cédula / DIMEX">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-center">
                                <select wire:model="participant_payment_method" class="cecap-input bg-white" @disabled($participant_is_imas)>
                                    <option value="exento_gratuito">Exento / Actividad Gratuita</option>
                                    @foreach($paymentMethods as $code => $label)
                                        <option value="{{ $code }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input wire:model="participant_payment_reference" type="text" class="cecap-input bg-white" placeholder="Referencia de pago / oficio IMAS (opc.)">
                                <button type="submit" class="cecap-btn-primary">Inscribir Participante</button>
                            </div>
                        </form>
                    @endif
                    <div class="divide-y divide-slate-100">
                        @foreach($selectedWorkshop->participants as $p)
                            <div class="py-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                                <div>
                                    <strong>{{ $p->participant_name }}</strong>
                                    <span class="text-xs text-slate-500">{{ $p->identification_number }}</span>
                                    <div class="text-xs text-slate-600 flex flex-wrap items-center gap-1.5 mt-0.5">
                                        @if((float) $p->amount_charged > 0)
                                            <span>Monto: <strong>₡{{ number_format((float) $p->amount_charged, 2) }}</strong></span>
                                        @else
                                            <span>Gratuito</span>
                                        @endif
                                        <span>· Método:</span>
                                        @if($p->payment_method === 'imas')
                                            <span class="cecap-badge bg-emerald-100 text-emerald-900">IMAS (Patrocinador)</span>
                                        @elseif($p->payment_method === 'exento_gratuito' || ! $p->payment_method)
                                            <span class="cecap-badge bg-slate-100 text-slate-700">Exento / Gratuito</span>
                                        @else
                                            <span class="cecap-badge bg-sky-100 text-sky-900">{{ $paymentMethods[$p->payment_method] ?? $p->payment_method }}</span>
                                        @endif
                                        @if($p->payment_reference)
                                            <span class="text-[11px] text-slate-500">Ref: {{ $p->payment_reference }}</span>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" wire:click="toggleAttendance({{ $p->id }})" class="cecap-badge cursor-pointer {{ $p->attended ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-700' }}">
                                    {{ $p->attended ? 'Asistió' : 'Pendiente' }}
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
