<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'profile' => [
                'uid' => $user->uid,
                'displayName' => $user->display_name ?? $user->name,
                'fullName' => $user->full_name ?? $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '+1 555-019-8921',
                'avatarInitials' => $user->avatar_initials ?? 'CP',
                'accountCreationDate' => $user->created_at->format('M d, Y'),
                'accountStatus' => $user->status,
                'verificationTier' => $user->kyc_tier,
                'vipLevel' => $user->vip_level,
                'country' => $user->country ?? 'United States',
                'language' => $user->language,
                'timeZone' => $user->time_zone,
                'displayCurrency' => $user->display_currency,
                'dateFormat' => $user->date_format,
                'defaultDashboardView' => $user->default_dashboard_view,
                'themePreference' => $user->theme_preference,
                'notifications' => $user->notifications_config ?? [
                    'tradeFills' => true,
                    'securityAlerts' => true,
                    'priceAlerts' => true,
                    'marketingNews' => false,
                ],
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'displayName' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'max:32'],
            'timeZone' => ['nullable', 'string', 'max:64'],
            'displayCurrency' => ['nullable', 'string', 'max:10'],
            'dateFormat' => ['nullable', 'string', 'max:20'],
            'defaultDashboardView' => ['nullable', 'string', 'in:pro_terminal,classic,portfolio'],
            'themePreference' => ['nullable', 'string', 'in:black_gold,oled_dark'],
            'notifications' => ['nullable', 'array'],
        ]);

        if (isset($validated['displayName'])) $user->display_name = $validated['displayName'];
        if (isset($validated['phone'])) $user->phone = $validated['phone'];
        if (isset($validated['country'])) $user->country = $validated['country'];
        if (isset($validated['language'])) $user->language = $validated['language'];
        if (isset($validated['timeZone'])) $user->time_zone = $validated['timeZone'];
        if (isset($validated['displayCurrency'])) $user->display_currency = $validated['displayCurrency'];
        if (isset($validated['dateFormat'])) $user->date_format = $validated['dateFormat'];
        if (isset($validated['defaultDashboardView'])) $user->default_dashboard_view = $validated['defaultDashboardView'];
        if (isset($validated['themePreference'])) $user->theme_preference = $validated['themePreference'];
        if (isset($validated['notifications'])) $user->notifications_config = $validated['notifications'];

        $user->save();

        return response()->json([
            'message' => 'Profile preferences updated successfully.',
            'user' => $user,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'The provided current password does not match our records.',
            ], 422);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function toggle2FA(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $user->two_factor_enabled = $validated['enabled'];
        $user->save();

        return response()->json([
            'twoFactorEnabled' => $user->two_factor_enabled,
            'message' => $user->two_factor_enabled ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.',
        ]);
    }

    public function getSessions(Request $request): JsonResponse
    {
        return response()->json([
            'sessions' => [
                [
                    'id' => 'sess-1',
                    'device' => 'Chrome on Windows 11 (Current)',
                    'ip' => $request->ip() ?: '127.0.0.1',
                    'location' => 'Localhost, XAMPP Node',
                    'isCurrent' => true,
                    'lastActive' => 'Just now',
                ],
                [
                    'id' => 'sess-2',
                    'device' => 'Safari on iPhone 15 Pro',
                    'ip' => '192.168.1.105',
                    'location' => 'Mobile Gateway',
                    'isCurrent' => false,
                    'lastActive' => '2 days ago',
                ],
            ],
        ]);
    }
}
