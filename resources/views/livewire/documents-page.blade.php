<div class="space-y-6">
    @if($feedback)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">{{ $feedback }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <ul class="list-disc pl-4 space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        @if(auth()->user()->hasEffectivePermission('documentos.manage'))
            <form wire:submit="createDocument" class="cecap-card space-y-3">
                <h2 class="text-base font-bold text-brand-950">Archivar Documento Institucional</h2>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Título *</label>
                    <input wire:model="title" type="text" required class="cecap-input" placeholder="Reglamento Estudiantil 2026">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Categoría *</label>
                    <select wire:model="category" class="cecap-input">
                        @foreach($categories as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Visibilidad *</label>
                    <select wire:model="visibility" class="cecap-input">
                        <option value="interno">Interno (Personal)</option>
                        <option value="alumnas">Alumnas y Personal</option>
                        <option value="publico">Público</option>
                        <option value="direccion">Solo Dirección</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Descripción</label>
                    <textarea wire:model="description" rows="2" class="cecap-input"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Archivo (PDF/JPG/PNG/WebP máx 10MB) *</label>
                    <input wire:model="file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                </div>
                <button type="submit" class="cecap-btn-primary w-full">Subir al Repositorio Privado</button>
            </form>
        @endif

        <div class="cecap-card lg:col-span-2 space-y-4">
            <h2 class="text-base font-bold text-slate-900">Repositorio Documental Versionado (Sin Borrado Físico)</h2>

            @if($versioningDocId)
                <form wire:submit="uploadNewVersion" class="rounded-xl border border-brand-200 bg-brand-50 p-4 space-y-3">
                    <div class="font-bold text-sm text-brand-950">Cargar Nueva Versión (la versión anterior se conserva íntegra)</div>
                    <input wire:model="new_version_file" type="file" required accept=".pdf,.jpg,.jpeg,.png,.webp" class="cecap-input">
                    <div class="flex gap-2">
                        <button type="submit" class="cecap-btn-primary !min-h-9 text-xs">Guardar Nueva Versión</button>
                        <button type="button" wire:click="cancelNewVersion" class="cecap-btn-secondary !min-h-9 text-xs">Cancelar</button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-slate-100">
                @forelse($documents as $doc)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="font-bold text-slate-900">{{ $doc->code }} — {{ $doc->title }} <span class="cecap-badge bg-brand-100 text-brand-900 ml-1">v{{ $doc->current_version }}</span></div>
                            <div class="text-xs text-slate-500">
                                {{ $categories[$doc->category] ?? $doc->category }} · Visibilidad: {{ $doc->visibility }} · Estado: {{ strtoupper($doc->status) }}
                                @if($doc->currentAttachment)
                                    · SHA-256: <span class="font-mono">{{ substr($doc->currentAttachment->sha256, 0, 12) }}…</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @if($doc->currentAttachment)
                                <a href="{{ route('attachments.download', $doc->currentAttachment->uuid) }}" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Descargar</a>
                            @endif
                            @if(auth()->user()->hasEffectivePermission('documentos.manage') && $doc->status === 'vigente')
                                <button type="button" wire:click="startNewVersion({{ $doc->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs">Nueva Versión</button>
                                <button type="button" wire:click="archiveDocument({{ $doc->id }})" class="cecap-btn-secondary !min-h-9 !px-3 text-xs text-amber-700">Archivar</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Sin documentos archivados.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
