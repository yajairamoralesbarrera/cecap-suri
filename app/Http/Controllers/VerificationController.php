<?php

namespace App\Http\Controllers;

use App\Models\GraduationRecord;
use App\Models\InstitutionSetting;
use Illuminate\View\View;

class VerificationController extends Controller
{
    public function verifyDiploma(string $token): View
    {
        $record = null;
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $token)) {
            $record = GraduationRecord::query()
                ->where('verification_token', $token)
                ->with(['student', 'enrollment.edition.course.category'])
                ->first();
        }

        return view('public.verify-diploma', [
            'record' => $record,
            'token' => $token,
            'settings' => InstitutionSetting::current(),
        ]);
    }
}
