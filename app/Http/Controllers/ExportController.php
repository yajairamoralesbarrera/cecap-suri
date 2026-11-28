<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\PaymentReceipt;
use App\Models\StaffPayment;
use App\Models\Student;
use App\Services\AttendanceReportService;
use App\Services\AuditService;
use App\Services\SimpleXlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function exportAttendanceExcel(Request $request, AttendanceReportService $reportService): Response
    {
        abort_unless($request->user()->hasEffectivePermission('seguimiento.view'), 403);

        $editions = $reportService->accessibleEditionsFor($request->user());
        $filters = [
            'course_id' => $request->query('course_id'),
            'edition_id' => $request->query('edition_id'),
            'subject_id' => $request->query('subject_id', ''),
            'student_id' => $request->query('student_id'),
            'student_search' => $request->query('student_search', ''),
            'status' => $request->query('status', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];

        $result = $reportService->query($editions->pluck('id')->all(), $filters);

        $summaryRows = [];
        foreach ($result['byStudent'] as $row) {
            $summaryRows[] = [
                $row['student']?->student_code ?? '',
                $row['student']?->full_name ?? '',
                $row['student']?->identification_number ?? '',
                $row['course_name'],
                $row['edition_name'],
                $row['subject_label'],
                (int) $row['presente'],
                (int) $row['tardia'],
                (int) $row['justificada'],
                (int) $row['ausente'],
                (int) $row['total'],
                (float) $row['percent'],
                (float) $row['min_percent'],
                $row['passed'] ? 'Cumple' : 'Bajo mínimo',
            ];
        }

        $detailRows = [];
        foreach ($result['records'] as $rec) {
            $detailRows[] = [
                $rec->session_date?->format('d/m/Y') ?? '',
                $rec->student?->student_code ?? '',
                $rec->student?->full_name ?? '',
                $rec->student?->identification_number ?? '',
                $rec->edition?->course?->name ?? '—',
                $rec->edition?->name ?? '—',
                $rec->subject ? ($rec->subject->code.' — '.$rec->subject->name) : 'General del curso',
                $rec->status_label,
                'v'.$rec->version,
                $rec->recorder?->name ?? 'Sistema',
                $rec->rectification_reason ?? '',
            ];
        }

        $binary = SimpleXlsxWriter::build([
            [
                'title' => 'Resumen Asistencia',
                'headers' => [
                    'Código Alumna',
                    'Estudiante',
                    'Identificación',
                    'Curso',
                    'Cohorte / Edición',
                    'Materia',
                    'Presentes',
                    'Tardías',
                    'Justificadas',
                    'Ausentes',
                    'Total Sesiones',
                    '% Asistencia',
                    'Mínimo (%)',
                    'Condición',
                ],
                'rows' => $summaryRows,
            ],
            [
                'title' => 'Detalle por Fecha',
                'headers' => [
                    'Fecha',
                    'Código Alumna',
                    'Estudiante',
                    'Identificación',
                    'Curso',
                    'Cohorte / Edición',
                    'Materia',
                    'Estado',
                    'Versión',
                    'Registrado por',
                    'Motivo Rectificación',
                ],
                'rows' => $detailRows,
            ],
        ]);

        AuditService::log('seguimiento.attendance_excel_exported', 'seguimiento', null, null, [
            'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'summary_rows' => count($summaryRows),
            'detail_rows' => count($detailRows),
        ]);

        $filename = sprintf('cecap_asistencias_%s.xlsx', now()->format('Ymd_His'));

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function exportCsv(Request $request, string $type): StreamedResponse
    {
        abort_unless($request->user()->hasEffectivePermission('direccion.exports.csv'), 403);

        $filename = sprintf('cecap_%s_%s.csv', $type, now()->format('Ymd_His'));

        AuditService::log('direccion.csv_exported', 'direccion', null, null, ['type' => $type]);

        return response()->streamDownload(function () use ($type): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM para apertura correcta con tildes y ₡ en Excel Costa Rica
            fwrite($out, "\xEF\xBB\xBF");

            match ($type) {
                'students' => $this->writeStudents($out),
                'enrollments' => $this->writeEnrollments($out),
                'payments' => $this->writePayments($out),
                'staff_payments' => $this->writeStaffPayments($out),
                'audit_logs' => $this->writeAuditLogs($out),
                default => fputcsv($out, ['Tipo de exportación no soportado']),
            };

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function writeStudents($out): void
    {
        fputcsv($out, ['Código', 'Nombre Completo', 'Identificación', 'Correo', 'Teléfono', 'Género', 'Escolaridad', 'Estado']);
        Student::query()->orderBy('id')->chunk(200, function ($rows) use ($out): void {
            foreach ($rows as $s) {
                fputcsv($out, [$s->student_code, $s->full_name, $s->identification_number, $s->email, $s->phone, $s->gender, $s->education_level, $s->status]);
            }
        });
    }

    private function writeEnrollments($out): void
    {
        fputcsv($out, ['Código Matrícula', 'Alumna', 'Identificación', 'Curso', 'Cohorte', 'Origen', 'Estado', 'Docs Completos', 'Excepción']);
        Enrollment::query()->with(['student', 'edition.course'])->orderBy('id')->chunk(200, function ($rows) use ($out): void {
            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->enrollment_code,
                    $e->student?->full_name,
                    $e->student?->identification_number,
                    $e->edition?->course?->name,
                    $e->edition?->name,
                    $e->source,
                    $e->status,
                    $e->documents_complete ? 'Sí' : 'No',
                    $e->exception_authorized ? 'Sí' : 'No',
                ]);
            }
        });
    }

    private function writePayments($out): void
    {
        fputcsv($out, ['Recibo', 'Fecha', 'Alumna', 'Método', 'Referencia', 'Monto CRC', 'Estado', 'Cajera']);
        PaymentReceipt::query()->with(['student', 'cashier'])->orderBy('id')->chunk(200, function ($rows) use ($out): void {
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->receipt_number,
                    $r->paid_at?->format('Y-m-d H:i'),
                    $r->student?->full_name,
                    $r->payment_method,
                    $r->reference_number,
                    number_format((float) $r->total_amount, 2, '.', ''),
                    $r->status,
                    $r->cashier?->name,
                ]);
            }
        });
    }

    private function writeStaffPayments($out): void
    {
        fputcsv($out, ['Comprobante', 'Fecha', 'Colaboradora', 'Régimen CR', 'Bruto', 'CCSS', 'BP', 'Renta', 'IVA', 'Clave Hacienda 50d', 'Neto']);
        StaffPayment::query()->with('staffMember')->orderBy('id')->chunk(200, function ($rows) use ($out): void {
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->voucher_number,
                    $p->payment_date?->format('Y-m-d'),
                    $p->staffMember?->full_name,
                    $p->hiring_regime,
                    $p->gross_amount,
                    $p->ccss_deduction,
                    $p->banco_popular_deduction,
                    $p->income_tax_deduction,
                    $p->iva_amount,
                    $p->hacienda_electronic_invoice_key,
                    $p->net_amount,
                ]);
            }
        });
    }

    private function writeAuditLogs($out): void
    {
        fputcsv($out, ['ID', 'Fecha', 'Usuario', 'Módulo', 'Evento', 'Entidad', 'Motivo', 'IP']);
        AuditLog::query()->latest('id')->limit(2000)->get()->each(function (AuditLog $a) use ($out): void {
            fputcsv($out, [
                $a->id,
                $a->created_at?->format('Y-m-d H:i:s'),
                $a->actor_name,
                $a->module,
                $a->event,
                $a->auditable_type ? class_basename($a->auditable_type).'#'.$a->auditable_id : '',
                $a->reason,
                $a->ip_address,
            ]);
        });
    }
}
