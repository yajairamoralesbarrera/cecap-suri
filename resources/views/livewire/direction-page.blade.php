<div class="space-y-6">
    @if($feedback)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ $feedback }}</div>
    @endif
    @if($issuedTempPassword)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-950">{{ $issuedTempPassword }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('kpis')" class="{{ $tab === 'kpis' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">1. Tablero y CSV BOM</button>
        <button type="button" wire:click="setTab('users')" class="{{ $tab === 'users' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">2. Usuarios y 8 Roles ({{ $users->count() }})</button>
        <button type="button" wire:click="setTab('audit')" class="{{ $tab === 'audit' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">3. Auditoría Inmutable</button>
        <button type="button" wire:click="setTab('picklists')" class="{{ $tab === 'picklists' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">4. Picklists CR</button>
        <button type="button" wire:click="setTab('settings')" class="{{ $tab === 'settings' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">5. Configuración e Inscripción</button>
    </div>

    {{-- TAB 1: KPIs Y CSV UTF-8 BOM --}}
    @if($tab === 'kpis')
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="cecap-card"><div class="text-xs uppercase font-semibold text-slate-500">Alumnas</div><div class="mt-1 text-2xl font-bold text-brand-950">{{ $kpis['students'] }}</div></div>
            <div class="cecap-card"><div class="text-xs uppercase font-semibold text-slate-500">Matrículas Confirmadas</div><div class="mt-1 text-2xl font-bold text-emerald-700">{{ $kpis['enrollments_confirmed'] }}</div></div>
            <div class="cecap-card"><div class="text-xs uppercase font-semibold text-slate-500">Preinscritas / Docs Pendientes</div><div class="mt-1 text-2xl font-bold text-accent-600">{{ $kpis['enrollments_pending'] }}</div></div>
            <div class="cecap-card"><div class="text-xs uppercase font-semibold text-slate-500">Recaudación Acumulada</div><div class="mt-1 text-2xl font-bold text-brand-900">₡{{ number_format($kpis['total_collected'], 2) }}</div></div>
            <div class="cecap-card"><div class="text-xs uppercase font-semibold text-slate-500">Cartera por Cobrar</div><div class="mt-1 text-2xl font-bold text-rose-700">₡{{ number_format($kpis['pending_receivables'], 2) }}</div></div>
            <div class="cecap-card"><div class="text-xs uppercase font-semibold text-slate-500">Graduadas</div><div class="mt-1 text-2xl font-bold text-brand-800">{{ $kpis['graduations'] }}</div></div>
        </div>

        @if(auth()->user()->hasEffectivePermission('direccion.exports.csv'))
            <div class="cecap-card space-y-3">
                <h2 class="text-base font-bold text-slate-900">Exportaciones Oficiales CSV UTF-8 BOM (Excel Costa Rica)</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('direccion.export', 'students') }}" class="cecap-btn-secondary text-xs">Alumnas CSV</a>
                    <a href="{{ route('direccion.export', 'enrollments') }}" class="cecap-btn-secondary text-xs">Matrículas CSV</a>
                    <a href="{{ route('direccion.export', 'payments') }}" class="cecap-btn-secondary text-xs">Recibos Caja CSV</a>
                    <a href="{{ route('direccion.export', 'staff_payments') }}" class="cecap-btn-secondary text-xs">Pagos Personal CR CSV</a>
                    <a href="{{ route('direccion.export', 'audit_logs') }}" class="cecap-btn-secondary text-xs">Auditoría CSV</a>
                </div>
            </div>
        @endif
    @endif

    {{-- TAB 2: USUARIOS, 8 ROLES, PERMISOS POR PERSONA Y CLONACIÓN SEGURA --}}
    @if($tab === 'users')
        <div class="cecap-card space-y-3">
            <h2 class="text-base font-bold text-brand-950">Catálogo Oficial de los 8 Roles de Gobernanza</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                @foreach($rolesCatalog as $roleKey => $meta)
                    <div class="rounded-xl border p-3 {{ $meta['is_critical'] ? 'border-brand-300 bg-brand-50/70' : 'border-slate-200 bg-slate-50' }}">
                        <div class="font-bold text-slate-900">{{ $meta['label'] }} <span class="font-mono text-[10px] text-slate-500">({{ $roleKey }})</span></div>
                        <p class="mt-1 text-slate-600">{{ $meta['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        @if(auth()->user()->hasEffectivePermission('direccion.users.manage'))
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <form wire:submit="createUser" class="cecap-card space-y-3">
                    <h2 class="text-base font-bold text-brand-950">Crear Usuario (must_change_password = true)</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input wire:model="new_user_name" type="text" required class="cecap-input" placeholder="Nombre completo *">
                        <input wire:model="new_user_email" type="email" required class="cecap-input" placeholder="Correo institucional *">
                        <input wire:model="new_user_id_num" type="text" required class="cecap-input" placeholder="Cédula / DIMEX *">
                        <input wire:model="new_user_phone" type="tel" required class="cecap-input" placeholder="Teléfono *">
                        <div class="relative sm:col-span-2">
                            <input id="new_user_password" wire:model="new_user_password" type="password" required class="cecap-input pr-12" placeholder="Contraseña temporal inicial (mín. 8) *">
                            @include('partials.password-toggle', ['target' => 'new_user_password'])
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="text-xs font-bold uppercase text-slate-600">Roles (Multirrol soportado, ej. Profesora + Alumna)</div>
                        <div class="grid grid-cols-2 gap-1.5 text-xs">
                            @foreach($rolesCatalog as $rk => $rm)
                                @if($rk !== 'super_admin' || $isSuperAdmin)
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" wire:model.live="new_user_roles" value="{{ $rk }}" class="h-4 w-4 rounded text-brand-900">
                                        <span>{{ $rm['label'] }}</span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @if(in_array('coordinador_categoria', $new_user_roles, true))
                        <div class="rounded-xl border border-brand-200 bg-brand-50/70 p-3 space-y-2 text-xs">
                            <div class="font-bold text-brand-950">Categorías asignadas (Coordinador de Área / Categoría)</div>
                            @if($categories->isEmpty())
                                <p class="text-amber-900">Aún no hay categorías académicas creadas en la base limpia. Puede crear una aquí mismo o en <a href="{{ route('academico') }}" class="font-bold underline">Académico → Categorías</a>:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <input wire:model="quick_cat_code" type="text" class="cecap-input !min-h-9 !py-1 text-xs" placeholder="Código (ej. CAT-GASTRO)">
                                    <input wire:model="quick_cat_name" type="text" class="cecap-input !min-h-9 !py-1 text-xs" placeholder="Nombre categoría">
                                    <button type="button" wire:click="createQuickCategory" class="cecap-btn-secondary !min-h-9 !py-1 text-xs">+ Crear categoría</button>
                                </div>
                            @else
                                <div class="flex flex-wrap gap-3">
                                    @foreach($categories as $cat)
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" wire:model="new_user_categories" value="{{ $cat->id }}" class="h-4 w-4 rounded text-brand-900"> {{ $cat->name }}</label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                    <button type="submit" class="cecap-btn-primary w-full">Crear Usuario</button>
                </form>

                <form wire:submit="cloneUserPermissions" class="cecap-card space-y-3 border-accent-500/40">
                    <h2 class="text-base font-bold text-brand-950">Clonación Segura de Permisos (Exige Datos Personales Nuevos)</h2>
                    <select wire:model="clone_source_user_id" required class="cecap-input">
                        <option value="">-- Usuario modelo a clonar --</option>
                        @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role_labels }})</option>@endforeach
                    </select>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input wire:model="clone_name" type="text" required class="cecap-input" placeholder="Nombre persona nueva *">
                        <input wire:model="clone_email" type="email" required class="cecap-input" placeholder="Correo nuevo único *">
                        <input wire:model="clone_id_num" type="text" required class="cecap-input" placeholder="Cédula/DIMEX nueva *">
                        <input wire:model="clone_phone" type="tel" required class="cecap-input" placeholder="Teléfono nuevo *">
                        <div class="relative sm:col-span-2">
                            <input id="clone_temp_password" wire:model="clone_temp_password" type="password" required class="cecap-input pr-12" placeholder="Contraseña temporal inicial *">
                            @include('partials.password-toggle', ['target' => 'clone_temp_password'])
                        </div>
                    </div>
                    <button type="submit" class="cecap-btn-accent w-full">Clonar Roles y Permisos al Nuevo Usuario</button>
                </form>
            </div>
        @endif

        <div class="cecap-card overflow-x-auto">
            <h2 class="text-base font-bold text-slate-900 mb-3">Usuarios de la Plataforma</h2>
            <table class="w-full text-sm text-left">
                <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                    <tr><th class="py-2 pr-3">Usuario</th><th class="py-2 pr-3">Roles / Modo</th><th class="py-2 pr-3">Estado</th><th class="py-2">Acciones</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        <tr wire:key="user-row-{{ $u->id }}" class="{{ $editingUserId === $u->id ? 'bg-slate-50/80' : '' }}">
                            <td class="py-2.5 pr-3 font-semibold">{{ $u->name }}<div class="text-xs text-slate-500">{{ $u->email }} · {{ $u->identification_number }}</div></td>
                            <td class="py-2.5 pr-3 text-xs">
                                <div class="font-semibold">{{ $u->role_labels }}</div>
                                <div class="text-slate-500">{{ $u->uses_custom_permissions ? 'Permisos personalizados' : 'Permisos base del cargo' }}</div>
                                @if($u->hasRole('coordinador_categoria'))
                                    <div class="mt-1">
                                        @if($u->academicCategories->isNotEmpty())
                                            <span class="cecap-badge bg-brand-100 text-brand-950">Categorías: {{ $u->academicCategories->pluck('name')->implode(', ') }}</span>
                                        @else
                                            <span class="cecap-badge bg-amber-100 text-amber-900">Sin categoría asignada (pulsar Accesos)</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="py-2.5 pr-3">
                                <span class="cecap-badge {{ $u->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-rose-100 text-rose-800' }}">{{ $u->is_active ? 'ACTIVO' : 'INACTIVO' }}</span>
                            </td>
                            <td class="py-2.5">
                                <div class="flex flex-wrap gap-1.5">
                                    <button type="button" wire:click="editUserAccess({{ $u->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">Accesos</button>
                                    <button type="button" wire:click="applyBasePermissions({{ $u->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">Aplicar permisos base</button>
                                    <button type="button" wire:click="issueTempPassword({{ $u->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">Clave temporal</button>
                                    <button type="button" wire:click="toggleUserActive({{ $u->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">{{ $u->is_active ? 'Inactivar' : 'Reactivar' }}</button>
                                </div>
                            </td>
                        </tr>
                        @if($editingUserId === $u->id)
                            <tr wire:key="user-access-{{ $u->id }}">
                                <td colspan="4" class="py-3 px-1">
                                    <div class="rounded-2xl border-2 border-brand-700 bg-brand-50/60 p-4 space-y-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h3 class="text-sm sm:text-base font-bold text-brand-950">Editar Roles, Categorías y Permisos — {{ $u->name }}</h3>
                                            <button type="button" wire:click="cancelEditUserAccess" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                            @foreach($rolesCatalog as $rk => $rm)
                                                @if($rk !== 'super_admin' || $isSuperAdmin)
                                                    <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.live="edit_roles" value="{{ $rk }}" class="h-4 w-4 rounded"> {{ $rm['label'] }}</label>
                                                @endif
                                            @endforeach
                                        </div>
                                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-brand-950 cursor-pointer">
                                            <input type="checkbox" wire:model.live="edit_uses_custom" class="h-4 w-4 rounded">
                                            <span>Activar permisos personalizados por persona (uses_custom_permissions)</span>
                                        </label>
                                        @if($edit_uses_custom)
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-1.5 text-xs bg-white p-3 rounded-xl border border-slate-200 max-h-52 overflow-y-auto">
                                                @foreach($allPermissions as $perm)
                                                    @if($perm !== 'system.code.manage' || $isSuperAdmin)
                                                        <label class="inline-flex items-center gap-1.5"><input type="checkbox" wire:model="edit_direct_permissions" value="{{ $perm }}" class="h-3.5 w-3.5 rounded"> {{ $perm }}</label>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                        <div class="rounded-xl border border-brand-200 bg-white p-3 space-y-2 text-xs">
                                            <div class="font-bold text-brand-950">Categorías asignadas (Coordinador de Área / Categoría)</div>
                                            @if($categories->isEmpty())
                                                <p class="text-amber-900">Aún no existen categorías académicas. Cree una aquí mismo o desde <a href="{{ route('academico') }}" class="font-bold underline">Académico → Categorías</a>:</p>
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                    <input wire:model="quick_cat_code" type="text" class="cecap-input !min-h-9 !py-1 text-xs" placeholder="Código (ej. CAT-GASTRO)">
                                                    <input wire:model="quick_cat_name" type="text" class="cecap-input !min-h-9 !py-1 text-xs" placeholder="Nombre categoría">
                                                    <button type="button" wire:click="createQuickCategory" class="cecap-btn-secondary !min-h-9 !py-1 text-xs">+ Crear categoría</button>
                                                </div>
                                            @else
                                                <div class="flex flex-wrap gap-3">
                                                    @foreach($categories as $cat)
                                                        <label class="inline-flex items-center gap-1.5 cursor-pointer"><input type="checkbox" wire:model="edit_categories" value="{{ $cat->id }}" class="h-4 w-4 rounded text-brand-900"> {{ $cat->name }}</label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" wire:click="saveUserAccess" class="cecap-btn-primary text-xs sm:text-sm">Guardar Accesos</button>
                                            <button type="button" wire:click="cancelEditUserAccess" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- TAB 3: AUDITORÍA INMUTABLE --}}
    @if($tab === 'audit')
        <div class="cecap-card space-y-4 overflow-x-auto">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-900">Bitácora Inmutable (Append-Only PostgreSQL)</h2>
                <select wire:model.live="audit_module_filter" class="cecap-input w-56">
                    <option value="">Todos los módulos</option>
                    @foreach(['seguridad', 'academico', 'admisiones', 'seguimiento', 'finanzas', 'graduacion', 'personal', 'actividades', 'documentos', 'direccion'] as $mod)
                        <option value="{{ $mod }}">{{ ucfirst($mod) }}</option>
                    @endforeach
                </select>
            </div>
            <table class="w-full text-xs text-left">
                <thead class="border-b border-slate-200 uppercase text-slate-500">
                    <tr><th class="py-2 pr-3">Fecha</th><th class="py-2 pr-3">Actor</th><th class="py-2 pr-3">Módulo / Evento</th><th class="py-2">Motivo / Detalle</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($auditLogs as $log)
                        <tr>
                            <td class="py-2 pr-3 font-mono whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td class="py-2 pr-3 font-semibold">{{ $log->actor_name }}</td>
                            <td class="py-2 pr-3"><span class="cecap-badge bg-slate-100 text-slate-800">{{ $log->module }}</span> <span class="font-mono">{{ $log->event }}</span></td>
                            <td class="py-2 text-slate-600">{{ $log->reason ?: json_encode($log->new_values, JSON_UNESCAPED_UNICODE) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin eventos registrados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- TAB 4: PICKLISTS CR --}}
    @if($tab === 'picklists')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <form wire:submit="addPicklistItem" class="cecap-card space-y-3">
                <h2 class="text-base font-bold text-brand-950">Agregar Opción de Picklist CR</h2>
                <select wire:model="pick_group" class="cecap-input">
                    <option value="education_level">Nivel Educativo</option>
                    <option value="id_type">Tipo de Identificación</option>
                    <option value="payment_method">Método de Pago</option>
                    <option value="grade_rectification_reason">Motivo Rectificación Nota</option>
                    <option value="enrollment_rejection_reason">Motivo de Rechazo de Admisión</option>
                    <option value="full_capacity_decision">Decisión por Cupo Máximo Alcanzado</option>
                    <option value="document_category">Categoría Documental</option>
                    <option value="workshop_type">Tipo de Taller</option>
                </select>
                <input wire:model="pick_code" type="text" required class="cecap-input" placeholder="codigo_unico">
                <input wire:model="pick_label" type="text" required class="cecap-input" placeholder="Etiqueta visible">
                <button type="submit" class="cecap-btn-primary w-full">Agregar al Catálogo</button>

                <div class="border-t border-slate-200 pt-3 text-xs text-slate-600">
                    <strong>División Territorial CR Activa:</strong>
                    @foreach($provinces as $prov)
                        <span class="inline-block mr-2">{{ $prov->name }} ({{ $prov->cantons_count }})</span>
                    @endforeach
                </div>
            </form>

            <div class="cecap-card lg:col-span-2 overflow-x-auto max-h-[520px]">
                <h2 class="text-base font-bold text-slate-900 mb-3">Catálogos Administrables (Sin Borrado Físico)</h2>
                <table class="w-full text-xs text-left">
                    <thead class="border-b border-slate-200 uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Grupo</th><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Etiqueta</th><th class="py-2">Estado</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($picklists as $pk)
                            <tr>
                                <td class="py-2 pr-3 font-mono">{{ $pk->group_key }}</td>
                                <td class="py-2 pr-3 font-mono">{{ $pk->code }}</td>
                                <td class="py-2 pr-3 font-semibold">{{ $pk->label }}</td>
                                <td class="py-2">
                                    <button type="button" wire:click="togglePicklistActive({{ $pk->id }})" class="cecap-badge cursor-pointer {{ $pk->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $pk->is_active ? 'Activo' : 'Inactivo' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB 5: CONFIGURACIÓN INSTITUCIONAL, INSCRIPCIÓN EN LÍNEA, GÉNERO Y MÓDULOS GLOBALES --}}
    @if($tab === 'settings')
        <form wire:submit="saveSettings" class="cecap-card space-y-6">
            <h2 class="text-base font-bold text-brand-950">Tab 5 — Configuración Institucional, Parámetros de Inscripción y Módulos Globales</h2>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div><label class="block text-sm font-semibold text-slate-700 mb-1">Nombre Institución *</label><input wire:model="set_institution_name" type="text" required class="cecap-input"></div>
                <div><label class="block text-sm font-semibold text-slate-700 mb-1">Nombre Corto PWA *</label><input wire:model="set_short_name" type="text" required class="cecap-input"></div>
                <div><label class="block text-sm font-semibold text-slate-700 mb-1">Plazo Provisional en Línea (días) *</label><input wire:model="set_provisional_days" type="number" min="1" max="60" required class="cecap-input"></div>
                <div><label class="block text-sm font-semibold text-slate-700 mb-1">Teléfono</label><input wire:model="set_phone" type="text" class="cecap-input"></div>
                <div><label class="block text-sm font-semibold text-slate-700 mb-1">Correo Institucional</label><input wire:model="set_email" type="email" class="cecap-input"></div>
                <div><label class="block text-sm font-semibold text-slate-700 mb-1">Prefijo Recibos</label><input wire:model="set_receipt_prefix" type="text" class="cecap-input"></div>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Texto Parametrizable de Inscripción en Línea Provisional (7 días) *</label>
                    <textarea wire:model="set_online_notice" rows="3" required class="cecap-input"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Horario de Atención Presencial CeCap *</label>
                    <input wire:model="set_attention_hours" type="text" required class="cecap-input">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Mensaje Parametrizable de Parámetro de Género (/inscripcion) *</label>
                    <textarea wire:model="set_gender_policy_message" rows="2" required class="cecap-input"></textarea>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800 cursor-pointer">
                    <input type="checkbox" wire:model="set_require_solvency" class="h-4 w-4 rounded text-brand-900">
                    <span>Exigir solvencia financiera para graduación (salvo excepción de Dirección)</span>
                </label>
            </div>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
                <div class="text-sm font-bold text-slate-900">Activación / Desactivación Global de Módulos (institution_settings.enabled_modules)</div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 text-sm">
                    @foreach($modulesCatalog as $modKey => $modLabel)
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="set_enabled_modules.{{ $modKey }}" @disabled($modKey === 'direccion') class="h-4 w-4 rounded text-brand-900">
                            <span>{{ $modLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            @if($canCodeManage)
                <div class="rounded-xl border border-brand-300 bg-brand-50 p-4 text-xs text-brand-950">
                    <strong>Panel Exclusivo Super Administrador (<code>system.code.manage</code>):</strong>
                    PostgreSQL 16 (<code>pgcrypto</code> + <code>btree_gist</code>), Livewire 4 <code>csp_safe=true</code>, Triggers <code>BEFORE DELETE</code> y append-only activos.
                </div>
            @endif

            <button type="submit" class="cecap-btn-primary">Guardar Configuración Institucional</button>
        </form>
    @endif
</div>
