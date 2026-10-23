<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\User;

class KycService
{
    /**
     * Get user KYC verification status.
     */
    public function getStatus(User $user): array
    {
        $verification = KycVerification::where('user_id', $user->id)->latest()->first();

        return [
            'tier' => $user->kyc_tier,
            'status' => $verification ? $verification->status : 'NOT_SUBMITTED',
            'details' => $verification ? $verification->details : null,
            'submittedAt' => $verification ? $verification->created_at : null,
            'verifiedAt' => $verification ? $verification->verified_at : null,
        ];
    }

    /**
     * Submit Tier 1 verification.
     */
    public function submitTier1(User $user, array $data): KycVerification
    {
        $verification = KycVerification::create([
            'user_id' => $user->id,
            'tier' => 'TIER_1',
            'status' => 'APPROVED',
            'details' => $data,
            'verified_at' => now(),
        ]);

        $user->kyc_tier = 'TIER_1';
        $user->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'KYC_TIER1_APPROVED',
            'details' => ['tier' => 'TIER_1'],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $verification;
    }

    /**
     * Submit Tier 2 verification.
     */
    public function submitTier2(User $user, array $data): KycVerification
    {
        $verification = KycVerification::create([
            'user_id' => $user->id,
            'tier' => 'TIER_2',
            'status' => 'PENDING',
            'details' => $data,
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'KYC_TIER2_SUBMITTED',
            'details' => ['document_type' => $data['document_type']],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $verification;
    }
}
