<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Notice extends Model
{
    public const AUDIENCES = [
        'public' => 'Everyone (website and dashboards)',
        'applicants' => 'All registered applicants',
        'submitted' => 'Applicants who submitted the form',
        'paid' => 'Applicants who paid the fee',
    ];

    protected $fillable = ['title', 'body', 'audience', 'is_published', 'is_pinned', 'in_ticker', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_pinned' => 'boolean', 'in_ticker' => 'boolean', 'published_at' => 'datetime', 'emailed_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true)->orderByDesc('is_pinned')->orderByDesc('published_at');
    }

    /** Public notices chosen for the scrolling bar on the website. Cached for a minute. */
    public static function ticker(): Collection
    {
        // plain arrays: the cache does not store model objects
        return collect(Cache::remember('notices.ticker', 60, fn () => static::published()
            ->where('audience', 'public')->where('in_ticker', true)->limit(10)->get(['id', 'title', 'published_at'])
            ->map(fn ($n) => ['id' => $n->id, 'title' => $n->title, 'new' => (bool) $n->published_at?->gt(now()->subDays(7))])->all()));
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget('notices.ticker');
        static::saved($forget);
        static::deleted($forget);
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
