<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /**
     * Get profile data for user.
     */
    public function getProfile(User $user): array
    {
        return $user->only([
            'id', 'name', 'email', 'role', 'status', 'kyc_tier', 'two_factor_enabled', 'created_at',
        ]);
    }

    /**
     * Update user profile name.
     */
    public function updateProfile(User $user, array $data): array
    {
        if (isset($data['name'])) {
            $user->name = $data['name'];
            $user->save();
        }

        return $this->getProfile($user);
    }

    /**
     * Change user password.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password does not match.'],
            ]);
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'ACCOUNT_PASSWORD_CHANGE',
            'details' => ['status' => 'SUCCESS'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return true;
    }

    /**
     * Toggle 2FA status.
     */
    public function toggle2FA(User $user): array
    {
        $user->two_factor_enabled = ! $user->two_factor_enabled;
        $user->save();

        return [
            'two_factor_enabled' => (bool) $user->two_factor_enabled,
        ];
    }
}
