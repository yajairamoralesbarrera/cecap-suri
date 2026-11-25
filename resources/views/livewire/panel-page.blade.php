<div class="space-y-6">
    <div class="rounded-2xl bg-gradient-to-r from-brand-950 via-brand-900 to-brand-800 p-6 text-white shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-accent-400">CeCap — SURÍ Proyecto Educativo · Costa Rica</p>
                <h2 class="mt-1 text-2xl font-bold">Bienvenida, {{ $user->name }}</h2>
                <p class="mt-1 text-sm text-brand-100">Roles activos: {{ $user->role_labels ?: 'Usuario' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('inscripcion.publica') }}" class="cecap-btn-accent">Portal de Inscripción</a>
                @if($user->hasEffectivePermission('consultas.view'))
                    <a href="{{ route('consultas') }}" class="inline-flex min-h-11 items-center rounded-xl bg-white/15 px-4 text-sm font-semibold text-white hover:bg-white/25">Ventanilla 360°</a>
                @endif
            </div>
        </div>
    </div>

    @if(! $isAlumnaOnly)
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Alumnas Registradas</div>
                <div class="mt-2 text-2xl font-bold text-brand-950">{{ $stats['students'] }}</div>
            </div>
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Admisiones Pendientes</div>
                <div class="mt-2 text-2xl font-bold text-accent-600">{{ $stats['pending_admissions'] }}</div>
            </div>
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Matrículas Activas</div>
                <div class="mt-2 text-2xl font-bold text-emerald-700">{{ $stats['confirmed_enrollments'] }}</div>
            </div>
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Recaudación Mes (CRC)</div>
                <div class="mt-2 text-2xl font-bold text-brand-900">₡{{ number_format($stats['collected_month'], 0) }}</div>
            </div>
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Ediciones Abiertas/Curso</div>
                <div class="mt-2 text-2xl font-bold text-slate-800">{{ $stats['active_editions'] }}</div>
            </div>
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Cargos en Morosidad</div>
                <div class="mt-2 text-2xl font-bold text-rose-700">{{ $stats['overdue_charges'] }}</div>
            </div>
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Graduadas Emitidas</div>
                <div class="mt-2 text-2xl font-bold text-brand-800">{{ $stats['graduations'] }}</div>
            </div>
            <div class="cecap-card">
                <div class="text-xs font-semibold uppercase text-slate-500">Personal Activo</div>
                <div class="mt-2 text-2xl font-bold text-slate-800">{{ $stats['staff'] }}</div>
            </div>
        </div>
    @endif

    @if($teacherEditions->isNotEmpty())
        <div class="cecap-card space-y-3">
            <h3 class="text-base font-bold text-slate-900">Mis Grupos Asignados como Profesora</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($teacherEditions as $ed)
                    <div class="rounded-xl border border-slate-200 p-4 flex items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-slate-900">{{ $ed->name }}</div>
                            <div class="text-xs text-slate-500">{{ $ed->course?->name }} · {{ $ed->start_date?->format('d/m/Y') }}</div>
                        </div>
                        <a href="{{ route('seguimiento') }}" class="cecap-btn-secondary !px-3 text-xs">Seguimiento</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Portal de Alumna (visible para Alumna y también en escenario Multirrol ej. Profesora + Alumna) --}}
    @if($studentProfile)
        <div class="cecap-card space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-brand-950">Mi Portal de Alumna — {{ $studentProfile->student_code }}</h3>
                    <p class="text-xs text-slate-500">{{ $studentProfile->full_name }} · {{ $studentProfile->identification_number }}</p>
                </div>
                <button type="button"
                        data-pdf-modal-url="{{ route('pdf.habilidades', $studentProfile) }}"
                        data-pdf-modal-title="Constancia de Habilidades Blandas"
                        class="cecap-btn-secondary text-xs">
                    Ver Constancia Habilidades Blandas PDF
                </button>
            </div>

            <div class="space-y-3">
                <h4 class="text-sm font-bold text-slate-800">Mis Matrículas e Inscripciones</h4>
                @forelse($studentProfile->enrollments as $enr)
                    <div class="rounded-xl border border-slate-200 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-slate-900">{{ $enr->edition?->course?->name }} — {{ $enr->edition?->name }}</div>
                            <div class="text-xs text-slate-600 mt-0.5">
                                Código: <strong>{{ $enr->enrollment_code }}</strong> ·
                                Estado: <span class="uppercase font-semibold text-brand-900">{{ str_replace('_', ' ', $enr->status) }}</span> ·
                                Promedio: <strong>{{ number_format($enr->calculateFinalGrade(), 1) }}</strong> ·
                                Asistencia: <strong>{{ number_format($enr->calculateAttendancePercent(), 0) }}%</strong>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button"
                                    data-pdf-modal-url="{{ route('pdf.planilla', $enr) }}"
                                    data-pdf-modal-title="Planilla {{ $enr->enrollment_code }}"
                                    class="cecap-btn-primary !px-3 text-xs">
                                Ver / Imprimir Planilla PDF
                            </button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Aún no posee matrículas registradas. Puede iniciar una solicitud en el Portal de Inscripción.</p>
                @endforelse
            </div>

            @if($studentProfile->paymentReceipts->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="text-sm font-bold text-slate-800">Mis Recibos de Pago</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($studentProfile->paymentReceipts as $rec)
                            <div class="rounded-xl border border-slate-200 p-3 flex items-center justify-between gap-2 text-sm">
                                <div>
                                    <div class="font-bold">{{ $rec->receipt_number }}</div>
                                    <div class="text-xs text-slate-500">{{ $rec->paid_at?->format('d/m/Y') }} · ₡{{ number_format((float) $rec->total_amount, 2) }}</div>
                                </div>
                                <button type="button"
                                        data-pdf-modal-url="{{ route('pdf.recibo', $rec) }}"
                                        data-pdf-modal-title="Recibo {{ $rec->receipt_number }}"
                                        class="cecap-btn-secondary !px-3 text-xs">
                                    Ver Recibo
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
