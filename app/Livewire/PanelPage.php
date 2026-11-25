<?php

namespace App\Livewire;

use App\Models\CourseEdition;
use App\Models\Enrollment;
use App\Models\FeeCharge;
use App\Models\GraduationRecord;
use App\Models\PaymentReceipt;
use App\Models\StaffMember;
use App\Models\Student;
use App\Models\Workshop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Panel Principal')]
class PanelPage extends Component
{
    public function render()
    {
        $user = Auth::user();
        $isAlumnaOnly = $user->hasRole('alumna') && ! $user->hasAnyRole([
            'super_admin', 'direccion', 'secretaria', 'administracion_caja',
            'coordinacion_academica', 'coordinador_categoria', 'profesora',
        ]);

        $studentProfile = Student::query()
            ->where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->with([
                'enrollments.edition.course',
                'enrollments.edition.schedules',
                'enrollments.gradeRecords',
                'enrollments.attendanceRecords',
                'feeCharges',
                'paymentReceipts',
                'softSkills',
                'graduations',
            ])
            ->first();

        $stats = [
            'students' => Student::count(),
            'active_editions' => CourseEdition::whereIn('status', ['abierta', 'en_curso'])->count(),
            'pending_admissions' => Enrollment::whereIn('status', ['preinscrita', 'documentacion_pendiente'])->count(),
            'confirmed_enrollments' => Enrollment::whereIn('status', ['confirmada', 'en_curso'])->count(),
            'collected_month' => (float) PaymentReceipt::where('status', 'emitido')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('total_amount'),
            'overdue_charges' => FeeCharge::whereIn('status', ['pendiente', 'parcial'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->count(),
            'graduations' => GraduationRecord::where('status', 'emitido')->count(),
            'staff' => StaffMember::where('status', 'activo')->count(),
            'upcoming_workshops' => Workshop::where('status', 'programado')->count(),
        ];

        $teacherEditions = $user->hasRole('profesora')
            ? CourseEdition::where('teacher_id', $user->id)->with(['course', 'schedules'])->latest()->get()
            : collect();

        return view('livewire.panel-page', [
            'user' => $user,
            'isAlumnaOnly' => $isAlumnaOnly,
            'studentProfile' => $studentProfile,
            'stats' => $stats,
            'teacherEditions' => $teacherEditions,
        ]);
    }
}
