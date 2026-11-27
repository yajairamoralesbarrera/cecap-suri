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
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            text-transform: uppercase;
        }
        .body {
            padding: 18px;
            text-align: center;
            background: #f8fafc;
        }
        .preview-img {
            max-width: 100%;
            max-height: 76vh;
            height: auto;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
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
            <span class="badge">{{ strtoupper($attachment->status) }}</span>
        </div>

        <div class="body">
            @if($isImage)
                <img src="data:{{ $attachment->mime_type }};base64,{{ $base64 }}"
                     alt="{{ $attachment->original_name }}"
                     class="preview-img">
            @elseif($isPdf)
                <object data="data:application/pdf;base64,{{ $base64 }}"
                        type="application/pdf"
                        class="pdf-box">
                    <div class="fallback">
                        <p><strong>Vista previa de PDF no disponible directamente en este visor.</strong></p>
                        <p>Haga clic en el botón <strong>Descargar</strong> en la parte superior para abrir el archivo PDF completo ({{ $attachment->original_name }}).</p>
                    </div>
                </object>
            @else
                <div class="fallback">
                    <p>Archivo adjunto listo para descarga: <strong>{{ $attachment->original_name }}</strong> ({{ $attachment->mime_type }}).</p>
                    <p>Utilice el botón <strong>Descargar</strong> en la barra superior.</p>
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
