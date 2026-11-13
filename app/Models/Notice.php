<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    public const AUDIENCES = [
        'public' => 'Everyone (website and dashboards)',
        'applicants' => 'All registered applicants',
        'submitted' => 'Applicants who submitted the form',
        'paid' => 'Applicants who paid the fee',
    ];

    protected $fillable = ['title', 'body', 'audience', 'is_published', 'is_pinned', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_pinned' => 'boolean', 'published_at' => 'datetime', 'emailed_at' => 'datetime'];
    }

    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderByDesc('is_pinned')->orderByDesc('published_at');
    }

    /** Notices an applicant should see on the dashboard. */
    public static function forUser(?User $user): Builder
    {
        if (! $user) {
            return static::published()->where('audience', 'public');
        }
        $app = $user->application()->first();
        $aud = ['public', 'applicants'];
        if ($app?->isSubmitted()) {
            $aud[] = 'submitted';
        }
        if ($app?->isPaid()) {
            $aud[] = 'paid';
        }

        return static::published()->whereIn('audience', $aud);
    }

    /** Users who should get the email for this notice. */
    public function recipients(): Builder
    {
        $q = User::query()->whereNotNull('email_verified_at');

        return match ($this->audience) {
            'submitted' => $q->whereHas('application', fn ($a) => $a->where('status', Application::SUBMITTED)),
            'paid' => $q->whereHas('application', fn ($a) => $a->whereNotNull('fee_paid_at')),
            default => $q,
        };
    }
}
