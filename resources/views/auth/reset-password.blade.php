@component('layouts.guest', ['title' => 'Restablecer Contraseña'])
<div class="mx-auto max-w-md">
    <div class="cecap-card space-y-5">
        <div>
            <h1 class="text-xl font-bold text-brand-950">Definir Nueva Contraseña</h1>
            <p class="text-sm text-slate-600 mt-1">Ingrese su nueva contraseña institucional (mínimo 8 caracteres).</p>
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

        <form method="POST" action="{{ route('password.reset.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="cecap-input">
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Nueva contraseña</label>
                <input id="password" name="password" type="password" required class="cecap-input">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1">Confirmar nueva contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="cecap-input">
            </div>
            <button type="submit" class="cecap-btn-primary w-full">Restablecer Contraseña</button>
        </form>
    </div>
</div>
@endcomponent
