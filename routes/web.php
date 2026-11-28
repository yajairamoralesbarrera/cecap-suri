<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentPdfController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\VerificationController;
use App\Livewire\AcademicPage;
use App\Livewire\ActivitiesPage;
use App\Livewire\AdmissionsPage;
use App\Livewire\DirectionPage;
use App\Livewire\DocumentsPage;
use App\Livewire\FinancePage;
use App\Livewire\GraduationPage;
use App\Livewire\PanelPage;
use App\Livewire\PublicEnrollmentPage;
use App\Livewire\QueriesPage;
use App\Livewire\StaffPage;
use App\Livewire\TrackingPage;
use Illuminate\Support\Facades\Route;

// PWA Universal (dinámico y sin caché transaccional)
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.sw');

// Raíz -> Portal de Inscripción o Panel si autenticado
Route::get('/', function () {
    return auth()->check() ? redirect()->route('panel') : redirect()->route('inscripcion.publica');
})->name('home');

// Rutas Públicas: Inscripción en Línea, Planilla Firmada (7 días) y Verificación QR de Diploma
Route::get('/inscripcion', PublicEnrollmentPage::class)->name('inscripcion.publica');
Route::get('/inscripcion/planilla/{enrollment}', [DocumentPdfController::class, 'publicEnrollmentForm'])->name('inscripcion.planilla.publica');
Route::get('/verificar/{token}', [VerificationController::class, 'verifyDiploma'])->name('diploma.verify');

// Autenticación, Auto-registro de Alumna y Recuperación Dual
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
    Route::get('/registro', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/registro', [AuthController::class, 'register'])->name('register.post');

    Route::get('/recuperar-contrasena', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/recuperar-contrasena/correo', [AuthController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::post('/recuperar-contrasena/identidad', [AuthController::class, 'verifyIdentityRecovery'])->name('password.identity');

    Route::get('/restablecer-contrasena/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/restablecer-contrasena', [AuthController::class, 'resetPassword'])->name('password.reset.update');
});

// Rutas Autenticadas
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/cambiar-contrasena', [AuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/cambiar-contrasena', [AuthController::class, 'updatePassword'])->name('password.change.update');

    // Módulos protegidos por cambio obligatorio de contraseña
    Route::middleware(['password.changed'])->group(function () {
        Route::get('/panel', PanelPage::class)->middleware('cecap.perm:panel.view')->name('panel');

        Route::get('/academico', AcademicPage::class)->middleware(['module.enabled:academico', 'cecap.perm:academico.view'])->name('academico');
        Route::get('/admisiones', AdmissionsPage::class)->middleware(['module.enabled:admisiones', 'cecap.perm:admisiones.view'])->name('admisiones');
        Route::get('/seguimiento', TrackingPage::class)->middleware(['module.enabled:seguimiento', 'cecap.perm:seguimiento.view'])->name('seguimiento');
        Route::get('/finanzas', FinancePage::class)->middleware(['module.enabled:finanzas', 'cecap.perm:finanzas.view'])->name('finanzas');
        Route::get('/graduacion', GraduationPage::class)->middleware(['module.enabled:graduacion', 'cecap.perm:graduacion.view'])->name('graduacion');
        Route::get('/personal', StaffPage::class)->middleware(['module.enabled:personal', 'cecap.perm:personal.view'])->name('personal');
        Route::get('/actividades', ActivitiesPage::class)->middleware(['module.enabled:actividades', 'cecap.perm:actividades.view'])->name('actividades');
        Route::get('/documentos', DocumentsPage::class)->middleware(['module.enabled:documentos', 'cecap.perm:documentos.view'])->name('documentos');
        Route::get('/consultas', QueriesPage::class)->middleware(['module.enabled:consultas', 'cecap.perm:consultas.view'])->name('consultas');
        Route::get('/direccion', DirectionPage::class)->middleware(['module.enabled:direccion', 'cecap.perm:direccion.view'])->name('direccion');

        // Exportaciones CSV UTF-8 BOM y Excel (.xlsx)
        Route::get('/direccion/exportar/{type}', [ExportController::class, 'exportCsv'])->name('direccion.export');
        Route::get('/seguimiento/asistencias/excel', [ExportController::class, 'exportAttendanceExcel'])
            ->middleware(['module.enabled:seguimiento', 'cecap.perm:seguimiento.view'])
            ->name('seguimiento.attendance.excel');

        // Adjuntos privados protegidos
        Route::get('/adjuntos/{uuid}', [AttachmentController::class, 'download'])->name('attachments.download');

        // Documentos PDF Oficiales (soportan ?vista=html para modal seguro y descarga PDF DomPDF)
        Route::get('/pdf/planilla/{enrollment}', [DocumentPdfController::class, 'enrollmentForm'])->name('pdf.planilla');
        Route::get('/pdf/recibo/{receipt}', [DocumentPdfController::class, 'paymentReceipt'])->name('pdf.recibo');
        Route::get('/pdf/habilidades-blandas/{student}', [DocumentPdfController::class, 'softSkillsCertificate'])->name('pdf.habilidades');
        Route::get('/pdf/acta-grado/{graduation}', [DocumentPdfController::class, 'graduationAct'])->name('pdf.acta');
        Route::get('/pdf/diploma/{graduation}', [DocumentPdfController::class, 'diploma'])->name('pdf.diploma');
        Route::get('/pdf/contrato/{contract}', [DocumentPdfController::class, 'staffContract'])->name('pdf.contrato');
        Route::get('/pdf/comprobante-personal/{payment}', [DocumentPdfController::class, 'staffPaymentVoucher'])->name('pdf.comprobante-personal');
    });
});
