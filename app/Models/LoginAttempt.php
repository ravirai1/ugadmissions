<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ip', 'guard', 'email', 'successful', 'user_agent'];

    protected function casts(): array
    {
        return ['successful' => 'boolean', 'created_at' => 'datetime'];
    }
}
