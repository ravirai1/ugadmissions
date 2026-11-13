<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Course extends Model
{
    protected $fillable = ['code', 'name', 'department_id', 'offered_at_jmc', 'eligibility', 'seats', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'offered_at_jmc' => 'boolean',
            'eligibility' => 'array',
            'seats' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
