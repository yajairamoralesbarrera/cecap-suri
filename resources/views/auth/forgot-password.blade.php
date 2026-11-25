@component('layouts.guest', ['title' => 'Recuperar Contraseña'])
<div class="mx-auto max-w-4xl space-y-6">
    <div class="text-center space-y-1">
        <h1 class="text-2xl font-bold text-brand-950">Recuperación Dual de Contraseña</h1>
        <p class="text-sm text-slate-600">Elija el método más conveniente para restablecer el acceso a su cuenta CeCap SURÍ.</p>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Opción 1: Enlace por correo --}}
        <div class="cecap-card space-y-4">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-900 font-bold">1</span>
                <div>
                    <h2 class="font-bold text-slate-900">Enlace por Correo Electrónico</h2>
                    <p class="text-xs text-slate-500">Enviamos un enlace temporal seguro a su correo registrado.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico registrado</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required class="cecap-input" placeholder="nombre@cecap.or.cr">
                </div>
                <button type="submit" class="cecap-btn-primary w-full">Enviar Enlace de Recuperación</button>
            </form>
        </div>

        {{-- Opción 2: Validación de datos personales --}}
        <div class="cecap-card space-y-4 border-accent-500/40">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-100 text-accent-600 font-bold">2</span>
                <div>
                    <h2 class="font-bold text-slate-900">Validación de Datos Personales</h2>
                    <p class="text-xs text-slate-500">Verifique correo + Cédula/DIMEX + teléfono (protegido con RateLimiter y auditoría).</p>
                </div>
            </div>

            <form method="POST" action="{{ route('password.identity') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="identity_email" class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico</label>
                    <input id="identity_email" name="identity_email" type="email" value="{{ old('identity_email') }}" required class="cecap-input">
                </div>
                <div>
                    <label for="identification_number" class="block text-sm font-semibold text-slate-700 mb-1">Cédula / DIMEX</label>
                    <input id="identification_number" name="identification_number" type="text" value="{{ old('identification_number') }}" required class="cecap-input" placeholder="1-1234-5678">
                </div>
                <div>
                    <label for="phone" class="block text-sm font-semibold text-slate-700 mb-1">Teléfono registrado</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required class="cecap-input" placeholder="8888-8888">
                </div>
                <button type="submit" class="cecap-btn-accent w-full">Validar Identidad y Restablecer</button>
            </form>
        </div>
    </div>

    <div class="text-center">
        <a href="{{ route('login') }}" class="text-sm font-semibold text-brand-800 hover:underline">← Volver a Iniciar Sesión</a>
    </div>
</div>
@endcomponent
