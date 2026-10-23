<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function getStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $submissions = KycVerification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'currentTier' => $user->kyc_tier,
            'status' => $user->status,
            'submissions' => $submissions,
            'limits' => [
                'tier1DailyLimitUsd' => 10000,
                'tier2DailyLimitUsd' => 500000,
                'tier3DailyLimitUsd' => 'Unlimited',
            ],
        ]);
    }

    public function submitTier1(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullName' => ['required', 'string'],
            'dateOfBirth' => ['required', 'string'],
            'nationality' => ['required', 'string'],
            'idType' => ['required', 'string'],
            'idNumber' => ['required', 'string'],
        ]);

        $user = $request->user();
        $user->kyc_tier = 'Tier 1 (Basic)';
        $user->save();

        $case = KycVerification::create([
            'case_ref' => 'KYC-' . rand(10000, 99999),
            'user_id' => $user->id,
            'tier' => 'Tier 1',
            'status' => 'approved',
            'document_type' => $validated['idType'],
            'document_number' => $validated['idNumber'],
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'kycTier' => $user->kyc_tier,
            'case' => $case,
            'message' => 'Tier 1 Basic Identity Verification approved.',
        ]);
    }

    public function submitTier2(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documentType' => ['required', 'string'],
            'documentNumber' => ['required', 'string'],
            'proofOfAddressType' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $case = KycVerification::create([
            'case_ref' => 'KYC-' . rand(10000, 99999),
            'user_id' => $user->id,
            'tier' => 'Tier 2',
            'status' => 'in_review',
            'document_type' => $validated['documentType'],
            'document_number' => $validated['documentNumber'],
            'proof_of_address_type' => $validated['proofOfAddressType'] ?? 'Bank Statement',
            'id_front_path' => 'simulated_front.jpg',
            'id_back_path' => 'simulated_back.jpg',
            'proof_of_address_path' => 'simulated_poa.pdf',
        ]);

        return response()->json([
            'case' => $case,
            'message' => 'Tier 2 verification documents submitted. Review is in progress.',
        ], 201);
    }
}
