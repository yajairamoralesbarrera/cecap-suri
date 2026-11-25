<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Constancia Habilidades Blandas {{ $student->student_code }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 28px; }
    .header { text-align: center; border-bottom: 3px solid #0F4C4C; padding-bottom: 12px; margin-bottom: 18px; }
    .title { font-size: 16px; font-weight: bold; color: #0F4C4C; }
    .doc-title { font-size: 14px; font-weight: bold; margin-top: 10px; text-transform: uppercase; color: #C87D32; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; }
    th { background: #0F4C4C; color: #fff; }
</style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $settings->institution_name }}</div>
        <div>{{ $settings->address }} · {{ $settings->country }}</div>
        <div class="doc-title">Constancia Permanente de Desarrollo de Habilidades Blandas</div>
    </div>

    <p>Se hace constar que <strong>{{ $student->full_name }}</strong>, portadora de la identificación <strong>{{ $student->identification_number }}</strong> (código {{ $student->student_code }}), registra el siguiente historial permanente de evaluación de habilidades blandas en nuestra institución:</p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Habilidad Blanda</th>
                <th>Nivel Alcanzado</th>
                <th>Curso Asociado</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($student->softSkills as $skill)
                <tr>
                    <td>{{ $skill->evaluated_on?->format('d/m/Y') }}</td>
                    <td><strong>{{ $skill->skill_name }}</strong></td>
                    <td>{{ ucfirst(str_replace('_', ' ', $skill->level)) }}{{ $skill->score ? ' ('.$skill->score.'/100)' : '' }}</td>
                    <td>{{ $skill->enrollment?->edition?->course?->name ?? 'Seguimiento transversal' }}</td>
                    <td>{{ $skill->observations ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">No hay evaluaciones de habilidades blandas registradas a la fecha.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:46px; text-align:center;">
        <div style="border-top:1px solid #334155; width:260px; margin:0 auto 5px auto;"></div>
        <strong>{{ $settings->director_name }}</strong><br>
        {{ $settings->director_title }}<br>
        <span style="font-size:9px; color:#64748b;">Emitido el {{ now()->format('d/m/Y') }}</span>
    </div>
</body>
</html>
