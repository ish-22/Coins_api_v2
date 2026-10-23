<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Validation\ValidationException;

class AdminService
{
    /**
     * Get aggregated platform metrics.
     */
    public function getMetrics(): array
    {
        return [
            'totalUsers' => User::count(),
            'activeUsers24h' => User::where('status', 'ACTIVE')->count(),
            'totalTrades24h' => Order::where('status', 'FILLED')->count(),
            'totalVolume24hUsd' => (float) Order::where('status', 'FILLED')->sum('total'),
            'pendingKycCount' => KycVerification::where('status', 'PENDING')->count(),
            'pendingWithdrawalsCount' => Transaction::where('type', 'WITHDRAWAL')->where('status', 'PROCESSING')->count(),
            'systemHealth' => 'HEALTHY',
            'matchingEngineLatencyMs' => 4.2,
        ];
    }

    /**
     * Update user account status.
     */
    public function updateUserStatus(User $admin, int $userId, string $status): User
    {
        $user = User::findOrFail($userId);
        $user->status = strtoupper($status);
        $user->save();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'ADMIN_USER_STATUS_UPDATE',
            'details' => ['target_user_id' => $user->id, 'new_status' => $user->status],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $user;
    }

    /**
     * Review KYC case.
     */
    public function reviewKycCase(User $admin, int $caseId, string $status, ?string $notes = null): KycVerification
    {
        $case = KycVerification::with('user')->findOrFail($caseId);
        $case->status = strtoupper($status);
        $case->rejection_reason = $notes;
        $case->verified_at = now();
        $case->save();

        if ($case->status === 'APPROVED' && $case->user) {
            $case->user->kyc_tier = $case->tier;
            $case->user->save();
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'ADMIN_KYC_REVIEW',
            'details' => ['case_id' => $case->id, 'decision' => $status],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $case;
    }
}
