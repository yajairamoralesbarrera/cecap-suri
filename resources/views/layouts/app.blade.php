@php
    $settings = \App\Models\InstitutionSetting::current();
    $user = auth()->user();
    $navItems = [
        ['route' => 'panel', 'module' => null, 'perm' => 'panel.view', 'label' => 'Panel', 'short' => 'Panel', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['route' => 'academico', 'module' => 'academico', 'perm' => 'academico.view', 'label' => 'Académico', 'short' => 'Cursos', 'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['route' => 'admisiones', 'module' => 'admisiones', 'perm' => 'admisiones.view', 'label' => 'Admisiones', 'short' => 'Matrícula', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['route' => 'seguimiento', 'module' => 'seguimiento', 'perm' => 'seguimiento.view', 'label' => 'Seguimiento', 'short' => 'Notas', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ['route' => 'finanzas', 'module' => 'finanzas', 'perm' => 'finanzas.view', 'label' => 'Finanzas y Caja', 'short' => 'Caja', 'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        ['route' => 'graduacion', 'module' => 'graduacion', 'perm' => 'graduacion.view', 'label' => 'Graduación', 'short' => 'Diplomas', 'icon' => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
        ['route' => 'personal', 'module' => 'personal', 'perm' => 'personal.view', 'label' => 'Personal CR', 'short' => 'Personal', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['route' => 'actividades', 'module' => 'actividades', 'perm' => 'actividades.view', 'label' => 'Talleres', 'short' => 'Talleres', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['route' => 'documentos', 'module' => 'documentos', 'perm' => 'documentos.view', 'label' => 'Documentos', 'short' => 'Docs', 'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'],
        ['route' => 'consultas', 'module' => 'consultas', 'perm' => 'consultas.view', 'label' => 'Expediente Alumnas', 'short' => 'Expedientes', 'icon' => 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
        ['route' => 'direccion', 'module' => 'direccion', 'perm' => 'direccion.view', 'label' => 'Dirección', 'short' => 'Dirección', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
    ];

    $visibleNav = collect($navItems)->filter(function ($item) use ($user, $settings) {
        if (! $user) return false;
        if ($item['module'] && ! $settings->isModuleEnabled($item['module'])) {
            if (! ($item['module'] === 'direccion' && $user->hasRole('super_admin'))) {
                return false;
            }
        }
        return $user->hasEffectivePermission($item['perm']);
    })->values();
@endphp
<!DOCTYPE html>
<html lang="es-CR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ $settings->theme_color ?: '#0F4C4C' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ $settings->short_name }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ $settings->short_name }}</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    {{-- Sidebar Desktop Colapsable Limpio (w-64 <-> w-20, sin recortes a 2 letras) --}}
    <aside id="cecap-sidebar"
           data-sidebar-collapsed="false"
           class="fixed inset-y-0 left-0 z-30 hidden md:flex md:w-64 flex-col bg-brand-950 text-slate-100 transition-all duration-200 border-r border-brand-900/80">
        <div class="flex h-16 items-center justify-between px-4 border-b border-white/10">
            <a href="{{ route('panel') }}" class="flex items-center gap-3 min-w-0" title="{{ $settings->institution_name }}">
                <img src="{{ $settings->logoUrl() }}" alt="SURÍ Proyecto Educativo" class="h-9 w-9 max-h-9 shrink-0 rounded-lg bg-white p-0.5 object-contain shadow-xs">
                <div class="sidebar-label truncate">
                    <div class="text-sm font-bold tracking-tight text-white truncate">{{ $settings->short_name }}</div>
                    <div class="text-[11px] text-accent-400 truncate">SURÍ · Costa Rica</div>
                </div>
            </a>
            <button type="button"
                    data-sidebar-toggle
                    aria-label="Colapsar o expandir barra lateral"
                    title="Colapsar / Expandir menú"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h10M4 18h16"/>
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1" aria-label="Navegación principal">
            @foreach($visibleNav as $item)
                @php $active = request()->routeIs($item['route'].'*'); @endphp
                <a href="{{ route($item['route']) }}"
                   title="{{ $item['label'] }}"
                   class="sidebar-item flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-brand-800 text-white shadow-xs' : 'text-slate-200/90 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                    </svg>
                    <span class="sidebar-label truncate">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        {{-- Botón Compacto PWA Universal + Cerrar Sesión --}}
        <div class="border-t border-white/10 p-3 space-y-2">
            <button type="button"
                    data-pwa-install-btn
                    title="Instalar App PWA en este dispositivo"
                    class="sidebar-item flex w-full min-h-11 items-center gap-2.5 rounded-xl bg-accent-500 px-3 py-2 text-xs font-semibold text-white hover:bg-accent-600 transition cursor-pointer">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span class="sidebar-label truncate" data-pwa-status>Instalar App PWA</span>
            </button>

            <a href="{{ route('inscripcion.publica') }}"
               title="Portal Público de Inscripción"
               class="sidebar-item flex w-full min-h-11 items-center gap-2.5 rounded-xl bg-white/5 px-3 py-2 text-xs font-medium text-slate-200 hover:bg-white/10 transition">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                <span class="sidebar-label truncate">Inscripción Pública</span>
            </a>
        </div>
    </aside>

    {{-- Drawer Móvil Completo --}}
    <div id="cecap-mobile-drawer" class="fixed inset-0 z-50 hidden md:hidden">
        <div class="absolute inset-0 bg-slate-900/60" data-mobile-drawer-toggle></div>
        <div class="relative flex h-full w-72 max-w-[85vw] flex-col bg-brand-950 text-white shadow-2xl">
            <div class="flex h-16 items-center justify-between px-4 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <img src="{{ $settings->logoUrl() }}" alt="SURÍ Proyecto Educativo" class="h-8 w-8 max-h-8 rounded-lg bg-white p-0.5 object-contain">
                    <span class="font-bold text-sm">{{ $settings->short_name }}</span>
                </div>
                <button type="button" data-mobile-drawer-toggle class="inline-flex h-11 w-11 items-center justify-center rounded-lg hover:bg-white/10">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto p-3 space-y-1">
                @foreach($visibleNav as $item)
                    <a href="{{ route($item['route']) }}" class="flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium {{ request()->routeIs($item['route'].'*') ? 'bg-brand-800 text-white' : 'text-slate-200 hover:bg-white/10' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/></svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-white/10 p-3 space-y-2">
                <button type="button" data-pwa-install-btn class="flex w-full min-h-11 items-center justify-center gap-2 rounded-xl bg-accent-500 px-3 py-2 text-sm font-semibold text-white">
                    Instalar App PWA
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full min-h-11 items-center justify-center rounded-xl bg-white/10 px-3 py-2 text-sm font-semibold text-white hover:bg-white/20">
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Contenedor Principal --}}
    <div id="cecap-main-wrap" class="min-h-screen md:pl-64 pb-20 md:pb-8 transition-all duration-200">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 sm:px-6 backdrop-blur">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button"
                        data-mobile-drawer-toggle
                        aria-label="Abrir menú"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-700 md:hidden">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="truncate">
                    <h1 class="text-base sm:text-lg font-bold text-slate-900 truncate">{{ $title ?? $settings->institution_name }}</h1>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                @if($user)
                    <div class="hidden sm:block text-right leading-tight">
                        <div class="text-sm font-semibold text-slate-800">{{ $user->name }}</div>
                        <div class="text-xs text-slate-500">{{ $user->role_labels }}</div>
                    </div>
                    <a href="{{ route('password.change') }}" title="Cambiar contraseña" class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Clave
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 items-center rounded-xl bg-slate-100 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-200 cursor-pointer">
                            Salir
                        </button>
                    </form>
                @endif
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            @if(session('status'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                    {{ session('status') }}
                </div>
            @endif
            @if(session('warning'))
                <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
                    {{ session('warning') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    {{-- Barra Inferior Móvil (>=44px touch targets) --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-around border-t border-slate-200 bg-white/95 px-1 py-1 backdrop-blur md:hidden" aria-label="Accesos rápidos móvil">
        @foreach($visibleNav->take(5) as $item)
            @php $active = request()->routeIs($item['route'].'*'); @endphp
            <a href="{{ route($item['route']) }}"
               class="flex min-h-11 min-w-11 flex-1 flex-col items-center justify-center rounded-lg px-1 py-1 text-[11px] font-semibold {{ $active ? 'text-brand-900 bg-brand-50' : 'text-slate-600' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                </svg>
                <span class="mt-0.5 truncate max-w-[68px]">{{ $item['short'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Visor PDF Modal Seguro (Blob / ?vista=html) --}}
    <div id="cecap-pdf-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-2 sm:p-6">
        <div class="flex h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
                <h2 id="cecap-pdf-title" class="text-sm sm:text-base font-bold text-slate-900 truncate">Documento Oficial</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="cecap-pdf-mode-html" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">Vista HTML</button>
                    <button type="button" id="cecap-pdf-mode-blob" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">PDF Blob</button>
                    <button type="button" id="cecap-pdf-print" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">Imprimir</button>
                    <button type="button" id="cecap-pdf-download" class="cecap-btn-primary !min-h-9 !px-3 !py-1 text-xs">Descargar PDF</button>
                    <a id="cecap-pdf-open-tab" href="#" target="_blank" rel="noopener" class="cecap-btn-secondary !min-h-9 !px-3 !py-1 text-xs">Nueva pestaña</a>
                    <button type="button" data-pdf-modal-close class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-200 cursor-pointer" aria-label="Cerrar visor">✕</button>
                </div>
            </div>
            <iframe id="cecap-pdf-frame" title="Visor seguro de documento" class="flex-1 w-full bg-slate-100 border-0"></iframe>
        </div>
    </div>

    {{-- Modal Guía de Instalación PWA (iOS / macOS / Windows / Linux / Android) --}}
    <div id="cecap-pwa-guide-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900">Instalar App PWA {{ $settings->short_name }}</h2>
                <button type="button" data-pwa-guide-close class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 cursor-pointer">✕</button>
            </div>
            <p class="text-sm text-slate-600">Esta plataforma funciona como aplicación nativa en cualquier dispositivo:</p>
            <ul class="space-y-2 text-sm text-slate-700">
                <li><strong>Windows / macOS / Linux (Chrome/Edge):</strong> Haga clic en el ícono de instalar en la barra de direcciones.</li>
                <li><strong>Android (Chrome):</strong> Menú ⋮ → <em>Instalar aplicación</em> o <em>Agregar a pantalla de inicio</em>.</li>
                <li><strong>iOS / iPadOS (Safari):</strong> Botón <em>Compartir</em> ↑ → <em>Agregar a inicio</em>.</li>
            </ul>
            <button type="button" data-pwa-guide-close class="cecap-btn-primary w-full">Entendido</button>
        </div>
    </div>

    @livewireScripts
</body>
</html>
