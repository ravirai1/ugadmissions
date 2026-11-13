<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationCourse extends Model
{
    protected $fillable = [
        'application_id', 'course_id', 'du_preference', 'jmc_preference', 'jmc_not_applied',
        'combination', 'papers', 'cuet_score', 'statement', 'added_after_payment', 'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'papers' => 'array',
            'cuet_score' => 'float',
            'jmc_not_applied' => 'boolean',
            'added_after_payment' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
