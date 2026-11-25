@component('layouts.guest', ['title' => 'Iniciar Sesión'])
<div class="mx-auto max-w-md">
    <div class="cecap-card space-y-6">
        <div class="text-center space-y-1">
            <img src="/icons/cecap-192.svg" alt="CeCap SURÍ" class="mx-auto h-14 w-14 rounded-2xl shadow-sm">
            <h1 class="text-xl font-bold text-brand-950">Ingreso a la Plataforma</h1>
            <p class="text-sm text-slate-500">CeCap — SURÍ Proyecto Educativo, Costa Rica</p>
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

        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="cecap-input" placeholder="usuario@cecap.or.cr">
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="cecap-input" placeholder="••••••••">
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="inline-flex items-center gap-2 text-slate-600 cursor-pointer">
                    <input type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-brand-900">
                    Recordarme
                </label>
                <a href="{{ route('password.request') }}" class="font-semibold text-brand-800 hover:underline">¿Olvidó su contraseña?</a>
            </div>

            <button type="submit" class="cecap-btn-primary w-full">Iniciar Sesión</button>
        </form>

        <div class="border-t border-slate-200 pt-4 text-center space-y-2 text-sm">
            <p class="text-slate-600">¿Es alumna nueva y desea crear su acceso al portal?</p>
            <div class="flex flex-col sm:flex-row gap-2">
                <a href="{{ route('register') }}" class="cecap-btn-secondary flex-1">Crear cuenta de Alumna</a>
                <a href="{{ route('inscripcion.publica') }}" class="cecap-btn-accent flex-1">Preinscripción en Línea</a>
            </div>
        </div>
    </div>
</div>
@endcomponent
