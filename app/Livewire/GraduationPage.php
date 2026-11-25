<?php

namespace App\Livewire;

use App\Models\DiplomaTemplate;
use App\Models\Enrollment;
use App\Models\GraduationRecord;
use App\Services\AuditService;
use App\Services\GraduationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Graduación y Diplomas')]
class GraduationPage extends Component
{
    public string $tab = 'issue';

    public ?string $feedback = null;

    // Emisión de acta y diploma
    public ?int $enrollment_id = null;

    public ?int $template_id = null;

    public string $tomo = 'I';

    public string $folio = '01';

    public string $asiento = '001';

    public string $graduation_date = '';

    public bool $solvency_exception = false;

    public string $solvency_exception_reason = '';

    // Plantilla gráfica de diploma
    public string $tpl_name = '';

    public string $tpl_header = 'Certificado Oficial de Aprovechamiento';

    public string $tpl_body = 'CeCap — SURÍ Proyecto Educativo hace constar que {{student_name}} ({{student_id}}) completó y aprobó "{{course_name}}" con nota final de {{final_grade}}.';

    public string $tpl_primary_color = '#107050';

    public string $tpl_accent_color = '#68A040';

    public string $tpl_signatory_one_name = 'Dirección General CeCap';

    public string $tpl_signatory_one_title = 'Directora General';

    public string $tpl_signatory_two_name = 'Coordinación Académica';

    public string $tpl_signatory_two_title = 'Coordinación Académica';

    public function mount(): void
    {
        $this->graduation_date = now()->toDateString();
        $this->template_id = DiplomaTemplate::where('is_default', true)->value('id');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->feedback = null;
        $this->resetValidation();
    }

    public function issue(GraduationService $graduationService): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('graduacion.manage'), 403);

        $this->validate([
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'tomo' => ['required', 'string', 'max:30'],
            'folio' => ['required', 'string', 'max:30'],
            'asiento' => ['required', 'string', 'max:30'],
            'graduation_date' => ['required', 'date'],
        ]);

        $enrollment = Enrollment::with(['student', 'edition.course'])->findOrFail($this->enrollment_id);

        try {
            $record = $graduationService->issueGraduation(
                enrollment: $enrollment,
                tomo: $this->tomo,
                folio: $this->folio,
                asiento: $this->asiento,
                graduationDate: $this->graduation_date,
                templateId: $this->template_id,
                solvencyException: $this->solvency_exception,
                solvencyExceptionReason: $this->solvency_exception_reason ?: null,
                actor: Auth::user()
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $msgs) {
                $this->addError($k, $msgs[0]);
            }

            return;
        }

        $this->reset(['enrollment_id', 'solvency_exception', 'solvency_exception_reason']);
        $this->asiento = str_pad((string) (GraduationRecord::count() + 1), 3, '0', STR_PAD_LEFT);
        $this->feedback = sprintf('Graduación emitida: %s (Tomo %s, Folio %s, Asiento %s) con QR verificable.', $record->act_number, $record->tomo, $record->folio, $record->asiento);
    }

    public function createTemplate(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('graduacion.manage'), 403);

        $this->validate([
            'tpl_name' => ['required', 'string', 'max:140'],
            'tpl_header' => ['required', 'string', 'max:200'],
            'tpl_body' => ['required', 'string'],
        ]);

        $tpl = DiplomaTemplate::create([
            'name' => trim($this->tpl_name),
            'header_title' => trim($this->tpl_header),
            'body_template' => trim($this->tpl_body),
            'primary_color' => $this->tpl_primary_color,
            'accent_color' => $this->tpl_accent_color,
            'signatory_one_name' => $this->tpl_signatory_one_name,
            'signatory_one_title' => $this->tpl_signatory_one_title,
            'signatory_two_name' => $this->tpl_signatory_two_name ?: null,
            'signatory_two_title' => $this->tpl_signatory_two_title ?: null,
            'is_default' => false,
            'is_active' => true,
        ]);

        AuditService::log('graduation.template_created', 'graduacion', $tpl);
        $this->tpl_name = '';
        $this->feedback = 'Plantilla gráfica de diploma creada correctamente.';
    }

    public function render(GraduationService $graduationService)
    {
        $candidates = Enrollment::with(['student', 'edition.course'])
            ->whereIn('status', ['confirmada', 'en_curso', 'finalizada'])
            ->whereDoesntHave('graduationRecord')
            ->latest()
            ->limit(60)
            ->get();

        $selectedEval = null;
        if ($this->enrollment_id) {
            $selectedEnr = $candidates->firstWhere('id', $this->enrollment_id);
            if ($selectedEnr) {
                $selectedEval = $graduationService->evaluateEligibility($selectedEnr);
            }
        }

        return view('livewire.graduation-page', [
            'candidates' => $candidates,
            'selectedEval' => $selectedEval,
            'graduations' => GraduationRecord::with(['student', 'enrollment.edition.course', 'template'])->latest('graduation_date')->limit(60)->get(),
            'templates' => DiplomaTemplate::where('is_active', true)->orderByDesc('is_default')->get(),
            'canAuthorizeSolvencyException' => Auth::user()->hasAnyRole(['direccion', 'super_admin']),
        ]);
    }
}
