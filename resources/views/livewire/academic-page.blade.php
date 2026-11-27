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
            @if(auth()->user()->hasEffectivePermission('academico.manage'))
                <form wire:submit="createCourse" class="cecap-card space-y-4 {{ $editingCourseId ? 'border-brand-700 bg-brand-50/30' : '' }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold text-brand-950">
                            {{ $editingCourseId ? 'Editar Características del Curso (Cambio Auditado)' : 'Registrar Curso Corto / Programa / Curso Técnico' }}
                        </h2>
                        @if($editingCourseId)
                            <button type="button" wire:click="cancelEditCourse" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cancelar Edición</button>
                        @endif
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
                                <option value="programa">Programa</option>
                                <option value="curso_tecnico">Curso Técnico (exige materias + atestados)</option>
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
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Descripción / Perfil del Curso</label>
                            <textarea wire:model="course_description" rows="2" class="cecap-input" placeholder="Descripción opcional del curso o programa"></textarea>
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4 space-y-2">
                        <div class="text-sm font-semibold text-slate-800">Checklist Mínimo de Documentos Requeridos (Configurable por Curso)</div>
                        <div class="flex flex-wrap gap-4 text-sm">
                            @foreach(['cedula_dimex', 'atestado_academico'] as $docKey)
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="course_required_documents" value="{{ $docKey }}" class="h-4 w-4 rounded text-brand-900">
                                    <span>{{ $documentTypes[$docKey] ?? $docKey }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @if($course_program_type === 'curso_tecnico' && ! $editingCourseId)
                        <div class="rounded-xl border border-amber-300 bg-amber-50/70 p-4 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="text-sm font-bold text-amber-950">Materias Obligatorias del Curso Técnico *</div>
                                    <div class="text-xs text-amber-800">Debe definir al menos una materia para crear un Curso Técnico. En Horas y Sesiones ingrese únicamente números.</div>
                                </div>
                                <button type="button" wire:click="addTechnicalSubjectRow" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">+ Agregar Materia</button>
                            </div>

                            {{-- Encabezados claros de columnas en escritorio --}}
                            <div class="hidden sm:grid sm:grid-cols-12 gap-2 px-1 text-[11px] font-bold uppercase tracking-wide text-amber-950">
                                <div class="sm:col-span-2">Código</div>
                                <div class="sm:col-span-5">Nombre de la Materia *</div>
                                <div class="sm:col-span-2">Horas (solo #)</div>
                                <div class="sm:col-span-2">N.° Sesiones</div>
                                <div class="sm:col-span-1 text-center">Quitar</div>
                            </div>

                            @foreach($technical_subjects as $idx => $subj)
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end" wire:key="tech-subj-{{ $idx }}">
                                    <div class="sm:col-span-2">
                                        <label class="block sm:hidden text-xs font-semibold text-amber-950 mb-1">Código</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.code" type="text" class="cecap-input" placeholder="MAT-01">
                                    </div>
                                    <div class="sm:col-span-5">
                                        <label class="block sm:hidden text-xs font-semibold text-amber-950 mb-1">Nombre de la Materia *</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.name" type="text" class="cecap-input" placeholder="Ej. Contabilidad I">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block sm:hidden text-xs font-semibold text-amber-950 mb-1">Horas (solo número)</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.hours" type="number" min="1" step="1" class="cecap-input" placeholder="Ej. 60">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block sm:hidden text-xs font-semibold text-amber-950 mb-1">N.° Sesiones</label>
                                        <input wire:model="technical_subjects.{{ $idx }}.sessions_count" type="number" min="1" step="1" class="cecap-input" placeholder="Ej. 12">
                                    </div>
                                    <div class="sm:col-span-1">
                                        <button type="button" wire:click="removeTechnicalSubjectRow({{ $idx }})" title="Quitar materia" class="cecap-btn-secondary w-full !px-2 text-xs">✕</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="cecap-btn-primary">{{ $editingCourseId ? 'Guardar Cambios del Curso' : 'Guardar Curso' }}</button>
                        @if($editingCourseId)
                            <button type="button" wire:click="cancelEditCourse" class="cecap-btn-secondary">Cancelar</button>
                        @endif
                    </div>
                </form>
            @endif

            <div class="cecap-card overflow-x-auto">
                <h2 class="text-base font-bold text-slate-900 mb-3">Cursos y Programas Registrados</h2>
                <table class="w-full text-sm text-left">
                    <thead class="border-b border-slate-200 text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">Código</th>
                            <th class="py-2 pr-3">Curso</th>
                            <th class="py-2 pr-3">Tipo</th>
                            <th class="py-2 pr-3">Nivel Previo</th>
                            <th class="py-2 pr-3">Cap. Defecto</th>
                            <th class="py-2 pr-3">Checklist</th>
                            <th class="py-2 pr-3">Materias (Horas / Sesiones)</th>
                            <th class="py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($courses as $c)
                            <tr wire:key="course-row-{{ $c->id }}" class="{{ ($managingCourse && $managingCourse->id === $c->id) ? 'bg-slate-50/80' : '' }}">
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $c->code }}</td>
                                <td class="py-2.5 pr-3 font-semibold text-slate-900">
                                    {{ $c->name }}
                                    <div class="text-xs text-slate-500">{{ $c->category?->name }}</div>
                                </td>
                                <td class="py-2.5 pr-3"><span class="cecap-badge bg-brand-100 text-brand-900">{{ $c->program_type_label }}</span></td>
                                <td class="py-2.5 pr-3 text-xs">{{ $educationLevels[$c->required_education_level] ?? $c->required_education_level }}</td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $c->default_max_capacity }}</td>
                                <td class="py-2.5 pr-3 text-xs">{{ implode(', ', $c->required_documents ?? []) }}</td>
                                <td class="py-2.5 pr-3 text-xs">
                                    @if($c->subjects->isNotEmpty())
                                        <div class="font-semibold text-slate-800">{{ $c->subjects->count() }} materia(s)</div>
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
                                            <button type="button" wire:click="openCourseSubjects({{ $c->id }})" class="cecap-btn-secondary !min-h-9 !px-2.5 text-xs">+ Materias</button>
                                            <button type="button" wire:click="toggleCourseActive({{ $c->id }})" class="cecap-badge cursor-pointer {{ $c->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-600' }}">{{ $c->is_active ? 'Activo' : 'Inactivo' }}</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @if($managingCourse && $managingCourse->id === $c->id)
                                <tr wire:key="course-subjects-{{ $c->id }}">
                                    <td colspan="8" class="py-3 px-1">
                                        <div class="rounded-2xl border-2 border-brand-700 bg-brand-50/50 p-4 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h3 class="text-sm sm:text-base font-bold text-brand-950">Gestionar Materias — {{ $managingCourse->code }} · {{ $managingCourse->name }}</h3>
                                                    <p class="text-xs text-slate-600">Agregue nuevas materias con horas y número de sesiones o active/inactive existentes (sin borrado físico).</p>
                                                </div>
                                                <button type="button" wire:click="closeCourseSubjects" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Cerrar</button>
                                            </div>

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
                                                        <tr><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Materia</th><th class="py-2 pr-3">Horas</th><th class="py-2 pr-3">N.° Sesiones</th><th class="py-2">Estado</th></tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100">
                                                        @forelse($managingCourse->subjects as $sub)
                                                            <tr>
                                                                <td class="py-2 pr-3 font-mono font-bold">{{ $sub->code }}</td>
                                                                <td class="py-2 pr-3 font-semibold text-slate-900">{{ $sub->name }}</td>
                                                                <td class="py-2 pr-3">{{ $sub->hours }} h</td>
                                                                <td class="py-2 pr-3">{{ $sub->sessions_count }} sesiones</td>
                                                                <td class="py-2">
                                                                    <button type="button" wire:click="toggleSubjectActive({{ $sub->id }})" class="cecap-badge cursor-pointer {{ $sub->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-600' }}">
                                                                        {{ $sub->is_active ? 'Activa' : 'Inactiva' }}
                                                                    </button>
                                                                </td>
                                                            </tr>
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
                        <tr><th class="py-2 pr-3">Código</th><th class="py-2 pr-3">Edición</th><th class="py-2 pr-3">Turno / Mod.</th><th class="py-2 pr-3">Profesora</th><th class="py-2 pr-3">Cupos</th><th class="py-2">Horarios</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($editions as $ed)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs font-bold">{{ $ed->code }}</td>
                                <td class="py-2.5 pr-3 font-semibold">{{ $ed->name }}<div class="text-xs text-slate-500">{{ $ed->start_date?->format('d/m/Y') }} - {{ $ed->end_date?->format('d/m/Y') }}</div></td>
                                <td class="py-2.5 pr-3 text-xs">{{ $ed->shift?->name }} · {{ $ed->modality?->name }}</td>
                                <td class="py-2.5 pr-3 text-xs">{{ $ed->teacher?->name ?? 'Por asignar' }}</td>
                                <td class="py-2.5 pr-3 text-xs font-bold">{{ $ed->activeEnrollmentsCount() }} / {{ $ed->max_capacity }}</td>
                                <td class="py-2.5 text-xs">
                                    @foreach($ed->schedules as $sch)
                                        <div>{{ $sch->formatted_slot }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">Sin ediciones registradas.</td></tr>
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
