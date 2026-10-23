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
        $tickets = SupportTicket::with('messages')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'tickets' => $tickets,
        ]);
    }

    public function createTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:trading,account,deposits,withdrawals,kyc,security'],
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

        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_name' => $user->display_name ?? $user->name,
            'message' => $validated['message'],
        ]);

        return response()->json([
            'ticket' => $ticket->load('messages'),
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
        ]);

        return response()->json([
            'message' => 'Reply posted.',
            'reply' => $msg,
        ], 201);
    }

    public function submitContact(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'subject' => 'required|string',
            'message' => 'required|string',
        ]);

        return response()->json([
            'message' => 'Your message has been sent to the CoinPlus customer relations team.',
        ]);
    }
}
