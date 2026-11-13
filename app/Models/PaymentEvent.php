<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['payment_id', 'source', 'status', 'detail', 'admin_id', 'ip'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
