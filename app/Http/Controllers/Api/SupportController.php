<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    public function listTickets(Request $request): JsonResponse
    {
        $user = $request->user();
        $tickets = SupportTicket::with(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }])
            ->where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        $formatted = $tickets->map(function ($ticket) {
            $unreadCount = $ticket->messages->where('sender_type', 'agent')->where('is_read', false)->count();
            return [
                'id' => (string) $ticket->id,
                'ticket_ref' => $ticket->ticket_ref,
                'ticketRef' => $ticket->ticket_ref,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'unreadCount' => $unreadCount,
                'createdAt' => $ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : null,
                'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
                'messages' => $ticket->messages->map(function ($msg) {
                    return [
                        'id' => (string) $msg->id,
                        'sender' => $msg->sender_type,
                        'senderName' => $msg->sender_name,
                        'message' => $msg->message,
                        'isRead' => (bool) $msg->is_read,
                        'timestamp' => $msg->created_at ? $msg->created_at->format('M d, Y H:i') : null,
                    ];
                })->values()->all(),
            ];
        });

        $totalUnread = $formatted->sum('unreadCount');

        return response()->json([
            'tickets' => $formatted,
            'totalUnread' => $totalUnread,
        ]);
    }

    public function createTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:trading,account,deposits,withdrawals,kyc,security,wallet'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string'],
        ]);

        $user = $request->user();

        $ticket = SupportTicket::create([
            'ticket_ref' => 'TCK-' . rand(10000, 99999),
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
        ]);

        $msg = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_name' => $user->display_name ?? $user->name,
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        return response()->json([
            'ticket' => [
                'id' => (string) $ticket->id,
                'ticket_ref' => $ticket->ticket_ref,
                'ticketRef' => $ticket->ticket_ref,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'unreadCount' => 0,
                'createdAt' => $ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : null,
                'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
                'messages' => [[
                    'id' => (string) $msg->id,
                    'sender' => 'user',
                    'senderName' => $msg->sender_name,
                    'message' => $msg->message,
                    'isRead' => false,
                    'timestamp' => $msg->created_at ? $msg->created_at->format('M d, Y H:i') : null,
                ]],
            ],
            'message' => 'Support inquiry submitted successfully.',
        ], 201);
    }

    public function replyTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $user = $request->user();
        $ticket = SupportTicket::where('user_id', $user->id)->findOrFail($id);

        $msg = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_name' => $user->display_name ?? $user->name,
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        // Re-open ticket if it was resolved or waiting_user
        if (in_array($ticket->status, ['resolved', 'waiting_user', 'closed'])) {
            $ticket->status = 'open';
        }
        $ticket->touch();
        $ticket->save();

        return response()->json([
            'message' => 'Reply posted.',
            'reply' => [
                'id' => (string) $msg->id,
                'sender' => 'user',
                'senderName' => $msg->sender_name,
                'message' => $msg->message,
                'isRead' => false,
                'timestamp' => $msg->created_at ? $msg->created_at->format('M d, Y H:i') : null,
            ],
            'ticket' => [
                'id' => (string) $ticket->id,
                'status' => $ticket->status,
                'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
            ],
        ], 201);
    }

    public function markTicketRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $ticket = SupportTicket::where('user_id', $user->id)->findOrFail($id);

        SupportMessage::where('support_ticket_id', $ticket->id)
            ->where('sender_type', 'agent')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All agent messages marked as read.',
        ]);
    }

    public function updateTicketStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting_user,resolved,closed'],
        ]);

        $user = $request->user();
        $ticket = SupportTicket::where('user_id', $user->id)->findOrFail($id);

        $ticket->status = $validated['status'];
        $ticket->save();

        return response()->json([
            'success' => true,
            'status' => $ticket->status,
            'message' => "Ticket {$ticket->ticket_ref} marked as {$ticket->status}.",
        ]);
    }

    public function submitContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'category' => 'nullable|string',
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        $referenceId = 'CP-INQ-' . rand(10000, 99999);

        $user = \App\Models\User::where('email', strtolower(trim($validated['email'])))->first() 
            ?: \App\Models\User::where('email', 'demo.trader@coinplus.io')->first()
            ?: \App\Models\User::first();

        if ($user) {
            $cat = in_array($validated['category'] ?? '', ['trading', 'account', 'deposits', 'withdrawals', 'kyc', 'security', 'wallet'])
                ? $validated['category']
                : 'account';

            $ticket = SupportTicket::create([
                'ticket_ref' => $referenceId,
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'category' => $cat,
                'priority' => 'medium',
                'status' => 'open',
            ]);

            SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_type' => 'user',
                'sender_name' => $validated['name'],
                'message' => $validated['message'],
                'is_read' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'referenceId' => $referenceId,
            'message' => 'Your message has been sent to the CoinPlus customer relations team.',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
