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
            'currentTier' => $user->kyc_tier ?? 'Tier 0 (Unverified)',
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
        // Trading account remains unverified until administrative compliance approval
        $user->kyc_tier = 'Tier 1 (In Review)';
        $user->save();

        $case = KycVerification::create([
            'case_ref' => 'KYC-' . rand(10000, 99999),
            'user_id' => $user->id,
            'tier' => 'Tier 1',
            'status' => 'in_review',
            'document_type' => $validated['idType'],
            'document_number' => $validated['idNumber'],
            'id_front_path' => 'tier1_id_front.jpg',
            'id_back_path' => 'tier1_id_back.jpg',
            'reviewed_at' => null,
        ]);

        return response()->json([
            'kycTier' => $user->kyc_tier,
            'case' => $case,
            'message' => 'Tier 1 Basic Identity verification submitted. Compliance review is pending administrator approval.',
        ], 201);
    }

    public function submitTier2(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'firstName' => ['nullable', 'string'],
            'lastName' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'addressLine1' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'postalCode' => ['nullable', 'string'],
            'documentType' => ['nullable', 'string'],
            'documentNumber' => ['nullable', 'string'],
            'proofOfAddressType' => ['nullable', 'string'],
            'idFront' => ['nullable', 'string'],
            'idBack' => ['nullable', 'string'],
            'proofOfAddress' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Update user profile address & names if provided
        if (!empty($validated['firstName']) && !empty($validated['lastName'])) {
            $user->full_name = trim($validated['firstName'] . ' ' . $validated['lastName']);
            $user->name = $user->full_name;
        }
        if (!empty($validated['country'])) {
            $user->country = $validated['country'];
        }
        // Trading account remains unverified until administrative compliance approval
        $user->kyc_tier = 'Tier 2 (In Review)';
        $user->save();

        $docType = $validated['documentType'] ?? 'Passport';
        $frontName = $validated['idFront'] ?? ($docType === 'Passport' ? 'passport_front_scan.jpg' : 'id_front_scan.jpg');
        $backName = $validated['idBack'] ?? ($docType === 'Passport' ? 'passport_signature_page.jpg' : 'id_back_scan.jpg');
        $poaName = $validated['proofOfAddress'] ?? 'proof_of_address.pdf';

        $case = KycVerification::create([
            'case_ref' => 'KYC-' . rand(10000, 99999),
            'user_id' => $user->id,
            'tier' => 'Tier 2',
            'status' => 'in_review',
            'document_type' => $docType,
            'document_number' => $validated['documentNumber'] ?? ('DOC-' . rand(100000, 999999)),
            'proof_of_address_type' => $validated['proofOfAddressType'] ?? 'Utility Bill',
            'id_front_path' => $frontName,
            'id_back_path' => $backName,
            'proof_of_address_path' => $poaName,
            'reviewed_at' => null,
        ]);

        return response()->json([
            'case' => $case,
            'kycTier' => $user->kyc_tier,
            'message' => 'Identity verification documents submitted. Compliance review is pending administrator approval.',
        ], 201);
    }
}
