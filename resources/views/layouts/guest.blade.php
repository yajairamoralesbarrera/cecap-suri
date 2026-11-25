@php
    $settings = \App\Models\InstitutionSetting::current();
@endphp
<!DOCTYPE html>
<html lang="es-CR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $settings->theme_color ?: '#0F4C4C' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $settings->short_name }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $settings->institution_name }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/icons/cecap-192.svg">
    <link rel="apple-touch-icon" href="/icons/cecap-192.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gradient-to-br from-brand-50 via-slate-50 to-amber-50/40 text-slate-900">
    <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('inscripcion.publica') }}" class="flex items-center gap-3">
                <img src="/icons/cecap-192.svg" alt="CeCap" class="h-10 w-10 rounded-xl shadow-xs">
                <div>
                    <div class="text-sm sm:text-base font-bold text-brand-950">{{ $settings->institution_name }}</div>
                    <div class="text-xs text-slate-500">Costa Rica · Plataforma Educativa PWA</div>
                </div>
            </a>
            <div class="flex items-center gap-2">
                <a href="{{ route('inscripcion.publica') }}" class="cecap-btn-secondary !px-3 text-xs sm:text-sm">Inscripción</a>
                @auth
                    <a href="{{ route('panel') }}" class="cecap-btn-primary !px-3 text-xs sm:text-sm">Mi Panel</a>
                @else
                    <a href="{{ route('login') }}" class="cecap-btn-primary !px-3 text-xs sm:text-sm">Ingresar</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        @if(session('status'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                {{ session('status') }}
            </div>
        @endif
        @if(session('warning'))
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                {{ session('warning') }}
            </div>
        @endif
        {{ $slot }}
    </main>

    {{-- Visor PDF Modal Seguro también disponible en vistas públicas (Planilla firmada) --}}
    <div id="cecap-pdf-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-2 sm:p-6">
        <div class="flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 id="cecap-pdf-title" class="text-sm sm:text-base font-bold text-slate-900 truncate">Documento Oficial</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="cecap-pdf-mode-html" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">Vista HTML</button>
                    <button type="button" id="cecap-pdf-mode-blob" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">PDF Blob</button>
                    <button type="button" id="cecap-pdf-print" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">Imprimir</button>
                    <a id="cecap-pdf-download" href="#" class="cecap-btn-primary !min-h-9 !px-3 !py-1 text-xs">Descargar PDF</a>
                    <a id="cecap-pdf-open-tab" href="#" target="_blank" rel="noopener" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">Nueva pestaña</a>
                    <button type="button" data-pdf-modal-close class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-200 cursor-pointer">✕</button>
                </div>
            </div>
            <iframe id="cecap-pdf-frame" title="Visor seguro de documento" class="flex-1 w-full bg-slate-100 border-0"></iframe>
        </div>
    </div>

    @livewireScripts
</body>
</html>
