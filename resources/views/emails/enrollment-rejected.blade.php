<!DOCTYPE html>
<html lang="es-CR">
<head>
    <meta charset="utf-8">
    <title>Resolución de Solicitud {{ $enrollment->enrollment_code }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; background-color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
        <div style="background: #107050; color: #ffffff; padding: 20px 24px;">
            <div style="font-size: 18px; font-weight: bold;">{{ $settings->institution_name }}</div>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 4px;">Notificación oficial de Admisiones</div>
        </div>

        <div style="padding: 24px; font-size: 14px; line-height: 1.6;">
            <p style="margin-top: 0;">Estimada <strong>{{ $enrollment->student?->full_name }}</strong>,</p>

            <p>
                Le informamos que su solicitud de inscripción <strong>{{ $enrollment->enrollment_code }}</strong>
                para el curso <strong>{{ $enrollment->edition?->course?->name }}</strong>
                ({{ $enrollment->edition?->name }}) no ha podido ser aprobada en esta ocasión y ha sido registrada en estado
                <strong>Rechazada</strong>.
            </p>

            <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px 16px; margin: 16px 0;">
                <div style="font-size: 12px; text-transform: uppercase; font-weight: bold; color: #9f1239;">Motivo de rechazo</div>
                <div style="font-size: 14px; font-weight: bold; color: #881337; margin-top: 4px;">{{ $reasonLabel }}</div>
                @if(! empty($additionalNotes))
                    <div style="font-size: 13px; color: #475569; margin-top: 8px;">
                        <strong>Observaciones:</strong> {{ $additionalNotes }}
                    </div>
                @endif
            </div>

            <p>
                Si desea recibir orientación, completar requisitos o postular a una próxima cohorte, puede comunicarse con nuestra Secretaría / Admisiones:
            </p>

            <div style="background: #f1f5f9; border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #334155;">
                <div><strong>Horario de atención:</strong> {{ $settings->attention_hours }}</div>
                <div><strong>Teléfono:</strong> {{ $settings->phone }} · <strong>Correo:</strong> {{ $settings->email }}</div>
                <div><strong>Dirección:</strong> {{ $settings->address }}</div>
            </div>

            <p style="margin-bottom: 0; margin-top: 20px; font-size: 12px; color: #64748b;">
                Atentamente,<br>
                <strong>{{ $settings->institution_name }}</strong>
            </p>
        </div>
    </div>
</body>
</html>
