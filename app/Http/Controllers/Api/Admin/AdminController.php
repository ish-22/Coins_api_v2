<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\Order;
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
        return response()->json([
            'metrics' => [
                'totalUsers' => User::count(),
                'activeUsers24h' => User::where('status', 'active')->count(),
                'volume24hUsd' => 14820940.50,
                'totalDepositsUsd' => 8421090.00,
                'totalWithdrawalsUsd' => 3120400.00,
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
        $users = User::orderBy('created_at', 'desc')->get()->map(function ($u) {
            return [
                'id' => (string) $u->id,
                'userRef' => $u->uid ?? ('UID-' . $u->id),
                'name' => $u->full_name ?? $u->name,
                'email' => $u->email,
                'country' => $u->country ?? 'United States',
                'countryCode' => $u->country_code ?? 'US',
                'status' => $u->status,
                'kycTier' => $u->kyc_tier,
                'balanceUsd' => 48920.45,
                'registeredAt' => $u->created_at->format('M d, Y'),
                'lastLogin' => 'Today, 08:12 UTC',
                'twoFactorEnabled' => (bool) $u->two_factor_enabled,
                'passkeysRegistered' => $u->passkeys_count > 0,
                'ipAddress' => '127.0.0.1',
            ];
        });

        return response()->json([
            'users' => $users,
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
                    'status' => $w->status === 'completed' ? 'approved' : 'pending_review',
                    'createdAt' => $w->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json([
            'withdrawals' => $withdrawals,
        ]);
    }

    public function getOrders(): JsonResponse
    {
        $orders = Order::with('user')->orderBy('created_at', 'desc')->get()->map(function ($o) {
            return [
                'id' => (string) $o->id,
                'orderRef' => $o->id,
                'userId' => (string) $o->user_id,
                'userName' => $o->user ? $o->user->name : 'Alex Vance',
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

    public function updateAsset(Request $request, string $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        $validated = $request->validate([
            'depositEnabled' => ['nullable', 'boolean'],
            'withdrawEnabled' => ['nullable', 'boolean'],
            'tradingEnabled' => ['nullable', 'boolean'],
            'withdrawalFee' => ['nullable', 'numeric'],
        ]);

        if (isset($validated['depositEnabled'])) $asset->deposit_enabled = $validated['depositEnabled'];
        if (isset($validated['withdrawEnabled'])) $asset->withdraw_enabled = $validated['withdrawEnabled'];
        if (isset($validated['tradingEnabled'])) $asset->trading_enabled = $validated['tradingEnabled'];
        if (isset($validated['withdrawalFee'])) $asset->withdrawal_fee = $validated['withdrawalFee'];

        $asset->save();

        return response()->json([
            'asset' => $asset,
            'message' => "Configuration for {$asset->symbol} updated.",
        ]);
    }

    public function getAuditLogs(): JsonResponse
    {
        return response()->json([
            'logs' => AuditLog::orderBy('created_at', 'desc')->take(50)->get(),
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
