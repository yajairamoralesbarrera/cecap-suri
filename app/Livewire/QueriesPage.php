<?php

namespace App\Livewire;

use App\Models\Enrollment;
use App\Models\PaymentReceipt;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Expediente Alumnas')]
class QueriesPage extends Component
{
    public string $search = '';

    public ?int $selected_student_id = null;

    public function mount(): void
    {
        $alumnaId = request()->integer('alumna');
        if ($alumnaId > 0 && Student::whereKey($alumnaId)->exists()) {
            $this->selected_student_id = $alumnaId;
        }
    }

    public function selectStudent(int $id): void
    {
        $this->selected_student_id = $id;
    }

    public function render()
    {
        $term = trim($this->search);
        $matches = collect();

        if ($term !== '') {
            $like = '%'.strtolower($term).'%';
            $normalizedId = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $term));
            $studentIdsFromEnroll = Enrollment::whereRaw('LOWER(enrollment_code) LIKE ?', [$like])->pluck('student_id');
            $studentIdsFromReceipt = PaymentReceipt::whereRaw('LOWER(receipt_number) LIKE ?', [$like])->pluck('student_id');

            $matches = Student::query()
                ->where(function ($q) use ($like, $normalizedId, $studentIdsFromEnroll, $studentIdsFromReceipt): void {
                    $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(identification_number) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(student_code) LIKE ?', [$like])
                        ->orWhereIn('id', $studentIdsFromEnroll)
                        ->orWhereIn('id', $studentIdsFromReceipt);

                    if ($normalizedId !== '') {
                        $q->orWhereRaw("UPPER(REGEXP_REPLACE(identification_number, '[^0-9A-Za-z]', '', 'g')) LIKE ?", ['%'.$normalizedId.'%']);
                    }
                })
                ->limit(25)
                ->get();
        } else {
            $matches = Student::latest()->limit(15)->get();
        }

        $selected = $this->selected_student_id
            ? Student::with([
                'province', 'canton', 'district',
                'enrollments.edition.course', 'enrollments.gradeRecords', 'enrollments.attendanceRecords.subject',
                'feeCharges', 'paymentReceipts.cashier',
                'softSkills.evaluator',
                'graduations.enrollment.edition.course',
                'attachments',
                'lifecycleEvents',
            ])->find($this->selected_student_id)
            : null;

        return view('livewire.queries-page', [
            'matches' => $matches,
            'selected' => $selected,
            'idTypes' => \App\Models\Picklist::optionsFor('id_type'),
            'genders' => \App\Models\Picklist::optionsFor('gender'),
            'educationLevels' => \App\Models\Picklist::optionsFor('education_level'),
        ]);
    }
}
