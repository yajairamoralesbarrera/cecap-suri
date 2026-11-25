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
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block text-xs font-semibold mb-1">Lugar</label><input wire:model="location" type="text" class="cecap-input"></div>
                    <div><label class="block text-xs font-semibold mb-1">Cupo Máx.</label><input wire:model="max_capacity" type="number" min="1" class="cecap-input"></div>
                </div>
                <button type="submit" class="cecap-btn-primary w-full">Programar Taller</button>
            </form>
        @endif

        <div class="cecap-card lg:col-span-2 space-y-4">
            <h2 class="text-base font-bold text-slate-900">Agenda de Talleres y Actividades</h2>
            <div class="space-y-2.5">
                @forelse($workshops as $w)
                    <div class="rounded-xl border border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-slate-900">{{ $w->code }} — {{ $w->title }}</div>
                            <div class="text-xs text-slate-600">{{ $w->event_date?->format('d/m/Y') }} · {{ $w->start_time }} - {{ $w->end_time }} · {{ $w->facilitator_name }} · Participantes: {{ $w->participants_count }}/{{ $w->max_capacity }}</div>
                        </div>
                        <button type="button" wire:click="selectWorkshop({{ $w->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Ver Participantes</button>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 py-4 text-center">Sin talleres programados.</p>
                @endforelse
            </div>

            @if($selectedWorkshop)
                <div class="border-t border-slate-200 pt-4 space-y-3">
                    <h3 class="font-bold text-brand-950">Participantes — {{ $selectedWorkshop->title }}</h3>
                    @if(auth()->user()->hasEffectivePermission('actividades.manage'))
                        <form wire:submit="addParticipant" class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                            <select wire:model.live="participant_student_id" class="cecap-input">
                                <option value="">Alumna registrada (opc.)</option>
                                @foreach($students as $st)<option value="{{ $st->id }}">{{ $st->full_name }}</option>@endforeach
                            </select>
                            <input wire:model="participant_name" type="text" required class="cecap-input" placeholder="Nombre completo *">
                            <input wire:model="participant_id_num" type="text" class="cecap-input" placeholder="Cédula">
                            <button type="submit" class="cecap-btn-primary">Agregar</button>
                        </form>
                    @endif
                    <div class="divide-y divide-slate-100">
                        @foreach($selectedWorkshop->participants as $p)
                            <div class="py-2 flex items-center justify-between text-sm">
                                <span><strong>{{ $p->participant_name }}</strong> <span class="text-xs text-slate-500">{{ $p->identification_number }}</span></span>
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
