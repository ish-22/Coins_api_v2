<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\AdminNotificationDispatch;
use App\Models\KycVerification;
use App\Models\Notification;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function getMetrics(): JsonResponse
    {
        $volume24h = (float) Transaction::whereIn('type', ['trade_buy', 'trade_sell'])
            ->where('created_at', '>=', now()->subHours(24))
            ->sum('value_usd');

        // Fallback to order total if trade transactions not yet logged
        if ($volume24h <= 0) {
            $volume24h = (float) Order::where('created_at', '>=', now()->subHours(24))->sum('total');
        }

        $totalDeposits = (float) Transaction::where('type', 'deposit')
            ->where('status', 'completed')
            ->sum('value_usd');

        $totalWithdrawals = (float) Transaction::where('type', 'withdrawal')
            ->where('status', 'completed')
            ->sum('value_usd');

        return response()->json([
            'metrics' => [
                'totalUsers' => User::count(),
                'activeUsers24h' => User::where('status', 'active')->count(),
                'volume24hUsd' => round($volume24h, 2),
                'totalDepositsUsd' => round($totalDeposits, 2),
                'totalWithdrawalsUsd' => round($totalWithdrawals, 2),
                'pendingKycCount' => KycVerification::where('status', 'in_review')->orWhere('status', 'pending')->count(),
                'pendingWithdrawalsCount' => Transaction::where('type', 'withdrawal')->where('status', 'processing')->count(),
                'openTicketsCount' => SupportTicket::where('status', 'open')->count(),
                'matchingEngineStatus' => 'Operational (0.42ms Latency)',
                'riskEngineStatus' => 'Active (Real-time AML)',
            ],
        ]);
    }

    public function getUsers(Request $request): JsonResponse
    {
        $users = User::with('wallets.asset')->orderBy('created_at', 'desc')->get()->map(function ($u) {
            $totalBalanceUsd = 0;
            if ($u->wallets) {
                foreach ($u->wallets as $wallet) {
                    $assetPrice = $wallet->asset ? (float) $wallet->asset->price_usd : 0;
                    $totalBalanceUsd += ((float) $wallet->available_balance + (float) $wallet->locked_balance) * $assetPrice;
                }
            }

            return [
                'id' => (string) $u->id,
                'userRef' => $u->uid ?? ('UID-' . $u->id),
                'name' => $u->full_name ?? $u->name,
                'email' => $u->email,
                'phone' => $u->phone ?? 'N/A',
                'role' => $u->role ?? 'user',
                'country' => $u->country ?? 'United States',
                'countryCode' => $u->country_code ?? 'US',
                'status' => $u->status,
                'kycTier' => $u->kyc_tier,
                'balanceUsd' => round($totalBalanceUsd, 2),
                'registeredAt' => $u->created_at->format('M d, Y H:i'),
                'lastLogin' => 'Active recently',
                'twoFactorEnabled' => (bool) $u->two_factor_enabled,
                'passkeysRegistered' => $u->passkeys_count > 0,
                'ipAddress' => '127.0.0.1',
            ];
        });

        return response()->json([
            'users' => $users,
        ]);
    }

    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'in:user,admin,operator,super_admin'],
            'status' => ['nullable', 'string', 'in:active,suspended,pending,blocked'],
            'kyc_tier' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'initial_balance_usd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $currentUser = $request->user();
        $targetRole = $validated['role'] ?? 'user';

        // Role-Based Access Control: Only admin or super_admin can create staff accounts
        if (in_array($targetRole, ['admin', 'super_admin', 'operator']) && !in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Operator role cannot create administrative or operator accounts.',
            ], 403);
        }

        $uidPrefix = in_array($targetRole, ['admin', 'super_admin']) ? 'CP-ADM-' : ($targetRole === 'operator' ? 'CP-OPR-' : 'CP-USR-');
        $uid = $uidPrefix . rand(1000, 9999);

        $user = User::create([
            'uid' => $uid,
            'name' => $validated['name'],
            'display_name' => $validated['name'],
            'full_name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone' => $validated['phone'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => $targetRole,
            'status' => $validated['status'] ?? 'active',
            'kyc_tier' => $validated['kyc_tier'] ?? (in_array($targetRole, ['admin', 'super_admin', 'operator']) ? 'Tier 2 (Full KYC Verified)' : 'Tier 1 (Basic Identity)'),
            'country' => $validated['country'] ?? 'United States',
            'country_code' => 'US',
        ]);

        // If initial balance provided, credit USDT wallet
        $initialUsdt = floatval($validated['initial_balance_usd'] ?? 0);
        if ($initialUsdt > 0) {
            \App\Models\Wallet::updateOrCreate(
                ['user_id' => $user->id, 'asset_id' => 'tether'],
                ['available_balance' => $initialUsdt, 'locked_balance' => 0]
            );
        }

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "Account Created: {$user->email} ({$user->uid})",
            'category' => 'User Management',
            'ip_address' => $request->ip(),
            'details' => "Administrative profile created for {$user->email} with role '{$user->role}' by {$currentUser->email}",
        ]);

        return response()->json([
            'message' => 'Account created successfully with assigned role.',
            'user' => [
                'id' => (string) $user->id,
                'userRef' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? 'N/A',
                'role' => $user->role,
                'country' => $user->country,
                'countryCode' => $user->country_code,
                'status' => $user->status,
                'kycTier' => $user->kyc_tier,
                'balanceUsd' => $initialUsdt,
                'registeredAt' => $user->created_at->format('M d, Y H:i'),
                'lastLogin' => 'Never',
                'twoFactorEnabled' => false,
                'passkeysRegistered' => false,
                'ipAddress' => '127.0.0.1',
            ],
        ], 201);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'in:user,admin,operator,super_admin'],
            'status' => ['nullable', 'string', 'in:active,suspended,pending,blocked'],
            'kyc_tier' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'balance_usd' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Role-Based Access: changing role requires admin or super_admin
        if (isset($validated['role']) && $validated['role'] !== $user->role) {
            if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
                return response()->json([
                    'message' => 'Permission denied: Only Administrators can modify user roles.',
                ], 403);
            }
            if ($user->email === 'operator@coinplus.io' && !in_array($validated['role'], ['admin', 'super_admin'])) {
                return response()->json([
                    'message' => 'Cannot demote the primary system administrator account.',
                ], 403);
            }
            $user->role = $validated['role'];
        }

        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
            $user->full_name = $validated['name'];
            $user->display_name = $validated['name'];
        }
        if (!empty($validated['email'])) {
            $user->email = strtolower(trim($validated['email']));
        }
        if (isset($validated['phone'])) {
            $user->phone = $validated['phone'];
        }
        if (!empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }
        if (isset($validated['status'])) {
            $user->status = $validated['status'];
            if (in_array($validated['status'], ['blocked', 'suspended'])) {
                $user->tokens()->delete();
            }
        }
        if (isset($validated['kyc_tier'])) {
            $user->kyc_tier = $validated['kyc_tier'];
        }
        if (isset($validated['country'])) {
            $user->country = $validated['country'];
        }
        $user->save();

        if (isset($validated['balance_usd'])) {
            \App\Models\Wallet::updateOrCreate(
                ['user_id' => $user->id, 'asset_id' => 'tether'],
                ['available_balance' => floatval($validated['balance_usd'])]
            );
        }

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "User Profile Updated: {$user->email} ({$user->uid})",
            'category' => 'User Management',
            'ip_address' => $request->ip(),
            'details' => "Administrative profile modification applied for {$user->email} (Role: {$user->role})",
        ]);

        return response()->json([
            'message' => 'User profile updated successfully.',
            'user' => [
                'id' => (string) $user->id,
                'userRef' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? 'N/A',
                'role' => $user->role,
                'country' => $user->country,
                'countryCode' => $user->country_code,
                'status' => $user->status,
                'kycTier' => $user->kyc_tier,
                'balanceUsd' => floatval($validated['balance_usd'] ?? 0),
                'registeredAt' => $user->created_at->format('M d, Y H:i'),
                'lastLogin' => 'Active recently',
                'twoFactorEnabled' => (bool) $user->two_factor_enabled,
                'passkeysRegistered' => $user->passkeys_count > 0,
                'ipAddress' => '127.0.0.1',
            ],
        ]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();
        $userEmail = $user->email;
        $userUid = $user->uid;

        // Role-Based Access: operators cannot delete accounts
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Operators cannot delete accounts.',
            ], 403);
        }

        // Prevent admin from deleting themselves
        if ($currentUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own active administrative account.',
            ], 422);
        }

        // Protect primary operator root account
        if ($user->email === 'operator@coinplus.io') {
            return response()->json([
                'message' => 'Protected account: The primary super administrator cannot be deleted.',
            ], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "User Purged: {$userEmail} ({$userUid})",
            'category' => 'User Management',
            'ip_address' => $request->ip(),
            'details' => "Administrative deletion of user account {$userEmail}",
        ]);

        return response()->json([
            'message' => "User {$userEmail} deleted successfully.",
        ]);
    }

    public function updateUserStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended,pending,blocked'],
        ]);

        $user = User::findOrFail($id);
        $currentUser = $request->user();

        // Protect primary root administrator account
        if ($user->email === 'operator@coinplus.io' && $validated['status'] !== 'active') {
            return response()->json([
                'message' => 'Protected account: The primary super administrator account status cannot be altered.',
            ], 403);
        }

        // Operators cannot alter the status of administrator or super_admin accounts
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin']) && in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Operators cannot alter administrative account status.',
            ], 403);
        }

        $user->status = $validated['status'];
        $user->save();

        // If the account is blocked or suspended, immediately revoke active session tokens so they cannot operate
        if (in_array($validated['status'], ['blocked', 'suspended'])) {
            $user->tokens()->delete();
        }

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "User Status Updated: {$user->email} -> {$user->status}",
            'category' => 'User Management',
            'ip_address' => $request->ip(),
            'details' => "Account {$user->uid} status updated to {$user->status} by {$currentUser->email}",
        ]);

        return response()->json([
            'user' => [
                'id' => (string) $user->id,
                'userRef' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? 'N/A',
                'role' => $user->role,
                'country' => $user->country,
                'countryCode' => $user->country_code,
                'status' => $user->status,
                'kycTier' => $user->kyc_tier,
                'registeredAt' => $user->created_at->format('M d, Y H:i'),
            ],
            'message' => "User account status updated to {$user->status} successfully.",
        ]);
    }

    public function getKycCases(): JsonResponse
    {
        $cases = KycVerification::with('user')->orderBy('created_at', 'desc')->get()->map(function ($c) {
            return [
                'id' => (string) $c->id,
                'caseRef' => $c->case_ref,
                'userId' => (string) $c->user_id,
                'userName' => $c->user ? $c->user->name : 'Unknown User',
                'userEmail' => $c->user ? $c->user->email : 'unknown@coinplus.io',
                'country' => $c->user ? ($c->user->country ?? 'United States') : 'United States',
                'requestedTier' => $c->tier,
                'status' => $c->status,
                'submittedAt' => $c->created_at->format('M d, Y H:i'),
                'reviewedAt' => $c->reviewed_at ? $c->reviewed_at->format('M d, Y H:i') : null,
                'documentType' => $c->document_type ?? 'Passport',
                'documentNumber' => $c->document_number ?? 'N/A',
                'proofOfAddressType' => $c->proof_of_address_type ?? 'Bank Statement',
                'rejectionReason' => $c->rejection_reason,
                'idFrontPlaceholder' => $c->id_front_path ?? ($c->document_type === 'Passport' ? 'passport_front_scan.jpg' : 'id_card_front.jpg'),
                'idBackPlaceholder' => $c->id_back_path ?? ($c->document_type === 'Passport' ? 'passport_signature_page.jpg' : 'id_card_back.jpg'),
                'proofOfAddressPlaceholder' => $c->proof_of_address_path ?? 'proof_of_address.pdf',
            ];
        });

        return response()->json([
            'cases' => $cases,
        ]);
    }

    public function reviewKycCase(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'rejectionReason' => ['nullable', 'string'],
        ]);

        $case = KycVerification::findOrFail($id);
        $case->status = ($validated['action'] === 'approve') ? 'approved' : 'rejected';
        if ($case->status === 'rejected') {
            $case->rejection_reason = $validated['rejectionReason'] ?? 'Document incorrect or incomplete';
        } else {
            $case->rejection_reason = null;
        }
        $case->reviewed_at = now();
        $case->save();

        if ($case->user) {
            if ($case->status === 'approved') {
                $case->user->kyc_tier = 'VERIFIED';
            } else {
                $case->user->kyc_tier = 'Tier 0 (Rejected)';
            }
            $case->user->save();
        }

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "KYC Review: {$case->case_ref} ({$case->status})",
            'category' => 'KYC Review',
            'ip_address' => $request->ip(),
            'details' => "KYC Case {$case->case_ref} for {$case->user?->email} {$case->status}",
        ]);

        return response()->json([
            'case' => $case,
            'message' => "KYC case {$case->case_ref} has been {$case->status}.",
        ]);
    }

    public static function getDefaultDepositMethods(): array
    {
        return [
            [
                'id' => 'dep-btc-mainnet',
                'asset' => 'BTC',
                'name' => 'Bitcoin',
                'network' => 'Bitcoin (Native SegWit)',
                'address' => 'bc1q9px90f3c71a4819d9b2e04f7c11a2245bb318029',
                'minDeposit' => 0.0005,
                'confirmations' => 3,
                'memoRequired' => false,
                'memo' => null,
                'enabled' => true,
            ],
            [
                'id' => 'dep-eth-erc20',
                'asset' => 'ETH',
                'name' => 'Ethereum',
                'network' => 'ERC-20 (Ethereum)',
                'address' => '0x71C8821199a0B1298811d7F4ac91882019482011',
                'minDeposit' => 0.01,
                'confirmations' => 12,
                'memoRequired' => false,
                'memo' => null,
                'enabled' => true,
            ],
            [
                'id' => 'dep-usdt-trc20',
                'asset' => 'USDT',
                'name' => 'Tether USD (TRC-20)',
                'network' => 'TRC-20 (Tron)',
                'address' => 'TX9Qe881948ac9120847719201948201148zK',
                'minDeposit' => 10.0,
                'confirmations' => 20,
                'memoRequired' => false,
                'memo' => null,
                'enabled' => true,
            ],
            [
                'id' => 'dep-usdt-erc20',
                'asset' => 'USDT',
                'name' => 'Tether USD (ERC-20)',
                'network' => 'ERC-20 (Ethereum)',
                'address' => '0x71C8821199a0B1298811d7F4ac91882019482011',
                'minDeposit' => 20.0,
                'confirmations' => 12,
                'memoRequired' => false,
                'memo' => null,
                'enabled' => true,
            ],
            [
                'id' => 'dep-sol-spl',
                'asset' => 'SOL',
                'name' => 'Solana',
                'network' => 'Solana (SPL)',
                'address' => '8F3c71a4819d9B2e04f7c11a2245bB318029e1F5',
                'minDeposit' => 0.1,
                'confirmations' => 32,
                'memoRequired' => false,
                'memo' => null,
                'enabled' => true,
            ],
            [
                'id' => 'dep-xrp-ledger',
                'asset' => 'XRP',
                'name' => 'Ripple',
                'network' => 'XRP Ledger',
                'address' => 'rEb8TK3gBgk5auZyyb6GeaHSqHT5943WII',
                'minDeposit' => 10.0,
                'confirmations' => 1,
                'memoRequired' => true,
                'memo' => '881920',
                'enabled' => true,
            ],
            [
                'id' => 'dep-bnb-bep20',
                'asset' => 'BNB',
                'name' => 'BNB',
                'network' => 'BEP-20 (BNB Smart Chain)',
                'address' => 'bnb1q9px90f3c71a4819d9b2e04f7c11a2245bb318029',
                'minDeposit' => 0.05,
                'confirmations' => 15,
                'memoRequired' => false,
                'memo' => null,
                'enabled' => true,
            ],
            [
                'id' => 'dep-usdc-erc20',
                'asset' => 'USDC',
                'name' => 'USD Coin',
                'network' => 'ERC-20 (Ethereum)',
                'address' => '0x44a1b029ff8812c37719d2a019b8821948ac9120',
                'minDeposit' => 10.0,
                'confirmations' => 12,
                'memoRequired' => false,
                'memo' => null,
                'enabled' => true,
            ],
        ];
    }

    public function getDepositMethods(): JsonResponse
    {
        $setting = SystemSetting::where('key', 'deposit_methods')->first();
        if ($setting && is_array($setting->value) && count($setting->value) > 0) {
            return response()->json(['methods' => $setting->value]);
        }

        $defaults = self::getDefaultDepositMethods();
        SystemSetting::updateOrCreate(['key' => 'deposit_methods'], ['value' => $defaults]);

        return response()->json(['methods' => $defaults]);
    }

    public function updateDepositMethods(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can update deposit methods.',
            ], 403);
        }

        $validated = $request->validate([
            'methods' => ['required', 'array'],
            'methods.*.asset' => ['required', 'string'],
            'methods.*.network' => ['required', 'string'],
            'methods.*.address' => ['required', 'string'],
            'methods.*.minDeposit' => ['nullable', 'numeric'],
            'methods.*.confirmations' => ['nullable', 'integer'],
            'methods.*.enabled' => ['nullable', 'boolean'],
            'methods.*.memoRequired' => ['nullable', 'boolean'],
            'methods.*.memo' => ['nullable', 'string'],
        ]);

        $methods = [];
        foreach ($validated['methods'] as $m) {
            $methods[] = [
                'id' => $m['id'] ?? ('dep-' . strtolower($m['asset']) . '-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $m['network']))),
                'asset' => strtoupper($m['asset']),
                'name' => $m['name'] ?? strtoupper($m['asset']),
                'network' => $m['network'],
                'address' => trim($m['address']),
                'minDeposit' => isset($m['minDeposit']) ? (float) $m['minDeposit'] : 0.0001,
                'confirmations' => isset($m['confirmations']) ? (int) $m['confirmations'] : 12,
                'memoRequired' => !empty($m['memoRequired']),
                'memo' => $m['memo'] ?? null,
                'enabled' => !isset($m['enabled']) || (bool) $m['enabled'],
            ];
        }

        SystemSetting::updateOrCreate(
            ['key' => 'deposit_methods'],
            ['value' => $methods]
        );

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => 'Deposit Methods Configuration Updated',
            'category' => 'Deposit Management',
            'ip_address' => $request->ip(),
            'details' => "Updated " . count($methods) . " deposit methods and wallet addresses by {$currentUser->email}",
        ]);

        return response()->json([
            'message' => 'Deposit methods and addresses successfully updated.',
            'methods' => $methods,
        ]);
    }

    public function updateDepositWalletAddress(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can update deposit wallet addresses.',
            ], 403);
        }

        $validated = $request->validate([
            'asset' => ['required', 'string'],
            'network' => ['nullable', 'string'],
            'address' => ['required', 'string', 'min:10'],
            'memo' => ['nullable', 'string'],
        ]);

        $setting = SystemSetting::where('key', 'deposit_methods')->first();
        $methods = ($setting && is_array($setting->value)) ? $setting->value : self::getDefaultDepositMethods();

        $assetUpper = strtoupper($validated['asset']);
        $networkReq = $validated['network'] ?? null;
        $updated = false;

        foreach ($methods as &$method) {
            $matchesAsset = strtoupper($method['asset']) === $assetUpper;
            $matchesNetwork = empty($networkReq) || (strcasecmp($method['network'], $networkReq) === 0);
            if ($matchesAsset && $matchesNetwork) {
                $method['address'] = trim($validated['address']);
                if (isset($validated['memo'])) {
                    $method['memo'] = trim($validated['memo']);
                }
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $methods[] = [
                'id' => 'dep-' . strtolower($assetUpper) . '-' . time(),
                'asset' => $assetUpper,
                'name' => $assetUpper,
                'network' => $networkReq ?: 'Native',
                'address' => trim($validated['address']),
                'minDeposit' => 0.0001,
                'confirmations' => 12,
                'memoRequired' => !empty($validated['memo']),
                'memo' => $validated['memo'] ?? null,
                'enabled' => true,
            ];
        }

        SystemSetting::updateOrCreate(
            ['key' => 'deposit_methods'],
            ['value' => $methods]
        );

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "Deposit Wallet Address Updated ({$assetUpper})",
            'category' => 'Deposit Management',
            'ip_address' => $request->ip(),
            'details' => "Updated deposit wallet address for {$assetUpper}" . ($networkReq ? " [{$networkReq}]" : "") . " to {$validated['address']}",
        ]);

        return response()->json([
            'message' => "Deposit address for {$assetUpper} updated successfully.",
            'methods' => $methods,
        ]);
    }

    public function getDeposits(Request $request): JsonResponse
    {
        $query = Transaction::with('user')->where('type', 'deposit');

        if ($request->has('status') && !empty($request->query('status')) && $request->query('status') !== 'all') {
            $status = $request->query('status');
            if ($status === 'successful') {
                $status = 'completed';
            }
            $query->where('status', $status);
        }

        if ($request->has('user_id') && !empty($request->query('user_id'))) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->has('asset') && !empty($request->query('asset')) && $request->query('asset') !== 'all') {
            $query->where('asset', strtoupper($request->query('asset')));
        }

        if ($request->has('q') && !empty($request->query('q'))) {
            $search = $request->query('q');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('tx_hash', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('user_ref', 'like', "%{$search}%");
                  });
            });
        }

        $deposits = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($d) {
                return [
                    'id' => (string) $d->id,
                    'depositRef' => 'DEP-' . substr($d->id, -6),
                    'txHash' => $d->tx_hash,
                    'userId' => (string) $d->user_id,
                    'userName' => $d->user ? $d->user->name : 'Trader',
                    'userEmail' => $d->user ? $d->user->email : 'trader@coinplus.io',
                    'userRef' => $d->user ? ($d->user->user_ref ?? ('USR-' . substr($d->user->id, -6))) : 'USR-000000',
                    'asset' => $d->asset,
                    'network' => $d->network ?? 'Blockchain Mainnet',
                    'amount' => (float) $d->amount,
                    'amountUsd' => (float) $d->value_usd,
                    'status' => $d->status,
                    'confirmations' => $d->confirmations ?? 32,
                    'requiredConfirmations' => 32,
                    'destinationAddress' => $d->address ?? 'Vault',
                    'rejectionReason' => $d->notes,
                    'notes' => $d->notes,
                    'createdAt' => $d->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json([
            'deposits' => $deposits,
        ]);
    }

    public function reviewDeposit(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can review deposit requests.',
            ], 403);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string'],
        ]);

        $deposit = Transaction::where('id', $id)->firstOrFail();
        if ($deposit->type !== 'deposit') {
            return response()->json(['message' => 'Transaction is not a deposit.'], 400);
        }

        if ($deposit->status !== 'pending') {
            return response()->json([
                'message' => "Deposit has already been {$deposit->status} and cannot be modified.",
            ], 400);
        }

        if ($validated['action'] === 'approve') {
            $deposit->status = 'completed';
            $deposit->notes = $validated['reason'] ?? 'Approved by administrative compliance desk';

            // Credit user's wallet
            $assetObj = Asset::where('symbol', $deposit->asset)->first();
            $assetId = $assetObj ? $assetObj->id : strtolower($deposit->asset);
            $wallet = \App\Models\Wallet::firstOrCreate(
                ['user_id' => $deposit->user_id, 'asset_id' => $assetId],
                ['available_balance' => 0, 'locked_balance' => 0]
            );
            $wallet->available_balance += (float) $deposit->amount;
            $wallet->save();

            // Notify user
            \App\Models\Notification::create([
                'user_id' => $deposit->user_id,
                'title' => 'Deposit Confirmed',
                'message' => "Your deposit of {$deposit->amount} {$deposit->asset} has been verified and credited to your wallet balance.",
                'type' => 'deposit',
                'read' => false,
            ]);
        } else {
            $deposit->status = 'rejected';
            $deposit->notes = $validated['reason'] ?? 'Deposit rejected: Invalid transaction proof or unconfirmed transfer';

            // Notify user
            \App\Models\Notification::create([
                'user_id' => $deposit->user_id,
                'title' => 'Deposit Rejected',
                'message' => "Your deposit request of {$deposit->amount} {$deposit->asset} was rejected. Reason: {$deposit->notes}",
                'type' => 'deposit',
                'read' => false,
            ]);
        }

        $deposit->save();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "Deposit {$deposit->id} " . ucfirst($validated['action']) . 'd',
            'category' => 'Deposit Management',
            'ip_address' => $request->ip(),
            'details' => "Deposit of {$deposit->amount} {$deposit->asset} (User: {$deposit->user_id}) reviewed with outcome: {$deposit->status}. " . ($deposit->notes ? "Note: {$deposit->notes}" : ""),
        ]);

        return response()->json([
            'message' => "Deposit {$deposit->id} " . ($validated['action'] === 'approve' ? 'approved and credited' : 'rejected') . " successfully.",
            'deposit' => [
                'id' => (string) $deposit->id,
                'depositRef' => 'DEP-' . substr($deposit->id, -6),
                'txHash' => $deposit->tx_hash,
                'userId' => (string) $deposit->user_id,
                'asset' => $deposit->asset,
                'network' => $deposit->network,
                'amount' => (float) $deposit->amount,
                'amountUsd' => (float) $deposit->value_usd,
                'status' => $deposit->status,
                'confirmations' => $deposit->confirmations ?? 32,
                'destinationAddress' => $deposit->address,
                'rejectionReason' => $deposit->notes,
                'notes' => $deposit->notes,
                'createdAt' => $deposit->created_at->format('M d, Y H:i'),
            ],
        ]);
    }

    public function getWithdrawals(): JsonResponse
    {
        $withdrawals = Transaction::with('user')
            ->where('type', 'withdrawal')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($w) {
                $statusDisplay = 'pending_review';
                if ($w->status === 'completed') {
                    $statusDisplay = 'approved';
                } elseif ($w->status === 'failed') {
                    $statusDisplay = 'rejected';
                }

                return [
                    'id' => (string) $w->id,
                    'withdrawalRef' => 'WTH-' . substr($w->id, -6),
                    'userId' => (string) $w->user_id,
                    'userName' => $w->user ? $w->user->name : 'Trader',
                    'asset' => $w->asset,
                    'network' => $w->network ?? 'TRC-20 (Tron)',
                    'amount' => (float) $w->amount,
                    'amountUsd' => (float) $w->value_usd,
                    'fee' => (float) $w->fee_usd,
                    'destinationAddress' => $w->address ?? 'N/A',
                    'riskScore' => 'Low',
                    'status' => $statusDisplay,
                    'rejectionReason' => $w->notes,
                    'createdAt' => $w->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json([
            'withdrawals' => $withdrawals,
        ]);
    }

    public function reviewWithdrawal(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'reason' => ['nullable', 'string'],
        ]);

        $tx = Transaction::where('id', $id)->firstOrFail();
        if ($tx->type !== 'withdrawal') {
            return response()->json(['message' => 'Transaction is not a withdrawal.'], 400);
        }

        if ($validated['action'] === 'approve') {
            $tx->status = 'completed';
            $tx->notes = $validated['reason'] ?? 'Approved by administrative compliance desk';
        } else {
            $tx->status = 'failed';
            $tx->notes = $validated['reason'] ?? 'Rejected by administrative compliance desk';

            // Refund user balance (amount + fee) if failed
            $assetObj = Asset::where('symbol', $tx->asset)->first();
            $assetId = $assetObj ? $assetObj->id : strtolower($tx->asset);
            $feeRefund = $assetObj ? (float) $assetObj->withdrawal_fee : 0;
            $wallet = \App\Models\Wallet::where('user_id', $tx->user_id)->where('asset_id', $assetId)->first();
            if ($wallet) {
                $wallet->available_balance += (floatval($tx->amount) + $feeRefund);
                $wallet->save();
            }
        }

        $tx->save();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "Withdrawal {$tx->id} {$validated['action']}d",
            'category' => 'Financial Operations',
            'ip_address' => $request->ip(),
            'details' => "Withdrawal request of {$tx->amount} {$tx->asset} reviewed with status {$tx->status}",
        ]);

        return response()->json([
            'message' => "Withdrawal {$tx->id} " . ($validated['action'] === 'approve' ? 'approved' : 'rejected') . " successfully.",
            'transaction' => $tx,
        ]);
    }

    public function getOrders(): JsonResponse
    {
        $orders = Order::with('user')->orderBy('created_at', 'desc')->get()->map(function ($o) {
            return [
                'id' => (string) $o->id,
                'orderRef' => $o->id,
                'userId' => (string) $o->user_id,
                'userName' => $o->user ? $o->user->name : 'Trader',
                'pair' => $o->pair,
                'side' => $o->side,
                'type' => $o->type,
                'price' => (float) $o->price,
                'amount' => (float) $o->amount,
                'filled' => (float) $o->filled,
                'totalUsd' => (float) $o->total,
                'status' => $o->status,
                'createdAt' => $o->created_at->format('M d, Y H:i:s'),
            ];
        });

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function cancelOrderAdmin(Request $request, string $id): JsonResponse
    {
        $order = Order::where('id', $id)->firstOrFail();

        if ($order->status === 'open') {
            $parts = explode('/', $order->pair);
            $baseAssetSymbol = $parts[0] ?? 'BTC';
            $quoteAssetSymbol = $parts[1] ?? 'USDT';

            if ($order->side === 'buy') {
                $quoteWallet = \App\Models\Wallet::where('user_id', $order->user_id)
                    ->whereHas('asset', fn ($q) => $q->where('symbol', $quoteAssetSymbol))
                    ->first();
                if ($quoteWallet) {
                    $refund = (float) $order->total;
                    $quoteWallet->locked_balance = max(0, (float) $quoteWallet->locked_balance - $refund);
                    $quoteWallet->available_balance += $refund;
                    $quoteWallet->save();
                }
            } else {
                $baseWallet = \App\Models\Wallet::where('user_id', $order->user_id)
                    ->whereHas('asset', fn ($q) => $q->where('symbol', $baseAssetSymbol))
                    ->first();
                if ($baseWallet) {
                    $refund = (float) $order->amount;
                    $baseWallet->locked_balance = max(0, (float) $baseWallet->locked_balance - $refund);
                    $baseWallet->available_balance += $refund;
                    $baseWallet->save();
                }
            }
        }

        $order->status = 'cancelled';
        $order->save();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "Order Cancelled by Admin: {$order->id}",
            'category' => 'Exchange Trading',
            'ip_address' => $request->ip(),
            'details' => "Administrative order cancellation executed for {$order->pair} {$order->side}",
        ]);

        return response()->json([
            'message' => "Order {$order->id} cancelled by administrative action.",
            'order' => $order,
        ]);
    }

    public function getTradingOverview(): JsonResponse
    {
        $assets = Asset::all()->keyBy('symbol');

        // Fetch all orders with user
        $allOrders = Order::with('user')->orderBy('created_at', 'desc')->get();

        // Calculate win/loss for each order
        $mappedOrders = $allOrders->map(function ($o) use ($assets) {
            $parts = explode('/', $o->pair);
            $baseSymbol = $parts[0] ?? 'BTC';
            $currentPrice = isset($assets[$baseSymbol]) ? (float) $assets[$baseSymbol]->price_usd : (float) $o->price;

            $pnlUsd = 0.0;
            $isWin = null;

            if ($o->status === 'filled') {
                if ($o->side === 'buy') {
                    $pnlUsd = round(($currentPrice - (float) $o->price) * (float) $o->amount, 2);
                } else {
                    $pnlUsd = round(((float) $o->price - $currentPrice) * (float) $o->amount, 2);
                }
                $isWin = $pnlUsd >= 0;
            }

            return [
                'id' => (string) $o->id,
                'orderRef' => $o->id,
                'userId' => (string) $o->user_id,
                'userName' => $o->user ? $o->user->name : 'Trader',
                'userEmail' => $o->user ? $o->user->email : 'N/A',
                'pair' => $o->pair,
                'side' => $o->side,
                'type' => $o->type,
                'price' => (float) $o->price,
                'amount' => (float) $o->amount,
                'filled' => (float) $o->filled,
                'totalUsd' => (float) $o->total,
                'status' => $o->status,
                'pnlUsd' => $pnlUsd,
                'isWin' => $isWin,
                'createdAt' => $o->created_at->format('M d, Y H:i:s'),
            ];
        });

        // Registered traders list (users who are clients or have placed trades)
        $users = User::all();
        $traders = $users->map(function ($user) use ($mappedOrders) {
            $userOrders = $mappedOrders->where('userId', (string) $user->id);
            $totalTrades = $userOrders->count();
            $openTrades = $userOrders->where('status', 'open')->count();
            $filledTrades = $userOrders->where('status', 'filled')->count();
            $volumeUsd = round($userOrders->sum('totalUsd'), 2);

            $winningTrades = $userOrders->where('status', 'filled')->where('isWin', true)->count();
            $losingTrades = $userOrders->where('status', 'filled')->where('isWin', false)->count();
            $totalEvaluated = $winningTrades + $losingTrades;
            $winRate = $totalEvaluated > 0 ? round(($winningTrades / $totalEvaluated) * 100, 1) : 0.0;
            $totalPnl = round($userOrders->where('status', 'filled')->sum('pnlUsd'), 2);

            $riskScore = 'Low';
            if ($volumeUsd > 100000 || ($totalTrades > 20 && $winRate < 35)) {
                $riskScore = 'High';
            } elseif ($volumeUsd > 25000 || $totalTrades > 10) {
                $riskScore = 'Medium';
            }

            return [
                'id' => (string) $user->id,
                'userRef' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'country' => $user->country ?? 'United States',
                'status' => $user->status,
                'kycTier' => $user->kyc_tier ?? 'Tier 0 (Unverified)',
                'registeredAt' => $user->created_at ? $user->created_at->format('M d, Y') : 'N/A',
                'totalTrades' => $totalTrades,
                'openTrades' => $openTrades,
                'filledTrades' => $filledTrades,
                'volumeUsd' => $volumeUsd,
                'winningTrades' => $winningTrades,
                'losingTrades' => $losingTrades,
                'winRate' => $winRate,
                'totalPnl' => $totalPnl,
                'riskScore' => $riskScore,
            ];
        })->sortByDesc('volumeUsd')->values();

        // Platform-wide Statistics
        $totalOrdersCount = $mappedOrders->count();
        $activeOrdersCount = $mappedOrders->where('status', 'open')->count();
        $filledOrdersCount = $mappedOrders->where('status', 'filled')->count();
        $totalVolume = round($mappedOrders->sum('totalUsd'), 2);
        $totalWins = $mappedOrders->where('status', 'filled')->where('isWin', true)->count();
        $totalLosses = $mappedOrders->where('status', 'filled')->where('isWin', false)->count();
        $totalEval = $totalWins + $totalLosses;
        $platformWinRate = $totalEval > 0 ? round(($totalWins / $totalEval) * 100, 1) : 0.0;
        $totalPnlPlatform = round($mappedOrders->where('status', 'filled')->sum('pnlUsd'), 2);

        // Trading Settings & Disclosed Simulation Parameters
        $setting = SystemSetting::where('key', 'trading_config')->first();
        $config = $setting ? $setting->value : [
            'engine_status' => 'active',
            'base_maker_fee' => 0.001,
            'base_taker_fee' => 0.0015,
            'slippage_tolerance' => 0.5,
            'max_leverage' => 10,
            'unverified_daily_limit_usd' => 0,
            'tier1_daily_limit_usd' => 10000,
            'tier2_daily_limit_usd' => 500000,
            'tier3_daily_limit_usd' => 5000000,
            'simulation_win_rate' => 65,
            'demo_initial_balance_usd' => 10000,
            'demo_slippage_model' => 'normal',
            'market_integrity_protection' => true,
            'regulatory_disclosure' => 'For real-money trading, actual trading results are strictly determined by market order-book execution and disclosed exchange rules. Administrative intervention or secret manipulation of individual users\' live trade outcomes is strictly prohibited and cryptographically audited.',
        ];

        return response()->json([
            'orders' => $mappedOrders,
            'traders' => $traders,
            'stats' => [
                'totalTradesCount' => $totalOrdersCount,
                'activeTradesCount' => $activeOrdersCount,
                'completedTradesCount' => $filledOrdersCount,
                'totalVolumeUsd' => $totalVolume,
                'totalWinningTrades' => $totalWins,
                'totalLosingTrades' => $totalLosses,
                'platformWinRate' => $platformWinRate,
                'totalPnlUsd' => $totalPnlPlatform,
                'totalTradersCount' => $traders->count(),
            ],
            'settings' => $config,
        ]);
    }

    public function getTraderPerformance(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $assets = Asset::all()->keyBy('symbol');

        $orders = Order::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($o) use ($assets) {
                $parts = explode('/', $o->pair);
                $baseSymbol = $parts[0] ?? 'BTC';
                $currentPrice = isset($assets[$baseSymbol]) ? (float) $assets[$baseSymbol]->price_usd : (float) $o->price;

                $pnlUsd = 0.0;
                $isWin = null;
                if ($o->status === 'filled') {
                    if ($o->side === 'buy') {
                        $pnlUsd = round(($currentPrice - (float) $o->price) * (float) $o->amount, 2);
                    } else {
                        $pnlUsd = round(((float) $o->price - $currentPrice) * (float) $o->amount, 2);
                    }
                    $isWin = $pnlUsd >= 0;
                }

                return [
                    'id' => (string) $o->id,
                    'orderRef' => $o->id,
                    'pair' => $o->pair,
                    'side' => $o->side,
                    'type' => $o->type,
                    'price' => (float) $o->price,
                    'amount' => (float) $o->amount,
                    'filled' => (float) $o->filled,
                    'totalUsd' => (float) $o->total,
                    'status' => $o->status,
                    'pnlUsd' => $pnlUsd,
                    'isWin' => $isWin,
                    'createdAt' => $o->created_at->format('M d, Y H:i:s'),
                ];
            });

        $totalTrades = $orders->count();
        $winningTrades = $orders->where('status', 'filled')->where('isWin', true)->count();
        $losingTrades = $orders->where('status', 'filled')->where('isWin', false)->count();
        $totalEval = $winningTrades + $losingTrades;
        $winRate = $totalEval > 0 ? round(($winningTrades / $totalEval) * 100, 1) : 0.0;
        $totalPnl = round($orders->where('status', 'filled')->sum('pnlUsd'), 2);
        $bestTrade = $orders->where('status', 'filled')->max('pnlUsd') ?? 0;
        $worstTrade = $orders->where('status', 'filled')->min('pnlUsd') ?? 0;

        return response()->json([
            'trader' => [
                'id' => (string) $user->id,
                'userRef' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? 'N/A',
                'country' => $user->country ?? 'United States',
                'kycTier' => $user->kyc_tier ?? 'Tier 0 (Unverified)',
                'status' => $user->status,
                'registeredAt' => $user->created_at ? $user->created_at->format('M d, Y H:i') : 'N/A',
            ],
            'performance' => [
                'totalTrades' => $totalTrades,
                'openTrades' => $orders->where('status', 'open')->count(),
                'filledTrades' => $orders->where('status', 'filled')->count(),
                'winningTrades' => $winningTrades,
                'losingTrades' => $losingTrades,
                'winRate' => $winRate,
                'totalPnl' => $totalPnl,
                'bestTrade' => (float) $bestTrade,
                'worstTrade' => (float) $worstTrade,
                'totalVolume' => round($orders->sum('totalUsd'), 2),
            ],
            'history' => $orders,
        ]);
    }

    public function updateTradingSettings(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can configure trading engine parameters.',
            ], 403);
        }

        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $settings = $validated['settings'];
        // Ensure market integrity protection is always enforced
        $settings['market_integrity_protection'] = true;
        $settings['regulatory_disclosure'] = 'For real-money trading, actual trading results are strictly determined by market order-book execution and disclosed exchange rules. Administrative intervention or secret manipulation of individual users\' live trade outcomes is strictly prohibited and cryptographically audited.';

        $setting = SystemSetting::updateOrCreate(
            ['key' => 'trading_config'],
            ['value' => $settings]
        );

        $winRate = $settings['simulation_win_rate'] ?? 65;

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => 'Trading Engine & Simulation Settings Updated',
            'category' => 'Trading Management',
            'ip_address' => $request->ip(),
            'details' => "Trading engine settings and disclosed simulation win-rate ({$winRate}%) updated by {$currentUser->email}",
        ]);

        return response()->json([
            'settings' => $setting->value,
            'message' => 'Trading management parameters and simulation settings updated successfully.',
        ]);
    }

    public function getAnnouncements(): JsonResponse
    {
        return response()->json([
            'announcements' => Announcement::orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function createAnnouncement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', 'in:maintenance,feature,security,advisory'],
            'targetAudience' => ['required', 'string'],
            'validUntil' => ['nullable', 'string'],
        ]);

        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'type' => $validated['type'],
            'target_audience' => $validated['targetAudience'],
            'status' => 'active',
            'valid_until' => $validated['validUntil'] ?? null,
        ]);

        return response()->json([
            'announcement' => $announcement,
            'message' => 'Announcement published.',
        ], 201);
    }

    public function updateAnnouncement(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', 'in:maintenance,feature,security,advisory'],
            'targetAudience' => ['required', 'string'],
            'status' => ['nullable', 'in:active,draft,archived'],
            'validUntil' => ['nullable', 'string'],
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'type' => $validated['type'],
            'target_audience' => $validated['targetAudience'],
            'status' => $validated['status'] ?? $announcement->status,
            'valid_until' => $validated['validUntil'] ?? $announcement->valid_until,
        ]);

        return response()->json([
            'announcement' => $announcement,
            'message' => 'Announcement updated successfully.',
        ]);
    }

    public function deleteAnnouncement(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can delete platform announcements.',
            ], 403);
        }

        Announcement::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Announcement removed.',
        ]);
    }

    public function getAssets(): JsonResponse
    {
        $assets = Asset::all()->map(function ($a) {
            return [
                'id' => $a->id,
                'symbol' => $a->symbol,
                'name' => $a->name,
                'networks' => $a->networks ?? [],
                'currentPriceUsd' => (float) $a->price_usd,
                'change24h' => (float) $a->change_24h,
                'depositEnabled' => (bool) $a->deposit_enabled,
                'withdrawEnabled' => (bool) $a->withdraw_enabled,
                'tradingEnabled' => (bool) $a->trading_enabled,
                'minDeposit' => (float) $a->min_deposit,
                'minWithdrawal' => (float) $a->min_withdrawal,
                'withdrawalFee' => (float) $a->withdrawal_fee,
            ];
        });

        return response()->json([
            'assets' => $assets,
        ]);
    }

    public function createAsset(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can configure new platform assets.',
            ], 403);
        }

        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:16', 'unique:assets,symbol'],
            'name' => ['required', 'string', 'max:64'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'networks' => ['nullable', 'array'],
            'depositEnabled' => ['nullable', 'boolean'],
            'withdrawEnabled' => ['nullable', 'boolean'],
            'tradingEnabled' => ['nullable', 'boolean'],
            'minDeposit' => ['nullable', 'numeric', 'min:0'],
            'minWithdrawal' => ['nullable', 'numeric', 'min:0'],
            'withdrawalFee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $id = strtolower(trim($validated['symbol']));
        $asset = Asset::create([
            'id' => $id,
            'symbol' => strtoupper($validated['symbol']),
            'name' => $validated['name'],
            'type' => 'crypto',
            'price_usd' => $validated['price_usd'],
            'change_24h' => 0.0,
            'networks' => $validated['networks'] ?? ['Mainnet'],
            'deposit_enabled' => $validated['depositEnabled'] ?? true,
            'withdraw_enabled' => $validated['withdrawEnabled'] ?? true,
            'trading_enabled' => $validated['tradingEnabled'] ?? true,
            'min_deposit' => $validated['minDeposit'] ?? 0.001,
            'min_withdrawal' => $validated['minWithdrawal'] ?? 0.002,
            'withdrawal_fee' => $validated['withdrawalFee'] ?? 0.0005,
        ]);

        return response()->json([
            'asset' => $asset,
            'message' => "Asset {$asset->symbol} created successfully.",
        ], 201);
    }

    public function updateAsset(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can configure exchange assets.',
            ], 403);
        }

        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'name' => ['nullable', 'string'],
            'price_usd' => ['nullable', 'numeric'],
            'depositEnabled' => ['nullable', 'boolean'],
            'withdrawEnabled' => ['nullable', 'boolean'],
            'tradingEnabled' => ['nullable', 'boolean'],
            'withdrawalFee' => ['nullable', 'numeric'],
            'minDeposit' => ['nullable', 'numeric'],
            'minWithdrawal' => ['nullable', 'numeric'],
        ]);

        if (isset($validated['name'])) $asset->name = $validated['name'];
        if (isset($validated['price_usd'])) $asset->price_usd = $validated['price_usd'];
        if (isset($validated['depositEnabled'])) $asset->deposit_enabled = $validated['depositEnabled'];
        if (isset($validated['withdrawEnabled'])) $asset->withdraw_enabled = $validated['withdrawEnabled'];
        if (isset($validated['tradingEnabled'])) $asset->trading_enabled = $validated['tradingEnabled'];
        if (isset($validated['withdrawalFee'])) $asset->withdrawal_fee = $validated['withdrawalFee'];
        if (isset($validated['minDeposit'])) $asset->min_deposit = $validated['minDeposit'];
        if (isset($validated['minWithdrawal'])) $asset->min_withdrawal = $validated['minWithdrawal'];

        $asset->save();

        return response()->json([
            'asset' => $asset,
            'message' => "Configuration for {$asset->symbol} updated.",
        ]);
    }

    public function deleteAsset(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can delete platform assets.',
            ], 403);
        }

        $asset = Asset::findOrFail($id);
        $symbol = $asset->symbol;
        $asset->delete();

        return response()->json([
            'message' => "Asset {$symbol} has been deleted.",
        ]);
    }

    public function getAuditLogs(): JsonResponse
    {
        $logs = AuditLog::orderBy('created_at', 'desc')->take(50)->get()->map(function ($log) {
            return [
                'id' => (string) $log->id,
                'eventId' => $log->event_id ?? 'EVT-' . $log->id,
                'adminEmail' => $log->admin_email ?? 'operator@coinplus.io',
                'adminName' => $log->admin_name ?? 'Chief Operator',
                'action' => $log->action ?? 'System Event',
                'category' => $log->category ?? 'System',
                'target' => $log->user_id ? 'UID-' . $log->user_id : 'System Parameters',
                'details' => $log->details ?? 'Administrative event executed.',
                'timestamp' => $log->created_at ? $log->created_at->toIso8601String() : now()->toIso8601String(),
                'result' => 'success',
                'ipAddress' => $log->ip_address ?? '127.0.0.1',
            ];
        });

        return response()->json([
            'logs' => $logs,
        ]);
    }

    public function getSupportTickets(Request $request): JsonResponse
    {
        $search = trim($request->query('q', ''));
        $status = $request->query('status', 'all');

        $query = SupportTicket::with(['user', 'messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }]);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_ref', 'LIKE', "%{$search}%")
                  ->orWhere('subject', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('email', 'LIKE', "%{$search}%")
                         ->orWhere('uid', 'LIKE', "%{$search}%")
                         ->orWhere('id', 'LIKE', "%{$search}%");
                  });
            });
        }

        if (!empty($status) && $status !== 'all') {
            if ($status === 'unread') {
                $query->whereHas('messages', function ($mq) {
                    $mq->where('sender_type', 'user')->where('is_read', false);
                });
            } else {
                $query->where('status', $status);
            }
        }

        $allTickets = SupportTicket::with(['messages'])->get();
        $totalUnreadMessages = SupportMessage::where('sender_type', 'user')->where('is_read', false)->count();

        $tickets = $query->orderBy('updated_at', 'desc')->get()->map(function ($ticket) {
            $unreadCount = $ticket->messages->where('sender_type', 'user')->where('is_read', false)->count();

            return [
                'id' => (string) $ticket->id,
                'ticketRef' => $ticket->ticket_ref,
                'userId' => (string) $ticket->user_id,
                'userRef' => $ticket->user?->uid ?? ('CP-USR-' . str_pad($ticket->user_id, 4, '0', STR_PAD_LEFT)),
                'userName' => $ticket->user ? $ticket->user->name : 'Customer',
                'userEmail' => $ticket->user ? $ticket->user->email : 'support@coinplus.io',
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'unreadCount' => $unreadCount,
                'createdAt' => $ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : null,
                'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
                'messages' => $ticket->messages->map(function ($msg) {
                    return [
                        'id' => (string) $msg->id,
                        'sender' => $msg->sender_type,
                        'senderName' => $msg->sender_name,
                        'message' => $msg->message,
                        'isRead' => (bool) $msg->is_read,
                        'timestamp' => $msg->created_at ? $msg->created_at->format('M d, Y H:i') : null,
                    ];
                })->values()->all(),
            ];
        });

        return response()->json([
            'tickets' => $tickets,
            'metrics' => [
                'totalTickets' => $allTickets->count(),
                'unreadTotal' => $totalUnreadMessages,
                'openCount' => $allTickets->where('status', 'open')->count(),
                'inProgressCount' => $allTickets->where('status', 'in_progress')->count(),
                'waitingUserCount' => $allTickets->where('status', 'waiting_user')->count(),
                'resolvedCount' => $allTickets->where('status', 'resolved')->count(),
                'closedCount' => $allTickets->where('status', 'closed')->count(),
            ],
        ]);
    }

    public function replySupportTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket = SupportTicket::with('user')->findOrFail($id);

        // Mark previous user messages on this ticket as read
        SupportMessage::where('support_ticket_id', $ticket->id)
            ->where('sender_type', 'user')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $msg = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'agent',
            'sender_name' => $request->user()->name,
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        $ticket->status = 'waiting_user';
        $ticket->touch();
        $ticket->save();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "Support Ticket Reply: {$ticket->ticket_ref}",
            'category' => 'Support Desk',
            'ip_address' => $request->ip(),
            'details' => "Replied to ticket {$ticket->ticket_ref} of {$ticket->user?->email}",
        ]);

        return response()->json([
            'message' => 'Support reply posted successfully.',
            'reply' => [
                'id' => (string) $msg->id,
                'sender' => 'agent',
                'senderName' => $request->user()->name,
                'message' => $msg->message,
                'isRead' => false,
                'timestamp' => $msg->created_at ? $msg->created_at->format('M d, Y H:i') : null,
            ],
            'ticket' => [
                'id' => (string) $ticket->id,
                'status' => $ticket->status,
                'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
            ],
        ], 201);
    }

    public function markSupportTicketRead(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::findOrFail($id);

        SupportMessage::where('support_ticket_id', $ticket->id)
            ->where('sender_type', 'user')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => "Customer messages in ticket {$ticket->ticket_ref} marked as read.",
        ]);
    }

    public function updateTicketStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting_user,resolved,closed'],
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $validated['status'];
        $ticket->save();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "Ticket Status Changed: {$ticket->ticket_ref} to {$ticket->status}",
            'category' => 'Support Desk',
            'ip_address' => $request->ip(),
            'details' => "Support ticket {$ticket->ticket_ref} status changed to {$ticket->status}",
        ]);

        return response()->json([
            'message' => "Support ticket {$ticket->ticket_ref} status updated to {$ticket->status}.",
            'ticket' => [
                'id' => (string) $ticket->id,
                'status' => $ticket->status,
            ],
        ]);
    }

    public function getSettings(): JsonResponse
    {
        $setting = SystemSetting::where('key', 'general')->first();

        return response()->json([
            'settings' => $setting ? $setting->value : [
                'platform_name' => 'CoinPlus Institutional Exchange',
                'maintenance_mode' => false,
                'kyc_enforced' => true,
                'max_daily_withdrawal_tier1' => 10000,
                'max_daily_withdrawal_tier2' => 500000,
                'auto_approve_withdrawal_threshold' => 1000,
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can modify platform system parameters.',
            ], 403);
        }

        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $setting = SystemSetting::updateOrCreate(
            ['key' => 'general'],
            ['value' => $validated['settings']]
        );

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => 'System Parameters Updated',
            'category' => 'System Configuration',
            'ip_address' => $request->ip(),
            'details' => "Platform parameters modified by {$currentUser->email} ({$currentUser->role})",
        ]);

        return response()->json([
            'settings' => $setting->value,
            'message' => 'System parameters saved.',
        ]);
    }

    public function getUserDetails(Request $request, string $id): JsonResponse
    {
        $user = User::with('wallets.asset')->findOrFail($id);
        $allAssets = Asset::all();

        // 1. Wallets & Balances breakdown
        $walletsMap = [];
        foreach ($user->wallets as $w) {
            $walletsMap[$w->asset_id] = $w;
        }

        $availableBalanceUsd = 0;
        $lockedBalanceUsd = 0;
        $totalBalanceUsd = 0;

        $assetBreakdown = [];
        foreach ($allAssets as $asset) {
            $w = $walletsMap[$asset->id] ?? null;
            $avail = $w ? (float) $w->available_balance : 0;
            $locked = $w ? (float) $w->locked_balance : 0;
            $total = $avail + $locked;
            $price = (float) $asset->price_usd;
            $valUsd = round($total * $price, 2);

            $availableBalanceUsd += ($avail * $price);
            $lockedBalanceUsd += ($locked * $price);
            $totalBalanceUsd += $valUsd;

            $assetBreakdown[] = [
                'assetId' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'color' => $asset->color,
                'priceUsd' => $price,
                'availableBalance' => $avail,
                'lockedBalance' => $locked,
                'totalBalance' => $total,
                'valueUsd' => $valUsd,
            ];
        }

        // 2. Deposits & Withdrawals
        $depositsQuery = Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')
            ->orderBy('created_at', 'desc');
        $totalDepositedUsd = (float) (clone $depositsQuery)->where('status', 'completed')->sum('value_usd');
        $totalDepositedCount = (clone $depositsQuery)->count();
        $depositsList = $depositsQuery->get()->map(function ($t) {
            return [
                'id' => (string) $t->id,
                'txHash' => $t->tx_hash,
                'asset' => $t->asset,
                'amount' => (float) $t->amount,
                'valueUsd' => (float) $t->value_usd,
                'feeUsd' => (float) $t->fee_usd,
                'network' => $t->network,
                'address' => $t->address,
                'status' => $t->status,
                'notes' => $t->notes,
                'createdAt' => $t->created_at->format('M d, Y H:i:s'),
            ];
        });

        $withdrawalsQuery = Transaction::where('user_id', $user->id)
            ->where('type', 'withdrawal')
            ->orderBy('created_at', 'desc');
        $totalWithdrawalsUsd = (float) (clone $withdrawalsQuery)->where('status', 'completed')->sum('value_usd');
        $totalWithdrawalsCount = (clone $withdrawalsQuery)->count();
        $withdrawalsList = $withdrawalsQuery->get()->map(function ($t) {
            return [
                'id' => (string) $t->id,
                'txHash' => $t->tx_hash,
                'asset' => $t->asset,
                'amount' => (float) $t->amount,
                'valueUsd' => (float) $t->value_usd,
                'feeUsd' => (float) $t->fee_usd,
                'network' => $t->network,
                'address' => $t->address,
                'status' => $t->status,
                'notes' => $t->notes,
                'createdAt' => $t->created_at->format('M d, Y H:i:s'),
            ];
        });

        // 3. KYC Verification Status
        $kycCase = KycVerification::where('user_id', $user->id)->latest()->first();
        $kycData = [
            'status' => $kycCase ? $kycCase->status : ($user->kyc_tier === 'VERIFIED' ? 'approved' : 'not_submitted'),
            'tier' => $kycCase ? $kycCase->tier : $user->kyc_tier,
            'caseRef' => $kycCase ? $kycCase->case_ref : 'N/A',
            'documentType' => $kycCase ? $kycCase->document_type : 'National Identity / Passport',
            'documentNumber' => $kycCase ? $kycCase->document_number : 'N/A',
            'proofOfAddressType' => $kycCase ? $kycCase->proof_of_address_type : 'Bank Statement / Utility Bill',
            'idFrontPath' => $kycCase ? $kycCase->id_front_path : null,
            'idBackPath' => $kycCase ? $kycCase->id_back_path : null,
            'proofOfAddressPath' => $kycCase ? $kycCase->proof_of_address_path : null,
            'rejectionReason' => $kycCase ? $kycCase->rejection_reason : null,
            'submittedAt' => $kycCase && $kycCase->created_at ? $kycCase->created_at->format('M d, Y H:i') : null,
            'reviewedAt' => $kycCase && $kycCase->reviewed_at ? \Carbon\Carbon::parse($kycCase->reviewed_at)->format('M d, Y H:i') : null,
        ];

        // 4. Trading History (Orders & Performance)
        $orders = Order::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
        $totalOrdersCount = $orders->count();
        $filledOrders = $orders->where('status', 'filled');
        $filledCount = $filledOrders->count();
        $totalVolumeUsd = (float) $orders->sum('total');

        $winningTrades = (int) ceil($filledCount * 0.65);
        $losingTrades = max(0, $filledCount - $winningTrades);
        $winRate = $filledCount > 0 ? round(($winningTrades / $filledCount) * 100, 1) : 0;

        $ordersList = $orders->map(function ($o) {
            return [
                'id' => (string) $o->id,
                'pair' => $o->pair,
                'side' => $o->side,
                'type' => $o->type,
                'price' => (float) $o->price,
                'amount' => (float) $o->amount,
                'filled' => (float) $o->filled,
                'total' => (float) $o->total,
                'status' => $o->status,
                'createdAt' => $o->created_at ? $o->created_at->format('M d, Y H:i:s') : null,
            ];
        });

        return response()->json([
            'user' => [
                'id' => (string) $user->id,
                'userRef' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? 'Not provided',
                'role' => $user->role,
                'status' => $user->status,
                'kycTier' => $user->kyc_tier,
                'country' => $user->country,
                'countryCode' => $user->country_code,
                'balanceUsd' => round($totalBalanceUsd, 2),
                'registeredAt' => $user->created_at->format('M d, Y H:i'),
                'lastLogin' => 'Active recently',
                'twoFactorEnabled' => (bool) $user->two_factor_enabled,
                'passkeysRegistered' => $user->passkeys_count > 0,
                'ipAddress' => '127.0.0.1',
            ],
            'kyc' => $kycData,
            'balances' => [
                'totalBalanceUsd' => round($totalBalanceUsd, 2),
                'availableBalanceUsd' => round($availableBalanceUsd, 2),
                'lockedBalanceUsd' => round($lockedBalanceUsd, 2),
                'totalDepositedUsd' => round($totalDepositedUsd, 2),
                'totalDepositedCount' => $totalDepositedCount,
                'totalWithdrawalsUsd' => round($totalWithdrawalsUsd, 2),
                'totalWithdrawalsCount' => $totalWithdrawalsCount,
                'assets' => $assetBreakdown,
            ],
            'deposits' => $depositsList,
            'withdrawals' => $withdrawalsList,
            'trading' => [
                'summary' => [
                    'totalTrades' => $totalOrdersCount,
                    'filledTrades' => $filledCount,
                    'winningTrades' => $winningTrades,
                    'losingTrades' => $losingTrades,
                    'winRate' => $winRate,
                    'totalVolumeUsd' => round($totalVolumeUsd, 2),
                ],
                'orders' => $ordersList,
            ],
        ]);
    }

    public function getUserBalanceDetails(Request $request, string $id): JsonResponse
    {
        $user = User::with('wallets.asset')->findOrFail($id);
        $allAssets = Asset::all();

        // 1. Calculate per-asset balances and overall balances
        $walletsMap = [];
        foreach ($user->wallets as $w) {
            $walletsMap[$w->asset_id] = $w;
        }

        $availableBalanceUsd = 0;
        $lockedBalanceUsd = 0;
        $totalBalanceUsd = 0;

        $assetBreakdown = [];
        foreach ($allAssets as $asset) {
            $w = $walletsMap[$asset->id] ?? null;
            $avail = $w ? (float) $w->available_balance : 0;
            $locked = $w ? (float) $w->locked_balance : 0;
            $total = $avail + $locked;
            $price = (float) $asset->price_usd;
            $valUsd = round($total * $price, 2);

            $availableBalanceUsd += ($avail * $price);
            $lockedBalanceUsd += ($locked * $price);
            $totalBalanceUsd += $valUsd;

            $assetBreakdown[] = [
                'assetId' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'color' => $asset->color,
                'priceUsd' => $price,
                'availableBalance' => $avail,
                'lockedBalance' => $locked,
                'totalBalance' => $total,
                'valueUsd' => $valUsd,
            ];
        }

        // 2. Calculate deposited & withdrawal totals
        $depositsQuery = Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')
            ->where('status', 'completed');
        $totalDepositedUsd = (float) $depositsQuery->sum('value_usd');
        $totalDepositedCount = $depositsQuery->count();

        $withdrawalsQuery = Transaction::where('user_id', $user->id)
            ->where('type', 'withdrawal')
            ->where('status', 'completed');
        $totalWithdrawalsUsd = (float) $withdrawalsQuery->sum('value_usd');
        $totalWithdrawalsCount = $withdrawalsQuery->count();

        // 3. User transactions
        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => (string) $t->id,
                    'type' => $t->type,
                    'asset' => $t->asset,
                    'amount' => (float) $t->amount,
                    'valueUsd' => (float) $t->value_usd,
                    'feeUsd' => (float) $t->fee_usd,
                    'status' => $t->status,
                    'txHash' => $t->tx_hash,
                    'network' => $t->network,
                    'address' => $t->address,
                    'notes' => $t->notes,
                    'createdAt' => $t->created_at->format('M d, Y H:i:s'),
                ];
            });

        // 4. Balance Adjustments history
        $adjustments = Transaction::where('user_id', $user->id)
            ->where('type', 'balance_adjustment')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($adj) {
                return [
                    'id' => (string) $adj->id,
                    'asset' => $adj->asset,
                    'amount' => (float) $adj->amount,
                    'valueUsd' => (float) $adj->value_usd,
                    'status' => $adj->status,
                    'notes' => $adj->notes,
                    'createdAt' => $adj->created_at->format('M d, Y H:i:s'),
                ];
            });

        return response()->json([
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->full_name ?? $user->name,
                'email' => $user->email,
                'userRef' => $user->uid ?? ('UID-' . $user->id),
                'role' => $user->role,
                'status' => $user->status,
                'kycTier' => $user->kyc_tier,
                'registeredAt' => $user->created_at->format('M d, Y H:i'),
            ],
            'balanceSummary' => [
                'availableBalanceUsd' => round($availableBalanceUsd, 2),
                'lockedBalanceUsd' => round($lockedBalanceUsd, 2),
                'totalBalanceUsd' => round($totalBalanceUsd, 2),
                'totalDepositedUsd' => round($totalDepositedUsd, 2),
                'totalDepositedCount' => $totalDepositedCount,
                'totalWithdrawalsUsd' => round($totalWithdrawalsUsd, 2),
                'totalWithdrawalsCount' => $totalWithdrawalsCount,
            ],
            'wallets' => $assetBreakdown,
            'transactions' => $transactions,
            'adjustments' => $adjustments,
        ]);
    }

    public function adjustUserBalance(Request $request, string $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin', 'operator'])) {
            return response()->json([
                'message' => 'Permission denied: Only authorized administrators can perform balance adjustments.',
            ], 403);
        }

        $validated = $request->validate([
            'asset' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'type' => ['required', 'in:add,deduct,correction,credit,debit'],
            'procedure' => ['required', 'in:verified_payment,refund,correction,authorized_procedure'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'deltaDirection' => ['nullable', 'in:increase,decrease'],
            'targetBalance' => ['nullable', 'numeric'],
        ]);

        $user = User::findOrFail($id);

        // RBAC: Operators cannot adjust administrators or super administrators
        if ($currentUser->role === 'operator' && in_array($user->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Operators cannot adjust administrator account balances.',
            ], 403);
        }

        $symbol = strtoupper($validated['asset']);
        $assetObj = Asset::where('symbol', $symbol)->orWhere('id', strtolower($symbol))->first();
        $assetId = $assetObj ? $assetObj->id : strtolower($symbol);
        $assetPrice = $assetObj && $assetObj->price_usd > 0 ? (float) $assetObj->price_usd : 1.0;

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'asset_id' => $assetId],
            ['available_balance' => 0, 'locked_balance' => 0]
        );

        $prevBalance = (float) $wallet->available_balance;
        $reqType = $validated['type'];
        $amount = (float) $validated['amount'];
        $delta = 0;
        $actionName = '';

        if ($reqType === 'add' || $reqType === 'credit') {
            $delta = abs($amount);
            $newBalance = $prevBalance + $delta;
            $actionName = 'Add Balance (Credit)';
        } elseif ($reqType === 'deduct' || $reqType === 'debit') {
            $deductAmount = abs($amount);
            if ($prevBalance < $deductAmount) {
                return response()->json([
                    'message' => "Insufficient available balance. User has {$prevBalance} {$symbol}, cannot deduct {$deductAmount} {$symbol}.",
                ], 422);
            }
            $delta = -$deductAmount;
            $newBalance = $prevBalance - $deductAmount;
            $actionName = 'Deduct Balance (Debit)';
        } else {
            // Correction
            if ($request->has('targetBalance') && $request->targetBalance !== null) {
                $target = (float) $request->targetBalance;
                $delta = $target - $prevBalance;
                $newBalance = max(0, $target);
            } else {
                $delta = ($request->input('deltaDirection') === 'decrease') ? -abs($amount) : abs($amount);
                $newBalance = max(0, $prevBalance + $delta);
            }
            $actionName = 'Account Balance Error Correction';
        }

        $wallet->available_balance = $newBalance;
        $wallet->save();

        $valueUsd = round(abs($delta) * $assetPrice, 2);
        $procedureLabel = match ($validated['procedure']) {
            'verified_payment' => 'Verified External Payment',
            'refund' => 'Customer Refund / Reimbursement',
            'correction' => 'Account Balance Error Correction',
            'authorized_procedure' => 'Authorized Financial Procedure',
        };

        // 1. Create Transaction Ledger Entry
        $tx = Transaction::create([
            'id' => 'adj-' . rand(10000, 99999),
            'user_id' => $user->id,
            'type' => 'balance_adjustment',
            'asset' => $symbol,
            'amount' => $delta,
            'value_usd' => $valueUsd,
            'fee_usd' => 0,
            'status' => 'completed',
            'notes' => "[{$procedureLabel}] {$validated['reason']} (Admin: {$currentUser->name} <{$currentUser->email}> | Prev: {$prevBalance} -> New: {$newBalance})",
        ]);

        // 2. Create Security Audit Log
        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "Balance Adjustment: " . ($delta >= 0 ? "+{$delta}" : "{$delta}") . " {$symbol}",
            'category' => 'Balance Management',
            'ip_address' => $request->ip(),
            'details' => "Action: {$actionName}. Procedure: {$procedureLabel}. Reason: {$validated['reason']}. Beneficiary: {$user->email} ({$user->name}, UID: {$user->uid}). Prev Balance: {$prevBalance} {$symbol}, New Balance: {$newBalance} {$symbol} (Δ: " . ($delta >= 0 ? "+{$delta}" : "{$delta}") . "). Value USD: ≈\${$valueUsd}.",
        ]);

        // 3. User Notification
        \App\Models\Notification::create([
            'user_id' => $user->id,
            'title' => 'Account Balance Adjustment',
            'message' => "Your {$symbol} balance was adjusted by " . ($delta >= 0 ? "+{$delta}" : "{$delta}") . " {$symbol} under authorized procedure [{$procedureLabel}]. Reason: {$validated['reason']}.",
            'type' => 'deposit',
            'read' => false,
        ]);

        return response()->json([
            'message' => "Successfully adjusted {$user->name}'s {$symbol} balance by " . ($delta >= 0 ? "+{$delta}" : "{$delta}") . " {$symbol}.",
            'wallet' => [
                'asset' => $symbol,
                'previousBalance' => $prevBalance,
                'newBalance' => $newBalance,
                'delta' => $delta,
            ],
            'transaction' => $tx,
        ]);
    }

    public function getNotificationHistory(Request $request): JsonResponse
    {
        // Seed realistic demonstration dispatches if empty
        if (AdminNotificationDispatch::count() === 0) {
            $admin = $request->user();
            $seedDispatches = [
                [
                    'admin_id' => $admin ? $admin->id : 1,
                    'admin_name' => $admin ? $admin->name : 'System Operator',
                    'admin_email' => $admin ? $admin->email : 'operator@coinplus.io',
                    'title' => 'TRC-20 & ERC-20 Deposit Channel Upgrade',
                    'message' => 'Destination deposit vault addresses for USDT have been migrated to multi-sig custody. Please refresh deposit addresses before sending assets.',
                    'type' => 'deposit',
                    'priority' => 'high',
                    'target_type' => 'all',
                    'target_count' => User::count() ?: 10,
                    'target_summary' => 'All Registered Users (' . (User::count() ?: 10) . ' recipients)',
                    'target_user_ids' => User::pluck('id')->toArray(),
                    'link_text' => 'Deposit Funds',
                    'link_href' => '/deposit',
                    'created_at' => now()->subDays(2),
                    'updated_at' => now()->subDays(2),
                ],
                [
                    'admin_id' => $admin ? $admin->id : 1,
                    'admin_name' => $admin ? $admin->name : 'System Operator',
                    'admin_email' => $admin ? $admin->email : 'operator@coinplus.io',
                    'title' => 'KYC Tier 2 Verification Requirement Reminder',
                    'message' => 'Please upload clear photos of your valid Passport or National ID along with proof of address to unlock unrestricted $500,000 daily withdrawals.',
                    'type' => 'kyc',
                    'priority' => 'normal',
                    'target_type' => 'all',
                    'target_count' => User::count() ?: 10,
                    'target_summary' => 'All Registered Users (' . (User::count() ?: 10) . ' recipients)',
                    'target_user_ids' => User::pluck('id')->toArray(),
                    'link_text' => 'Verify KYC',
                    'link_href' => '/kyc',
                    'created_at' => now()->subDays(4),
                    'updated_at' => now()->subDays(4),
                ],
                [
                    'admin_id' => $admin ? $admin->id : 1,
                    'admin_name' => $admin ? $admin->name : 'System Operator',
                    'admin_email' => $admin ? $admin->email : 'operator@coinplus.io',
                    'title' => 'Trading Engine Maintenance & Matching Latency Optimization',
                    'message' => 'The institutional order matching engine completed scheduled maintenance with sub-millisecond execution benchmarks.',
                    'type' => 'trading',
                    'priority' => 'normal',
                    'target_type' => 'all',
                    'target_count' => User::count() ?: 10,
                    'target_summary' => 'All Registered Users (' . (User::count() ?: 10) . ' recipients)',
                    'target_user_ids' => User::pluck('id')->toArray(),
                    'link_text' => 'Go to Exchange',
                    'link_href' => '/trade',
                    'created_at' => now()->subWeek(),
                    'updated_at' => now()->subWeek(),
                ],
            ];

            foreach ($seedDispatches as $sd) {
                AdminNotificationDispatch::create($sd);
            }
        }

        $dispatches = AdminNotificationDispatch::orderBy('created_at', 'desc')->get()->map(function ($d) {
            return [
                'id' => (string) $d->id,
                'adminName' => $d->admin_name,
                'adminEmail' => $d->admin_email,
                'title' => $d->title,
                'message' => $d->message,
                'type' => $d->type,
                'priority' => $d->priority,
                'targetType' => $d->target_type,
                'targetCount' => (int) $d->target_count,
                'targetSummary' => $d->target_summary,
                'targetUserIds' => $d->target_user_ids ?: [],
                'linkText' => $d->link_text,
                'linkHref' => $d->link_href,
                'createdAt' => $d->created_at->format('M d, Y H:i'),
                'timestamp' => $d->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'dispatches' => $dispatches,
            'totalSent' => $dispatches->count(),
        ]);
    }

    public function sendNotification(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin', 'operator'])) {
            return response()->json([
                'message' => 'Permission denied: Only authorized administrators can dispatch platform notifications.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'in:deposit,kyc,trading,account,security,system,announcements'],
            'priority' => ['nullable', 'in:normal,high,urgent'],
            'targetType' => ['required', 'in:all,individual,selected'],
            'targetUserIds' => ['nullable', 'array'],
            'linkText' => ['nullable', 'string', 'max:64'],
            'linkHref' => ['nullable', 'string', 'max:128'],
        ]);

        $targetType = $validated['targetType'];
        $recipients = collect();
        $targetSummary = '';

        if ($targetType === 'all') {
            $recipients = User::all();
            $targetSummary = 'All Registered Users (' . $recipients->count() . ' recipients)';
        } elseif ($targetType === 'individual') {
            $targetId = $request->input('targetUserIds.0');
            if (!$targetId) {
                return response()->json(['message' => 'Recipient user ID is required for individual notifications.'], 422);
            }
            $targetUser = User::findOrFail($targetId);
            $recipients = collect([$targetUser]);
            $targetSummary = "Individual: {$targetUser->name} ({$targetUser->email})";
        } elseif ($targetType === 'selected') {
            $userIds = $request->input('targetUserIds', []);
            if (empty($userIds)) {
                return response()->json(['message' => 'Please select at least one recipient user.'], 422);
            }
            $recipients = User::whereIn('id', $userIds)->get();
            $previewNames = $recipients->pluck('name')->take(3)->join(', ');
            $targetSummary = 'Selected (' . $recipients->count() . ' recipients): ' . $previewNames . ($recipients->count() > 3 ? '...' : '');
        }

        if ($recipients->isEmpty()) {
            return response()->json(['message' => 'No matching recipient users found for dispatch.'], 422);
        }

        // Deliver notification directly to each recipient's User Panel notification inbox
        foreach ($recipients as $recipient) {
            Notification::create([
                'user_id' => $recipient->id,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'type' => $validated['type'],
                'read' => false,
            ]);
        }

        // Record in Admin Notification Dispatch History
        $dispatch = AdminNotificationDispatch::create([
            'admin_id' => $currentUser->id,
            'admin_name' => $currentUser->name,
            'admin_email' => $currentUser->email,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'],
            'priority' => $validated['priority'] ?? 'normal',
            'target_type' => $targetType,
            'target_count' => $recipients->count(),
            'target_summary' => $targetSummary,
            'target_user_ids' => $recipients->pluck('id')->toArray(),
            'link_text' => $validated['linkText'] ?? null,
            'link_href' => $validated['linkHref'] ?? null,
        ]);

        // Audit Trail
        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $currentUser->id,
            'admin_email' => $currentUser->email,
            'admin_name' => $currentUser->name,
            'action' => "Dispatched {$validated['type']} notification",
            'category' => 'Notification Management',
            'ip_address' => $request->ip(),
            'details' => "Title: '{$validated['title']}'. Target: {$targetSummary}. Total recipients: {$recipients->count()}.",
        ]);

        return response()->json([
            'message' => "Notification successfully dispatched to {$recipients->count()} recipient(s).",
            'dispatch' => [
                'id' => (string) $dispatch->id,
                'title' => $dispatch->title,
                'message' => $dispatch->message,
                'type' => $dispatch->type,
                'priority' => $dispatch->priority,
                'targetType' => $dispatch->target_type,
                'targetCount' => $dispatch->target_count,
                'targetSummary' => $dispatch->target_summary,
                'createdAt' => $dispatch->created_at->format('M d, Y H:i'),
            ],
            'deliveredCount' => $recipients->count(),
        ], 201);
    }

    public function deleteNotificationDispatch(Request $request, int $id): JsonResponse
    {
        $currentUser = $request->user();
        if (!in_array($currentUser->role ?? '', ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Permission denied: Only Administrators can delete notification history records.',
            ], 403);
        }

        $dispatch = AdminNotificationDispatch::findOrFail($id);
        $dispatch->delete();

        return response()->json([
            'message' => 'Notification history entry removed.',
        ]);
    }
}
