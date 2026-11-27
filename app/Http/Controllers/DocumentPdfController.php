<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\GraduationRecord;
use App\Models\InstitutionSetting;
use App\Models\PaymentReceipt;
use App\Models\StaffContract;
use App\Models\StaffPayment;
use App\Models\Student;
use App\Services\GraduationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DocumentPdfController extends Controller
{
    public function publicEnrollmentForm(Request $request, Enrollment $enrollment): Response|View
    {
        $hasValidSignature = $request->hasValidSignatureWhileIgnoring(['vista', 'descargar'], false)
            || $request->hasValidSignatureWhileIgnoring(['vista', 'descargar'], true);

        abort_unless($hasValidSignature, 403, 'El enlace de descarga de su Planilla de Inscripción ha expirado o no es válido.');

        return $this->renderEnrollmentForm($request, $enrollment);
    }

    public function enrollmentForm(Request $request, Enrollment $enrollment): Response|View
    {
        $user = $request->user();
        $isOwner = $enrollment->student?->user_id === $user->id;
        abort_unless(
            $isOwner || $user->hasEffectivePermission('admisiones.view') || $user->hasEffectivePermission('consultas.view'),
            403
        );

        return $this->renderEnrollmentForm($request, $enrollment);
    }

    protected function renderEnrollmentForm(Request $request, Enrollment $enrollment): Response|View
    {
        $enrollment->load([
            'student.province',
            'student.canton',
            'student.district',
            'edition.course.category',
            'edition.shift',
            'edition.modality',
            'edition.schedules',
        ]);

        $data = [
            'enrollment' => $enrollment,
            'settings' => InstitutionSetting::current(),
            'documentTypes' => config('cecap.document_types'),
        ];

        return $this->outputDocument($request, 'pdf.enrollment-form', $data, 'Planilla-'.$enrollment->enrollment_code.'.pdf');
    }

    public function paymentReceipt(Request $request, PaymentReceipt $receipt): Response|View
    {
        $user = $request->user();
        $isOwner = $receipt->student?->user_id === $user->id;
        abort_unless(
            $isOwner || $user->hasEffectivePermission('finanzas.view') || $user->hasEffectivePermission('consultas.view'),
            403
        );

        $receipt->load(['student', 'payer', 'cashier', 'allocations.charge', 'enrollment.edition.course']);

        return $this->outputDocument($request, 'pdf.payment-receipt', [
            'receipt' => $receipt,
            'settings' => InstitutionSetting::current(),
        ], $receipt->receipt_number.'.pdf');
    }

    public function softSkillsCertificate(Request $request, Student $student): Response|View
    {
        $user = $request->user();
        $isOwner = $student->user_id === $user->id;
        abort_unless(
            $isOwner || $user->hasEffectivePermission('seguimiento.view') || $user->hasEffectivePermission('consultas.view'),
            403
        );

        $student->load(['softSkills.evaluator', 'softSkills.enrollment.edition.course']);

        return $this->outputDocument($request, 'pdf.soft-skills', [
            'student' => $student,
            'settings' => InstitutionSetting::current(),
        ], 'Constancia-Habilidades-'.$student->student_code.'.pdf');
    }

    public function graduationAct(Request $request, GraduationRecord $graduation): Response|View
    {
        abort_unless($request->user()->hasEffectivePermission('graduacion.view'), 403);
        $graduation->load(['student', 'enrollment.edition.course', 'issuer', 'solvencyExceptionAuthorizer']);

        return $this->outputDocument($request, 'pdf.graduation-act', [
            'graduation' => $graduation,
            'settings' => InstitutionSetting::current(),
        ], 'Acta-'.$graduation->act_number.'.pdf');
    }

    public function diploma(Request $request, GraduationRecord $graduation, GraduationService $service): Response|View
    {
        $user = $request->user();
        $isOwner = $graduation->student?->user_id === $user->id;
        abort_unless($isOwner || $user->hasEffectivePermission('graduacion.view') || $user->hasEffectivePermission('consultas.view'), 403);

        $graduation->load(['student', 'enrollment.edition.course', 'template']);
        $verifyUrl = route('diploma.verify', ['token' => $graduation->verification_token]);
        $qrSvg = $service->generateVerificationQrSvg($verifyUrl, 130);
        $qrDataUri = 'data:image/svg+xml;base64,'.base64_encode($qrSvg);

        return $this->outputDocument($request, 'pdf.diploma', [
            'graduation' => $graduation,
            'template' => $graduation->template,
            'settings' => InstitutionSetting::current(),
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => $qrDataUri,
        ], 'Diploma-'.$graduation->act_number.'.pdf', 'landscape');
    }

    public function staffContract(Request $request, StaffContract $contract): Response|View
    {
        abort_unless($request->user()->hasEffectivePermission('personal.view'), 403);
        $contract->load('staffMember');

        return $this->outputDocument($request, 'pdf.staff-contract', [
            'contract' => $contract,
            'settings' => InstitutionSetting::current(),
        ], 'Contrato-'.$contract->contract_number.'.pdf');
    }

    public function staffPaymentVoucher(Request $request, StaffPayment $payment): Response|View
    {
        abort_unless($request->user()->hasEffectivePermission('personal.view'), 403);
        $payment->load(['staffMember', 'contract', 'recorder']);

        return $this->outputDocument($request, 'pdf.staff-payment', [
            'payment' => $payment,
            'settings' => InstitutionSetting::current(),
        ], $payment->voucher_number.'.pdf');
    }

    protected function outputDocument(
        Request $request,
        string $view,
        array $data,
        string $filename,
        string $orientation = 'portrait'
    ): Response|View {
        $shared = array_merge($data, [
            'logoDataUri' => ($data['settings'] ?? InstitutionSetting::current())->logoDataUri(),
        ]);

        $pdf = Pdf::loadView($view, array_merge($shared, ['isHtmlPreview' => false]))
            ->setPaper('letter', $orientation);

        if ($request->query('vista') === 'html') {
            $html = view($view, array_merge($shared, ['isHtmlPreview' => true]))->render();
            $pdfDataUri = 'data:application/pdf;base64,'.base64_encode($pdf->output());
            $safeFilename = e($filename);

            $toolbar = <<<HTML
<style>
    @media print { .cecap-doc-toolbar { display: none !important; } }
    .cecap-doc-toolbar {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;
        background: #0F4C4C; color: #ffffff; padding: 10px 16px; border-radius: 10px;
        margin-bottom: 16px; font-family: Arial, Helvetica, sans-serif; font-size: 12px;
    }
    .cecap-doc-toolbar-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .cecap-doc-btn {
        display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px;
        font-size: 12px; font-weight: bold; text-decoration: none; cursor: pointer; border: 1px solid rgba(255,255,255,0.35);
    }
    .cecap-doc-btn-primary { background: #107050; color: #ffffff; border-color: #6ee7b7; }
    .cecap-doc-btn-secondary { background: #ffffff; color: #0F4C4C; border-color: #ffffff; }
</style>
<div class="cecap-doc-toolbar">
    <div><strong>Vista previa lista para imprimir o descargar</strong> · {$safeFilename}</div>
    <div class="cecap-doc-toolbar-actions">
        <button type="button" id="cecap-doc-print-btn" class="cecap-doc-btn cecap-doc-btn-secondary">Imprimir</button>
        <a href="{$pdfDataUri}" download="{$safeFilename}" class="cecap-doc-btn cecap-doc-btn-primary">Descargar PDF</a>
    </div>
</div>
<script>
    (function () {
        var btn = document.getElementById('cecap-doc-print-btn');
        if (btn) {
            btn.addEventListener('click', function () {
                try { window.focus(); window.print(); } catch (e) {}
            });
        }
    })();
</script>
HTML;

            $injected = preg_replace('/<body([^>]*)>/i', '<body$1>'.$toolbar, $html, 1) ?? ($toolbar.$html);

            return response($injected, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        if ($request->boolean('descargar')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
