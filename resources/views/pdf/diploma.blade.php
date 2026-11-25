@php
    $primary = $template?->primary_color ?: '#107050';
    $accent = $template?->accent_color ?: '#68A040';
    $course = $graduation->enrollment?->edition?->course;
    $body = $template?->body_template ?: 'Hace constar que {{student_name}} ({{student_id}}) aprobó satisfactoriamente "{{course_name}}".';
    $renderedBody = strtr($body, [
        '{{student_name}}' => $graduation->student?->full_name ?? '',
        '{{student_id}}' => $graduation->student?->identification_number ?? '',
        '{{course_name}}' => $course?->name ?? '',
        '{{program_type}}' => $course?->program_type_label ?? '',
        '{{duration_hours}}' => (string) ($course?->duration_hours ?? 0),
        '{{final_grade}}' => number_format((float) $graduation->final_grade, 2),
    ]);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Diploma {{ $graduation->act_number }}</title>
<style>
    @page { size: letter landscape; margin: 18px; }
    body { font-family: DejaVu Sans, sans-serif; color: #0f172a; margin: 0; }
    .outer { border: 8px double {{ $primary }}; padding: 14px; }
    .inner { border: 2px solid {{ $accent }}; padding: 22px 32px; text-align: center; }
    .inst { font-size: 16px; font-weight: bold; color: {{ $primary }}; letter-spacing: 1px; text-transform: uppercase; }
    .sub { font-size: 10px; color: #475569; margin-top: 2px; }
    .heading { font-size: 26px; font-weight: bold; color: {{ $accent }}; margin: 14px 0 8px 0; }
    .recipient { font-size: 24px; font-weight: bold; color: #0f172a; margin: 10px 0; border-bottom: 2px solid {{ $accent }}; display: inline-block; padding-bottom: 4px; }
    .body-text { font-size: 12.5px; line-height: 1.6; max-width: 780px; margin: 10px auto; color: #334155; }
    .footer-table { width: 100%; border-collapse: collapse; margin-top: 26px; }
    .footer-table td { vertical-align: bottom; text-align: center; }
    .sig-line { border-top: 1px solid #334155; width: 200px; margin: 0 auto 4px auto; }
    .qr-box { text-align: right; font-size: 8.5px; color: #475569; }
</style>
</head>
<body>
<div class="outer">
    <div class="inner">
        @if(!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="SURÍ Proyecto Educativo" style="height:78px; margin-bottom:6px;"><br>
        @endif
        <div class="inst">{{ $settings->institution_name }}</div>
        <div class="sub">{{ $settings->country }} · Cédula Jurídica {{ $settings->legal_id }}</div>

        <div class="heading">{{ $template?->header_title ?: 'Certificado Oficial de Aprovechamiento' }}</div>

        <div style="font-size:12px; color:#64748b;">OTORGADO A</div>
        <div class="recipient">{{ $graduation->student?->full_name }}</div>

        <p class="body-text">{{ $renderedBody }}</p>

        <div style="font-size:10.5px; color:#475569; margin-top:6px;">
            Acta N.° <strong>{{ $graduation->act_number }}</strong> ·
            Tomo <strong>{{ $graduation->tomo }}</strong> ·
            Folio <strong>{{ $graduation->folio }}</strong> ·
            Asiento <strong>{{ $graduation->asiento }}</strong> ·
            Fecha: <strong>{{ $graduation->graduation_date?->format('d/m/Y') }}</strong>
        </div>

        <table class="footer-table">
            <tr>
                <td style="width:36%;">
                    <div class="sig-line"></div>
                    <strong>{{ $template?->signatory_one_name ?: $settings->director_name }}</strong><br>
                    <span style="font-size:10px; color:#475569;">{{ $template?->signatory_one_title ?: $settings->director_title }}</span>
                </td>
                <td style="width:36%;">
                    @if($template?->signatory_two_name)
                        <div class="sig-line"></div>
                        <strong>{{ $template->signatory_two_name }}</strong><br>
                        <span style="font-size:10px; color:#475569;">{{ $template->signatory_two_title }}</span>
                    @endif
                </td>
                <td class="qr-box" style="width:28%;">
                    <img src="{{ $qrDataUri }}" alt="QR Verificación" style="width:92px; height:92px;"><br>
                    <strong>Verificación Oficial QR</strong><br>
                    {{ $verifyUrl }}
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
