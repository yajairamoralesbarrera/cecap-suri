<?php

namespace App\Livewire;

use App\Models\Enrollment;
use App\Models\PaymentReceipt;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ventanilla Única 360°')]
class QueriesPage extends Component
{
    public string $search = '';

    public ?int $selected_student_id = null;

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
            $studentIdsFromEnroll = Enrollment::whereRaw('LOWER(enrollment_code) LIKE ?', [$like])->pluck('student_id');
            $studentIdsFromReceipt = PaymentReceipt::whereRaw('LOWER(receipt_number) LIKE ?', [$like])->pluck('student_id');

            $matches = Student::query()
                ->where(function ($q) use ($like, $studentIdsFromEnroll, $studentIdsFromReceipt): void {
                    $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(identification_number) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(student_code) LIKE ?', [$like])
                        ->orWhereIn('id', $studentIdsFromEnroll)
                        ->orWhereIn('id', $studentIdsFromReceipt);
                })
                ->limit(25)
                ->get();
        } else {
            $matches = Student::latest()->limit(15)->get();
        }

        $selected = $this->selected_student_id
            ? Student::with([
                'province', 'canton', 'district',
                'enrollments.edition.course', 'enrollments.gradeRecords', 'enrollments.attendanceRecords',
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
        ]);
    }
}
