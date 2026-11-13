<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationCuetScore extends Model
{
    protected $fillable = ['application_id', 'subject_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
