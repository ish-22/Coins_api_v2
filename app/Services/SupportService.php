<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SupportService
{
    /**
     * List tickets for user.
     */
    public function listTickets(User $user): array
    {
        return SupportTicket::with('messages')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Create new support ticket.
     */
    public function createTicket(User $user, array $data): SupportTicket
    {
        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'ticket_number' => 'TKT-' . strtoupper(bin2hex(random_bytes(4))),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'priority' => strtoupper($data['priority']),
            'status' => 'OPEN',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $data['message'],
            'is_staff' => false,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'SUPPORT_TICKET_CREATED',
            'details' => ['ticket_id' => $ticket->id, 'number' => $ticket->ticket_number],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $ticket->load('messages');
    }

    /**
     * Reply to ticket.
     */
    public function replyTicket(User $user, int $ticketId, string $message): SupportMessage
    {
        $ticket = SupportTicket::where('id', $ticketId)->where('user_id', $user->id)->first();

        if (! $ticket) {
            throw ValidationException::withMessages(['ticket' => ['Support ticket not found.']]);
        }

        $reply = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $message,
            'is_staff' => false,
        ]);

        $ticket->status = 'OPEN';
        $ticket->save();

        return $reply;
    }
}
