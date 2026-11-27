<?php

namespace App\Livewire;

use App\Models\InstitutionalDocument;
use App\Models\Picklist;
use App\Services\AttachmentService;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Repositorio Documental')]
class DocumentsPage extends Component
{
    use WithFileUploads;

    public ?string $feedback = null;

    public string $title = '';

    public string $category = 'reglamento';

    public string $visibility = 'interno';

    public string $description = '';

    public $file = null;

    public ?int $versioningDocId = null;

    public $new_version_file = null;

    public function createDocument(AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('documentos.manage'), 403);

        $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'visibility' => ['required', 'in:publico,alumnas,interno,direccion'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ]);

        $doc = InstitutionalDocument::create([
            'code' => InstitutionalDocument::nextCode(),
            'title' => trim($this->title),
            'category' => $this->category,
            'description' => $this->description ?: null,
            'visibility' => $this->visibility,
            'current_version' => 1,
            'status' => 'vigente',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $att = $attachmentService->store($doc, $this->file, 'documento_institucional', 'Versión inicial v1');
        $doc->update(['current_attachment_id' => $att->id]);

        AuditService::log('documents.created', 'documentos', $doc, null, ['code' => $doc->code, 'title' => $doc->title]);
        $this->reset(['title', 'description', 'file']);
        $this->feedback = 'Documento institucional archivado con hash SHA-256.';
    }

    public function startNewVersion(int $docId): void
    {
        $this->versioningDocId = $docId;
        $this->new_version_file = null;
    }

    public function cancelNewVersion(): void
    {
        $this->versioningDocId = null;
    }

    public function uploadNewVersion(AttachmentService $attachmentService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('documentos.manage'), 403);
        if (! $this->versioningDocId) {
            return;
        }

        $this->validate([
            'new_version_file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:25600'],
        ]);

        $doc = InstitutionalDocument::findOrFail($this->versioningDocId);
        $att = $attachmentService->store($doc, $this->new_version_file, 'documento_institucional', 'Actualización de versión');
        $doc->update([
            'current_version' => $att->version,
            'current_attachment_id' => $att->id,
            'updated_by' => Auth::id(),
        ]);

        AuditService::log('documents.version_uploaded', 'documentos', $doc, null, ['version' => $att->version, 'sha256' => $att->sha256]);
        $this->versioningDocId = null;
        $this->new_version_file = null;
        $this->feedback = sprintf('Nueva versión v%d cargada conservando versiones anteriores.', $att->version);
    }

    public function archiveDocument(int $docId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('documentos.manage'), 403);
        $doc = InstitutionalDocument::findOrFail($docId);
        $doc->update(['status' => 'archivado', 'updated_by' => Auth::id()]);
        AuditService::log('documents.archived', 'documentos', $doc, ['status' => 'vigente'], ['status' => 'archivado']);
        $this->feedback = 'Documento archivado sin borrado físico.';
    }

    public function render()
    {
        $user = Auth::user();
        $query = InstitutionalDocument::with(['currentAttachment', 'attachments'])->latest();

        if ($user->hasRole('alumna') && ! $user->hasAnyRole(['super_admin', 'direccion', 'secretaria', 'administracion_caja', 'coordinacion_academica', 'coordinador_categoria', 'profesora'])) {
            $query->whereIn('visibility', ['publico', 'alumnas'])->where('status', 'vigente');
        }

        return view('livewire.documents-page', [
            'documents' => $query->get(),
            'categories' => Picklist::optionsFor('document_category'),
        ]);
    }
}
