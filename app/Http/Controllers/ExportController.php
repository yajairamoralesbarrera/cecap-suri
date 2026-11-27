<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Enrollment;
use App\Models\PaymentReceipt;
use App\Models\StaffPayment;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
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
