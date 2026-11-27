<?php

namespace App\Services;

use App\Models\RecordAttachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AttachmentService
{
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const MAX_BYTES = 26214400; // 25 MB

    public function store(
        Model $attachable,
        UploadedFile $file,
        string $documentType,
        ?string $notes = null,
        ?int $uploadedBy = null,
        bool $replaceExisting = true
    ): RecordAttachment {
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new InvalidArgumentException('Tipo de archivo no permitido. Solo se aceptan PDF, JPG, PNG o WebP.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('El archivo supera el tamaño máximo permitido de 25 MB.');
        }

        $nextVersion = ((int) RecordAttachment::query()
            ->where('attachable_type', $attachable->getMorphClass())
            ->where('attachable_id', $attachable->getKey())
            ->where('document_type', $documentType)
            ->max('version')) + 1;

        if ($replaceExisting) {
            $previous = RecordAttachment::query()
                ->where('attachable_type', $attachable->getMorphClass())
                ->where('attachable_id', $attachable->getKey())
                ->where('document_type', $documentType)
                ->where('is_current', true)
                ->get();

            foreach ($previous as $old) {
                $old->update([
                    'is_current' => false,
                    'status' => 'reemplazado',
                ]);
            }
        }

        $uuid = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $folder = sprintf('attachments/%s/%d', Str::snake(class_basename($attachable)), $attachable->getKey());
        $storedPath = $file->storeAs($folder, $uuid.'.'.$ext, 'local');
        $fullPath = Storage::disk('local')->path($storedPath);
        $sha256 = hash_file('sha256', $fullPath);

        $attachment = RecordAttachment::create([
            'uuid' => $uuid,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'document_type' => $documentType,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => $mime,
            'size_bytes' => (int) $file->getSize(),
            'sha256' => $sha256,
            'version' => $nextVersion,
            'is_current' => true,
            'status' => 'activo',
            'notes' => $notes,
            'uploaded_by' => $uploadedBy ?? Auth::id(),
        ]);

        AuditService::log(
            event: 'attachment.uploaded',
            module: 'documentos',
            auditable: $attachment,
            newValues: [
                'attachable' => class_basename($attachable).'#'.$attachable->getKey(),
                'document_type' => $documentType,
                'original_name' => $attachment->original_name,
                'version' => $nextVersion,
                'sha256' => $sha256,
            ]
        );

        return $attachment;
    }

    public function archive(RecordAttachment $attachment, string $reason): RecordAttachment
    {
        $old = ['status' => $attachment->status, 'is_current' => $attachment->is_current];

        $attachment->update([
            'status' => 'archivado',
            'is_current' => false,
            'archived_by' => Auth::id(),
            'archived_at' => now(),
            'notes' => trim(($attachment->notes ? $attachment->notes."\n" : '').'Archivado: '.$reason),
        ]);

        AuditService::log(
            event: 'attachment.archived',
            module: 'documentos',
            auditable: $attachment,
            oldValues: $old,
            newValues: ['status' => 'archivado', 'is_current' => false],
            reason: $reason
        );

        return $attachment;
    }

    public function restore(RecordAttachment $attachment, string $reason): RecordAttachment
    {
        $old = ['status' => $attachment->status, 'is_current' => $attachment->is_current];

        $attachment->update([
            'status' => 'activo',
            'is_current' => true,
            'archived_by' => null,
            'archived_at' => null,
            'notes' => trim(($attachment->notes ? $attachment->notes."\n" : '').'Restaurado: '.$reason),
        ]);

        AuditService::log(
            event: 'attachment.restored',
            module: 'documentos',
            auditable: $attachment,
            oldValues: $old,
            newValues: ['status' => 'activo', 'is_current' => true],
            reason: $reason
        );

        return $attachment;
    }
}
