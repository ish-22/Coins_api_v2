<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\TradingPair;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TradeService
{
    /**
     * Get user orders with optional status filter.
     */
    public function getOrders(User $user, ?string $status = null, ?string $pair = null): array
    {
        $query = Order::where('user_id', $user->id);

        if ($status && $status !== 'all') {
            $query->where('status', strtoupper($status));
        }

        if ($pair) {
            $query->where('trading_pair', strtoupper($pair));
        }

        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    /**
     * Place limit or market order.
     */
    public function placeOrder(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $pairSymbol = strtoupper($data['pair']);
            $pair = TradingPair::where('symbol', $pairSymbol)->first();

            if (! $pair) {
                throw ValidationException::withMessages(['pair' => ['Trading pair not supported.']]);
            }

            $side = strtoupper($data['side']);
            $type = strtoupper($data['type']);
            $amount = (float) $data['amount'];
            $price = ($type === 'MARKET') ? (float) $pair->last_price : (float) ($data['price'] ?? $pair->last_price);
            $total = round($amount * $price, 2);

            // Asset lock validation
            if ($side === 'BUY') {
                $quoteAsset = Asset::where('symbol', $pair->quote_asset)->first();
                $quoteWallet = Wallet::where('user_id', $user->id)->where('asset_id', $quoteAsset->id)->first();

                if (! $quoteWallet || $quoteWallet->balance < $total) {
                    throw ValidationException::withMessages([
                        'amount' => ["Insufficient {$pair->quote_asset} balance. Required: {$total}, Available: " . ($quoteWallet ? $quoteWallet->balance : 0)],
                    ]);
                }

                $quoteWallet->balance -= $total;
                $quoteWallet->locked_balance += $total;
                $quoteWallet->save();
            } else {
                $baseAsset = Asset::where('symbol', $pair->base_asset)->first();
                $baseWallet = Wallet::where('user_id', $user->id)->where('asset_id', $baseAsset->id)->first();

                if (! $baseWallet || $baseWallet->balance < $amount) {
                    throw ValidationException::withMessages([
                        'amount' => ["Insufficient {$pair->base_asset} balance. Required: {$amount}, Available: " . ($baseWallet ? $baseWallet->balance : 0)],
                    ]);
                }

                $baseWallet->balance -= $amount;
                $baseWallet->locked_balance += $amount;
                $baseWallet->save();
            }

            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . strtoupper(bin2hex(random_bytes(6))),
                'trading_pair' => $pairSymbol,
                'side' => $side,
                'type' => $type,
                'price' => $price,
                'amount' => $amount,
                'filled_amount' => ($type === 'MARKET') ? $amount : 0,
                'total' => $total,
                'fee' => round($total * 0.001, 4),
                'status' => ($type === 'MARKET') ? 'FILLED' : 'OPEN',
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'ORDER_PLACE',
                'details' => ['order_id' => $order->id, 'pair' => $pairSymbol, 'side' => $side, 'type' => $type, 'amount' => $amount, 'price' => $price],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $order->toArray();
        });
    }

    /**
     * Cancel open order and release locked funds.
     */
    public function cancelOrder(User $user, int $orderId): array
    {
        return DB::transaction(function () use ($user, $orderId) {
            $order = Order::where('user_id', $user->id)->where('id', $orderId)->first();

            if (! $order) {
                throw ValidationException::withMessages(['order' => ['Order not found or unauthorized.']]);
            }

            if (! in_array($order->status, ['OPEN', 'PARTIALLY_FILLED'])) {
                throw ValidationException::withMessages(['order' => ['Only open or partially filled orders can be cancelled.']]);
            }

            $pair = TradingPair::where('symbol', $order->trading_pair)->first();
            $unfilled = $order->amount - $order->filled_amount;

            if ($order->side === 'BUY') {
                $quoteAsset = Asset::where('symbol', $pair->quote_asset)->first();
                $wallet = Wallet::where('user_id', $user->id)->where('asset_id', $quoteAsset->id)->first();
                $refund = round($unfilled * $order->price, 2);

                if ($wallet) {
                    $wallet->locked_balance = max(0, $wallet->locked_balance - $refund);
                    $wallet->balance += $refund;
                    $wallet->save();
                }
            } else {
                $baseAsset = Asset::where('symbol', $pair->base_asset)->first();
                $wallet = Wallet::where('user_id', $user->id)->where('asset_id', $baseAsset->id)->first();

                if ($wallet) {
                    $wallet->locked_balance = max(0, $wallet->locked_balance - $unfilled);
                    $wallet->balance += $unfilled;
                    $wallet->save();
                }
            }

            $order->status = 'CANCELLED';
            $order->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'ORDER_CANCEL',
                'details' => ['order_id' => $order->id],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $order->toArray();
        });
    }
}
