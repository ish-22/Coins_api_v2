<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TradingPair;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradeController extends Controller
{
    public function getOrders(Request $request): JsonResponse
    {
        $user = $request->user();

        $openOrders = Order::where('user_id', $user->id)
            ->whereIn('status', ['open', 'pending'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($o) {
                return [
                    'id' => $o->id,
                    'pair' => $o->pair,
                    'side' => $o->side,
                    'type' => $o->type,
                    'price' => (float) $o->price,
                    'amount' => (float) $o->amount,
                    'filled' => (float) $o->filled,
                    'total' => (float) $o->total,
                    'status' => $o->status,
                    'time' => $o->created_at->format('H:i:s'),
                    'dateTime' => $o->created_at->format('Y-m-d H:i:s'),
                    'feeEstimate' => round((float) $o->total * 0.0004, 4),
                ];
            });

        $orderHistory = Order::where('user_id', $user->id)
            ->whereIn('status', ['filled', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($o) {
                return [
                    'id' => $o->id,
                    'pair' => $o->pair,
                    'side' => $o->side,
                    'type' => $o->type,
                    'price' => (float) $o->price,
                    'avgPrice' => (float) $o->price,
                    'amount' => (float) $o->amount,
                    'executed' => (float) $o->filled,
                    'total' => (float) $o->total,
                    'status' => $o->status,
                    'time' => $o->created_at->format('M d H:i'),
                    'createdAt' => $o->created_at->format('Y-m-d H:i:s'),
                    'updatedAt' => $o->updated_at->format('Y-m-d H:i:s'),
                    'fee' => round((float) $o->total * 0.0004, 4),
                    'feeAsset' => 'USDT',
                ];
            });

        $tradeHistory = Transaction::where('user_id', $user->id)
            ->whereIn('type', ['trade_buy', 'trade_sell'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($t) {
                $side = $t->type === 'trade_buy' ? 'buy' : 'sell';
                $price = $t->amount > 0 ? round((float) $t->value_usd / (float) $t->amount, 2) : (float) $t->value_usd;
                return [
                    'id' => $t->id,
                    'orderId' => 'ord-' . substr($t->id, -4),
                    'pair' => $t->asset . '/USDT',
                    'side' => $side,
                    'execPrice' => $price,
                    'amount' => (float) $t->amount,
                    'totalValue' => (float) $t->value_usd,
                    'fee' => (float) $t->fee_usd,
                    'feeAsset' => 'USDT',
                    'role' => 'taker',
                    'dateTime' => $t->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'openOrders' => $openOrders,
            'orderHistory' => $orderHistory,
            'tradeHistory' => $tradeHistory,
        ]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pair' => ['required', 'string'],
            'side' => ['required', 'in:buy,sell'],
            'type' => ['required', 'in:limit,market,stop_limit'],
            'price' => ['required', 'numeric', 'min:0.00000001'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $user = $request->user();
        $pairSymbol = str_replace('-', '/', strtoupper($validated['pair']));
        $parts = explode('/', $pairSymbol);
        $baseAssetSymbol = $parts[0] ?? 'BTC';
        $quoteAssetSymbol = $parts[1] ?? 'USDT';

        $total = round($validated['price'] * $validated['amount'], 4);

        return DB::transaction(function () use ($user, $validated, $pairSymbol, $baseAssetSymbol, $quoteAssetSymbol, $total) {
            $quoteWallet = Wallet::where('user_id', $user->id)
                ->whereHas('asset', fn ($q) => $q->where('symbol', $quoteAssetSymbol))
                ->first();

            $baseWallet = Wallet::where('user_id', $user->id)
                ->whereHas('asset', fn ($q) => $q->where('symbol', $baseAssetSymbol))
                ->first();

            if ($validated['side'] === 'buy') {
                if (! $quoteWallet || $quoteWallet->available_balance < $total) {
                    return response()->json([
                        'message' => "Insufficient {$quoteAssetSymbol} balance to execute this trade.",
                    ], 422);
                }

                if ($validated['type'] === 'market') {
                    $quoteWallet->available_balance -= $total;
                    $quoteWallet->save();

                    if ($baseWallet) {
                        $baseWallet->available_balance += $validated['amount'];
                        $baseWallet->save();
                    }
                } else {
                    $quoteWallet->available_balance -= $total;
                    $quoteWallet->locked_balance += $total;
                    $quoteWallet->save();
                }
            } else {
                if (! $baseWallet || $baseWallet->available_balance < $validated['amount']) {
                    return response()->json([
                        'message' => "Insufficient {$baseAssetSymbol} balance to place this sell order.",
                    ], 422);
                }

                if ($validated['type'] === 'market') {
                    $baseWallet->available_balance -= $validated['amount'];
                    $baseWallet->save();

                    if ($quoteWallet) {
                        $quoteWallet->available_balance += $total;
                        $quoteWallet->save();
                    }
                } else {
                    $baseWallet->available_balance -= $validated['amount'];
                    $baseWallet->locked_balance += $validated['amount'];
                    $baseWallet->save();
                }
            }

            $orderId = 'ord-' . rand(1000, 9999);
            $isFilled = ($validated['type'] === 'market');

            $order = Order::create([
                'id' => $orderId,
                'user_id' => $user->id,
                'pair' => $pairSymbol,
                'side' => $validated['side'],
                'type' => $validated['type'],
                'price' => $validated['price'],
                'amount' => $validated['amount'],
                'filled' => $isFilled ? $validated['amount'] : 0,
                'total' => $total,
                'status' => $isFilled ? 'filled' : 'open',
            ]);

            if ($isFilled) {
                Transaction::create([
                    'id' => 'tx-' . uniqid(),
                    'user_id' => $user->id,
                    'type' => $validated['side'] === 'buy' ? 'trade_buy' : 'trade_sell',
                    'asset' => $baseAssetSymbol,
                    'amount' => $validated['amount'],
                    'value_usd' => $total,
                    'fee_usd' => round($total * 0.0004, 2),
                    'status' => 'completed',
                    'notes' => "Market order execution for {$validated['amount']} {$baseAssetSymbol}",
                ]);
            }

            return response()->json([
                'order' => [
                    'id' => $order->id,
                    'pair' => $order->pair,
                    'side' => $order->side,
                    'type' => $order->type,
                    'price' => (float) $order->price,
                    'amount' => (float) $order->amount,
                    'filled' => (float) $order->filled,
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'time' => $order->created_at->format('H:i:s'),
                ],
                'message' => $isFilled ? 'Market order filled instantly.' : 'Limit order placed in the order book.',
            ], 201);
        });
    }

    public function cancelOrder(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($user, $id) {
            $order = Order::where('id', $id)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->firstOrFail();

            $parts = explode('/', $order->pair);
            $baseAssetSymbol = $parts[0] ?? 'BTC';
            $quoteAssetSymbol = $parts[1] ?? 'USDT';

            if ($order->side === 'buy') {
                $quoteWallet = Wallet::where('user_id', $user->id)
                    ->whereHas('asset', fn ($q) => $q->where('symbol', $quoteAssetSymbol))
                    ->first();
                if ($quoteWallet) {
                    $refund = $order->total;
                    $quoteWallet->locked_balance = max(0, $quoteWallet->locked_balance - $refund);
                    $quoteWallet->available_balance += $refund;
                    $quoteWallet->save();
                }
            } else {
                $baseWallet = Wallet::where('user_id', $user->id)
                    ->whereHas('asset', fn ($q) => $q->where('symbol', $baseAssetSymbol))
                    ->first();
                if ($baseWallet) {
                    $refund = $order->amount;
                    $baseWallet->locked_balance = max(0, $baseWallet->locked_balance - $refund);
                    $baseWallet->available_balance += $refund;
                    $baseWallet->save();
                }
            }

            $order->status = 'cancelled';
            $order->save();

            return response()->json([
                'order' => $order,
                'message' => "Order {$order->id} successfully cancelled. Funds returned to your available balance.",
            ]);
        });
    }
}
