@component('layouts.app', ['title' => 'Cambio Obligatorio de Contraseña'])
<div class="mx-auto max-w-lg">
    <div class="cecap-card space-y-5">
        <div>
            <h2 class="text-lg font-bold text-brand-950">Actualizar Contraseña</h2>
            <p class="text-sm text-slate-600 mt-1">
                @if(auth()->user()->must_change_password)
                    Su cuenta requiere cambio obligatorio de contraseña antes de acceder a los módulos de la plataforma.
                @else
                    Actualice periódicamente su contraseña para mantener protegida su cuenta institucional.
                @endif
            </p>
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

        <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
            @csrf
            <div>
                <label for="current_password" class="block text-sm font-semibold text-slate-700 mb-1">Contraseña actual o temporal</label>
                <div class="relative">
                    <input id="current_password" name="current_password" type="password" required class="cecap-input pr-12">
                    @include('partials.password-toggle', ['target' => 'current_password'])
                </div>
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Nueva contraseña (mínimo 8 caracteres)</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required class="cecap-input pr-12">
                    @include('partials.password-toggle', ['target' => 'password'])
                </div>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1">Confirmar nueva contraseña</label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="cecap-input pr-12">
                    @include('partials.password-toggle', ['target' => 'password_confirmation'])
                </div>
            </div>
            <button type="submit" class="cecap-btn-primary w-full">Guardar Nueva Contraseña</button>
        </form>
    </div>
</div>
@endcomponent
