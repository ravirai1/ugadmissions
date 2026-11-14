<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrutinyVerification extends Model
{
    public const OK = 'ok';

    public const CORRECTED = 'corrected';

    protected $fillable = ['scrutiny_id', 'field', 'label', 'applicant_value', 'verified_value', 'result', 'verified_by'];

    public function scrutiny(): BelongsTo
    {
        return $this->belongsTo(Scrutiny::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'verified_by');
    }
}
