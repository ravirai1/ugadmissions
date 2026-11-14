<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Rules\ValidCaptcha;
use App\Services\LoginGuard;
use App\Services\MathCaptcha;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private LoginGuard $loginGuard, private MathCaptcha $captcha) {}

    public function showRegister()
    {
        return view('auth.register', ['captcha' => $this->captcha->generate('register')]);
    }

    public function register(Request $request)
    {
        $clean = [
            'name' => fn ($v) => preg_replace('/\s+/', ' ', trim((string) $v)),
            'email' => fn ($v) => strtolower(trim((string) $v)),
            'cuet_no' => fn ($v) => preg_replace('/\s+/', '', (string) $v),
            'mobile' => fn ($v) => preg_replace('/\D/', '', (string) $v),
        ];
        // Each field is typed twice (paste is blocked on the form) to catch typing mistakes.
        foreach ($clean as $field => $fn) {
            $request->merge([$field => $fn($request->input($field)), $field.'_confirmation' => $fn($request->input($field.'_confirmation'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\s.\'-]+$/u', 'confirmed'],
            'email' => ['required', 'email:rfc', 'max:150', 'unique:users,email', 'confirmed'],
            'mobile' => ['required', 'regex:/^[6-9][0-9]{9}$/', 'confirmed'],
            'cuet_no' => ['required', 'regex:'.config('admissions.cuet_regex'), 'unique:users,cuet_no', 'confirmed'],
            'password' => ['required', 'confirmed', StrongPassword::rule()],
            'captcha' => ['required', new ValidCaptcha('register')],
        ], [
            'name.regex' => 'Name may only contain letters, spaces, dots, hyphens and apostrophes.',
            'mobile.regex' => 'Enter a valid 10-digit Indian mobile number.',
            'cuet_no.regex' => 'CUET application number must start with '.config('admissions.cuet_prefix').' and contain only digits.',
            'cuet_no.unique' => 'An account already exists for this CUET application number.',
            'name.confirmed' => 'The two names do not match.',
            'email.confirmed' => 'The two email addresses do not match.',
            'mobile.confirmed' => 'The two mobile numbers do not match.',
            'cuet_no.confirmed' => 'The two CUET application numbers do not match.',
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $user = User::create($data);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', 'Account created. We have sent a verification link to '.$user->email.'.');
    }

    public function showLogin()
    {
        return view('auth.login', ['captcha' => $this->captcha->generate('login')]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'captcha' => ['required', new ValidCaptcha('login')],
        ]);
        unset($credentials['captcha']);
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->loginGuard->recordFailure($request, 'applicant', $credentials['email']);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $this->loginGuard->recordSuccess($request, 'applicant', $credentials['email']);
        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return redirect()->intended(route('applicant.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
