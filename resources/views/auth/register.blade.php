@component('layouts.guest', ['title' => 'Registro de Alumna'])
<div class="mx-auto max-w-xl">
    <div class="cecap-card space-y-6">
        <div>
            <h1 class="text-xl font-bold text-brand-950">Registro de Cuenta — Portal de Alumnas</h1>
            <p class="text-sm text-slate-600 mt-1">Al registrarse recibirá automáticamente los permisos estándar del Portal de Alumnas para consultar matrículas, asistencia, calificaciones, recibos y descargar su Planilla PDF.</p>
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

        <form method="POST" action="{{ route('register.post') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @csrf
            <div>
                <label for="first_name" class="block text-sm font-semibold text-slate-700 mb-1">Nombre *</label>
                <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" required class="cecap-input">
            </div>
            <div>
                <label for="last_name" class="block text-sm font-semibold text-slate-700 mb-1">Primer apellido *</label>
                <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" required class="cecap-input">
            </div>
            <div>
                <label for="second_last_name" class="block text-sm font-semibold text-slate-700 mb-1">Segundo apellido</label>
                <input id="second_last_name" name="second_last_name" type="text" value="{{ old('second_last_name') }}" class="cecap-input">
            </div>
            <div>
                <label for="identification_type" class="block text-sm font-semibold text-slate-700 mb-1">Tipo de identificación *</label>
                <select id="identification_type" name="identification_type" class="cecap-input">
                    @foreach(\App\Models\Picklist::optionsFor('id_type') as $code => $label)
                        <option value="{{ $code }}" @selected(old('identification_type', 'cedula_nacional') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="identification_number" class="block text-sm font-semibold text-slate-700 mb-1">Número de Cédula / DIMEX *</label>
                <input id="identification_number" name="identification_number" type="text" value="{{ old('identification_number') }}" required class="cecap-input" placeholder="1-1234-5678">
            </div>
            <div>
                <label for="phone" class="block text-sm font-semibold text-slate-700 mb-1">Teléfono *</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required class="cecap-input" placeholder="8888-8888">
            </div>
            <div class="sm:col-span-2">
                <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico (Obligatorio) *</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="cecap-input" placeholder="nombre@correo.cr">
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 mb-1">Contraseña (mín. 8 caracteres) *</label>
                <input id="password" name="password" type="password" required class="cecap-input">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 mb-1">Confirmar contraseña *</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="cecap-input">
            </div>

            <div class="sm:col-span-2 pt-2 flex flex-col sm:flex-row gap-3">
                <button type="submit" class="cecap-btn-primary flex-1">Crear mi cuenta de Alumna</button>
                <a href="{{ route('login') }}" class="cecap-btn-secondary">Volver al ingreso</a>
            </div>
        </form>
    </div>
</div>
@endcomponent
