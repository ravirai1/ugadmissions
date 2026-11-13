<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    protected $fillable = ['ip', 'guard', 'reason', 'blocked_until', 'blocked_by'];

    protected function casts(): array
    {
        return ['blocked_until' => 'datetime'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()));
    }
}
