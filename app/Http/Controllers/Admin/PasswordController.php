<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Rules\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('admin.auth.change-password');
    }

    public function update(Request $request)
    {
        $admin = $request->user('admin');

        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'confirmed', 'different:current_password', StrongPassword::rule()],
        ]);

        $admin->forceFill([
            'password' => $request->password,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();
        AuditLog::record('auth.password_changed');

        return redirect()->route('admin.dashboard')->with('status', 'Password updated.');
    }
}
