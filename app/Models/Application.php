<?php

namespace App\Models;

use App\Support\ApplicationSteps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';

    protected $attributes = ['status' => self::DRAFT];

    protected $guarded = ['id', 'user_id', 'application_no', 'status', 'submitted_at', 'submitted_ip', 'completed_steps'];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'dob' => 'date',
            'perm_same' => 'boolean',
            'is_cst' => 'boolean',
            'baptism_details' => 'encrypted:array',
            'is_pwbd' => 'boolean',
            'udid_no' => 'encrypted',
            'needs_scribe' => 'boolean',
            'residence' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function class12Marks(): HasMany
    {
        return $this->hasMany(ApplicationClass12Mark::class);
    }

    public function cuetScores(): HasMany
    {
        return $this->hasMany(ApplicationCuetScore::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(ApplicationCourse::class)->orderBy('du_preference');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->where('is_current', true);
    }

    public function allDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function curState(): BelongsTo { return $this->belongsTo(State::class, 'cur_state_id'); }
    public function curDistrict(): BelongsTo { return $this->belongsTo(District::class, 'cur_district_id'); }
    public function permState(): BelongsTo { return $this->belongsTo(State::class, 'perm_state_id'); }
    public function permDistrict(): BelongsTo { return $this->belongsTo(District::class, 'perm_district_id'); }
    public function domicileState(): BelongsTo { return $this->belongsTo(State::class, 'domicile_state_id'); }
    public function pwbdType(): BelongsTo { return $this->belongsTo(PwbdType::class); }

    public function isSubmitted(): bool
    {
        return ($this->status ?? self::DRAFT) !== self::DRAFT;
    }

    public function isChristian(): bool
    {
        return $this->category_code === Category::CHRISTIAN;
    }

    public function isFemale(): bool
    {
        return $this->gender === 'Female';
    }

    public function hasCompleted(string $step): bool
    {
        return in_array($step, $this->completed_steps ?? [], true);
    }

    public function markCompleted(string $step): void
    {
        $steps = $this->completed_steps ?? [];
        if (! in_array($step, $steps, true)) {
            $steps[] = $step;
        }
        $this->completed_steps = array_values($steps);
        $this->save();
    }

    /** Clears completion of specific steps. */
    public function invalidate(string ...$steps): void
    {
        $this->completed_steps = array_values(array_diff($this->completed_steps ?? [], $steps));
        $this->save();
    }

    /** Clears completion of all steps after the given one. */
    public function invalidateAfter(string $step): void
    {
        $later = ApplicationSteps::after($step);
        $this->completed_steps = array_values(array_diff($this->completed_steps ?? [], $later));
        $this->save();
    }

    public function class12Percentage(): ?float
    {
        $marks = $this->relationLoaded('class12Marks') ? $this->class12Marks : $this->class12Marks()->get();
        $max = $marks->sum('max_marks');

        return $max > 0 ? round($marks->sum('marks_obtained') / $max * 100, 2) : null;
    }

    public function class10Percentage(): ?float
    {
        return $this->c10_max ? round($this->c10_obtained / $this->c10_max * 100, 2) : null;
    }

    /** Documents this applicant must upload, based on their answers. */
    public function requiredDocumentTypes(): array
    {
        $types = ['photo', 'signature', 'class10', 'class12', 'cuet_scorecard', 'csas_preference'];

        if ($this->isChristian()) {
            array_push($types, 'baptism', 'church_letter');
            if ($this->is_cst) {
                $types[] = 'st_certificate';
            }
        } elseif (in_array($this->category_code, ['OBC', 'SC', 'ST', 'EWS'], true)) {
            $types[] = 'category_certificate';
        }

        if ($this->is_pwbd) {
            $types[] = 'pwbd_certificate';
        }

        return $types;
    }
}
