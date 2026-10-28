<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function list(Request $request): JsonResponse
    {
        $user = $request->user();

        // If user has zero notifications, seed initial user onboarding notices
        if (Notification::where('user_id', $user->id)->count() === 0) {
            $defaultNotifs = [
                [
                    'user_id' => $user->id,
                    'title' => 'Welcome to CoinPlus Institutional',
                    'message' => 'Your institutional trading account has been created successfully. Complete KYC verification to unlock higher withdrawal limits.',
                    'type' => 'account',
                    'read' => false,
                    'created_at' => now()->subHours(1),
                    'updated_at' => now()->subHours(1),
                ],
                [
                    'user_id' => $user->id,
                    'title' => 'Security Recommendation: Enable 2FA',
                    'message' => 'Protect your digital assets with Two-Factor Authentication (TOTP). Go to Account Settings to activate.',
                    'type' => 'security',
                    'read' => false,
                    'created_at' => now()->subHours(3),
                    'updated_at' => now()->subHours(3),
                ],
                [
                    'user_id' => $user->id,
                    'title' => 'Institutional Spot Engine Online',
                    'message' => 'Live market depth and order matching on BTC/USDT, ETH/USDT, and SOL/USDT are active.',
                    'type' => 'trading',
                    'read' => true,
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ],
            ];

            foreach ($defaultNotifs as $dn) {
                Notification::create($dn);
            }
        }

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($n) {
                // Map backend type to frontend category
                $category = match ($n->type) {
                    'trade', 'trading' => 'trading',
                    'deposit', 'withdrawal', 'transactions' => 'transactions',
                    'security' => 'security',
                    'account' => 'account',
                    'announcement', 'announcements', 'system' => 'announcements',
                    default => 'announcements',
                };

                $linkData = match ($category) {
                    'trading' => ['linkText' => 'View Orders', 'linkHref' => '/orders'],
                    'transactions' => ['linkText' => 'Check Wallet', 'linkHref' => '/assets'],
                    'security' => ['linkText' => 'Security Settings', 'linkHref' => '/settings'],
                    'account' => ['linkText' => 'Verify KYC', 'linkHref' => '/kyc'],
                    default => ['linkText' => 'Learn More', 'linkHref' => '/markets'],
                };

                return [
                    'id' => (string) $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'category' => $category,
                    'type' => $n->type,
                    'isRead' => (bool) $n->read,
                    'timestamp' => $n->created_at->diffForHumans(),
                    'date' => $n->created_at->format('Y-m-d H:i'),
                    'priority' => $n->type === 'security' ? 'high' : 'normal',
                    'linkText' => $linkData['linkText'],
                    'linkHref' => $linkData['linkHref'],
                ];
            });

        return response()->json([
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->read = true;
        $notification->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    public function toggleRead(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->read = ! $notification->read;
        $notification->save();

        return response()->json([
            'success' => true,
            'isRead' => (bool) $notification->read,
            'message' => 'Notification status toggled.',
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->update(['read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully.',
        ]);
    }

    public function deleteRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('read', true)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'All read notifications cleared.',
        ]);
    }
}

