<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    /** How each update reached us, in words an applicant understands. */
    public const SOURCES = [
        'initiate' => 'Payment started, sent to SBI ePay',
        'redirect' => 'Reply from SBI when you returned to this site',
        'push' => 'SBI server notification (push)',
        'applicant' => 'Status check by you',
        'admin' => 'Status check by the College',
        'reconcile' => 'Automatic status check',
    ];

    protected $fillable = ['payment_id', 'source', 'status', 'detail', 'admin_id', 'ip'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
