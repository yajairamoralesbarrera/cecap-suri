@php
    $settings = \App\Models\InstitutionSetting::current();
@endphp
<!DOCTYPE html>
<html lang="es-CR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 · Acceso Restringido · {{ $settings->short_name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-brand-50 flex items-center justify-center p-6 text-slate-900">
    <div class="cecap-card max-w-lg w-full text-center space-y-5">
        <img src="{{ $settings->logoUrl() }}" alt="SURÍ Proyecto Educativo" class="mx-auto h-14 w-auto object-contain">
        <div class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-bold text-rose-800">
            ERROR 403 · ACCESO RESTRINGIDO
        </div>
        <h1 class="text-xl font-bold text-brand-950">Módulo o Recurso No Disponible</h1>
        <p class="text-sm text-slate-600">
            {{ $exception->getMessage() ?: 'No cuenta con permisos suficientes o este módulo se encuentra deshabilitado temporalmente.' }}
        </p>
        <div class="flex flex-wrap justify-center gap-3 pt-2">
            <a href="{{ route('panel') }}" class="cecap-btn-primary">← Volver al Panel</a>
            @if(auth()->check() && auth()->user()->hasEffectivePermission('direccion.view'))
                <a href="{{ route('direccion') }}" class="cecap-btn-secondary">Ir a Dirección</a>
            @endif
        </div>
    </div>
</body>
</html>
