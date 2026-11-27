<?php

namespace App\Http\Controllers;

use App\Models\RecordAttachment;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, string $uuid): StreamedResponse|Response
    {
        $user = $request->user();
        $attachment = RecordAttachment::where('uuid', $uuid)->firstOrFail();

        $allowed = $user->hasAnyRole([
            'super_admin',
            'direccion',
            'secretaria',
            'administracion_caja',
            'coordinacion_academica',
            'coordinador_categoria',
        ]);

        if (! $allowed && $attachment->attachable_type === Student::class) {
            $student = Student::find($attachment->attachable_id);
            $allowed = $student && $student->user_id === $user->id;
        }

        if (! $allowed && $attachment->document_type === 'documento_institucional') {
            $allowed = $user->hasEffectivePermission('documentos.view');
        }

        abort_unless($allowed, 403, 'No tiene autorización para descargar este adjunto privado.');

        if (! Storage::disk('local')->exists($attachment->stored_path)) {
            abort(404, 'El archivo físico no está disponible en el almacenamiento privado.');
        }

        $wantsHtmlPreview = $request->input('vista') === 'html'
            || (! $request->boolean('descargar')
                && ! $request->boolean('raw')
                && str_contains((string) $request->header('Accept'), 'text/html'));

        if ($wantsHtmlPreview) {
            AuditService::log('attachment.viewed', 'documentos', $attachment);
            $bytes = Storage::disk('local')->get($attachment->stored_path);

            return response()->view('attachments.preview', [
                'attachment' => $attachment,
                'base64' => base64_encode((string) $bytes),
                'isImage' => str_starts_with((string) $attachment->mime_type, 'image/'),
                'isPdf' => $attachment->mime_type === 'application/pdf',
            ]);
        }

        AuditService::log('attachment.downloaded', 'documentos', $attachment);

        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return Storage::disk('local')->response(
            $attachment->stored_path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => $disposition.'; filename="'.addslashes($attachment->original_name).'"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
