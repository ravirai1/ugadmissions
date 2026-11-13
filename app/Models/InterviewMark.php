<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewMark extends Model
{
    protected $fillable = ['call_letter_id', 'admin_id', 'marks', 'remarks'];

    protected function casts(): array
    {
        return ['marks' => 'decimal:2'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function callLetter(): BelongsTo
    {
        return $this->belongsTo(CallLetter::class);
    }
}
