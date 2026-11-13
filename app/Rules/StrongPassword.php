<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

class StrongPassword
{
    public static function rule(): Password
    {
        return Password::min(8)->letters()->mixedCase()->numbers()->symbols();
    }
}
