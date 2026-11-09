<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    private function saveDocumentFile($dataOrFile, string $prefix = 'doc'): ?string
    {
        if (empty($dataOrFile)) {
            return null;
        }

        // If it is an uploaded file
        if ($dataOrFile instanceof \Illuminate\Http\UploadedFile) {
            $path = $dataOrFile->store('kyc', 'public');
            return 'storage/' . $path;
        }

        // If it is a base64 data URI (from FileReader)
        if (is_string($dataOrFile) && preg_match('/^data:image\/(\w+);base64,/', $dataOrFile, $matches)) {
            $extension = strtolower($matches[1]);
            if ($extension === 'jpeg') $extension = 'jpg';
            $base64 = substr($dataOrFile, strpos($dataOrFile, ',') + 1);
            $decoded = base64_decode($base64);
            if ($decoded !== false) {
                $filename = $prefix . '_' . uniqid() . '.' . $extension;
                Storage::disk('public')->put('kyc/' . $filename, $decoded);
                return 'storage/kyc/' . $filename;
            }
        }

        // If it is already a stored path or plain filename under 255 chars
        if (is_string($dataOrFile)) {
            return substr($dataOrFile, 0, 255);
        }

        return null;
    }

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

        try {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'KYC Tier 1 Submitted',
                'message' => 'Your Tier 1 Basic Identity verification was submitted and is pending compliance review.',
                'type' => 'security',
                'read' => false,
            ]);
        } catch (\Throwable $e) {}

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
            'idFront' => ['nullable'],
            'idBack' => ['nullable'],
            'proofOfAddress' => ['nullable'],
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

        $user->kyc_tier = 'Tier 2 (In Review)';
        $user->save();

        $docType = $validated['documentType'] ?? 'Passport';
        $frontRaw = $request->file('idFront') ?? $validated['idFront'] ?? null;
        $backRaw = $request->file('idBack') ?? $validated['idBack'] ?? null;
        $poaRaw = $request->file('proofOfAddress') ?? $validated['proofOfAddress'] ?? null;

        $frontPath = $this->saveDocumentFile($frontRaw, 'id_front') ?? ($docType === 'Passport' ? 'passport_front_scan.jpg' : 'id_front_scan.jpg');
        $backPath = $this->saveDocumentFile($backRaw, 'id_back') ?? ($docType === 'Passport' ? 'passport_signature_page.jpg' : 'id_back_scan.jpg');
        $poaPath = $this->saveDocumentFile($poaRaw, 'poa') ?? 'proof_of_address.pdf';

        $case = KycVerification::create([
            'case_ref' => 'KYC-' . rand(10000, 99999),
            'user_id' => $user->id,
            'tier' => 'Tier 2',
            'status' => 'in_review',
            'document_type' => $docType,
            'document_number' => $validated['documentNumber'] ?? ('DOC-' . rand(100000, 999999)),
            'proof_of_address_type' => $validated['proofOfAddressType'] ?? 'Utility Bill',
            'id_front_path' => $frontPath,
            'id_back_path' => $backPath,
            'proof_of_address_path' => $poaPath,
            'reviewed_at' => null,
        ]);

        try {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'KYC Documents Submitted',
                'message' => 'Your Tier 2 verification documents have been submitted and are under compliance review.',
                'type' => 'security',
                'read' => false,
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'case' => $case,
            'kycTier' => $user->kyc_tier,
            'message' => 'Identity verification documents submitted. Compliance review is pending administrator approval.',
        ], 201);
    }
}
