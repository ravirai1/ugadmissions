<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The only way to create a super admin. Not reachable from the website.
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create-super {--name=} {--email=}';

    protected $description = 'Create a super admin account (command line only)';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = strtolower(trim($this->option('email') ?: $this->ask('Email')));
        $password = $this->secret('Password (min 12 chars, upper, lower, number, symbol)');
        $confirm = $this->secret('Confirm password');

        $v = Validator::make(
            compact('name', 'email', 'password') + ['password_confirmation' => $confirm],
            [
                'name' => ['required', 'max:100'],
                'email' => ['required', 'email', 'unique:admins,email'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            ]
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        Admin::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Roles::SUPER_ADMIN,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->info("Super admin {$email} created.");

        return self::SUCCESS;
    }
}
