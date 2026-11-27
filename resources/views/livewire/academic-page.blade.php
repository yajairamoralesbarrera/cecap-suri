<div class="space-y-6">
    @if($isScopedCoordinator)
        <div class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-950">
            <strong>Alcance restringido por categoría:</strong> Como Coordinador de Área/Categoría, únicamente visualiza y administra sus categorías asignadas.
        </div>
    @endif

    @if($feedback)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ $feedback }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setTab('categories')" class="{{ $tab === 'categories' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Categorías ({{ $categories->count() }})</button>
        <button type="button" wire:click="setTab('courses')" class="{{ $tab === 'courses' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Cursos y Materias ({{ $courses->count() }})</button>
        <button type="button" wire:click="setTab('editions')" class="{{ $tab === 'editions' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Ediciones ({{ $editions->count() }})</button>
        <button type="button" wire:click="setTab('schedules')" class="{{ $tab === 'schedules' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Horarios Anti-Choque ({{ $schedules->count() }})</button>
        <button type="button" wire:click="setTab('classrooms')" class="{{ $tab === 'classrooms' ? 'cecap-btn-primary' : 'cecap-btn-secondary' }}">Aulas ({{ $classrooms->count() }})</button>
    </div>

    {{-- TAB: CATEGORÍAS --}}
    @if($tab === 'categories')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if(auth()->user()->hasEffectivePermission('academico.manage') && ! $isScopedCoordinator)
                <form wire:submit="createCategory" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Nueva Categoría Académica</h2>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Código *</label>
                        <input wire:model="cat_code" type="text" required class="cecap-input" placeholder="CAT-GASTRO">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre *</label>
                        <input wire:model="cat_name" type="text" required class="cecap-input" placeholder="Gastronomía y Repostería">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Descripción</label>
                        <textarea wire:model="cat_description" rows="2" class="cecap-input"></textarea>
                    </div>
                    <button type="submit" class="cecap-btn-primary w-full">Guardar Categoría</button>
                </form>
            @endif
            <div class="cecap-card lg:col-span-2 overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Catálogo de Categorías</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Nombre</th><th class="py-2 pr-3">Cursos</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($categories as $cat)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $cat->code }}</td>
                                <td class="py-2.5 pr-3 font-semibold text-slate-900">{{ $cat->name }}</td>
                                <td class="py-2.5 pr-3">{{ $cat->courses_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-slate-500">Sin categorías registradas aún.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: CURSOS Y MATERIAS --}}
    @if($tab === 'courses')
        <div class="space-y-6">
            @if(auth()->user()->hasEffectivePermission('academico.manage') && ! $editingCourseId)
                <form wire:submit="createCourse" class="cecap-card space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-brand-950">Registrar Curso Corto / Programa / Curso Técnico</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Categoría *</label>
                            <select wire:model="course_category_id" required class="cecap-input">
                                <option value="">-- Seleccione --</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Código del Curso *</label>
                            <input wire:model="course_code" type="text" required class="cecap-input" placeholder="TEC-ADM-01">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Tipo de Programa *</label>
                            <select wire:model.live="course_program_type" class="cecap-input">
                                <option value="curso_corto">Curso Corto</option>
                                <option value="programa">Programa (por módulos)</option>
                                <option value="curso_tecnico">Curso Técnico (por materias + atestados)</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre Oficial *</label>
                            <input wire:model="course_name" type="text" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Nivel Académico Previo Requerido *</label>
                            <select wire:model="course_education_level" class="cecap-input">
                                @foreach($educationLevels as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Capacidad Máxima por Defecto *</label>
                            <input wire:model="course_default_max_capacity" type="number" min="1" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Horas Lectivas / Meses</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input wire:model="course_duration_hours" type="number" min="1" class="cecap-input" placeholder="Horas">
                                <input wire:model="course_duration_months" type="number" min="1" class="cecap-input" placeholder="Meses">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Matrícula / Mensualidad (CRC)</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input wire:model="course_enrollment_fee" type="number" step="0.01" min="0" class="cecap-input" placeholder="Matrícula">
                                <input wire:model="course_monthly_fee" type="number" step="0.01" min="0" class="cecap-input" placeholder="Mensualidad">
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Descripción / Perfil del Curso o Programa</label>
                            <textarea wire:model="course_description" rows="2" class="cecap-input" placeholder="Descripción opcional del curso o programa"></textarea>
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4 space-y-2">
                        <div class="text-sm font-semibold text-slate-800">Checklist Mínimo de Documentos Requeridos (Configurable por Curso o Programa)</div>
                        <div class="flex flex-wrap gap-4 text-sm">
                            @foreach(['cedula_dimex', 'atestado_academico'] as $docKey)
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="course_required_documents" value="{{ $docKey }}" class="h-4 w-4 rounded text-brand-900">
                                    <span>{{ $documentTypes[$docKey] ?? $docKey }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @if(in_array($course_program_type, ['curso_tecnico', 'programa'], true))
                        @php $isPrograma = $course_program_type === 'programa'; @endphp
                        <div class="rounded-xl border {{ $isPrograma ? 'border-brand-300 bg-brand-50/70' : 'border-amber-300 bg-amber-50/70' }} p-4 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="text-sm font-bold {{ $isPrograma ? 'text-brand-950' : 'text-amber-950' }}">
                                        {{ $isPrograma ? 'Módulos del Programa *' : 'Materias Obligatorias del Curso Técnico *' }}
                                    </div>
                                    <div class="text-xs {{ $isPrograma ? 'text-brand-800' : 'text-amber-800' }}">
                                        {{ $isPrograma ? 'Los Programas son por módulo: defina al menos un módulo (MOD-01, MOD-02...). En Horas y Sesiones ingrese únicamente números.' : 'Debe definir al menos una materia para crear un Curso Técnico. En Horas y Sesiones ingrese únicamente números.' }}
                                    </div>
                                </div>
                                <button type="button" wire:click="addTechnicalSubjectRow" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">
                                    {{ $isPrograma ? '+ Agregar Módulo' : '+ Agregar Materia' }}
                                </button>
                            </div>

                            {{-- Encabezados claros de columnas en escritorio --}}
                            <div class="hidden sm:grid sm:grid-cols-12 gap-2 px-1 text-[11px] font-bold uppercase tracking-wide {{ $isPrograma ? 'text-brand-950' : 'text-amber-950' }}">
                                <div class="sm:col-span-2">Código</div>
                                <div class="sm:col-span-5">{{ $isPrograma ? 'Nombre del Módulo *' : 'Nombre de la Materia *' }}</div>
                                <div class="sm:col-span-2">Horas (solo #)</div>
                                <div class="sm:col-span-2">N.° Sesiones</div>
                                <div class="sm:col-span-1 text-center">Quitar</div>
                            </div>

                            @foreach($technical_subjects as $idx => $subj)
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end" wire:key="tech-subj-{{ $idx }}">
                                    <div class="sm:col-span-2">
                                        <label class="block sm:hidden text-xs font-semibold text-slate-800 mb-1">Código</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.code" type="text" class="cecap-input" placeholder="{{ $isPrograma ? 'MOD-01' : 'MAT-01' }}">
                                    </div>
                                    <div class="sm:col-span-5">
                                        <label class="block sm:hidden text-xs font-semibold text-slate-800 mb-1">{{ $isPrograma ? 'Nombre del Módulo *' : 'Nombre de la Materia *' }}</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.name" type="text" class="cecap-input" placeholder="{{ $isPrograma ? 'Ej. Módulo I: Bases de Emprendimiento' : 'Ej. Contabilidad I' }}">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block sm:hidden text-xs font-semibold text-slate-800 mb-1">Horas (solo número)</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.hours" type="number" min="1" step="1" class="cecap-input" placeholder="Ej. 60">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block sm:hidden text-xs font-semibold text-slate-800 mb-1">N.° Sesiones</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.sessions_count" type="number" min="1" step="1" class="cecap-input" placeholder="Ej. 12">
                                    </div>
                                    <div class="sm:col-span-1">
                                        <button type="button" wire:click="removeTechnicalSubjectRow({{ $idx }})" title="{{ $isPrograma ? 'Quitar módulo' : 'Quitar materia' }}" class="cecap-btn-secondary w-full !px-2 text-xs">✕</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="cecap-btn-primary">
                            {{ $course_program_type === 'programa' ? 'Guardar Programa' : 'Guardar Curso' }}
                        </button>
                    </div>
                </form>
            @endif

            <div class="cecap-card overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Cursos y Programas Registrados</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Código</th>
                            <th class="py-2 pr-3">Curso / Programa</th>
                            <th class="py-2 pr-3">Tipo</th>
                            <th class="py-2 pr-3">Nivel Previo</th>
                            <th class="py-2 pr-3">Cap. Defecto</th>
                            <th class="py-2 pr-3">Checklist</th>
                            <th class="py-2 pr-3">Materias / Módulos</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($courses as $c)
                            @php
                                $courseEnrolledCount = $c->totalEnrollmentsCount();
                                $courseActiveCount = $c->activeEnrollmentsCount();
                                $cIsPrograma = $c->program_type === 'programa';
                            @endphp
                            <tr wire:key="course-row-{{ $c->id }}" class="{{ ($editingCourseId === $c->id || $duplicatingCourseId === $c->id || $closingCourseId === $c->id || $deletingCourseId === $c->id || ($managingCourse && $managingCourse->id === $c->id)) ? 'bg-slate-50/80' : '' }}">
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">
                                    {{ $c->code }}
                                    @if($c->isClosed())
                                        <div class="mt-1"><span class="cecap-badge bg-amber-100 text-amber-950">CERRADO</span></div>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 font-semibold text-slate-900">
                                    {{ $c->name }}
                                    <div class="text-xs text-slate-500">{{ $c->category?->name }} · {{ $courseActiveCount }} inscrita(s) activa(s)</div>
                                    @if($c->isClosed() && $c->closure_reason)
                                        <div class="text-[11px] text-amber-800 font-medium mt-0.5">Cierre: {{ $c->closure_reason }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3"><span class="cecap-badge bg-brand-100 text-brand-900">{{ $c->program_type_label }}</span></td>
                                <td class="py-2.5 pr-3 text-xs">{{ $educationLevels[$c->required_education_level] ?? $c->required_education_level }}</td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $c->default_max_capacity }}</td>
                                <td class="py-2.5 pr-3 text-xs">
                                    {{ collect($c->required_documents ?? [])->map(fn ($dk) => $documentTypes[$dk] ?? $dk)->implode(', ') }}
                                </td>
                                <td class="py-2.5 pr-3 text-xs">
                                    @if($c->subjects->isNotEmpty())
                                        <div class="font-semibold text-slate-800">{{ $c->subjects->count() }} {{ $cIsPrograma ? 'módulo(s)' : 'materia(s)' }}</div>
                                        @foreach($c->subjects as $sub)
                                            <div class="text-[11px] text-slate-600">{{ $sub->code }} · {{ $sub->name }} ({{ $sub->hours }} h · {{ $sub->sessions_count }} ses.)</div>
                                        @endforeach
                                    @else
                                        <span class="text-slate-400">0</span>
                                    @endif
                                </td>
                                <td class="py-2.5">
                                    @if(auth()->user()->hasEffectivePermission('academico.manage'))
                                        <div class="flex flex-wrap gap-1.5">
                                            <button type="button" wire:click="editCourse({{ $c->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">Editar</button>
                                            <button type="button" wire:click="startDuplicateCourse({{ $c->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-indigo-300 bg-indigo-50 px-2.5 text-xs font-semibold text-indigo-800 hover:bg-indigo-100 cursor-pointer">Duplicar</button>
                                            <button type="button" wire:click="openCourseSubjects({{ $c->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">{{ $cIsPrograma ? '+ Módulos' : '+ Materias' }}</button>
                                            @if($c->isClosed())
                                                <button type="button" wire:click="reopenClosedCourse({{ $c->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-emerald-400 bg-emerald-50 px-2.5 text-xs font-semibold text-emerald-900 hover:bg-emerald-100 cursor-pointer">Reabrir</button>
                                            @else
                                                <button type="button" wire:click="startCloseCourse({{ $c->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-amber-400 bg-amber-50 px-2.5 text-xs font-semibold text-amber-950 hover:bg-amber-100 cursor-pointer">Cerrar Curso</button>
                                            @endif
                                            @if($courseEnrolledCount === 0)
                                                <button type="button" wire:click="startDeleteCourse({{ $c->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-rose-300 bg-white px-2.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 cursor-pointer">Eliminar</button>
                                            @endif
                                            <button type="button" wire:click="toggleCourseActive({{ $c->id }})" class="cecap-badge cursor-pointer {{ $c->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-600' }}">{{ $c->is_active ? 'Activo' : 'Inactivo' }}</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if($editingCourseId === $c->id)
                                <tr wire:key="course-edit-{{ $c->id }}">
                                    <td colspan="8" class="py-3 px-1">
                                        <form wire:submit="createCourse" class="rounded-2xl border-2 border-brand-700 bg-brand-50/60 p-4 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <h3 class="text-sm sm:text-base font-bold text-brand-950">Editar Características del Curso {{ $c->code }} (Cambio Auditado)</h3>
                                                <button type="button" wire:click="cancelEditCourse" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar Edición</button>
                                            </div>
                                            @if($errors->any())
                                                <div class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                                    <ul class="list-disc pl-4 space-y-0.5">
                                                        @foreach($errors->all() as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Categoría *</label>
                                                    <select wire:model="course_category_id" required class="cecap-input">
                                                        <option value="">-- Seleccione --</option>
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Código del Curso (inmutable)</label>
                                                    <input wire:model="course_code" type="text" readonly class="cecap-input bg-slate-100 text-slate-600 cursor-not-allowed font-mono">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de Programa *</label>
                                                    <select wire:model.live="course_program_type" class="cecap-input">
                                                        <option value="curso_corto">Curso Corto</option>
                                                        <option value="programa">Programa</option>
                                                        <option value="curso_tecnico">Curso Técnico (exige materias + atestados)</option>
                                                    </select>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Oficial *</label>
                                                    <input wire:model="course_name" type="text" required class="cecap-input">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nivel Académico Previo Requerido *</label>
                                                    <select wire:model="course_education_level" class="cecap-input">
                                                        @foreach($educationLevels as $code => $label)
                                                            <option value="{{ $code }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Capacidad Máxima por Defecto *</label>
                                                    <input wire:model="course_default_max_capacity" type="number" min="1" required class="cecap-input">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Horas Lectivas / Meses</label>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <input wire:model="course_duration_hours" type="number" min="1" class="cecap-input" placeholder="Horas">
                                                        <input wire:model="course_duration_months" type="number" min="1" class="cecap-input" placeholder="Meses">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Matrícula / Mensualidad (CRC)</label>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <input wire:model="course_enrollment_fee" type="number" step="0.01" min="0" class="cecap-input" placeholder="Matrícula">
                                                        <input wire:model="course_monthly_fee" type="number" step="0.01" min="0" class="cecap-input" placeholder="Mensualidad">
                                                    </div>
                                                </div>
                                                <div class="md:col-span-3">
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Descripción / Perfil del Curso</label>
                                                    <textarea wire:model="course_description" rows="2" class="cecap-input"></textarea>
                                                </div>
                                            </div>
                                            <div class="rounded-xl bg-white p-3 border border-slate-200 space-y-2">
                                                <div class="text-xs font-semibold text-slate-800">Checklist Mínimo de Documentos Requeridos</div>
                                                <div class="flex flex-wrap gap-4 text-xs sm:text-sm">
                                                    @foreach(['cedula_dimex', 'atestado_academico'] as $docKey)
                                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                                            <input type="checkbox" wire:model="course_required_documents" value="{{ $docKey }}" class="h-4 w-4 rounded text-brand-900">
                                                            <span>{{ $documentTypes[$docKey] ?? $docKey }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" class="cecap-btn-primary text-xs sm:text-sm">Guardar Cambios del Curso</button>
                                                <button type="button" wire:click="cancelEditCourse" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                            @if($duplicatingCourseId === $c->id)
                                <tr wire:key="course-dup-{{ $c->id }}">
                                    <td colspan="8" class="py-3 px-1">
                                        <form wire:submit="saveDuplicatedCourse" class="rounded-2xl border-2 border-indigo-500 bg-indigo-50/70 p-4 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h3 class="text-sm sm:text-base font-bold text-indigo-950">Duplicar Curso {{ $c->code }} → Nuevo Código Incremental</h3>
                                                    <p class="text-xs text-indigo-800">Todos los datos y materias fueron copiados. Puede dejarlos igual o modificar cualquier campo antes de crear el nuevo curso.</p>
                                                </div>
                                                <button type="button" wire:click="cancelDuplicateCourse" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                            </div>
                                            @if($errors->any())
                                                <div class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                                    <ul class="list-disc pl-4 space-y-0.5">
                                                        @foreach($errors->all() as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Categoría *</label>
                                                    <select wire:model="dup_category_id" required class="cecap-input">
                                                        <option value="">-- Seleccione --</option>
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-indigo-900 mb-1">Código Incremental del Nuevo Curso *</label>
                                                    <input wire:model="dup_code" type="text" required class="cecap-input font-mono font-bold border-indigo-400">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Tipo de Programa *</label>
                                                    <select wire:model.live="dup_program_type" class="cecap-input">
                                                        <option value="curso_corto">Curso Corto</option>
                                                        <option value="programa">Programa</option>
                                                        <option value="curso_tecnico">Curso Técnico (exige materias + atestados)</option>
                                                    </select>
                                                </div>
                                                <div class="md:col-span-2">
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Oficial *</label>
                                                    <input wire:model="dup_name" type="text" required class="cecap-input">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nivel Académico Previo Requerido *</label>
                                                    <select wire:model="dup_education_level" class="cecap-input">
                                                        @foreach($educationLevels as $code => $label)
                                                            <option value="{{ $code }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Capacidad Máxima por Defecto *</label>
                                                    <input wire:model="dup_default_max_capacity" type="number" min="1" required class="cecap-input">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Horas Lectivas / Meses</label>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <input wire:model="dup_duration_hours" type="number" min="1" class="cecap-input" placeholder="Horas">
                                                        <input wire:model="dup_duration_months" type="number" min="1" class="cecap-input" placeholder="Meses">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Matrícula / Mensualidad (CRC)</label>
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <input wire:model="dup_enrollment_fee" type="number" step="0.01" min="0" class="cecap-input" placeholder="Matrícula">
                                                        <input wire:model="dup_monthly_fee" type="number" step="0.01" min="0" class="cecap-input" placeholder="Mensualidad">
                                                    </div>
                                                </div>
                                                <div class="md:col-span-3">
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Descripción / Perfil del Curso</label>
                                                    <textarea wire:model="dup_description" rows="2" class="cecap-input"></textarea>
                                                </div>
                                            </div>
                                            <div class="rounded-xl bg-white p-3 border border-slate-200 space-y-2">
                                                <div class="text-xs font-semibold text-slate-800">Checklist Mínimo de Documentos Requeridos</div>
                                                <div class="flex flex-wrap gap-4 text-xs sm:text-sm">
                                                    @foreach(['cedula_dimex', 'atestado_academico'] as $docKey)
                                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                                            <input type="checkbox" wire:model="dup_required_documents" value="{{ $docKey }}" class="h-4 w-4 rounded text-brand-900">
                                                            <span>{{ $documentTypes[$docKey] ?? $docKey }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div class="rounded-xl border border-indigo-200 bg-white p-3 space-y-3">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div>
                                                        <div class="text-xs sm:text-sm font-bold text-indigo-950">Materias del Curso Duplicado ({{ count($dup_subjects) }})</div>
                                                        <div class="text-[11px] text-slate-600">Se copian automáticamente del curso original. Puede dejarlas igual, editarlas, duplicar una fila con código incremental o agregar nuevas.</div>
                                                    </div>
                                                    <button type="button" wire:click="addDupSubjectRow" class="cecap-btn-secondary !min-h-8 !px-3 text-xs">+ Agregar Materia</button>
                                                </div>

                                                @if(! empty($dup_subjects))
                                                    <div class="hidden sm:grid sm:grid-cols-12 gap-2 px-1 text-[11px] font-bold uppercase tracking-wide text-slate-600">
                                                        <div class="sm:col-span-2">Código</div>
                                                        <div class="sm:col-span-4">Nombre de la Materia *</div>
                                                        <div class="sm:col-span-2">Horas</div>
                                                        <div class="sm:col-span-2">Sesiones</div>
                                                        <div class="sm:col-span-2 text-center">Acciones</div>
                                                    </div>
                                                    @foreach($dup_subjects as $idx => $dsub)
                                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end" wire:key="dup-subj-{{ $idx }}">
                                                            <div class="sm:col-span-2">
                                                                <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">Código</label>
                                                                <input wire:model="dup_subjects.{{ $idx }}.code" type="text" class="cecap-input font-mono text-xs" placeholder="MAT-01">
                                                            </div>
                                                            <div class="sm:col-span-4">
                                                                <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">Nombre *</label>
                                                                <input wire:model="dup_subjects.{{ $idx }}.name" type="text" class="cecap-input text-xs" placeholder="Nombre de la materia">
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">Horas</label>
                                                                <input wire:model="dup_subjects.{{ $idx }}.hours" type="number" min="1" step="1" class="cecap-input text-xs">
                                                            </div>
                                                            <div class="sm:col-span-2">
                                                                <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">Sesiones</label>
                                                                <input wire:model="dup_subjects.{{ $idx }}.sessions_count" type="number" min="1" step="1" class="cecap-input text-xs">
                                                            </div>
                                                            <div class="sm:col-span-2 flex gap-1">
                                                                <button type="button" wire:click="duplicateDupSubjectRow({{ $idx }})" title="Duplicar materia con código incremental" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-xl border border-indigo-300 bg-indigo-50 px-2 text-[11px] font-semibold text-indigo-800 hover:bg-indigo-100 cursor-pointer">Duplicar</button>
                                                                <button type="button" wire:click="removeDupSubjectRow({{ $idx }})" title="Quitar materia" class="cecap-btn-secondary !min-h-10 !px-2.5 text-xs">✕</button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <p class="text-xs text-slate-500 italic">El curso original no tiene materias registradas. Puede guardar así o agregar materias con "+ Agregar Materia".</p>
                                                @endif
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-700 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-indigo-800 cursor-pointer">Crear Curso Duplicado</button>
                                                <button type="button" wire:click="cancelDuplicateCourse" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                            @if($closingCourse && $closingCourse->id === $c->id)
                                <tr wire:key="course-close-{{ $c->id }}">
                                    <td colspan="8" class="py-3 px-1">
                                        <form wire:submit="confirmCloseCourse" class="rounded-2xl border-2 border-amber-500 bg-amber-50/80 p-4 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h3 class="text-sm sm:text-base font-bold text-amber-950">Cerrar Curso por Decisión Institucional — {{ $closingCourse->code }} · {{ $closingCourse->name }}</h3>
                                                    <p class="text-xs text-amber-900">Se cerrará el curso y se ocultarán sus ediciones abiertas para matrícula, incluso si tiene alumnas inscritas.</p>
                                                </div>
                                                <button type="button" wire:click="cancelCloseCourse" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                            </div>

                                            @if($errors->any())
                                                <div class="rounded-xl border border-rose-300 bg-white px-3 py-2 text-xs text-rose-800">
                                                    <ul class="list-disc pl-4 space-y-0.5">
                                                        @foreach($errors->all() as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            <div class="rounded-xl border border-amber-300 bg-white px-3 py-2.5 text-xs text-slate-800">
                                                <strong>Impacto:</strong>
                                                Hay <strong>{{ $closingNotifiableCount }} alumna(s) inscrita(s) / en espera</strong> asociadas a este curso.
                                                @if($closingNotifiableCount > 0)
                                                    Se enviará un correo automático con el mensaje institucional indicado abajo para que se mantengan pendientes de la nueva oferta.
                                                @endif
                                            </div>

                                            <div class="space-y-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo del Cierre Institucional (Auditoría) *</label>
                                                    <input wire:model="course_closure_reason" type="text" required class="cecap-input" placeholder="Ej. Reprogramación académica / cupo mínimo no alcanzado por decisión institucional">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Mensaje que recibirá cada alumna por correo *</label>
                                                    <textarea wire:model="course_closure_message" rows="3" required class="cecap-input"></textarea>
                                                </div>
                                                @if($closingNotifiableCount > 0)
                                                    <label class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-amber-950 cursor-pointer">
                                                        <input type="checkbox" wire:model="course_closure_notify_students" class="h-4 w-4 rounded text-amber-700">
                                                        <span>Enviar correo de notificación a las {{ $closingNotifiableCount }} alumna(s) inscrita(s)</span>
                                                    </label>
                                                @endif
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-700 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-amber-800 cursor-pointer">
                                                    {{ $closingNotifiableCount > 0 && $course_closure_notify_students ? 'Confirmar Cierre y Notificar Alumnas' : 'Confirmar Cierre Institucional' }}
                                                </button>
                                                <button type="button" wire:click="cancelCloseCourse" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                            @if($deletingCourse && $deletingCourse->id === $c->id)
                                <tr wire:key="course-delete-{{ $c->id }}">
                                    <td colspan="8" class="py-3 px-1">
                                        <form wire:submit="confirmDeleteCourse" class="rounded-2xl border-2 border-rose-400 bg-rose-50/80 p-4 space-y-3">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h3 class="text-sm sm:text-base font-bold text-rose-950">Eliminar Curso {{ $deletingCourse->code }} — {{ $deletingCourse->name }}</h3>
                                                    <p class="text-xs text-rose-900">Este curso tiene <strong>0 alumnas inscritas</strong>. Se retirará de la oferta académica manteniendo la traza en auditoría.</p>
                                                </div>
                                                <button type="button" wire:click="cancelDeleteCourse" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-slate-700 mb-1">Motivo de eliminación (opcional, para auditoría)</label>
                                                <input wire:model="course_delete_reason" type="text" class="cecap-input" placeholder="Ej. Curso de prueba / creado por error sin alumnas inscritas">
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-700 px-4 py-2 text-xs sm:text-sm font-semibold text-white hover:bg-rose-800 cursor-pointer">
                                                    Confirmar Eliminación del Curso
                                                </button>
                                                <button type="button" wire:click="cancelDeleteCourse" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                            @if($managingCourse && $managingCourse->id === $c->id)
                                <tr wire:key="course-subjects-{{ $c->id }}">
                                    <td colspan="8" class="py-3 px-1">
                                        <div class="rounded-2xl border-2 border-brand-700 bg-brand-50/50 p-4 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h3 class="text-sm sm:text-base font-bold text-brand-950">Gestionar Materias — {{ $managingCourse->code }} · {{ $managingCourse->name }}</h3>
                                                    <p class="text-xs text-slate-600">Agregue, edite o duplique materias con código incremental (sin borrado físico).</p>
                                                </div>
                                                <button type="button" wire:click="closeCourseSubjects" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cerrar</button>
                                            </div>

                                            @if($errors->any())
                                                <div class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-800">
                                                    <ul class="list-disc pl-4 space-y-0.5">
                                                        @foreach($errors->all() as $error)
                                                            <li>{{ $error }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif

                                            @if(auth()->user()->hasEffectivePermission('academico.manage'))
                                                <form wire:submit="addSubjectToCourse" class="space-y-2">
                                                    <div class="hidden sm:grid sm:grid-cols-12 gap-2 px-1 text-[11px] font-bold uppercase tracking-wide text-brand-950">
                                                        <div class="sm:col-span-2">Código *</div>
                                                        <div class="sm:col-span-4">Nombre de la Materia *</div>
                                                        <div class="sm:col-span-2">Horas (solo #) *</div>
                                                        <div class="sm:col-span-2">N.° Sesiones *</div>
                                                        <div class="sm:col-span-2">Acción</div>
                                                    </div>
                                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                                                        <div class="sm:col-span-2">
                                                            <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">Código *</label>
                                                            <input wire:model="new_sub_code" type="text" required class="cecap-input" placeholder="MAT-02">
                                                        </div>
                                                        <div class="sm:col-span-4">
                                                            <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">Nombre de la Materia *</label>
                                                            <input wire:model="new_sub_name" type="text" required class="cecap-input" placeholder="Ej. Legislación CR">
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">Horas (solo #) *</label>
                                                            <input wire:model="new_sub_hours" type="number" min="1" step="1" required class="cecap-input">
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <label class="block sm:hidden text-xs font-semibold text-slate-700 mb-1">N.° Sesiones *</label>
                                                            <input wire:model="new_sub_sessions" type="number" min="1" step="1" required class="cecap-input">
                                                        </div>
                                                        <div class="sm:col-span-2">
                                                            <button type="submit" class="cecap-btn-primary w-full text-xs">+ Agregar Materia</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            @endif

                                            <div class="overflow-x-auto bg-white rounded-xl border border-slate-200 p-3">
                                                <table class="w-full text-xs text-left">
                                                    <thead class="border-b border-slate-200 uppercase text-slate-500">
                                                        <tr><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Materia</th><th class="py-2 pr-3">Horas</th><th class="py-2 pr-3">N.° Sesiones</th><th class="py-2">Acciones / Estado</th></tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        @forelse($managingCourse->subjects as $sub)
                                                            <tr wire:key="sub-row-{{ $sub->id }}" class="{{ ($editingSubjectId === $sub->id || $duplicatingSubjectId === $sub->id) ? 'bg-slate-50/80' : '' }}">
                                                                <td class="py-2 pr-3 font-mono font-bold">{{ $sub->code }}</td>
                                                                <td class="py-2 pr-3 font-semibold text-slate-900">{{ $sub->name }}</td>
                                                                <td class="py-2 pr-3">{{ $sub->hours }} h</td>
                                                                <td class="py-2 pr-3">{{ $sub->sessions_count }} sesiones</td>
                                                                <td class="py-2">
                                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                                        @if(auth()->user()->hasEffectivePermission('academico.manage'))
                                                                            <button type="button" wire:click="startEditSubject({{ $sub->id }})" class="cecap-btn-secondary !min-h-8 !px-2 text-[11px]">Editar</button>
                                                                            <button type="button" wire:click="startDuplicateSubject({{ $sub->id }})" class="inline-flex min-h-8 items-center justify-center rounded-lg border border-indigo-300 bg-indigo-50 px-2 text-[11px] font-semibold text-indigo-800 hover:bg-indigo-100 cursor-pointer">Duplicar</button>
                                                                        @endif
                                                                        <button type="button" wire:click="toggleSubjectActive({{ $sub->id }})" class="cecap-badge cursor-pointer {{ $sub->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-600' }}">
                                                                            {{ $sub->is_active ? 'Activa' : 'Inactiva' }}
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                            @if($editingSubjectId === $sub->id)
                                                                <tr wire:key="sub-edit-{{ $sub->id }}">
                                                                    <td colspan="5" class="py-2 px-1">
                                                                        <form wire:submit="saveSubjectEdit" class="rounded-xl border border-brand-500 bg-brand-50/70 p-3 space-y-2">
                                                                            <div class="text-xs font-bold text-brand-950">Editar Materia {{ $sub->code }} (código inmutable)</div>
                                                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                                                                                <div class="sm:col-span-2">
                                                                                    <label class="block text-[11px] font-semibold text-slate-700 mb-0.5">Código</label>
                                                                                    <input wire:model="edit_sub_code" type="text" readonly class="cecap-input bg-slate-100 text-slate-600 cursor-not-allowed font-mono text-xs">
                                                                                </div>
                                                                                <div class="sm:col-span-4">
                                                                                    <label class="block text-[11px] font-semibold text-slate-700 mb-0.5">Nombre *</label>
                                                                                    <input wire:model="edit_sub_name" type="text" required class="cecap-input text-xs">
                                                                                </div>
                                                                                <div class="sm:col-span-2">
                                                                                    <label class="block text-[11px] font-semibold text-slate-700 mb-0.5">Horas *</label>
                                                                                    <input wire:model="edit_sub_hours" type="number" min="1" required class="cecap-input text-xs">
                                                                                </div>
                                                                                <div class="sm:col-span-2">
                                                                                    <label class="block text-[11px] font-semibold text-slate-700 mb-0.5">Sesiones *</label>
                                                                                    <input wire:model="edit_sub_sessions" type="number" min="1" required class="cecap-input text-xs">
                                                                                </div>
                                                                                <div class="sm:col-span-2 flex gap-1">
                                                                                    <button type="submit" class="cecap-btn-primary !min-h-9 flex-1 !px-2 text-[11px]">Guardar</button>
                                                                                    <button type="button" wire:click="cancelEditSubject" class="cecap-btn-secondary !min-h-9 !px-2 text-[11px]">✕</button>
                                                                                </div>
                                                                            </div>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                            @if($duplicatingSubjectId === $sub->id)
                                                                <tr wire:key="sub-dup-{{ $sub->id }}">
                                                                    <td colspan="5" class="py-2 px-1">
                                                                        <form wire:submit="saveDuplicatedSubject" class="rounded-xl border border-indigo-400 bg-indigo-50/80 p-3 space-y-2">
                                                                            <div class="text-xs font-bold text-indigo-950">Duplicar Materia {{ $sub->code }} → Código Incremental (edite o deje igual)</div>
                                                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                                                                                <div class="sm:col-span-2">
                                                                                    <label class="block text-[11px] font-semibold text-indigo-900 mb-0.5">Código Nuevo *</label>
                                                                                    <input wire:model="dup_sub_code" type="text" required class="cecap-input font-mono font-bold text-xs border-indigo-400">
                                                                                </div>
                                                                                <div class="sm:col-span-4">
                                                                                    <label class="block text-[11px] font-semibold text-slate-700 mb-0.5">Nombre *</label>
                                                                                    <input wire:model="dup_sub_name" type="text" required class="cecap-input text-xs">
                                                                                </div>
                                                                                <div class="sm:col-span-2">
                                                                                    <label class="block text-[11px] font-semibold text-slate-700 mb-0.5">Horas *</label>
                                                                                    <input wire:model="dup_sub_hours" type="number" min="1" required class="cecap-input text-xs">
                                                                                </div>
                                                                                <div class="sm:col-span-2">
                                                                                    <label class="block text-[11px] font-semibold text-slate-700 mb-0.5">Sesiones *</label>
                                                                                    <input wire:model="dup_sub_sessions" type="number" min="1" required class="cecap-input text-xs">
                                                                                </div>
                                                                                <div class="sm:col-span-2 flex gap-1">
                                                                                    <button type="submit" class="inline-flex min-h-9 flex-1 items-center justify-center rounded-xl bg-indigo-700 px-2 text-[11px] font-semibold text-white hover:bg-indigo-800 cursor-pointer">Duplicar</button>
                                                                                    <button type="button" wire:click="cancelDuplicateSubject" class="cecap-btn-secondary !min-h-9 !px-2 text-[11px]">✕</button>
                                                                                </div>
                                                                            </div>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                        @empty
                                                            <tr><td colspan="5" class="py-4 text-center text-slate-500">Este curso aún no tiene materias registradas.</td></tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8" class="py-6 text-center text-slate-500">Sin cursos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: EDICIONES --}}
    @if($tab === 'editions')
        <div class="space-y-6">
            @if(auth()->user()->hasEffectivePermission('academico.manage'))
                <form wire:submit="createEdition" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Abrir Nueva Edición (Autollenado de Capacidad y Tarifas)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Curso Base *</label>
                            <select wire:model.live="ed_course_id" required class="cecap-input">
                                <option value="">-- Seleccione curso --</option>
                                @foreach($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Código Edición *</label>
                            <input wire:model="ed_code" type="text" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre Edición *</label>
                            <input wire:model="ed_name" type="text" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Turno *</label>
                            <select wire:model="ed_shift_id" required class="cecap-input">
                                @foreach($shifts as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Modalidad *</label>
                            <select wire:model="ed_modality_id" required class="cecap-input">
                                @foreach($modalities as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Capacidad Máxima (Autollenada) *</label>
                            <input wire:model="ed_max_capacity" type="number" min="1" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Aula</label>
                            <select wire:model="ed_classroom_id" class="cecap-input">
                                <option value="">-- Sin asignar --</option>
                                @foreach($classrooms as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }} (Cap. {{ $r->capacity }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Profesora Titular</label>
                            <select wire:model="ed_teacher_id" class="cecap-input">
                                <option value="">-- Sin asignar --</option>
                                @foreach($teachers as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Inicio *</label>
                                <input wire:model="ed_start_date" type="date" required class="cecap-input">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Fin *</label>
                                <input wire:model="ed_end_date" type="date" required class="cecap-input">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Matrícula (CRC)</label>
                            <input wire:model="ed_enrollment_fee" type="number" step="0.01" class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Mensualidad (CRC)</label>
                            <input wire:model="ed_monthly_fee" type="number" step="0.01" class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Cuotas</label>
                            <input wire:model="ed_installments_count" type="number" min="1" class="cecap-input">
                        </div>
                    </div>
                    <button type="submit" class="cecap-btn-primary">Crear Edición</button>
                </form>
            @endif

            <div class="cecap-card overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Ediciones Activas</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Código</th>
                            <th class="py-2 pr-3">Edición</th>
                            <th class="py-2 pr-3">Turno / Mod.</th>
                            <th class="py-2 pr-3">Profesora</th>
                            <th class="py-2 pr-3">Cupos</th>
                            <th class="py-2 pr-3">Horarios</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($editions as $ed)
                            <tr wire:key="edition-row-{{ $ed->id }}" class="{{ $editingEditionId === $ed->id ? 'bg-slate-50/80' : '' }}">
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $ed->code }}</td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $ed->name }}<div class="text-xs text-slate-500">{{ $ed->start_date?->format('d/m/Y') }} - {{ $ed->end_date?->format('d/m/Y') }}</div></td>
                                <td class="py-2.5 pr-3 text-xs">{{ $ed->shift?->name }} · {{ $ed->modality?->name }}</td>
                                <td class="py-2.5 pr-3 text-xs">{{ $ed->teacher?->name ?? 'Por asignar' }}</td>
                                <td class="py-2.5 pr-3 text-xs font-bold">
                                    {{ $ed->activeEnrollmentsCount() }} / {{ $ed->max_capacity }}
                                    @if($ed->waitlistCount() > 0)
                                        <div class="text-[11px] font-semibold text-indigo-700">Lista espera: {{ $ed->waitlistCount() }}</div>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 text-xs">
                                    @foreach($ed->schedules as $sch)
                                        <div>{{ $sch->formatted_slot }}</div>
                                    @endforeach
                                </td>
                                <td class="py-2.5">
                                    @if(auth()->user()->hasEffectivePermission('academico.manage'))
                                        <div class="flex flex-wrap gap-1.5">
                                            <button type="button" wire:click="editEdition({{ $ed->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">Editar</button>
                                            <button type="button" wire:click="toggleEditionPublicOpen({{ $ed->id }})" class="cecap-badge cursor-pointer {{ $ed->is_public_enrollment_open ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-600' }}">
                                                {{ $ed->is_public_enrollment_open ? 'En línea: Abierta' : 'En línea: Oculta' }}
                                            </button>
                                            <button type="button" wire:click="closeEdition({{ $ed->id }})" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-rose-300 bg-white px-2.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 cursor-pointer">
                                                {{ $ed->activeEnrollmentsCount() === 0 ? 'Retirar' : 'Cerrar Edición' }}
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if($editingEditionId === $ed->id)
                                <tr wire:key="edition-edit-{{ $ed->id }}">
                                    <td colspan="7" class="py-3 px-1">
                                        <form wire:submit="saveEditionEdit" class="rounded-2xl border-2 border-brand-700 bg-brand-50/60 p-4 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <h3 class="text-sm sm:text-base font-bold text-brand-950">Editar Edición {{ $ed->code }} (Mantiene mismo código — cambio auditado)</h3>
                                                <button type="button" wire:click="cancelEditEdition" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar</button>
                                            </div>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Código Edición (inmutable)</label>
                                                    <input wire:model="edit_ed_code" type="text" readonly class="cecap-input bg-slate-100 text-slate-600 cursor-not-allowed font-mono">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Edición *</label>
                                                    <input wire:model="edit_ed_name" type="text" required class="cecap-input">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Capacidad Máxima (Cupos) *</label>
                                                    <input wire:model="edit_ed_max_capacity" type="number" min="1" required class="cecap-input">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Turno *</label>
                                                    <select wire:model="edit_ed_shift_id" required class="cecap-input">
                                                        @foreach($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Modalidad *</label>
                                                    <select wire:model="edit_ed_modality_id" required class="cecap-input">
                                                        @foreach($modalities as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Aula</label>
                                                    <select wire:model="edit_ed_classroom_id" class="cecap-input">
                                                        <option value="">-- Sin asignar --</option>
                                                        @foreach($classrooms as $r)<option value="{{ $r->id }}">{{ $r->name }} (Cap. {{ $r->capacity }})</option>@endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">Profesora Titular</label>
                                                    <select wire:model="edit_ed_teacher_id" class="cecap-input">
                                                        <option value="">-- Sin asignar --</option>
                                                        @foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                                                    </select>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Inicio *</label>
                                                        <input wire:model="edit_ed_start_date" type="date" required class="cecap-input">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Fin *</label>
                                                        <input wire:model="edit_ed_end_date" type="date" required class="cecap-input">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-3 gap-2">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Matrícula</label>
                                                        <input wire:model="edit_ed_enrollment_fee" type="number" step="0.01" class="cecap-input">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mensualidad</label>
                                                        <input wire:model="edit_ed_monthly_fee" type="number" step="0.01" class="cecap-input">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-slate-700 mb-1">Cuotas</label>
                                                        <input wire:model="edit_ed_installments_count" type="number" min="1" class="cecap-input">
                                                    </div>
                                                </div>
                                            </div>
                                            <label class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-slate-700 cursor-pointer">
                                                <input type="checkbox" wire:model="edit_ed_public_open" class="h-4 w-4 rounded text-brand-900">
                                                <span>Visible y abierta para inscripción en línea (/inscripcion)</span>
                                            </label>
                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" class="cecap-btn-primary text-xs sm:text-sm">Guardar Cambios de la Edición</button>
                                                <button type="button" wire:click="cancelEditEdition" class="cecap-btn-secondary text-xs sm:text-sm">Cancelar</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-500">Sin ediciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: HORARIOS ANTI-CHOQUE --}}
    @if($tab === 'schedules')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if(auth()->user()->hasEffectivePermission('academico.schedules.manage'))
                <form wire:submit="createSchedule" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Asignar Bloque Horario (PostgreSQL GiST)</h2>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Edición *</label>
                        <select wire:model.live="sch_edition_id" required class="cecap-input">
                            <option value="">-- Seleccione --</option>
                            @foreach($editions as $ed)
                                <option value="{{ $ed->id }}">{{ $ed->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Aula (bloqueo anti-choque)</label>
                        <select wire:model="sch_classroom_id" class="cecap-input">
                            <option value="">-- Ninguna / Virtual --</option>
                            @foreach($classrooms as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Profesora (bloqueo anti-choque)</label>
                        <select wire:model="sch_teacher_id" class="cecap-input">
                            <option value="">-- Ninguna --</option>
                            @foreach($teachers as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Día *</label>
                        <select wire:model="sch_day_of_week" class="cecap-input">
                            @foreach($daysOfWeek as $num => $dayName)
                                <option value="{{ $num }}">{{ $dayName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Hora Inicio *</label>
                            <input wire:model="sch_start_time" type="time" required class="cecap-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Hora Fin *</label>
                            <input wire:model="sch_end_time" type="time" required class="cecap-input">
                        </div>
                    </div>
                    <button type="submit" class="cecap-btn-primary w-full">Validar y Asignar Horario</button>
                </form>
            @endif

            <div class="cecap-card lg:col-span-2 overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Horarios Vigentes Protegidos</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Horario</th><th class="py-2 pr-3">Edición</th><th class="py-2 pr-3">Aula</th><th class="py-2">Profesora</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($schedules as $sch)
                            <tr>
                                <td class="py-2.5 pr-3 font-semibold text-brand-950">{{ $sch->formatted_slot }}</td>
                                <td class="py-2.5 pr-3">{{ $sch->edition?->name }}</td>
                                <td class="py-2.5 pr-3 text-xs">{{ $sch->classroom?->name ?? 'Virtual' }}</td>
                                <td class="py-2.5 text-xs">{{ $sch->teacher?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin horarios asignados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- TAB: AULAS --}}
    @if($tab === 'classrooms')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @if(auth()->user()->hasEffectivePermission('academico.manage'))
                <form wire:submit="createClassroom" class="cecap-card space-y-4">
                    <h2 class="text-base font-bold text-brand-950">Nueva Aula / Laboratorio</h2>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Código *</label>
                        <input wire:model="room_code" type="text" required class="cecap-input" placeholder="AULA-101">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre *</label>
                        <input wire:model="room_name" type="text" required class="cecap-input" placeholder="Laboratorio de Cómputo">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Capacidad *</label>
                        <input wire:model="room_capacity" type="number" min="1" required class="cecap-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Ubicación</label>
                        <input wire:model="room_location" type="text" class="cecap-input">
                    </div>
                    <button type="submit" class="cecap-btn-primary w-full">Guardar Aula</button>
                </form>
            @endif
            <div class="cecap-card lg:col-span-2 overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Aulas Disponibles</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Aula</th><th class="py-2 pr-3">Capacidad</th><th class="py-2">Ubicación</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($classrooms as $r)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $r->code }}</td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $r->name }}</td>
                                <td class="py-2.5 pr-3">{{ $r->capacity }}</td>
                                <td class="py-2.5 text-xs">{{ $r->location ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">Sin aulas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
