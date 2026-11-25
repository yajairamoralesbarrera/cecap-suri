@component('layouts.guest', ['title' => 'Verificación Oficial de Diploma'])
<div class="mx-auto max-w-2xl">
    <div class="cecap-card space-y-6">
        <div class="flex items-center gap-4 border-b border-slate-200 pb-4">
            <img src="{{ $settings->logoUrl() }}" alt="SURÍ Proyecto Educativo" class="h-12 w-auto max-h-12 shrink-0 object-contain">
            <div>
                <h1 class="text-xl font-bold text-brand-950">Verificación Pública de Diplomas y Certificados</h1>
                <p class="text-sm text-slate-600">{{ $settings->institution_name }} · {{ $settings->country }}</p>
            </div>
        </div>

        @if($record && $record->status === 'emitido')
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-700 px-3 py-1 text-xs font-bold text-white">
                    ✓ CERTIFICADO AUTÉNTICO Y VERIFICADO
                </div>
                <p class="text-sm text-emerald-900">Este certificado consta en los libros oficiales de graduación de {{ $settings->institution_name }}.</p>
            </div>

            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Graduada</dt>
                    <dd class="mt-1 font-bold text-slate-900">{{ $record->student?->full_name }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Identificación</dt>
                    <dd class="mt-1 font-bold text-slate-900">{{ $record->student?->identification_number }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5 sm:col-span-2">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Programa / Curso Aprobado</dt>
                    <dd class="mt-1 font-bold text-brand-950">{{ $record->enrollment?->edition?->course?->name }} ({{ $record->enrollment?->edition?->course?->program_type_label }})</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Registro Oficial</dt>
                    <dd class="mt-1 font-semibold text-slate-900">Tomo {{ $record->tomo }} · Folio {{ $record->folio }} · Asiento {{ $record->asiento }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Acta y Fecha</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $record->act_number }} · {{ $record->graduation_date?->format('d/m/Y') }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Nota Final</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ number_format((float) $record->final_grade, 2) }} / 100</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3.5">
                    <dt class="text-xs font-semibold uppercase text-slate-500">Token de Verificación</dt>
                    <dd class="mt-1 font-mono text-xs text-slate-700 break-all">{{ $record->verification_token }}</dd>
                </div>
            </dl>
        @else
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 space-y-2">
                <div class="inline-flex items-center gap-2 rounded-full bg-rose-700 px-3 py-1 text-xs font-bold text-white">
                    ✕ CERTIFICADO NO ENCONTRADO O INVÁLIDO
                </div>
                <p class="text-sm text-rose-900">No existe un diploma emitido vigente asociado al código <code class="font-mono">{{ $token }}</code>.</p>
            </div>
        @endif
    </div>
</div>
@endcomponent
