<?php

namespace App\Services;

/**
 * Self-hosted arithmetic CAPTCHA (no third-party scripts, keeps CSP strict).
 * Each form gets its own key so several forms can be open in different tabs.
 */
class MathCaptcha
{
    public function generate(string $key): string
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        session()->put("captcha.$key", $a + $b);

        return "$a + $b";
    }

    public function check(string $key, mixed $answer): bool
    {
        $expected = session()->pull("captcha.$key");

        return $expected !== null && is_numeric($answer) && (int) $answer === (int) $expected;
    }
}
