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
        abort_unless($request->hasValidSignatureWhileIgnoring(['vista', 'descargar']), 403, 'El enlace de descarga de su Planilla de Inscripción ha expirado o no es válido.');

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
        if ($request->query('vista') === 'html') {
            return view($view, array_merge($data, ['isHtmlPreview' => true]));
        }

        $pdf = Pdf::loadView($view, array_merge($data, ['isHtmlPreview' => false]))
            ->setPaper('letter', $orientation);

        if ($request->boolean('descargar')) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
