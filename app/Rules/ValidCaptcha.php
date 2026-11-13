<?php

namespace App\Rules;

use App\Services\MathCaptcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCaptcha implements ValidationRule
{
    public function __construct(private string $key) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (app()->runningUnitTests() && config('app.captcha_bypass_in_tests', true) && $value === 'test-pass') {
            return;
        }

        if (! app(MathCaptcha::class)->check($this->key, $value)) {
            $fail('The security answer is incorrect. Please try again.');
        }
    }
}
