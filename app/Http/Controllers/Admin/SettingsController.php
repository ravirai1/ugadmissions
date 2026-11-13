<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\FeeCalculator;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const FEE_LABELS = [
        'first_course' => 'First course',
        'additional_course' => 'Each additional course',
        'residence' => 'Residence',
        'course_after_payment' => 'Course added after payment (each)',
        'residence_after_payment' => 'Residence added after payment',
    ];

    public function fees()
    {
        return view('admin.settings.fees', ['fees' => FeeCalculator::fees(), 'labels' => self::FEE_LABELS]);
    }

    public function updateFees(Request $request)
    {
        $rules = collect(self::FEE_LABELS)->mapWithKeys(fn ($l, $k) => [$k => ['required', 'integer', 'min:0', 'max:100000']])->all();
        $data = array_map('intval', $request->validate($rules));
        $before = FeeCalculator::fees();

        Setting::put('fees', $data, $request->user('admin')->id);
        AuditLog::record('settings.fees', null, ['before' => $before, 'after' => $data]);

        return back()->with('status', 'Fees saved. They apply to payments started from now on.');
    }
}
