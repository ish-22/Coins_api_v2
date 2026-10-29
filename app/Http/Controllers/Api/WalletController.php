<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function getSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallets = Wallet::with('asset')->where('user_id', $user->id)->get();

        $totalBalanceUsd = 0;
        $availableBalanceUsd = 0;
        $lockedBalanceUsd = 0;

        foreach ($wallets as $w) {
            $price = $w->asset ? (float) $w->asset->price_usd : 0;
            $avail = (float) $w->available_balance;
            $locked = (float) $w->locked_balance;

            $availableBalanceUsd += ($avail * $price);
            $lockedBalanceUsd += ($locked * $price);
            $totalBalanceUsd += (($avail + $locked) * $price);
        }

        $btcAsset = Asset::where('symbol', 'BTC')->first();
        $btcPrice = $btcAsset && $btcAsset->price_usd > 0 ? (float) $btcAsset->price_usd : 68450.25;

        return response()->json([
            'summary' => [
                'totalBalanceUsd' => round($totalBalanceUsd, 2),
                'totalBalanceBtc' => round($totalBalanceUsd / $btcPrice, 5),
                'totalBalanceEur' => round($totalBalanceUsd * 0.92, 2),
                'availableBalanceUsd' => round($availableBalanceUsd, 2),
                'availableBalanceBtc' => round($availableBalanceUsd / $btcPrice, 5),
                'lockedBalanceUsd' => round($lockedBalanceUsd, 2),
                'lockedBalanceBtc' => round($lockedBalanceUsd / $btcPrice, 5),
                'change24hUsd' => 2410.80,
                'change24hPercentage' => 5.18,
                'totalAssetsCount' => $wallets->count(),
                'verificationTier' => $user->kyc_tier,
                'dailyWithdrawRemainingUsd' => 500000,
            ],
        ]);
    }

    public function getAssets(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallets = Wallet::with('asset')->where('user_id', $user->id)->get();

        $totalUsd = 0;
        $assetItems = [];

        foreach ($wallets as $w) {
            $asset = $w->asset;
            if (! $asset) continue;

            $avail = (float) $w->available_balance;
            $locked = (float) $w->locked_balance;
            $total = $avail + $locked;
            $price = (float) $asset->price_usd;
            $val = round($total * $price, 2);
            $totalUsd += $val;

            $assetItems[] = [
                'id' => $asset->id,
                'name' => $asset->name,
                'symbol' => $asset->symbol,
                'type' => $asset->type,
                'iconBg' => $asset->icon_bg,
                'availableBalance' => $avail,
                'lockedBalance' => $locked,
                'totalBalance' => $total,
                'priceUsd' => $price,
                'valueUsd' => $val,
                'change24h' => (float) $asset->change_24h,
                'allocationPercentage' => 0, // computed below
                'network' => is_array($asset->networks) && count($asset->networks) > 0 ? $asset->networks[0] : 'Mainnet',
                'color' => $asset->color,
            ];
        }

        // Compute allocation percentage
        foreach ($assetItems as &$item) {
            $item['allocationPercentage'] = $totalUsd > 0 ? round(($item['valueUsd'] / $totalUsd) * 100, 1) : 0;
        }

        return response()->json([
            'assets' => $assetItems,
        ]);
    }

    public function getTransactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'type' => $t->type,
                    'asset' => $t->asset,
                    'amount' => (float) $t->amount,
                    'valueUsd' => (float) $t->value_usd,
                    'feeUsd' => (float) $t->fee_usd,
                    'status' => $t->status,
                    'txHash' => $t->tx_hash,
                    'network' => $t->network,
                    'address' => $t->address,
                    'confirmations' => $t->confirmations,
                    'date' => $t->created_at->format('M d, Y'),
                    'time' => $t->created_at->format('H:i') . ' UTC',
                    'notes' => $t->notes,
                ];
            });

        return response()->json([
            'transactions' => $transactions,
        ]);
    }

    public function getDepositAddress(Request $request): JsonResponse
    {
        $assetSymbol = strtoupper($request->query('asset', 'BTC'));
        $network = $request->query('network', 'Native');

        $addresses = [
            'BTC' => 'bc1q9px90f3c71a4819d9b2e04f7c11a2245bb318029',
            'ETH' => '0x71C8821199a0B1298811d7F4ac91882019482011',
            'USDT' => 'TX9Qe881948ac9120847719201948201148zK',
            'SOL' => '8F3c71a4819d9B2e04f7c11a2245bB318029e1F5',
            'BNB' => 'bnb1q9px90f3c71a4819d9b2e04f7c11a2245bb318029',
            'XRP' => 'rEb8TK3gBgk5auZyyb6GeaHSqHT5943WII',
            'DOGE' => 'D8f3c71a4819d9b2e04f7c11a2245bb318029',
            'USDC' => '0x44a1b029ff8812c37719d2a019b8821948ac9120',
        ];

        $address = $addresses[$assetSymbol] ?? '0x71C8821199a0B1298811d7F4ac91882019482011';

        return response()->json([
            'asset' => $assetSymbol,
            'network' => $network,
            'address' => $address,
            'memoRequired' => ($assetSymbol === 'XRP'),
            'memo' => ($assetSymbol === 'XRP') ? '881920' : null,
            'minDeposit' => 0.0001,
            'confirmationsRequired' => ($assetSymbol === 'BTC') ? 3 : 12,
        ]);
    }

    public function simulateDeposit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'network' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $symbol = strtoupper($validated['asset']);

        return DB::transaction(function () use ($user, $symbol, $validated) {
            $asset = Asset::where('symbol', $symbol)->firstOrFail();
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id, 'asset_id' => $asset->id],
                ['available_balance' => 0, 'locked_balance' => 0]
            );

            $wallet->available_balance += $validated['amount'];
            $wallet->save();

            $price = (float) $asset->price_usd;
            $valUsd = round($validated['amount'] * $price, 2);

            $tx = Transaction::create([
                'id' => 'tx-dep-' . rand(1000, 9999),
                'user_id' => $user->id,
                'type' => 'deposit',
                'asset' => $symbol,
                'amount' => $validated['amount'],
                'value_usd' => $valUsd,
                'fee_usd' => 0,
                'status' => 'completed',
                'tx_hash' => '0x' . bin2hex(random_bytes(20)),
                'network' => $validated['network'] ?? 'Simulated Network',
                'address' => 'Simulated Inflow Wallet',
                'confirmations' => 'Confirmed',
                'notes' => "Demonstration deposit of {$validated['amount']} {$symbol} credited",
            ]);

            return response()->json([
                'transaction' => $tx,
                'wallet' => $wallet,
                'message' => "Deposit of {$validated['amount']} {$symbol} credited successfully.",
            ], 201);
        });
    }

    public function withdraw(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset' => ['required', 'string'],
            'network' => ['required', 'string'],
            'address' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'memo' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $symbol = strtoupper($validated['asset']);

        return DB::transaction(function () use ($user, $symbol, $validated) {
            $asset = Asset::where('symbol', $symbol)->firstOrFail();
            $wallet = Wallet::where('user_id', $user->id)
                ->where('asset_id', $asset->id)
                ->first();

            $fee = (float) $asset->withdrawal_fee;
            $totalDeduction = $validated['amount'] + $fee;

            if (! $wallet || $wallet->available_balance < $totalDeduction) {
                return response()->json([
                    'message' => "Insufficient {$symbol} balance (including network fee of {$fee} {$symbol}).",
                ], 422);
            }

            $wallet->available_balance -= $totalDeduction;
            $wallet->save();

            $price = (float) $asset->price_usd;
            $valUsd = round($validated['amount'] * $price, 2);

            $tx = Transaction::create([
                'id' => 'tx-wth-' . rand(1000, 9999),
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'asset' => $symbol,
                'amount' => $validated['amount'],
                'value_usd' => $valUsd,
                'fee_usd' => round($fee * $price, 2),
                'status' => 'processing',
                'tx_hash' => '0x' . bin2hex(random_bytes(20)),
                'network' => $validated['network'],
                'address' => $validated['address'],
                'confirmations' => 'Pending Review',
                'notes' => "Withdrawal request submitted. Awaiting administrative approval.",
            ]);

            return response()->json([
                'transaction' => $tx,
                'message' => "Withdrawal request of {$validated['amount']} {$symbol} submitted and is awaiting administrative approval.",
            ], 201);
        });
    }

    public function getWatchlist(Request $request): JsonResponse
    {
        $watchlist = Watchlist::where('user_id', $request->user()->id)->pluck('asset_symbol');

        return response()->json([
            'watchlist' => $watchlist,
        ]);
    }

    public function toggleWatchlist(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assetSymbol' => ['required', 'string'],
        ]);

        $user = $request->user();
        $symbol = strtoupper($validated['assetSymbol']);

        $existing = Watchlist::where('user_id', $user->id)->where('asset_symbol', $symbol)->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            Watchlist::create([
                'user_id' => $user->id,
                'asset_symbol' => $symbol,
            ]);
            $action = 'added';
        }

        $watchlist = Watchlist::where('user_id', $user->id)->pluck('asset_symbol');

        return response()->json([
            'watchlist' => $watchlist,
            'message' => "{$symbol} {$action} from watchlist.",
        ]);
    }
}
