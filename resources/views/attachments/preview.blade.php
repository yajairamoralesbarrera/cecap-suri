<!DOCTYPE html>
<html lang="es-CR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $attachment->document_type_label }} — {{ $attachment->original_name }}</title>
    <style>
        body {
            margin: 0;
            padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
        }
        .card {
            max-width: 980px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
        }
        .header {
            background: #107050;
            color: #ffffff;
            padding: 14px 18px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }
        .header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .title {
            font-size: 15px;
            font-weight: 700;
        }
        .meta {
            font-size: 12px;
            opacity: 0.92;
            margin-top: 2px;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            text-transform: uppercase;
        }
        .btn-download {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            background: #ffffff;
            color: #065f46;
            text-decoration: none;
            border: 1px solid rgba(255,255,255,0.4);
            cursor: pointer;
        }
        .btn-download:hover {
            background: #ecfdf5;
        }
        .btn-download-primary {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            background: #107050;
            color: #ffffff;
            text-decoration: none;
            margin-top: 12px;
        }
        .body {
            padding: 18px;
            text-align: center;
            background: #f8fafc;
        }
        .preview-img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }
        .pdf-pages {
            display: flex;
            flex-direction: column;
            gap: 16px;
            align-items: center;
        }
        .pdf-page-wrap {
            width: 100%;
            max-width: 860px;
        }
        .pdf-page-label {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .pdf-box {
            width: 100%;
            height: 76vh;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #ffffff;
        }
        .footer {
            padding: 10px 18px;
            font-size: 11px;
            color: #475569;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 8px;
        }
        .fallback {
            padding: 28px 16px;
            color: #334155;
            font-size: 14px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div>
                <div class="title">{{ $attachment->document_type_label }} — {{ $attachment->original_name }}</div>
                <div class="meta">
                    Versión v{{ $attachment->version }} · {{ number_format(($attachment->size_bytes ?? 0) / 1024, 1) }} KB · Subido el {{ $attachment->created_at?->format('d/m/Y H:i') }}
                </div>
            </div>
            <div class="header-actions">
                <span class="badge">{{ strtoupper($attachment->status) }}</span>
                <a href="data:{{ $attachment->mime_type }};base64,{{ $base64 }}"
                   download="{{ $attachment->original_name }}"
                   class="btn-download">
                    Descargar Archivo
                </a>
            </div>
        </div>

        <div class="body">
            @if($isImage)
                <img src="data:{{ $attachment->mime_type }};base64,{{ $base64 }}"
                     alt="{{ $attachment->original_name }}"
                     class="preview-img">
            @elseif($isPdf && ! empty($pdfPages))
                <div class="pdf-pages">
                    @foreach($pdfPages as $idx => $pageB64)
                        <div class="pdf-page-wrap">
                            <div class="pdf-page-label">Página {{ $idx + 1 }} de {{ count($pdfPages) }}</div>
                            <img src="data:image/png;base64,{{ $pageB64 }}"
                                 alt="Página {{ $idx + 1 }} de {{ $attachment->original_name }}"
                                 class="preview-img">
                        </div>
                    @endforeach
                </div>
                @if(count($pdfPages) >= 8)
                    <p style="font-size:12px; color:#475569; margin-top:14px;">
                        Mostrando las primeras 8 páginas. Use <strong>Descargar Archivo</strong> arriba para ver el PDF completo.
                    </p>
                @endif
            @elseif($isPdf)
                <object data="data:application/pdf;base64,{{ $base64 }}"
                        type="application/pdf"
                        class="pdf-box">
                    <div class="fallback">
                        <p><strong>El archivo PDF está listo para descarga.</strong></p>
                        <p>Haga clic en el siguiente botón para abrir o guardar <strong>{{ $attachment->original_name }}</strong>:</p>
                        <a href="data:{{ $attachment->mime_type }};base64,{{ $base64 }}"
                           download="{{ $attachment->original_name }}"
                           class="btn-download-primary">
                            Descargar {{ $attachment->original_name }}
                        </a>
                    </div>
                </object>
            @else
                <div class="fallback">
                    <p>Archivo adjunto listo para descarga: <strong>{{ $attachment->original_name }}</strong> ({{ $attachment->mime_type }}).</p>
                    <a href="data:{{ $attachment->mime_type }};base64,{{ $base64 }}"
                       download="{{ $attachment->original_name }}"
                       class="btn-download-primary">
                        Descargar {{ $attachment->original_name }}
                    </a>
                </div>
            @endif
        </div>

        <div class="footer">
            <span>SHA-256: <code>{{ substr((string) $attachment->sha256, 0, 24) }}...</code></span>
            @if($attachment->notes)
                <span>Nota: {{ $attachment->notes }}</span>
            @endif
        </div>
    </div>
</body>
</html>
