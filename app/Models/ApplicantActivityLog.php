<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantActivityLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $table = 'applicant_activity_logs';

    protected $fillable = ['user_id', 'login_id', 'event', 'method', 'path', 'status', 'ip', 'user_agent'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kept for the configured number of days (config admissions.activity_log_days). */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(config('admissions.activity_log_days', 400)));
    }
}
