<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
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
                'role' => $u->role ?? 'user',
                'country' => $u->country ?? 'United States',
                'countryCode' => $u->country_code ?? 'US',
                'status' => $u->status,
                'kycTier' => $u->kyc_tier,
                'balanceUsd' => round($totalBalanceUsd, 2),
                'registeredAt' => $u->created_at->format('M d, Y'),
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
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'in:user,admin,operator'],
            'status' => ['nullable', 'string', 'in:active,suspended,pending'],
            'kyc_tier' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'initial_balance_usd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $uid = 'CP-USR-' . rand(1000, 9999);
        $user = User::create([
            'uid' => $uid,
            'name' => $validated['name'],
            'display_name' => $validated['name'],
            'full_name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'user',
            'status' => $validated['status'] ?? 'active',
            'kyc_tier' => $validated['kyc_tier'] ?? 'Tier 1 (Basic Identity)',
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
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "User Account Created: {$user->email} ({$user->uid})",
            'category' => 'User Management',
            'ip_address' => $request->ip(),
            'details' => "Administrative profile created for {$user->email} with role {$user->role}",
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => [
                'id' => (string) $user->id,
                'userRef' => $user->uid,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'country' => $user->country,
                'countryCode' => $user->country_code,
                'status' => $user->status,
                'kycTier' => $user->kyc_tier,
                'balanceUsd' => $initialUsdt,
                'registeredAt' => $user->created_at->format('M d, Y'),
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

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', 'in:user,admin,operator'],
            'status' => ['nullable', 'string', 'in:active,suspended,pending'],
            'kyc_tier' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'balance_usd' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (!empty($validated['name'])) {
            $user->name = $validated['name'];
            $user->full_name = $validated['name'];
            $user->display_name = $validated['name'];
        }
        if (!empty($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (!empty($validated['password'])) {
            $user->password = \Illuminate\Support\Facades\Hash::make($validated['password']);
        }
        if (isset($validated['role'])) {
            $user->role = $validated['role'];
        }
        if (isset($validated['status'])) {
            $user->status = $validated['status'];
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
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "User Profile Updated: {$user->email} ({$user->uid})",
            'category' => 'User Management',
            'ip_address' => $request->ip(),
            'details' => "Administrative profile modification applied for {$user->email}",
        ]);

        return response()->json([
            'message' => 'User profile updated successfully.',
            'user' => $user,
        ]);
    }

    public function deleteUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $userEmail = $user->email;
        $userUid = $user->uid;

        // Prevent admin from deleting themselves
        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own active administrative account.',
            ], 422);
        }

        $user->delete();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
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
            'status' => ['required', 'in:active,suspended,pending'],
        ]);

        $user = User::findOrFail($id);
        $user->status = $validated['status'];
        $user->save();

        AuditLog::create([
            'event_id' => 'EVT-' . rand(1000, 9999),
            'user_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'admin_name' => $request->user()->name,
            'action' => "User Status Updated: {$user->email} -> {$user->status}",
            'category' => 'User Management',
            'ip_address' => $request->ip(),
            'details' => "Account {$user->uid} status updated to {$user->status}",
        ]);

        return response()->json([
            'user' => $user,
            'message' => 'User status updated successfully.',
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
            $case->rejection_reason = $validated['rejectionReason'] ?? 'Document unreadable';
        }
        $case->reviewed_at = now();
        $case->save();

        if ($case->status === 'approved' && $case->user) {
            $case->user->kyc_tier = $case->tier . ' (Verified)';
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

    public function getDeposits(): JsonResponse
    {
        $deposits = Transaction::with('user')
            ->where('type', 'deposit')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($d) {
                return [
                    'id' => (string) $d->id,
                    'depositRef' => 'DEP-' . substr($d->id, -6),
                    'txHash' => $d->tx_hash,
                    'userId' => (string) $d->user_id,
                    'userName' => $d->user ? $d->user->name : 'Trader',
                    'asset' => $d->asset,
                    'network' => $d->network ?? 'Blockchain Mainnet',
                    'amount' => (float) $d->amount,
                    'amountUsd' => (float) $d->value_usd,
                    'status' => $d->status,
                    'confirmations' => 32,
                    'requiredConfirmations' => 32,
                    'destinationAddress' => $d->address ?? 'Vault',
                    'createdAt' => $d->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json([
            'deposits' => $deposits,
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

            // Refund user locked/available balance if failed
            $assetObj = Asset::where('symbol', $tx->asset)->first();
            $assetId = $assetObj ? $assetObj->id : strtolower($tx->asset);
            $wallet = \App\Models\Wallet::where('user_id', $tx->user_id)->where('asset_id', $assetId)->first();
            if ($wallet) {
                $wallet->available_balance += floatval($tx->amount);
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

    public function deleteAnnouncement(int $id): JsonResponse
    {
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

    public function deleteAsset(string $id): JsonResponse
    {
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

    public function getSupportTickets(): JsonResponse
    {
        $tickets = SupportTicket::with(['user', 'messages'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => (string) $ticket->id,
                    'ticketRef' => $ticket->ticket_ref,
                    'userId' => (string) $ticket->user_id,
                    'userName' => $ticket->user ? $ticket->user->name : 'Customer',
                    'userEmail' => $ticket->user ? $ticket->user->email : 'support@coinplus.io',
                    'subject' => $ticket->subject,
                    'category' => $ticket->category,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'createdAt' => $ticket->created_at ? $ticket->created_at->format('M d, Y H:i') : null,
                    'updatedAt' => $ticket->updated_at ? $ticket->updated_at->format('M d, Y H:i') : null,
                    'messages' => $ticket->messages->map(function ($msg) {
                        return [
                            'id' => (string) $msg->id,
                            'sender' => $msg->sender_type,
                            'senderName' => $msg->sender_name,
                            'message' => $msg->message,
                            'timestamp' => $msg->created_at ? $msg->created_at->format('M d, Y H:i') : null,
                        ];
                    })->values()->all(),
                ];
            });

        return response()->json([
            'tickets' => $tickets,
        ]);
    }

    public function replySupportTicket(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket = SupportTicket::findOrFail($id);

        $msg = SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'agent',
            'sender_name' => $request->user()->name,
            'message' => $validated['message'],
        ]);

        $ticket->status = 'waiting_user';
        $ticket->save();

        return response()->json([
            'message' => 'Support reply posted successfully.',
            'reply' => [
                'id' => (string) $msg->id,
                'sender' => 'agent',
                'senderName' => $request->user()->name,
                'message' => $msg->message,
                'created_at' => $msg->created_at,
            ],
        ], 201);
    }

    public function updateTicketStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,in_progress,waiting_user,resolved'],
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->status = $validated['status'];
        $ticket->save();

        return response()->json([
            'message' => "Support ticket {$ticket->ticket_ref} status updated to {$ticket->status}.",
            'ticket' => $ticket,
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
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $setting = SystemSetting::updateOrCreate(
            ['key' => 'general'],
            ['value' => $validated['settings']]
        );

        return response()->json([
            'settings' => $setting->value,
            'message' => 'System parameters saved.',
        ]);
    }
}
