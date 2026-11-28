<?php

namespace App\Livewire;

use App\Models\Picklist;
use App\Models\Student;
use App\Models\Workshop;
use App\Models\WorkshopParticipant;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Talleres y Actividades')]
class ActivitiesPage extends Component
{
    public ?string $feedback = null;

    public string $title = '';

    public string $workshop_type = 'taller';

    public string $facilitator_name = '';

    public string $event_date = '';

    public string $start_time = '09:00';

    public string $end_time = '12:00';

    public string $location = 'Auditorio CeCap SURÍ';

    public int $max_capacity = 40;

    public int $soft_skills_absence_credits = 0;

    public bool $has_cost = false;

    public float|string $cost = 0;

    public string $description = '';

    public ?int $selected_workshop_id = null;

    public int $edit_workshop_credits = 0;

    public float|string $edit_workshop_cost = 0;

    public ?int $participant_student_id = null;

    public string $participant_name = '';

    public string $participant_id_num = '';

    public string $participant_email = '';

    public string $participant_payment_method = 'exento_gratuito';

    public string $participant_payment_reference = '';

    public bool $participant_is_imas = false;

    public function mount(): void
    {
        $this->event_date = now()->addDays(5)->toDateString();
    }

    public function updatedHasCost(bool $value): void
    {
        if (! $value) {
            $this->cost = 0;
        }
    }

    public function updatedParticipantStudentId($value): void
    {
        $this->participant_is_imas = false;
        $workshop = $this->selected_workshop_id ? Workshop::find($this->selected_workshop_id) : null;
        $hasCost = $workshop && (float) $workshop->cost > 0;

        if (! $value) {
            $this->participant_payment_method = $hasCost ? 'sinpe_movil' : 'exento_gratuito';

            return;
        }

        $st = Student::find($value);
        if ($st) {
            $this->participant_name = $st->full_name;
            $this->participant_id_num = $st->identification_number;
            $this->participant_email = $st->email;

            if ($st->hasImasSponsor()) {
                $this->participant_is_imas = true;
                $this->participant_payment_method = 'imas';
            } else {
                $this->participant_payment_method = $hasCost ? 'sinpe_movil' : 'exento_gratuito';
            }
        }
    }

    public function createWorkshop(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('actividades.manage'), 403);

        $rules = [
            'title' => ['required', 'string', 'max:180'],
            'workshop_type' => ['required', 'string', 'max:40'],
            'facilitator_name' => ['required', 'string', 'max:140'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'max_capacity' => ['required', 'integer', 'min:1'],
            'soft_skills_absence_credits' => ['required', 'integer', 'min:0', 'max:20'],
            'cost' => $this->has_cost ? ['required', 'numeric', 'min:1'] : ['nullable', 'numeric', 'min:0'],
        ];

        $this->validate($rules, [
            'cost.min' => 'Si la actividad tiene costo, indique un monto mayor a ₡0.',
        ]);

        $finalCost = ($this->has_cost || (float) $this->cost > 0) ? round((float) $this->cost, 2) : 0.0;

        $workshop = Workshop::create([
            'code' => Workshop::nextCode(),
            'title' => trim($this->title),
            'workshop_type' => $this->workshop_type,
            'description' => $this->description ?: null,
            'facilitator_name' => trim($this->facilitator_name),
            'event_date' => $this->event_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'location' => $this->location ?: null,
            'max_capacity' => $this->max_capacity,
            'soft_skills_absence_credits' => $this->soft_skills_absence_credits,
            'cost' => $finalCost,
            'status' => 'programado',
            'created_by' => Auth::id(),
        ]);

        AuditService::log('activities.workshop_created', 'actividades', $workshop, null, $workshop->only(['code', 'title', 'workshop_type', 'soft_skills_absence_credits', 'cost']));
        $this->reset(['title', 'facilitator_name', 'description']);
        $this->soft_skills_absence_credits = 0;
        $this->has_cost = false;
        $this->cost = 0;
        $this->selectWorkshop($workshop->id);
        $this->feedback = 'Taller / Actividad creado correctamente.';
    }

    public function selectWorkshop(int $id): void
    {
        $this->selected_workshop_id = $id;
        $w = Workshop::find($id);
        $this->edit_workshop_credits = (int) ($w?->soft_skills_absence_credits ?? 0);
        $this->edit_workshop_cost = (float) ($w?->cost ?? 0);
        if (! $this->participant_is_imas) {
            $this->participant_payment_method = ($w && (float) $w->cost > 0) ? 'sinpe_movil' : 'exento_gratuito';
        }
    }

    public function updateSelectedWorkshopCredits(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('actividades.manage'), 403);
        if (! $this->selected_workshop_id) {
            return;
        }

        $this->validate([
            'edit_workshop_credits' => ['required', 'integer', 'min:0', 'max:20'],
            'edit_workshop_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $w = Workshop::findOrFail($this->selected_workshop_id);
        $old = [
            'soft_skills_absence_credits' => (int) $w->soft_skills_absence_credits,
            'cost' => (float) $w->cost,
        ];
        $newCost = round((float) $this->edit_workshop_cost, 2);
        $w->update([
            'soft_skills_absence_credits' => $this->edit_workshop_credits,
            'cost' => $newCost,
        ]);

        AuditService::log(
            event: 'activities.workshop_credits_updated',
            module: 'actividades',
            auditable: $w,
            oldValues: $old,
            newValues: [
                'soft_skills_absence_credits' => $this->edit_workshop_credits,
                'cost' => $newCost,
            ]
        );

        $this->feedback = sprintf(
            'Actividad "%s" actualizada: Costo ₡%s · Compensa %d ausencia(s) en Habilidades Blandas.',
            $w->title,
            number_format($newCost, 2),
            $this->edit_workshop_credits
        );
    }

    public function addParticipant(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('actividades.manage'), 403);

        $this->validate([
            'selected_workshop_id' => ['required', 'integer', 'exists:workshops,id'],
            'participant_name' => ['required', 'string', 'max:160'],
            'participant_payment_method' => ['nullable', 'string', 'max:40'],
            'participant_payment_reference' => ['nullable', 'string', 'max:120'],
        ]);

        $workshop = Workshop::findOrFail($this->selected_workshop_id);
        $student = $this->participant_student_id ? Student::find($this->participant_student_id) : null;
        $isImasStudent = $student && $student->hasImasSponsor();

        $workshopCost = round((float) $workshop->cost, 2);
        $paymentMethod = $this->participant_payment_method ?: ($workshopCost > 0 ? 'sinpe_movil' : 'exento_gratuito');

        // Regla institucional: si la alumna tiene patrocinador IMAS, el método de cancelación SIEMPRE es IMAS
        if ($isImasStudent) {
            $paymentMethod = 'imas';
        }

        $paymentStatus = match (true) {
            $paymentMethod === 'imas' => 'cubierto_imas',
            $workshopCost <= 0 || $paymentMethod === 'exento_gratuito' => 'exento',
            default => 'pagado',
        };

        $participant = WorkshopParticipant::create([
            'workshop_id' => $workshop->id,
            'student_id' => $student?->id,
            'participant_name' => trim($this->participant_name),
            'identification_number' => $this->participant_id_num ?: null,
            'email' => $this->participant_email ?: null,
            'attended' => true,
            'amount_charged' => $workshopCost,
            'payment_method' => $paymentMethod,
            'payment_status' => $paymentStatus,
            'payment_reference' => trim($this->participant_payment_reference) !== '' ? trim($this->participant_payment_reference) : null,
            'recorded_by' => Auth::id(),
        ]);

        AuditService::log('activities.participant_registered', 'actividades', $participant, null, $participant->only([
            'workshop_id', 'student_id', 'participant_name', 'amount_charged', 'payment_method', 'payment_status',
        ]));

        $this->reset(['participant_student_id', 'participant_name', 'participant_id_num', 'participant_email', 'participant_payment_reference', 'participant_is_imas']);
        $this->participant_payment_method = $workshopCost > 0 ? 'sinpe_movil' : 'exento_gratuito';
        $this->feedback = $isImasStudent
            ? 'Participante con patrocinador IMAS registrada automáticamente con método de cancelación IMAS.'
            : 'Participante registrada en el taller.';
    }

    public function toggleAttendance(int $participantId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('actividades.manage'), 403);
        $p = WorkshopParticipant::findOrFail($participantId);
        $p->update(['attended' => ! $p->attended]);
    }

    public function render()
    {
        $paymentMethods = Picklist::optionsFor('payment_method');
        if (! array_key_exists('imas', $paymentMethods)) {
            $paymentMethods['imas'] = 'IMAS (Patrocinador)';
        }

        return view('livewire.activities-page', [
            'workshops' => Workshop::withCount('participants')->latest('event_date')->get(),
            'selectedWorkshop' => $this->selected_workshop_id ? Workshop::with('participants.student')->find($this->selected_workshop_id) : null,
            'students' => Student::orderBy('first_name')->orderBy('last_name')->limit(200)->get(),
            'workshopTypes' => Picklist::optionsFor('workshop_type'),
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
