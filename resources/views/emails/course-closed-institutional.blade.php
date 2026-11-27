<!DOCTYPE html>
<html lang="es-CR">
<head>
    <meta charset="utf-8">
    <title>Aviso de Cierre de Cohorte {{ $enrollment->edition?->code ?? $course->code }}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1e293b; background-color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
        <div style="background: #107050; color: #ffffff; padding: 20px 24px;">
            <div style="font-size: 18px; font-weight: bold;">{{ $settings->institution_name }}</div>
            <div style="font-size: 12px; opacity: 0.9; margin-top: 4px;">Comunicado oficial de Dirección Académica</div>
        </div>

        <div style="padding: 24px; font-size: 14px; line-height: 1.6;">
            <p style="margin-top: 0;">Estimada <strong>{{ $enrollment->student?->full_name }}</strong>,</p>

            <p>
                Le informamos que, por decisión institucional, la cohorte
                <strong>{{ $enrollment->edition?->name }}</strong>
                @if($enrollment->edition?->code)
                    (código <strong>{{ $enrollment->edition->code }}</strong>)
                @endif
                del curso/programa <strong>{{ $course->name }}</strong>
                (solicitud <strong>{{ $enrollment->enrollment_code }}</strong>)
                ha sido <strong>cerrada</strong>.
            </p>

            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px; margin: 16px 0;">
                <div style="font-size: 12px; text-transform: uppercase; font-weight: bold; color: #92400e;">Mensaje de la institución</div>
                <div style="font-size: 14px; color: #78350f; margin-top: 6px;">{{ $studentMessage }}</div>
                <div style="font-size: 12px; color: #64748b; margin-top: 8px;">
                    <strong>Motivo registrado:</strong> {{ $closureReason }}
                </div>
            </div>

            <p>
                Agradecemos profundamente su interés y le invitamos a <strong>mantenerse pendiente de nuestra oferta académica y laboral</strong>
                y próximas convocatorias. Asimismo, le solicitamos <strong>pasar por las oficinas del Centro de Capacitación</strong> para recibir orientación,
                opciones de reubicación o atención personalizada dentro de nuestro horario de atención:
            </p>

            <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #334155;">
                <div><strong>Oficinas del Centro de Capacitación:</strong> {{ $settings->institution_name }}</div>
                <div><strong>Horario de atención:</strong> {{ $settings->attention_hours }}</div>
                <div><strong>Dirección:</strong> {{ $settings->address }}</div>
                <div><strong>Teléfono:</strong> {{ $settings->phone }} · <strong>Correo:</strong> {{ $settings->email }}</div>
            </div>

            <p style="margin-bottom: 0; margin-top: 20px; font-size: 12px; color: #64748b;">
                Este aviso fue enviado al correo electrónico registrado en su inscripción ({{ $enrollment->student?->email }}).<br>
                Atentamente,<br>
                <strong>{{ $settings->institution_name }}</strong>
            </p>
        </div>
    </div>
</body>
</html>
