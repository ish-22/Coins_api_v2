<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportController extends Controller
{
    private function saveAttachment($dataOrFile, string $prefix = 'support'): ?string
    {
        if (empty($dataOrFile)) {
            return null;
        }

        // If it is an uploaded file
        if ($dataOrFile instanceof \Illuminate\Http\UploadedFile) {
            $path = $dataOrFile->store('support', 'public');
            return 'storage/' . $path;
        }

        // If it is a base64 data URI (from browser FileReader)
        if (is_string($dataOrFile) && preg_match('/^data:([a-zA-Z0-9\/\+]+);base64,/', $dataOrFile, $matches)) {
            $mime = strtolower($matches[1]);
            $extension = 'png';
            if (str_contains($mime, 'jpeg') || str_contains($mime, 'jpg')) $extension = 'jpg';
            elseif (str_contains($mime, 'png')) $extension = 'png';
            elseif (str_contains($mime, 'webp')) $extension = 'webp';
            elseif (str_contains($mime, 'pdf')) $extension = 'pdf';
            elseif (str_contains($mime, 'gif')) $extension = 'gif';

            $base64 = substr($dataOrFile, strpos($dataOrFile, ',') + 1);
            $decoded = base64_decode($base64);
            if ($decoded !== false) {
                $filename = $prefix . '_' . uniqid() . '.' . $extension;
                \Illuminate\Support\Facades\Storage::disk('public')->put('support/' . $filename, $decoded);
                return 'storage/support/' . $filename;
            }
        }

        // Plain string/URL under 500 chars
        if (is_string($dataOrFile) && strlen($dataOrFile) < 500) {
            return $dataOrFile;
        }

        return null;
    }

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
                'attachmentName' => $ticket->attachment_name,
                'attachment_name' => $ticket->attachment_name,
                'attachmentUrl' => $ticket->attachment_url ? (str_starts_with($ticket->attachment_url, 'http') ? $ticket->attachment_url : url($ticket->attachment_url)) : null,
                'attachment_url' => $ticket->attachment_url ? (str_starts_with($ticket->attachment_url, 'http') ? $ticket->attachment_url : url($ticket->attachment_url)) : null,
                'unreadCount' => $unreadCount,
                'createdAt' => $ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : null,
                'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
                'messages' => $ticket->messages->map(function ($msg) {
                    return [
                        'id' => (string) $msg->id,
                        'sender' => $msg->sender_type,
                        'senderName' => $msg->sender_name,
                        'message' => $msg->message,
                        'attachmentName' => $msg->attachment_name,
                        'attachment_name' => $msg->attachment_name,
                        'attachmentUrl' => $msg->attachment_url ? (str_starts_with($msg->attachment_url, 'http') ? $msg->attachment_url : url($msg->attachment_url)) : null,
                        'attachment_url' => $msg->attachment_url ? (str_starts_with($msg->attachment_url, 'http') ? $msg->attachment_url : url($msg->attachment_url)) : null,
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
            'category' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'message' => ['required', 'string'],
            'attachmentName' => ['nullable', 'string', 'max:255'],
            'attachmentData' => ['nullable', 'string'],
            'attachment' => ['nullable'],
        ]);

        $user = $request->user();

        $attachmentUrl = $this->saveAttachment(
            $request->file('attachment') ?? $validated['attachmentData'] ?? $request->input('attachment')
        );
        $attachmentName = $validated['attachmentName'] ?? ($request->hasFile('attachment') ? $request->file('attachment')->getClientOriginalName() : null);

        $ticket = SupportTicket::create([
            'ticket_ref' => 'TCK-' . rand(10000, 99999),
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'attachment_name' => $attachmentName,
            'attachment_url' => $attachmentUrl,
        ]);

        $msg = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_name' => $user->display_name ?? $user->name,
            'message' => $validated['message'],
            'attachment_name' => $attachmentName,
            'attachment_url' => $attachmentUrl,
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
                'attachmentName' => $ticket->attachment_name,
                'attachment_name' => $ticket->attachment_name,
                'attachmentUrl' => $ticket->attachment_url ? (str_starts_with($ticket->attachment_url, 'http') ? $ticket->attachment_url : url($ticket->attachment_url)) : null,
                'attachment_url' => $ticket->attachment_url ? (str_starts_with($ticket->attachment_url, 'http') ? $ticket->attachment_url : url($ticket->attachment_url)) : null,
                'unreadCount' => 0,
                'createdAt' => $ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : null,
                'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
                'messages' => [[
                    'id' => (string) $msg->id,
                    'sender' => 'user',
                    'senderName' => $msg->sender_name,
                    'message' => $msg->message,
                    'attachmentName' => $msg->attachment_name,
                    'attachment_name' => $msg->attachment_name,
                    'attachmentUrl' => $msg->attachment_url ? (str_starts_with($msg->attachment_url, 'http') ? $msg->attachment_url : url($msg->attachment_url)) : null,
                    'attachment_url' => $msg->attachment_url ? (str_starts_with($msg->attachment_url, 'http') ? $msg->attachment_url : url($msg->attachment_url)) : null,
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
            'attachmentName' => ['nullable', 'string', 'max:255'],
            'attachmentData' => ['nullable', 'string'],
            'attachment' => ['nullable'],
        ]);

        $user = $request->user();
        $ticket = SupportTicket::where('user_id', $user->id)->findOrFail($id);

        $attachmentUrl = $this->saveAttachment(
            $request->file('attachment') ?? $validated['attachmentData'] ?? $request->input('attachment')
        );
        $attachmentName = $validated['attachmentName'] ?? ($request->hasFile('attachment') ? $request->file('attachment')->getClientOriginalName() : null);

        $msg = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'user',
            'sender_name' => $user->display_name ?? $user->name,
            'message' => $validated['message'],
            'attachment_name' => $attachmentName,
            'attachment_url' => $attachmentUrl,
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
                'attachmentName' => $msg->attachment_name,
                'attachment_name' => $msg->attachment_name,
                'attachmentUrl' => $msg->attachment_url ? (str_starts_with($msg->attachment_url, 'http') ? $msg->attachment_url : url($msg->attachment_url)) : null,
                'attachment_url' => $msg->attachment_url ? (str_starts_with($msg->attachment_url, 'http') ? $msg->attachment_url : url($msg->attachment_url)) : null,
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
