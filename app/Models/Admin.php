<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Staff account (separate table and guard from applicants).
 */
class Admin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'mobile', 'password', 'role', 'department_id',
        'permissions', 'is_active', 'must_change_password', 'created_by',
    ];

    protected $hidden = ['password', 'remember_token', 'otp_hash'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'otp_expires_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Roles::SUPER_ADMIN;
    }

    public function hasRole(string ...$roles): bool
    {
        return $this->isSuperAdmin() || in_array($this->role, $roles, true);
    }

    public function can($ability, $arguments = []): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($ability, $this->permissions ?? [], true) || parent::can($ability, $arguments);
    }

    public function roleLabel(): string
    {
        return Roles::labels()[$this->role] ?? $this->role;
    }
}
