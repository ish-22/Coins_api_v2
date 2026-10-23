<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullName' => ['required', 'string', 'max:128'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'country' => ['nullable', 'string', 'max:64'],
            'acceptTerms' => ['accepted'],
        ]);

        $uid = 'UID-' . rand(100000, 999999);
        $nameParts = explode(' ', trim($validated['fullName']));
        $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

        $user = User::create([
            'uid' => $uid,
            'name' => $validated['fullName'],
            'display_name' => $validated['fullName'],
            'full_name' => $validated['fullName'],
            'email' => strtolower(trim($validated['email'])),
            'password' => Hash::make($validated['password']),
            'country' => $validated['country'] ?? 'United States',
            'role' => 'user',
            'status' => 'active',
            'kyc_tier' => 'Tier 0',
            'vip_level' => 'VIP 1',
            'avatar_initials' => $initials ?: 'CP',
            'language' => 'English (US)',
            'time_zone' => 'UTC-05:00 (Eastern Time)',
            'display_currency' => 'USD',
            'date_format' => 'YYYY-MM-DD',
            'default_dashboard_view' => 'pro_terminal',
            'theme_preference' => 'black_gold',
            'two_factor_enabled' => false,
            'notifications_config' => [
                'tradeFills' => true,
                'securityAlerts' => true,
                'priceAlerts' => true,
                'marketingNews' => false,
            ],
        ]);

        // Initialize zero balances for all active assets
        $assets = Asset::all();
        foreach ($assets as $asset) {
            Wallet::create([
                'user_id' => $user->id,
                'asset_id' => $asset->id,
                'available_balance' => 0,
                'locked_balance' => 0,
            ]);
        }

        $token = $user->createToken('coinplus_auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Registration successful.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password. Please verify your credentials.',
            ], 422);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is suspended or pending verification. Please contact support.',
            ], 403);
        }

        $token = $user->createToken('coinplus_auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Signed in successfully.',
        ]);
    }

    public function adminLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid administrative credentials.',
            ], 422);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized: This account does not have operator privileges.',
            ], 403);
        }

        $token = $user->createToken('coinplus_admin_token', ['admin'])->plainTextToken;

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $user->id,
            'admin_email' => $user->email,
            'admin_name' => $user->name,
            'action' => 'Admin Sign In',
            'category' => 'Authentication',
            'ip_address' => $request->ip(),
            'details' => 'Administrative operator signed in successfully.',
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
            'role' => 'admin',
            'message' => 'Operator console authenticated.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        return response()->json([
            'message' => 'Password reset instructions have been dispatched to your email address.',
        ]);
    }
}
