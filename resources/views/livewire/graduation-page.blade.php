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
        <button type="button" wire:click="setTab('issue')" class="{{ $tab === 'issue' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Elegibilidad y Emisión ({{ $graduations->count() }})</button>
        <button type="button" wire:click="setTab('templates')" class="{{ $tab === 'templates' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Plantillas Gráficas de Diploma ({{ $templates->count() }})</button>
    </div>

    @if($tab === 'issue')
        @if(auth()->user()->hasEffectivePermission('graduacion.manage'))
            <form wire:submit="issue" class="cecap-card space-y-4">
                <h2 class="text-base font-bold text-brand-950">Evaluación Automática y Emisión de Acta + Diploma QR</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Alumna / Matrícula Candidata *</label>
                        <select wire:model.live="enrollment_id" required class="cecap-input">
                            <option value="">-- Seleccione matrícula --</option>
                            @foreach($candidates as $cand)
                                <option value="{{ $cand->id }}">{{ $cand->enrollment_code }} — {{ $cand->student?->full_name }} ({{ $cand->edition?->course?->name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Plantilla de Diploma</label>
                        <select wire:model="template_id" class="cecap-input">
                            @foreach($templates as $tpl)<option value="{{ $tpl->id }}">{{ $tpl->name }}</option>@endforeach
                        </select>
                    </div>
                </div>

                @if($selectedEval)
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                        <div>
                            <div class="text-xs uppercase text-slate-500 font-semibold">Nota Final</div>
                            <div class="font-bold {{ $selectedEval['grade_passed'] ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ number_format($selectedEval['final_grade'], 2) }} / {{ $selectedEval['min_grade'] }} ({{ $selectedEval['grade_passed'] ? 'Aprobada' : 'Insuficiente' }})
                            </div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-slate-500 font-semibold">Asistencia</div>
                            <div class="font-bold {{ $selectedEval['attendance_passed'] ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ number_format($selectedEval['attendance_percent'], 1) }}% / {{ $selectedEval['min_attendance'] }}%
                            </div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-slate-500 font-semibold">Solvencia Financiera</div>
                            <div class="font-bold {{ $selectedEval['solvency_passed'] ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $selectedEval['is_solvent'] ? 'Solvente (₡0)' : 'Saldo: ₡'.number_format($selectedEval['pending_balance'], 2) }}
                            </div>
                        </div>
                    </div>

                    @if($selectedEval['needs_solvency_exception'])
                        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-3">
                            <div class="text-sm font-bold text-amber-950">Solvencia pendiente: requiere excepción exclusiva de Dirección / Super Admin.</div>
                            @if($canAuthorizeSolvencyException)
                                <label class="inline-flex items-center gap-2 text-sm font-semibold cursor-pointer">
                                    <input type="checkbox" wire:model.live="solvency_exception" class="h-4 w-4 rounded text-accent-600">
                                    <span>Autorizar excepción de solvencia financiera (Dirección)</span>
                                </label>
                                @if($solvency_exception)
                                    <input wire:model="solvency_exception_reason" type="text" class="cecap-input" placeholder="Motivo formal de la excepción de Dirección *">
                                @endif
                            @endif
                        </div>
                    @endif
                @endif

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Tomo *</label>
                        <input wire:model="tomo" type="text" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Folio *</label>
                        <input wire:model="folio" type="text" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Asiento (Único) *</label>
                        <input wire:model="asiento" type="text" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Fecha Graduación *</label>
                        <input wire:model="graduation_date" type="date" required class="cecap-input">
                    </div>
                </div>

                <button type="submit" class="cecap-btn-primary">Emitir Acta de Grado y Diploma QR</button>
            </form>
        @endif

        <div class="cecap-card overflow-x-auto">
            <h2 class="text-base font-bold text-slate-900 mb-3">Libro Oficial de Graduaciones</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr><th class="py-2 pr-3">Acta / Libro</th><th class="py-2 pr-3">Graduada</th><th class="py-2 pr-3">Programa</th><th class="py-2">Documentos PDF y QR</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($graduations as $grad)
                        <tr>
                            <td class="py-2.5 pr-3 font-mono text-xs font-bold">
                                {{ $grad->act_number }}
                                <div class="text-slate-500 font-sans">T:{{ $grad->tomo }} F:{{ $grad->folio }} A:{{ $grad->asiento }}</div>
                            </td>
                            <td class="py-2.5 pr-3 font-semibold">{{ $grad->student?->full_name }}<div class="text-xs text-slate-500">Nota: {{ $grad->final_grade }} · Asist: {{ $grad->attendance_percent }}%</div></td>
                            <td class="py-2.5 pr-3 text-xs">{{ $grad->enrollment?->edition?->course?->name }}</td>
                            <td class="py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" data-pdf-modal-url="{{ route('pdf.acta', $grad) }}" data-pdf-modal-title="Acta {{ $grad->act_number }}" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Acta PDF</button>
                                    <button type="button" data-pdf-modal-url="{{ route('pdf.diploma', $grad) }}" data-pdf-modal-title="Diploma {{ $grad->act_number }}" class="cecap-btn-primary !min-h-9 !px-3 text-xs">Diploma QR PDF</button>
                                    <a href="{{ route('diploma.verify', $grad->verification_token) }}" target="_blank" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Verificar QR</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin graduaciones emitidas aún.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if($tab === 'templates')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form wire:submit="createTemplate" class="cecap-card space-y-3">
                <h2 class="text-base font-bold text-brand-950">Nueva Plantilla Gráfica</h2>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre *</label>
                    <input wire:model="tpl_name" type="text" required class="cecap-input">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Título del Diploma *</label>
                    <input wire:model="tpl_header" type="text" required class="cecap-input">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="block text-xs font-semibold mb-1">Color Primario</label><input wire:model="tpl_primary_color" type="color" class="cecap-input !p-1"></div>
                    <div><label class="block text-xs font-semibold mb-1">Color Acento</label><input wire:model="tpl_accent_color" type="color" class="cecap-input !p-1"></div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Cuerpo (placeholders: &#123;&#123;student_name&#125;&#125;, &#123;&#123;course_name&#125;&#125;, &#123;&#123;final_grade&#125;&#125;)</label>
                    <textarea wire:model="tpl_body" rows="3" required class="cecap-input"></textarea>
                </div>
                <button type="submit" class="cecap-btn-primary w-full">Guardar Plantilla</button>
            </form>
            <div class="cecap-card lg:col-span-2 space-y-3">
                <h2 class="text-base font-bold text-slate-900">Plantillas Activas</h2>
                @foreach($templates as $tpl)
                    <div class="rounded-xl border border-slate-200 p-4 flex items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-slate-900">{{ $tpl->name }}</div>
                            <div class="text-xs text-slate-500">{{ $tpl->header_title }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-6 w-6 rounded-full border" style="background: {{ $tpl->primary_color }}"></span>
                            <span class="h-6 w-6 rounded-full border" style="background: {{ $tpl->accent_color }}"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
