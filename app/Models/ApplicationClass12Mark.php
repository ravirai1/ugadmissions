<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationClass12Mark extends Model
{
    protected $table = 'application_class12_marks';

    protected $fillable = ['application_id', 'class12_subject_id', 'max_marks', 'marks_obtained'];

    public function class12Subject(): BelongsTo
    {
        return $this->belongsTo(Class12Subject::class);
    }
}
