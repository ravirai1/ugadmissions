<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['ticket_id', 'sender', 'admin_id', 'body', 'attachment_path', 'attachment_mime'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** What the applicant sees as the sender (staff names stay internal). */
    public function senderLabel(): string
    {
        return $this->sender === 'staff' ? 'Admissions Helpdesk' : $this->ticket->name;
    }
}
