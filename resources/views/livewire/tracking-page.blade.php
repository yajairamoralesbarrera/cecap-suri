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
        <button type="button" wire:click="setTab('attendance')" class="{{ $tab === 'attendance' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Asistencia Móvil Versionada</button>
        <button type="button" wire:click="setTab('attendance_report')" class="{{ $tab === 'attendance_report' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Consulta de Asistencias</button>
        <button type="button" wire:click="setTab('grades')" class="{{ $tab === 'grades' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Calificaciones y Rectificación</button>
        <button type="button" wire:click="setTab('soft_skills')" class="{{ $tab === 'soft_skills' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Habilidades Blandas Permanentes</button>
    </div>

    {{-- TAB: ASISTENCIA --}}
    @if($tab === 'attendance')
        <div class="cecap-card space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Cohorte / Grupo</label>
                    <select wire:model.live="selected_edition_id" class="cecap-input">
                        <option value="">-- Seleccione cohorte --</option>
                        @foreach($editions as $ed)
                            <option value="{{ $ed->id }}">{{ $ed->course?->name }} — {{ $ed->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Materia (si aplica)</label>
                    <select wire:model.live="attendance_subject_id" class="cecap-input">
                        <option value="">-- General del curso --</option>
                        @foreach($selectedEdition?->course?->subjects ?? [] as $sub)
                            <option value="{{ $sub->id }}">{{ $sub->code }} — {{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Fecha de Sesión</label>
                    <input wire:model.live="session_date" type="date" class="cecap-input">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Motivo si rectifica versión</label>
                    <input wire:model="attendance_rectification_reason" type="text" class="cecap-input" placeholder="Ej. Justificación médica recibida">
                </div>
            </div>

            @if($selected_edition_id)
                <div class="space-y-2.5">
                    @forelse($editionEnrollments as $enr)
                        @php $curStatus = $attendance_statuses[$enr->id] ?? 'presente'; @endphp
                        <div class="rounded-xl border border-slate-200 p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <div class="font-bold text-slate-900">{{ $enr->student?->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $enr->enrollment_code }} · Asistencia acumulada: {{ number_format($enr->calculateAttendancePercent(), 0) }}%</div>
                            </div>
                            <div class="grid grid-cols-2 sm:flex gap-1.5">
                                @foreach(['presente' => 'Presente', 'tardia' => 'Tardía', 'justificada' => 'Justificada', 'ausente' => 'Ausente'] as $code => $label)
                                    <button type="button"
                                            wire:click="setAttendanceStatus({{ $enr->id }}, '{{ $code }}')"
                                            class="min-h-11 rounded-xl px-3 text-xs font-semibold border transition cursor-pointer {{ $curStatus === $code ? 'bg-brand-900 text-white border-brand-900' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50' }}">
                                        {{ $label }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 py-4 text-center">No hay alumnas confirmadas en esta cohorte.</p>
                    @endforelse
                </div>

                @if($editionEnrollments->isNotEmpty() && auth()->user()->hasEffectivePermission('seguimiento.attendance.manage'))
                    <button type="button" wire:click="saveAttendance" class="cecap-btn-primary">Guardar Asistencia Versionada</button>
                @endif
            @endif
        </div>
    @endif

    {{-- TAB: CONSULTA DE ASISTENCIAS (POR CURSO, MATERIA Y ESTUDIANTE) --}}
    @if($tab === 'attendance_report')
        <div class="space-y-6">
            <div class="cecap-card space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-brand-950">Consulta de Asistencias por Curso, Materia y Estudiante</h2>
                    @if($report_course_id || $report_edition_id || $report_subject_id !== '' || $report_student_id || trim($report_student_search) !== '' || $report_status !== '' || $report_date_from !== '' || $report_date_to !== '')
                        <button type="button" wire:click="clearAttendanceReportFilters" class="cecap-btn-secondary !min-h-8 !px-3 !py-1 text-xs">Limpiar filtros</button>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Curso</label>
                        <select wire:model.live="report_course_id" class="cecap-input bg-white">
                            <option value="">Todos los cursos</option>
                            @foreach($reportCourses as $course)
                                <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Materia</label>
                        <select wire:model.live="report_subject_id" class="cecap-input bg-white">
                            <option value="">Todas las materias</option>
                            <option value="__general__">General del curso (sin materia)</option>
                            @foreach($reportSubjects as $sub)
                                <option value="{{ $sub->id }}">
                                    {{ $sub->code }} — {{ $sub->name }}@if(!$report_course_id && $sub->course) ({{ $sub->course->name }})@endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Cohorte / Edición</label>
                        <select wire:model.live="report_edition_id" class="cecap-input bg-white">
                            <option value="">Todas las cohortes</option>
                            @foreach($reportEditions as $ed)
                                <option value="{{ $ed->id }}">{{ $ed->course?->name }} — {{ $ed->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Estudiante (seleccionar)</label>
                        <select wire:model.live="report_student_id" class="cecap-input bg-white">
                            <option value="">Todas las estudiantes</option>
                            @foreach($students as $st)
                                <option value="{{ $st->id }}">{{ $st->full_name }} ({{ $st->student_code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Buscar estudiante (nombre, cédula o código)</label>
                        <input wire:model.live.debounce.300ms="report_student_search" type="text" class="cecap-input bg-white" placeholder="Ej. María, 1-1234-5678, ALU-2026...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Estado</label>
                        <select wire:model.live="report_status" class="cecap-input bg-white">
                            <option value="">Todos los estados</option>
                            @foreach($attendanceStatusLabels as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Desde</label>
                            <input wire:model.live="report_date_from" type="date" class="cecap-input bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Hasta</label>
                            <input wire:model.live="report_date_to" type="date" class="cecap-input bg-white">
                        </div>
                    </div>
                </div>

                {{-- KPIs del resultado filtrado --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <div class="text-[11px] font-semibold uppercase text-slate-500">Sesiones</div>
                        <div class="mt-1 text-xl font-bold text-slate-900">{{ $reportSummary['total'] }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                        <div class="text-[11px] font-semibold uppercase text-emerald-700">Presentes</div>
                        <div class="mt-1 text-xl font-bold text-emerald-900">{{ $reportSummary['presente'] }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <div class="text-[11px] font-semibold uppercase text-amber-800">Tardías</div>
                        <div class="mt-1 text-xl font-bold text-amber-950">{{ $reportSummary['tardia'] }}</div>
                    </div>
                    <div class="rounded-xl border border-sky-200 bg-sky-50 p-3">
                        <div class="text-[11px] font-semibold uppercase text-sky-700">Justificadas</div>
                        <div class="mt-1 text-xl font-bold text-sky-900">{{ $reportSummary['justificada'] }}</div>
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50 p-3">
                        <div class="text-[11px] font-semibold uppercase text-rose-700">Ausentes</div>
                        <div class="mt-1 text-xl font-bold text-rose-900">{{ $reportSummary['ausente'] }}</div>
                    </div>
                    <div class="rounded-xl border border-brand-200 bg-brand-50 p-3">
                        <div class="text-[11px] font-semibold uppercase text-brand-800">% Asistencia</div>
                        <div class="mt-1 text-xl font-bold text-brand-950">{{ number_format($reportSummary['percent'], 1) }}%</div>
                    </div>
                </div>
            </div>

            {{-- Resumen agrupado por Estudiante / Curso / Materia --}}
            <div class="cecap-card overflow-x-auto">
                <h3 class="text-base font-bold text-slate-900 mb-3">Resumen por Estudiante, Curso y Materia ({{ $reportByStudent->count() }})</h3>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Estudiante</th>
                            <th class="py-2 pr-3">Curso / Cohorte</th>
                            <th class="py-2 pr-3">Materia</th>
                            <th class="py-2 pr-2 text-center">Pres.</th>
                            <th class="py-2 pr-2 text-center">Tard.</th>
                            <th class="py-2 pr-2 text-center">Just.</th>
                            <th class="py-2 pr-2 text-center">Aus.</th>
                            <th class="py-2 pr-2 text-center">Total</th>
                            <th class="py-2 text-right">% Asist.</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($reportByStudent as $row)
                            <tr>
                                <td class="py-2.5 pr-3 font-semibold">
                                    {{ $row['student']?->full_name }}
                                    <div class="text-xs font-normal text-slate-500">{{ $row['student']?->student_code }} · {{ $row['student']?->identification_number }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-xs">
                                    <div class="font-semibold text-slate-900">{{ $row['course_name'] }}</div>
                                    <div class="text-slate-500">{{ $row['edition_name'] }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-xs text-slate-700">{{ $row['subject_label'] }}</td>
                                <td class="py-2.5 pr-2 text-center text-xs font-semibold text-emerald-700">{{ $row['presente'] }}</td>
                                <td class="py-2.5 pr-2 text-center text-xs font-semibold text-amber-800">{{ $row['tardia'] }}</td>
                                <td class="py-2.5 pr-2 text-center text-xs font-semibold text-sky-700">{{ $row['justificada'] }}</td>
                                <td class="py-2.5 pr-2 text-center text-xs font-semibold text-rose-700">{{ $row['ausente'] }}</td>
                                <td class="py-2.5 pr-2 text-center text-xs font-bold text-slate-800">{{ $row['total'] }}</td>
                                <td class="py-2.5 text-right">
                                    <span class="cecap-badge {{ $row['passed'] ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-800' }}">
                                        {{ number_format($row['percent'], 1) }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-6 text-center text-slate-500">No hay registros de asistencia para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Detalle fecha por fecha --}}
            <div class="cecap-card overflow-x-auto">
                <h3 class="text-base font-bold text-slate-900 mb-3">Detalle de Asistencias Fecha por Fecha ({{ $reportRecords->count() }})</h3>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Fecha</th>
                            <th class="py-2 pr-3">Estudiante</th>
                            <th class="py-2 pr-3">Curso / Cohorte</th>
                            <th class="py-2 pr-3">Materia</th>
                            <th class="py-2 pr-3">Estado</th>
                            <th class="py-2">Registrado por / Rectificación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($reportRecords as $rec)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs font-semibold whitespace-nowrap">{{ $rec->session_date?->format('d/m/Y') }}</td>
                                <td class="py-2.5 pr-3 text-xs font-semibold">
                                    {{ $rec->student?->full_name }}
                                    <div class="font-normal text-slate-500">{{ $rec->student?->student_code }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-xs">
                                    <div class="font-semibold text-slate-900">{{ $rec->edition?->course?->name }}</div>
                                    <div class="text-slate-500">{{ $rec->edition?->name }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-xs text-slate-700">
                                    {{ $rec->subject ? ($rec->subject->code.' — '.$rec->subject->name) : 'General del curso' }}
                                </td>
                                <td class="py-2.5 pr-3 text-xs">
                                    <span class="cecap-badge {{ $rec->status === 'presente' ? 'bg-emerald-100 text-emerald-900' : ($rec->status === 'ausente' ? 'bg-rose-100 text-rose-800' : ($rec->status === 'tardia' ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-900')) }}">
                                        {{ $rec->status_label }}
                                    </span>
                                    @if($rec->version > 1)
                                        <span class="ml-1 text-[11px] font-semibold text-slate-500">v{{ $rec->version }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 text-xs text-slate-600">
                                    {{ $rec->recorder?->name ?? 'Sistema' }}
                                    @if($rec->rectification_reason)
                                        <div class="text-[11px] text-amber-800">Motivo: {{ $rec->rectification_reason }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">Sin sesiones registradas con esos filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: CALIFICACIONES Y RECTIFICACIÓN --}}
    @if($tab === 'grades')
        <div class="space-y-6">
            {{-- Selector de Cohorte --}}
            <div class="cecap-card space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">1. Seleccione la Cohorte / Grupo *</label>
                        <select wire:model.live="selected_edition_id" class="cecap-input">
                            <option value="">-- Seleccione cohorte --</option>
                            @foreach($editions as $ed)
                                <option value="{{ $ed->id }}">{{ $ed->course?->name }} — {{ $ed->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($selectedEdition)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                            <div><strong class="text-slate-900">{{ $selectedEdition->course?->name }}</strong> · {{ $selectedEdition->name }}</div>
                            <div>Alumnas inscritas: <strong>{{ $editionEnrollments->count() }}</strong> · Nota mínima: <strong>{{ number_format((float) ($selectedEdition->course?->min_passing_grade ?? 70), 0) }}</strong></div>
                        </div>
                    @endif
                </div>
            </div>

            @if($selected_edition_id)
                {{-- PASO 1: DEFINIR EVALUACIONES Y PORCENTAJES --}}
                @if(auth()->user()->hasEffectivePermission('seguimiento.grades.manage'))
                    <div class="cecap-card space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="text-base font-bold text-brand-950">Paso 1 — Plan de Evaluaciones y Porcentajes</h2>
                                <p class="text-xs text-slate-500">Defina primero las evaluaciones y el porcentaje de cada una (hasta 100%). Luego seleccione una evaluación para calificar a todas las alumnas.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="cecap-badge {{ abs($scopeWeightUsed - 100) < 0.01 ? 'bg-emerald-100 text-emerald-900' : 'bg-brand-100 text-brand-900' }}">
                                    Configurado: {{ number_format($scopeWeightUsed, 2) }}% / 100%
                                </span>
                                @if($scopeWeightRemaining > 0)
                                    <span class="cecap-badge bg-amber-100 text-amber-900">Disponible: {{ number_format($scopeWeightRemaining, 2) }}%</span>
                                @endif
                            </div>
                        </div>

                        <form wire:submit="createEvaluation" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Materia (si aplica)</label>
                                <select wire:model.live="eval_subject_id" class="cecap-input bg-white">
                                    <option value="">-- General del curso --</option>
                                    @foreach($selectedEdition?->course?->subjects ?? [] as $sub)
                                        <option value="{{ $sub->id }}">{{ $sub->code }} — {{ $sub->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre de la Evaluación *</label>
                                <input wire:model="eval_name" type="text" required class="cecap-input bg-white" placeholder="Ej. Examen Parcial 1, Proyecto Práctico">
                                @error('eval_name') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Porcentaje (%) *</label>
                                <input wire:model="eval_weight" type="number" step="0.5" min="0.5" max="100" required class="cecap-input bg-white">
                                @error('eval_weight') <div class="mt-1 text-xs font-semibold text-rose-700">{{ $message }}</div> @enderror
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-semibold text-slate-700 mb-1">Fecha (opcional)</label>
                                <input wire:model="eval_due_date" type="date" class="cecap-input bg-white">
                            </div>
                            <div class="md:col-span-12 flex justify-end">
                                <button type="submit" class="cecap-btn-primary !min-h-9 !px-4 text-xs">+ Agregar Evaluación</button>
                            </div>
                        </form>

                        {{-- Tabla de evaluaciones configuradas --}}
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left">
                                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="py-2 pr-3">Evaluación</th>
                                        <th class="py-2 pr-3">Materia</th>
                                        <th class="py-2 pr-3">Porcentaje</th>
                                        <th class="py-2 pr-3">Calificadas</th>
                                        <th class="py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($editionEvaluations as $ev)
                                        @php
                                            $gradedCount = $ev->gradeRecords->count();
                                            $totalRoster = $editionEnrollments->count();
                                            $isSelected = $selected_evaluation_id === $ev->id;
                                        @endphp
                                        <tr wire:key="eval-row-{{ $ev->id }}" class="{{ $isSelected ? 'bg-brand-50/70' : ($ev->is_active ? '' : 'opacity-60 bg-slate-50') }}">
                                            <td class="py-2.5 pr-3 font-semibold text-slate-900">
                                                {{ $ev->name }}
                                                @if($ev->due_date)
                                                    <div class="text-[11px] font-normal text-slate-500">Fecha: {{ $ev->due_date->format('d/m/Y') }}</div>
                                                @endif
                                                @if(! $ev->is_active)
                                                    <span class="cecap-badge bg-slate-200 text-slate-700 mt-1">Inactiva</span>
                                                @endif
                                            </td>
                                            <td class="py-2.5 pr-3 text-xs text-slate-700">
                                                {{ $ev->subject ? ($ev->subject->code.' — '.$ev->subject->name) : 'General del curso' }}
                                            </td>
                                            <td class="py-2.5 pr-3 font-bold text-brand-900">{{ number_format((float) $ev->weight_percent, 2) }}%</td>
                                            <td class="py-2.5 pr-3 text-xs">
                                                <span class="cecap-badge {{ $totalRoster > 0 && $gradedCount >= $totalRoster ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-700' }}">
                                                    {{ $gradedCount }} / {{ $totalRoster }} alumnas
                                                </span>
                                            </td>
                                            <td class="py-2.5">
                                                <div class="flex flex-wrap gap-1.5">
                                                    @if($ev->is_active)
                                                        <button type="button" wire:click="selectEvaluation({{ $ev->id }})" class="{{ $isSelected ? 'cecap-btn-primary' : 'cecap-btn-secondary' }} !min-h-8 !px-3 !py-1 text-xs">
                                                            {{ $isSelected ? 'Cargando Notas ✓' : 'Cargar Notas' }}
                                                        </button>
                                                    @endif
                                                    <button type="button" wire:click="toggleEvaluationActive({{ $ev->id }})" class="inline-flex min-h-8 items-center justify-center rounded-xl border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                                        {{ $ev->is_active ? 'Inactivar' : 'Reactivar' }}
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="py-4 text-center text-xs text-slate-500">Aún no hay evaluaciones configuradas para esta cohorte. Agregue la primera arriba.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- PASO 2: CARGAR NOTAS PARA TODAS LAS ALUMNAS DE LA EVALUACIÓN SELECCIONADA --}}
                    <div class="cecap-card space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-bold text-brand-950">Paso 2 — Carga de Notas por Evaluación (Todas las Alumnas)</h2>
                                <p class="text-xs text-slate-500">Seleccione una evaluación para listar a todas las alumnas inscritas y registrar sus calificaciones en un solo paso.</p>
                            </div>
                            <div class="w-full md:w-80">
                                <select wire:model.live="selected_evaluation_id" class="cecap-input">
                                    <option value="">-- Seleccione evaluación --</option>
                                    @foreach($activeEvaluations as $ev)
                                        <option value="{{ $ev->id }}">
                                            {{ $ev->name }} ({{ number_format((float) $ev->weight_percent, 2) }}%)@if($ev->subject) — {{ $ev->subject->name }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        @if($selectedEvaluation)
                            <div class="rounded-xl border border-brand-200 bg-brand-50/60 px-4 py-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div>
                                    <span class="font-bold text-brand-950">{{ $selectedEvaluation->name }}</span>
                                    · Peso: <strong>{{ number_format((float) $selectedEvaluation->weight_percent, 2) }}%</strong>
                                    · Materia: <strong>{{ $selectedEvaluation->subject ? ($selectedEvaluation->subject->code.' — '.$selectedEvaluation->subject->name) : 'General del curso' }}</strong>
                                </div>
                                <div class="font-semibold text-brand-900">
                                    {{ $evaluationGradesByEnrollment->count() }} de {{ $editionEnrollments->count() }} alumnas calificadas
                                </div>
                            </div>

                            @if($editionEnrollments->isNotEmpty())
                                <form wire:submit="saveEvaluationGrades" class="space-y-4">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-sm text-left">
                                            <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                                                <tr>
                                                    <th class="py-2 pr-3">Alumna / Matrícula</th>
                                                    <th class="py-2 pr-3">Nota Final Acumulada</th>
                                                    <th class="py-2 pr-3">Estado en esta Evaluación</th>
                                                    <th class="py-2">Nota (0 - 100)</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                @foreach($editionEnrollments as $enr)
                                                    @php $savedGrade = $evaluationGradesByEnrollment->get($enr->id); @endphp
                                                    <tr wire:key="roster-grade-{{ $selectedEvaluation->id }}-{{ $enr->id }}" class="{{ ($savedGrade && $rectifyingGradeId === $savedGrade->id) ? 'bg-amber-50/50' : '' }}">
                                                        <td class="py-2.5 pr-3 font-semibold text-slate-900">
                                                            {{ $enr->student?->full_name }}
                                                            <div class="text-xs font-normal text-slate-500">{{ $enr->enrollment_code }} · {{ $enr->student?->identification_number }}</div>
                                                        </td>
                                                        <td class="py-2.5 pr-3 text-xs">
                                                            <strong class="text-slate-900">{{ number_format($enr->calculateFinalGrade(), 1) }}</strong>
                                                            <div class="text-slate-500">Asist: {{ number_format($enr->calculateAttendancePercent(), 0) }}%</div>
                                                        </td>
                                                        <td class="py-2.5 pr-3 text-xs">
                                                            @if($savedGrade)
                                                                <span class="cecap-badge bg-emerald-100 text-emerald-900">Guardada (v{{ $savedGrade->version }})</span>
                                                            @else
                                                                <span class="cecap-badge bg-amber-100 text-amber-900">Pendiente</span>
                                                            @endif
                                                        </td>
                                                        <td class="py-2.5">
                                                            @if($savedGrade)
                                                                <div class="flex flex-wrap items-center gap-2">
                                                                    <span class="text-base font-bold text-brand-950">{{ number_format((float) $savedGrade->score, 2) }}</span>
                                                                    @if(auth()->user()->hasEffectivePermission('seguimiento.grades.rectify'))
                                                                        <button type="button" wire:click="startRectifyGrade({{ $savedGrade->id }})" class="cecap-btn-secondary !min-h-8 !px-2.5 !py-1 text-xs">Rectificar</button>
                                                                    @endif
                                                                </div>
                                                            @else
                                                                <div class="max-w-[140px]">
                                                                    <input
                                                                        wire:model="evaluation_scores.{{ $enr->id }}"
                                                                        type="number"
                                                                        step="0.01"
                                                                        min="0"
                                                                        max="100"
                                                                        placeholder="0 - 100"
                                                                        class="cecap-input !min-h-9 !py-1.5 text-sm"
                                                                    >
                                                                    @error('evaluation_scores.'.$enr->id)
                                                                        <div class="mt-1 text-[11px] font-semibold text-rose-700">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @if($savedGrade && $rectifyingGradeId === $savedGrade->id)
                                                        <tr wire:key="roster-rectify-{{ $savedGrade->id }}">
                                                            <td colspan="4" class="py-3 px-1">
                                                                <div class="rounded-2xl border-2 border-amber-300 bg-amber-50/80 p-4 space-y-3">
                                                                    <div class="flex items-center justify-between gap-2">
                                                                        <h3 class="text-sm font-bold text-amber-950">Rectificación Auditada — {{ $enr->student?->full_name }} ({{ $selectedEvaluation->name }})</h3>
                                                                        <button type="button" wire:click="cancelRectifyGrade" class="cecap-btn-secondary !min-h-8 !px-3 text-xs">Cancelar</button>
                                                                    </div>
                                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                                        <div>
                                                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Nueva Nota (0 - 100) *</label>
                                                                            <input wire:model="rectify_score" type="number" step="0.01" min="0" max="100" class="cecap-input bg-white">
                                                                        </div>
                                                                        <div>
                                                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo Estandarizado *</label>
                                                                            <select wire:model="rectify_reason_code" class="cecap-input bg-white">
                                                                                @foreach($rectificationReasons as $code => $label)
                                                                                    <option value="{{ $code }}">{{ $label }}</option>
                                                                                @endforeach
                                                                            </select>
                                                                        </div>
                                                                        <div>
                                                                            <label class="block text-xs font-semibold text-slate-700 mb-1">Justificación Detallada *</label>
                                                                            <input wire:model="rectify_notes" type="text" class="cecap-input bg-white" placeholder="Detalle auditado de la rectificación">
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex flex-wrap gap-2">
                                                                        <button type="button" wire:click="submitGradeRectification" class="cecap-btn-accent text-xs">Confirmar Rectificación Versionada</button>
                                                                        <button type="button" wire:click="cancelRectifyGrade" class="cecap-btn-secondary text-xs">Cancelar</button>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @if($evaluationGradesByEnrollment->count() < $editionEnrollments->count())
                                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100">
                                            <span class="text-xs text-slate-500">Puede dejar en blanco las alumnas que aún no han realizado la evaluación y completarlas después.</span>
                                            <button type="submit" class="cecap-btn-primary">Guardar Notas de "{{ $selectedEvaluation->name }}"</button>
                                        </div>
                                    @endif
                                </form>
                            @else
                                <p class="text-sm text-slate-500 py-4 text-center">No hay alumnas confirmadas o en curso en esta cohorte.</p>
                            @endif
                        @else
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 text-center text-xs text-slate-600">
                                @if($activeEvaluations->isEmpty())
                                    Primero cree al menos una evaluación en el <strong>Paso 1</strong> arriba para habilitar la carga de notas.
                                @else
                                    Haga clic en <strong>Cargar Notas</strong> en una evaluación del Paso 1 o selecciónela en el desplegable superior para ver a todas las alumnas del curso.
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                @if($editionEnrollments->isNotEmpty() && auth()->user()->hasEffectivePermission('seguimiento.grades.manage'))
                    <div class="cecap-card space-y-2.5">
                        <h2 class="text-sm font-bold text-slate-900">Estado Académico del Grupo (Aprobación / Reprobación)</h2>
                        <div class="divide-y divide-slate-100">
                            @foreach($editionEnrollments as $enr)
                                <div wire:key="grade-enr-status-{{ $enr->id }}" class="py-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <div>
                                        <span class="font-semibold text-slate-900">{{ $enr->student?->full_name }}</span>
                                        <span class="text-slate-500">({{ $enr->enrollment_code }})</span>
                                        · Nota: <strong>{{ number_format($enr->calculateFinalGrade(), 1) }}</strong>
                                        · Asistencia: <strong>{{ number_format($enr->calculateAttendancePercent(), 0) }}%</strong>
                                        @if($enr->status === 'reprobada')
                                            <span class="cecap-badge bg-rose-100 text-rose-900 ml-1">SIN APROBAR (REPROBADA)</span>
                                        @elseif($enr->isUnapproved())
                                            <span class="cecap-badge bg-amber-100 text-amber-900 ml-1">Bajo mínimo ({{ $enr->unapprovedSummary() }})</span>
                                        @endif
                                    </div>
                                    @if($enr->status !== 'reprobada' && $enr->status !== 'graduada')
                                        <button type="button" wire:click="markEnrollmentUnapproved({{ $enr->id }})" class="inline-flex min-h-8 items-center justify-center rounded-xl border border-rose-300 bg-white px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 cursor-pointer">
                                            Marcar Sin Aprobar
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif

            <div class="cecap-card overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Calificaciones Vigentes @if($selectedEdition) — {{ $selectedEdition->name }} @endif</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Alumna</th><th class="py-2 pr-3">Evaluación</th><th class="py-2 pr-3">Peso</th><th class="py-2 pr-3">Nota</th><th class="py-2 pr-3">Versión</th><th class="py-2">Acción</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentGrades as $g)
                            <tr wire:key="grade-row-{{ $g->id }}" class="{{ $rectifyingGradeId === $g->id ? 'bg-amber-50/40' : '' }}">
                                <td class="py-2.5 pr-3 font-semibold">{{ $g->student?->full_name }}<div class="text-xs text-slate-500">{{ $g->enrollment?->edition?->name }}</div></td>
                                <td class="py-2.5 pr-3">{{ $g->evaluation_name }} @if($g->subject)<span class="text-xs text-slate-500">({{ $g->subject->name }})</span>@endif</td>
                                <td class="py-2.5 pr-3 text-xs font-semibold text-slate-600">{{ number_format((float) $g->weight_percent, 0) }}%</td>
                                <td class="py-2.5 pr-3 font-bold text-brand-950">{{ number_format((float) $g->score, 2) }}</td>
                                <td class="py-2.5 pr-3 text-xs">v{{ $g->version }} @if($g->rectification_reason_code)<span class="text-amber-700">({{ $g->rectification_reason_code }})</span>@endif</td>
                                <td class="py-2.5">
                                    @if(auth()->user()->hasEffectivePermission('seguimiento.grades.rectify'))
                                        <button type="button" wire:click="startRectifyGrade({{ $g->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Rectificar</button>
                                    @endif
                                </td>
                            </tr>
                            @if($rectifyingGradeId === $g->id && (! $selectedEvaluation || (int) $g->edition_evaluation_id !== (int) $selectedEvaluation->id))
                                <tr wire:key="grade-rectify-{{ $g->id }}">
                                    <td colspan="6" class="py-3 px-1">
                                        <div class="rounded-2xl border-2 border-amber-300 bg-amber-50/80 p-4 space-y-4">
                                            <div class="flex items-center justify-between gap-2">
                                                <h3 class="text-sm font-bold text-amber-950">Rectificación Auditada de Calificación (Nueva Versión)</h3>
                                                <button type="button" wire:click="cancelRectifyGrade" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nueva Nota *</label>
                                                    <input wire:model="rectify_score" type="number" step="0.01" min="0" max="100" class="cecap-input">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo Estandarizado *</label>
                                                    <select wire:model="rectify_reason_code" class="cecap-input">
                                                        @foreach($rectificationReasons as $code => $label)
                                                            <option value="{{ $code }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Justificación Detallada *</label>
                                                    <input wire:model="rectify_notes" type="text" class="cecap-input" placeholder="Detalle auditado de la rectificación">
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" wire:click="submitGradeRectification" class="cecap-btn-accent text-xs sm:text-sm">Confirmar Rectificación Versionada</button>
                                                <button type="button" wire:click="cancelRectifyGrade" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">Sin calificaciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: HABILIDADES BLANDAS --}}
    @if($tab === 'soft_skills')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if(auth()->user()->hasEffectivePermission('seguimiento.soft_skills.manage'))
                <form wire:submit="recordSoftSkill" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Registrar Habilidad Blanda Permanente</h2>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Alumna *</label>
                        <select wire:model="skill_student_id" required class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($students as $st)<option value="{{ $st->id }}">{{ $st->full_name }} ({{ $st->identification_number }})</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Habilidad Blanda *</label>
                        <input wire:model="skill_name" type="text" required class="cecap-input">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Nivel *</label>
                            <select wire:model="skill_level" class="cecap-input">
                                <option value="en_desarrollo">En desarrollo</option>
                                <option value="competente">Competente</option>
                                <option value="destacado">Destacado</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Puntaje (1-100)</label>
                            <input wire:model="skill_score" type="number" min="1" max="100" class="cecap-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Observaciones</label>
                        <textarea wire:model="skill_observations" rows="2" class="cecap-input"></textarea>
                    </div>
                    <button type="submit" class="cecap-btn-primary w-full">Guardar Evaluación</button>
                </form>
            @endif

            <div class="cecap-card lg:col-span-2 overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Historial Permanente de Habilidades Blandas</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Alumna</th><th class="py-2 pr-3">Habilidad</th><th class="py-2 pr-3">Nivel</th><th class="py-2">Constancia</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($softSkills as $sk)
                            <tr>
                                <td class="py-2.5 pr-3 font-semibold">{{ $sk->student?->full_name }}</td>
                                <td class="py-2.5 pr-3">{{ $sk->skill_name }}<div class="text-xs text-slate-500">{{ $sk->evaluated_on?->format('d/m/Y') }}</div></td>
                                <td class="py-2.5 pr-3"><span class="cecap-badge bg-brand-100 text-brand-900">{{ ucfirst(str_replace('_', ' ', $sk->level)) }}</span></td>
                                <td class="py-2.5">
                                    @if($sk->student)
                                        <button type="button"
                                                data-pdf-modal-url="{{ route('pdf.habilidades', $sk->student) }}"
                                                data-pdf-modal-title="Constancia Habilidades Blandas"
                                                class="cecap-btn-secondary !min-h-9 !px-3 text-xs">
                                            Constancia PDF
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin evaluaciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
