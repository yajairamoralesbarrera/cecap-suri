<?php

namespace App\Http\Controllers;

use App\Models\RecordAttachment;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

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
            $isPdf = $attachment->mime_type === 'application/pdf';
            $pdfPages = $isPdf
                ? $this->renderPdfPagesAsPngBase64(Storage::disk('local')->path($attachment->stored_path), 8)
                : [];

            $referer = (string) $request->headers->get('referer', '');
            $backUrl = route('admisiones', [], false);
            if ($referer !== '') {
                $parsed = parse_url($referer);
                $refPath = (string) ($parsed['path'] ?? '');
                if ($refPath !== '' && ! str_starts_with($refPath, '/adjuntos/')) {
                    $backUrl = $refPath.(isset($parsed['query']) ? '?'.$parsed['query'] : '');
                }
            }

            return response()->view('attachments.preview', [
                'attachment' => $attachment,
                'base64' => base64_encode((string) $bytes),
                'isImage' => str_starts_with((string) $attachment->mime_type, 'image/'),
                'isPdf' => $isPdf,
                'pdfPages' => $pdfPages,
                'backUrl' => $backUrl,
                'downloadUrl' => route('attachments.download', ['uuid' => $attachment->uuid, 'descargar' => 1], false),
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

    /**
     * Convierte hasta las primeras $maxPages páginas de un PDF a imágenes PNG base64
     * usando pdftoppm (poppler-utils) para garantizar vista previa universal incluso en iframes restringidos.
     *
     * @return list<string>
     */
    protected function renderPdfPagesAsPngBase64(string $fullPath, int $maxPages = 8): array
    {
        if (! is_file($fullPath) || ! is_executable('/usr/bin/pdftoppm')) {
            return [];
        }

        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'cecap_pdf_'.Str::random(12);
        if (! @mkdir($tmpDir, 0700, true)) {
            return [];
        }

        $prefix = $tmpDir.DIRECTORY_SEPARATOR.'page';
        try {
            $process = new Process([
                '/usr/bin/pdftoppm',
                '-png',
                '-r',
                '130',
                '-f',
                '1',
                '-l',
                (string) max(1, $maxPages),
                $fullPath,
                $prefix,
            ]);
            $process->setTimeout(15);
            $process->run();

            if (! $process->isSuccessful()) {
                return [];
            }

            $files = glob($prefix.'-*.png') ?: [];
            natsort($files);

            $pages = [];
            foreach ($files as $pngFile) {
                $pngBytes = @file_get_contents($pngFile);
                if ($pngBytes !== false && $pngBytes !== '') {
                    $pages[] = base64_encode($pngBytes);
                }
            }

            return $pages;
        } catch (\Throwable) {
            return [];
        } finally {
            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }
}
