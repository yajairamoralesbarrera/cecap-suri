<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Planilla de Inscripción {{ $enrollment->enrollment_code }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
    .header { border-bottom: 3px solid #107050; padding-bottom: 10px; margin-bottom: 16px; }
    .title { font-size: 16px; font-weight: bold; color: #107050; }
    .subtitle { font-size: 10px; color: #475569; margin-top: 2px; }
    .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; background: #E6F4EC; color: #107050; }
    .section { margin-top: 14px; }
    .section-title { background: #107050; color: #fff; padding: 5px 8px; font-size: 11px; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
    th { background: #f1f5f9; width: 26%; font-weight: bold; }
    .notice { background: #F0F8E4; border: 1px solid #68A040; padding: 10px; margin-top: 12px; font-size: 10.5px; }
    .signatures { margin-top: 34px; width: 100%; border: none; }
    .signatures td { border: none; text-align: center; width: 50%; padding-top: 28px; }
    .sig-line { border-top: 1px solid #334155; width: 78%; margin: 0 auto 4px auto; }
</style>
</head>
<body>
    <div class="header">
        @if(!empty($logoDataUri))
            <img src="{{ $logoDataUri }}" alt="SURÍ" style="float:left; height:64px; margin-right:14px;">
        @endif
        <div class="title">{{ $settings->institution_name }}</div>
        <div class="subtitle">{{ $settings->address }} · Tel: {{ $settings->phone }} · {{ $settings->email }} · Cédula Jurídica: {{ $settings->legal_id }}</div>
        <div style="margin-top: 8px;">
            <strong>PLANILLA OFICIAL DE INSCRIPCIÓN / MATRÍCULA</strong> &nbsp;
            <span class="badge">{{ $enrollment->enrollment_code }}</span> &nbsp;
            <span class="badge">Estado: {{ strtoupper(str_replace('_', ' ', $enrollment->status)) }}</span>
        </div>
        <div style="clear:both;"></div>
    </div>

    <div class="section">
        <div class="section-title">1. Datos Personales de la Alumna</div>
        <table>
            <tr>
                <th>Nombre Completo</th>
                <td>{{ $enrollment->student?->full_name }}</td>
                <th>Código Alumna</th>
                <td>{{ $enrollment->student?->student_code }}</td>
            </tr>
            <tr>
                <th>Identificación</th>
                <td>{{ strtoupper($enrollment->student?->identification_type ?? '') }}: {{ $enrollment->student?->identification_number }}</td>
                <th>Género</th>
                <td>{{ ucfirst($enrollment->student?->gender ?? 'femenino') }}</td>
            </tr>
            <tr>
                <th>Correo Electrónico</th>
                <td>{{ $enrollment->student?->email }}</td>
                <th>Teléfono</th>
                <td>{{ $enrollment->student?->phone }}</td>
            </tr>
            <tr>
                <th>Dirección (CR)</th>
                <td colspan="3">
                    {{ collect([$enrollment->student?->province?->name, $enrollment->student?->canton?->name, $enrollment->student?->district?->name, $enrollment->student?->address_detail])->filter()->implode(', ') ?: 'No registrada' }}
                </td>
            </tr>
            <tr>
                <th>Nivel Educativo</th>
                <td>{{ $enrollment->student?->education_level ?: 'No indicado' }}</td>
                <th>Contacto Emergencia</th>
                <td>{{ trim(($enrollment->student?->emergency_contact_name ?? '').' '.($enrollment->student?->emergency_contact_phone ?? '')) ?: 'No indicado' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">2. Datos del Programa y Edición</div>
        <table>
            <tr>
                <th>Curso / Programa</th>
                <td>{{ $enrollment->edition?->course?->name }} ({{ $enrollment->edition?->course?->program_type_label }})</td>
                <th>Categoría</th>
                <td>{{ $enrollment->edition?->course?->category?->name }}</td>
            </tr>
            <tr>
                <th>Edición / Cohorte</th>
                <td>{{ $enrollment->edition?->name }} ({{ $enrollment->edition?->code }})</td>
                <th>Turno / Modalidad</th>
                <td>{{ $enrollment->edition?->shift?->name }} · {{ $enrollment->edition?->modality?->name }}</td>
            </tr>
            <tr>
                <th>Fechas</th>
                <td>{{ $enrollment->edition?->start_date?->format('d/m/Y') }} al {{ $enrollment->edition?->end_date?->format('d/m/Y') }}</td>
                <th>Inversión Referencial</th>
                <td>Matrícula: ₡{{ number_format((float) $enrollment->edition?->enrollment_fee, 2) }} · Mensualidad: ₡{{ number_format((float) $enrollment->edition?->monthly_fee, 2) }}</td>
            </tr>
            <tr>
                <th>Horarios</th>
                <td colspan="3">
                    @forelse($enrollment->edition?->schedules ?? [] as $sch)
                        <div>• {{ $sch->formatted_slot }}</div>
                    @empty
                        Por definir en coordinación académica
                    @endforelse
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">3. Verificación de Documentos y Estado</div>
        <table>
            <tr>
                <th>Documentos Requeridos</th>
                <td>
                    @foreach($enrollment->required_documents_snapshot ?? [] as $doc)
                        @php $present = in_array($doc, $enrollment->submitted_documents ?? [], true); @endphp
                        <div>[{{ $present ? 'X' : ' ' }}] {{ $documentTypes[$doc] ?? $doc }}</div>
                    @endforeach
                </td>
            </tr>
            @php $missingDocLabels = $enrollment->missingDocumentLabels(); @endphp
            @if(! empty($missingDocLabels))
                <tr>
                    <th>Documentos Faltantes</th>
                    <td style="color:#9f1239; font-weight:bold;">{{ implode(' · ', $missingDocLabels) }}</td>
                </tr>
            @endif
            @if($enrollment->exception_authorized)
                <tr>
                    <th>Excepción Autorizada</th>
                    <td>Motivo: {{ $enrollment->exception_reason }} · Fecha compromiso: {{ $enrollment->exception_commitment_date?->format('d/m/Y') }}</td>
                </tr>
            @endif
        </table>
    </div>

    @if(in_array($enrollment->status, ['preinscrita', 'documentacion_pendiente'], true))
        <div class="notice">
            <strong>AVISO DE INSCRIPCIÓN PROVISIONAL (PLAZO {{ $settings->online_enrollment_provisional_days }} DÍAS):</strong><br>
            {{ $settings->online_enrollment_notice }}<br>
            @if(! empty($missingDocLabels))
                <strong>Documentos pendientes por entregar:</strong> {{ implode(', ', $missingDocLabels) }}<br>
            @endif
            <strong>Vencimiento provisional:</strong> {{ $enrollment->provisional_expires_at?->format('d/m/Y H:i') ?? '7 días naturales' }}<br>
            <strong>Horario de atención CeCap:</strong> {{ $settings->attention_hours }}
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                Firma de la Alumna / Solicitante
            </td>
            <td>
                <div class="sig-line"></div>
                Recibido por Secretaría / Admisiones CeCap
            </td>
        </tr>
    </table>
    <div style="margin-top:14px; font-size:9px; color:#64748b; text-align:right;">
        Documento generado el {{ now()->format('d/m/Y H:i') }} · {{ $settings->institution_name }}
    </div>
</body>
</html>
