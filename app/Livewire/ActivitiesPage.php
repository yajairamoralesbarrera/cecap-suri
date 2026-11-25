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

    public string $description = '';

    public ?int $selected_workshop_id = null;

    public ?int $participant_student_id = null;

    public string $participant_name = '';

    public string $participant_id_num = '';

    public string $participant_email = '';

    public function mount(): void
    {
        $this->event_date = now()->addDays(5)->toDateString();
    }

    public function updatedParticipantStudentId($value): void
    {
        if (! $value) {
            return;
        }
        $st = Student::find($value);
        if ($st) {
            $this->participant_name = $st->full_name;
            $this->participant_id_num = $st->identification_number;
            $this->participant_email = $st->email;
        }
    }

    public function createWorkshop(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('actividades.manage'), 403);

        $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'workshop_type' => ['required', 'string', 'max:40'],
            'facilitator_name' => ['required', 'string', 'max:140'],
            'event_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'max_capacity' => ['required', 'integer', 'min:1'],
        ]);

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
            'status' => 'programado',
            'created_by' => Auth::id(),
        ]);

        AuditService::log('activities.workshop_created', 'actividades', $workshop);
        $this->reset(['title', 'facilitator_name', 'description']);
        $this->selected_workshop_id = $workshop->id;
        $this->feedback = 'Taller / Actividad creado correctamente.';
    }

    public function selectWorkshop(int $id): void
    {
        $this->selected_workshop_id = $id;
    }

    public function addParticipant(): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('actividades.manage'), 403);

        $this->validate([
            'selected_workshop_id' => ['required', 'integer', 'exists:workshops,id'],
            'participant_name' => ['required', 'string', 'max:160'],
        ]);

        WorkshopParticipant::create([
            'workshop_id' => $this->selected_workshop_id,
            'student_id' => $this->participant_student_id ?: null,
            'participant_name' => trim($this->participant_name),
            'identification_number' => $this->participant_id_num ?: null,
            'email' => $this->participant_email ?: null,
            'attended' => true,
            'recorded_by' => Auth::id(),
        ]);

        $this->reset(['participant_student_id', 'participant_name', 'participant_id_num', 'participant_email']);
        $this->feedback = 'Participante registrada en el taller.';
    }

    public function toggleAttendance(int $participantId): void
    {
        abort_unless(Auth::user()->hasEffectivePermission('actividades.manage'), 403);
        $p = WorkshopParticipant::findOrFail($participantId);
        $p->update(['attended' => ! $p->attended]);
    }

    public function render()
    {
        return view('livewire.activities-page', [
            'workshops' => Workshop::withCount('participants')->latest('event_date')->get(),
            'selectedWorkshop' => $this->selected_workshop_id ? Workshop::with('participants')->find($this->selected_workshop_id) : null,
            'students' => Student::orderBy('first_name')->limit(100)->get(),
            'workshopTypes' => Picklist::optionsFor('workshop_type'),
        ]);
    }
}
