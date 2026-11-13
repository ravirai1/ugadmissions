<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Notifications\TicketReceived;
use App\Notifications\TicketReplied;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class Helpdesk
{
    /** Applicants may attach screenshots (images); staff may also attach PDFs. */
    public const USER_MIMES = ['image/jpeg', 'image/png'];
    public const STAFF_MIMES = ['image/jpeg', 'image/png', 'application/pdf'];

    public static function attachmentRules(bool $staff = false): array
    {
        return ['nullable', 'file', 'max:2048', 'mimes:'.($staff ? 'jpg,jpeg,png,pdf' : 'jpg,jpeg,png'), 'mimetypes:'.implode(',', $staff ? self::STAFF_MIMES : self::USER_MIMES)];
    }

    public function openQuery(array $data, ?UploadedFile $file, ?string $ip): Ticket
    {
        $ticket = DB::transaction(function () use ($data, $file, $ip) {
            $t = Ticket::create([
                'channel' => 'query',
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'subject' => $data['subject'],
                'user_id' => $data['user_id'] ?? null,
                'status' => Ticket::OPEN,
                'last_message_at' => now(),
                'created_ip' => $ip,
            ]);
            $this->addMessage($t, 'user', $data['message'], $file);

            return $t->refresh();
        });

        Notification::route('mail', $ticket->email)->notify(new TicketReceived($ticket));

        return $ticket;
    }

    /** The applicant's current chat (the latest one not closed), created on first message. */
    public function chatFor($user): ?Ticket
    {
        return Ticket::where('user_id', $user->id)->where('channel', 'chat')->where('status', '!=', Ticket::CLOSED)->latest('id')->first();
    }

    public function userMessage($user, string $body, ?UploadedFile $file, ?string $ip): TicketMessage
    {
        return DB::transaction(function () use ($user, $body, $file, $ip) {
            $t = $this->chatFor($user) ?? Ticket::create([
                'user_id' => $user->id, 'channel' => 'chat', 'name' => $user->name, 'email' => $user->email, 'mobile' => $user->mobile,
                'subject' => 'Chat with applicant', 'status' => Ticket::OPEN, 'created_ip' => $ip,
            ]);
            $msg = $this->addMessage($t, 'user', $body, $file);
            $t->update(['status' => Ticket::OPEN, 'unread_for_staff' => true, 'last_message_at' => now()]);

            return $msg;
        });
    }

    public function staffReply(Ticket $t, Admin $admin, string $body, ?UploadedFile $file): TicketMessage
    {
        $msg = DB::transaction(function () use ($t, $admin, $body, $file) {
            $msg = $this->addMessage($t, 'staff', $body, $file, $admin);
            $t->update([
                'status' => Ticket::ANSWERED,
                'unread_for_staff' => false,
                'unread_for_user' => true,
                'assigned_to' => $t->assigned_to ?? $admin->id,
                'first_response_at' => $t->first_response_at ?? now(),
                'last_message_at' => now(),
            ]);

            return $msg;
        });

        Notification::route('mail', $t->email)->notify(new TicketReplied($t, $msg));

        return $msg;
    }

    private function addMessage(Ticket $t, string $sender, string $body, ?UploadedFile $file, ?Admin $admin = null): TicketMessage
    {
        $path = $mime = null;
        if ($file) {
            $mime = $file->getMimeType();
            if (str_starts_with($mime, 'image/') && @getimagesize($file->getRealPath()) === false) {
                throw ValidationException::withMessages(['attachment' => 'The image appears to be damaged.']);
            }
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime] ?? 'bin';
            $path = $file->storeAs('helpdesk/'.$t->id, bin2hex(random_bytes(10)).'.'.$ext, 'local');
        }

        return $t->messages()->create(['sender' => $sender, 'admin_id' => $admin?->id, 'body' => $body, 'attachment_path' => $path, 'attachment_mime' => $mime]);
    }
}
