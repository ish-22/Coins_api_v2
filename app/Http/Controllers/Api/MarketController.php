<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TradingPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    public function getTickers(Request $request): JsonResponse
    {
        $pairs = TradingPair::all()->map(function ($p) {
            return [
                'id' => $p->id,
                'symbol' => $p->symbol,
                'baseAsset' => $p->base_asset,
                'quoteAsset' => $p->quote_asset,
                'price' => (float) $p->price,
                'change24h' => (float) $p->change_24h,
                'high24h' => (float) $p->high_24h,
                'low24h' => (float) $p->low_24h,
                'volume24hBtc' => (float) $p->volume_24h_base,
                'volume24hUsdt' => (float) $p->volume_24h_quote,
                'priceDecimals' => (int) $p->price_decimals,
                'amountDecimals' => (int) $p->amount_decimals,
                'minAmount' => (float) $p->min_amount,
                'makerFee' => (float) $p->maker_fee,
                'takerFee' => (float) $p->taker_fee,
            ];
        });

        return response()->json([
            'pairs' => $pairs,
        ]);
    }

    public function getOrderBook(string $pair): JsonResponse
    {
        $formattedPair = str_replace('-', '/', strtoupper($pair));
        $tradingPair = TradingPair::where('symbol', $formattedPair)->orWhere('id', $pair)->first();
        $basePrice = $tradingPair ? (float) $tradingPair->price : 68450.25;

        $asks = [];
        $bids = [];

        // 12 Asks
        for ($i = 1; $i <= 12; $i++) {
            $price = round($basePrice + $i * 2.5, 2);
            $amount = round((rand(10, 80) / 100), 4);
            $total = round($price * $amount, 2);
            $asks[] = [
                'price' => $price,
                'amount' => $amount,
                'total' => $total,
                'depthPercent' => min(100, round(($total / ($basePrice * 4)) * 100, 1)),
            ];
        }

        // 12 Bids
        for ($i = 1; $i <= 12; $i++) {
            $price = round($basePrice - $i * 2.5, 2);
            $amount = round((rand(15, 95) / 100), 4);
            $total = round($price * $amount, 2);
            $bids[] = [
                'price' => $price,
                'amount' => $amount,
                'total' => $total,
                'depthPercent' => min(100, round(($total / ($basePrice * 4)) * 100, 1)),
            ];
        }

        return response()->json([
            'pair' => $tradingPair ? $tradingPair->symbol : $formattedPair,
            'asks' => array_reverse($asks),
            'bids' => $bids,
        ]);
    }

    public function getRecentTrades(string $pair): JsonResponse
    {
        $formattedPair = str_replace('-', '/', strtoupper($pair));
        $tradingPair = TradingPair::where('symbol', $formattedPair)->orWhere('id', $pair)->first();
        $basePrice = $tradingPair ? (float) $tradingPair->price : 68450.25;

        $trades = [];
        for ($i = 0; $i < 20; $i++) {
            $side = (rand(0, 100) > 48) ? 'buy' : 'sell';
            $delta = (rand(-60, 60) / 10);
            $price = round($basePrice + $delta, 2);
            $amount = round(rand(2, 65) / 100, 4);
            $time = date('H:i:s', time() - ($i * 3));

            $trades[] = [
                'id' => 'tr-' . time() . '-' . $i,
                'price' => $price,
                'amount' => $amount,
                'time' => $time,
                'side' => $side,
            ];
        }

        return response()->json([
            'trades' => $trades,
        ]);
    }

    public function getCandles(Request $request, string $pair): JsonResponse
    {
        $timeframe = $request->query('timeframe', '1h');
        $formattedPair = str_replace('-', '/', strtoupper($pair));
        $tradingPair = TradingPair::where('symbol', $formattedPair)->orWhere('id', $pair)->first();
        $basePrice = $tradingPair ? (float) $tradingPair->price : 68450.25;

        $count = 36;
        $candles = [];
        $currentPrice = $basePrice * 0.96;
        $volatility = 0.008;

        for ($i = 0; $i < $count; $i++) {
            $isBull = (rand(0, 100) > 45);
            $delta = $currentPrice * $volatility * (rand(20, 100) / 100);
            $open = $currentPrice;
            $close = $isBull ? $open + $delta : $open - $delta;
            $high = max($open, $close) + ($currentPrice * $volatility * (rand(10, 50) / 100));
            $low = min($open, $close) - ($currentPrice * $volatility * (rand(10, 50) / 100));
            $volume = rand(30, 210);

            $hour = (8 + (int) floor($i / 2)) % 24;
            $min = ($i % 2) * 30;
            $timeLabel = sprintf('%02d:%02d', $hour, $min);

            $candles[] = [
                'time' => $timeLabel,
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
                'volume' => $volume,
            ];

            $currentPrice = $close;
        }

        $candles[$count - 1]['close'] = $basePrice;

        return response()->json([
            'candles' => $candles,
        ]);
    }
}
