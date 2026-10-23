<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate customer trader.
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if ($user->status === 'SUSPENDED') {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended. Please contact institutional support.'],
            ]);
        }

        $token = $user->createToken('coinplus-trader-web')->plainTextToken;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'AUTH_LOGIN',
            'details' => ['method' => 'PASSWORD', 'role' => $user->role],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role', 'status', 'kyc_tier', 'two_factor_enabled']),
        ];
    }

    /**
     * Register a new customer.
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'USER',
                'status' => 'ACTIVE',
                'kyc_tier' => 'TIER_0',
                'two_factor_enabled' => false,
            ]);

            // Initialize default core wallets for customer
            $coreAssets = Asset::where('is_active', true)->get();
            foreach ($coreAssets as $asset) {
                Wallet::create([
                    'user_id' => $user->id,
                    'asset_id' => $asset->id,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'deposit_address' => '0x' . bin2hex(random_bytes(20)),
                ]);
            }

            $token = $user->createToken('coinplus-trader-web')->plainTextToken;

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'AUTH_REGISTER',
                'details' => ['method' => 'SELF_SIGNUP'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'token' => $token,
                'user' => $user->only(['id', 'name', 'email', 'role', 'status', 'kyc_tier', 'two_factor_enabled']),
            ];
        });
    }

    /**
     * Authenticate operator or compliance officer.
     */
    public function adminLogin(string $email, string $password, ?string $adminPin = null): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid operator credentials.'],
            ]);
        }

        if (! in_array($user->role, ['ADMIN', 'SUPER_ADMIN', 'COMPLIANCE'])) {
            throw ValidationException::withMessages([
                'email' => ['Access forbidden: account lacks administrative privileges.'],
            ]);
        }

        if ($user->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'email' => ['Administrator account is not in active state.'],
            ]);
        }

        $token = $user->createToken('coinplus-admin-session', ['admin:*'])->plainTextToken;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'ADMIN_LOGIN',
            'details' => ['role' => $user->role],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email', 'role', 'status']),
        ];
    }
}
