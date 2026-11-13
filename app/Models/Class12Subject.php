<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Class 12 subject name, mapped to the CUET paper it counts for (if any).
 */
class Class12Subject extends Model
{
    protected $table = 'class12_subjects';

    protected $fillable = ['name', 'subject_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
