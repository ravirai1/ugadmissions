<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Rules\StrongPassword;
use App\Rules\ValidCaptcha;
use App\Services\MathCaptcha;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function __construct(private MathCaptcha $captcha) {}

    public function request()
    {
        return view('auth.forgot-password', ['captcha' => $this->captcha->generate('forgot')]);
    }

    public function email(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'captcha' => ['required', new ValidCaptcha('forgot')],
        ]);

        Password::broker('users')->sendResetLink(['email' => strtolower(trim($request->email))]);

        // Same message whether or not the account exists, so emails cannot be enumerated.
        return back()->with('status', 'If an account exists for that email, a password reset link has been sent.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', StrongPassword::rule()],
        ]);

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Your password has been reset. Please log in.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}
