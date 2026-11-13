<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $fillable = ['name', 'is_ut', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['is_ut' => 'boolean', 'is_active' => 'boolean'];
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
