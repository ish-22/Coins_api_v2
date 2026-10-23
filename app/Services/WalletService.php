<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Watchlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /**
     * Get wallet summary, total estimated USD, and active allocations.
     */
    public function getSummary(User $user): array
    {
        $wallets = Wallet::with('asset')->where('user_id', $user->id)->get();

        $totalUsd = 0.0;
        $assetsList = [];

        foreach ($wallets as $wallet) {
            $asset = $wallet->asset;
            $price = $asset ? (float) $asset->current_price_usd : 1.0;
            $balance = (float) $wallet->balance;
            $locked = (float) $wallet->locked_balance;
            $total = $balance + $locked;
            $usdValue = round($total * $price, 2);
            $totalUsd += $usdValue;

            $assetsList[] = [
                'id' => $asset ? $asset->symbol : 'UNKNOWN',
                'name' => $asset ? $asset->name : 'Unknown',
                'symbol' => $asset ? $asset->symbol : 'UNKNOWN',
                'available' => $balance,
                'locked' => $locked,
                'total' => $total,
                'usdValue' => $usdValue,
                'price' => $price,
                'change24h' => $asset ? (float) $asset->change_24h : 0.0,
                'depositAddress' => $wallet->deposit_address,
            ];
        }

        return [
            'totalUsd' => round($totalUsd, 2),
            'availableUsd' => round($totalUsd, 2),
            'change24hUsd' => round($totalUsd * 0.024, 2),
            'change24hPercent' => 2.45,
            'assets' => $assetsList,
        ];
    }

    /**
     * Get deposit address for asset.
     */
    public function getDepositAddress(User $user, string $symbol, ?string $network = null): array
    {
        $symbol = strtoupper($symbol);
        $asset = Asset::where('symbol', $symbol)->first();

        if (! $asset) {
            throw ValidationException::withMessages(['asset' => ['Asset not supported.']]);
        }

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id, 'asset_id' => $asset->id],
            [
                'balance' => 0,
                'locked_balance' => 0,
                'deposit_address' => '0x' . bin2hex(random_bytes(20)),
            ]
        );

        return [
            'asset' => $asset->symbol,
            'network' => $network ?: 'Mainnet',
            'address' => $wallet->deposit_address,
            'tag' => ($asset->symbol === 'XRP') ? '892341' : null,
            'qrCode' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($wallet->deposit_address),
        ];
    }

    /**
     * Simulate deposit into user wallet.
     */
    public function simulateDeposit(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $asset = Asset::where('symbol', strtoupper($data['asset']))->first();
            if (! $asset) {
                throw ValidationException::withMessages(['asset' => ['Asset not supported.']]);
            }

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id, 'asset_id' => $asset->id],
                ['balance' => 0, 'locked_balance' => 0, 'deposit_address' => '0x' . bin2hex(random_bytes(20))]
            );

            $amount = (float) $data['amount'];
            $wallet->balance += $amount;
            $wallet->save();

            $tx = Transaction::create([
                'user_id' => $user->id,
                'asset_id' => $asset->id,
                'type' => 'DEPOSIT',
                'amount' => $amount,
                'fee' => 0,
                'tx_hash' => $data['tx_hash'] ?? ('0x' . bin2hex(random_bytes(32))),
                'to_address' => $wallet->deposit_address,
                'status' => 'COMPLETED',
                'metadata' => ['network' => 'Simulation', 'confirmations' => 12],
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'WALLET_DEPOSIT_SIMULATE',
                'details' => ['asset' => $asset->symbol, 'amount' => $amount, 'tx_id' => $tx->id],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'transaction' => $tx->toArray(),
                'newBalance' => (float) $wallet->balance,
            ];
        });
    }

    /**
     * Process withdrawal request.
     */
    public function withdraw(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $asset = Asset::where('symbol', strtoupper($data['asset']))->first();
            if (! $asset) {
                throw ValidationException::withMessages(['asset' => ['Asset not supported.']]);
            }

            $wallet = Wallet::where('user_id', $user->id)->where('asset_id', $asset->id)->first();
            $amount = (float) $data['amount'];
            $fee = round($amount * 0.0005, 4);
            $totalDeduction = $amount + $fee;

            if (! $wallet || $wallet->balance < $totalDeduction) {
                throw ValidationException::withMessages([
                    'amount' => ["Insufficient balance. Required with fee: {$totalDeduction}, Available: " . ($wallet ? $wallet->balance : 0)],
                ]);
            }

            $wallet->balance -= $totalDeduction;
            $wallet->save();

            $tx = Transaction::create([
                'user_id' => $user->id,
                'asset_id' => $asset->id,
                'type' => 'WITHDRAWAL',
                'amount' => $amount,
                'fee' => $fee,
                'tx_hash' => '0x' . bin2hex(random_bytes(32)),
                'to_address' => $data['address'],
                'status' => 'PROCESSING',
                'metadata' => [
                    'network' => $data['network'],
                    'memo' => $data['memo'] ?? null,
                ],
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'WALLET_WITHDRAWAL_INITIATED',
                'details' => ['asset' => $asset->symbol, 'amount' => $amount, 'address' => $data['address']],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return [
                'transaction' => $tx->toArray(),
                'remainingBalance' => (float) $wallet->balance,
            ];
        });
    }

    /**
     * Toggle item in user watchlist.
     */
    public function toggleWatchlist(User $user, string $pair): array
    {
        $existing = Watchlist::where('user_id', $user->id)->where('trading_pair', strtoupper($pair))->first();

        if ($existing) {
            $existing->delete();
            return ['trading_pair' => strtoupper($pair), 'is_watchlisted' => false];
        }

        Watchlist::create([
            'user_id' => $user->id,
            'trading_pair' => strtoupper($pair),
        ]);

        return ['trading_pair' => strtoupper($pair), 'is_watchlisted' => true];
    }
}
