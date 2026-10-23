<?php

namespace App\Services;

use App\Models\TradingPair;
use App\Models\Order;

class MarketService
{
    /**
     * Get 24h ticker overview for active pairs.
     */
    public function getTickers(): array
    {
        $pairs = TradingPair::where('is_active', true)->get();

        return $pairs->map(function ($pair) {
            return [
                'symbol' => $pair->symbol,
                'base' => $pair->base_asset,
                'quote' => $pair->quote_asset,
                'lastPrice' => (float) $pair->last_price,
                'priceChangePercent' => (float) $pair->change_24h,
                'high24h' => (float) $pair->high_24h,
                'low24h' => (float) $pair->low_24h,
                'volume24h' => (float) $pair->volume_24h,
                'quoteVolume24h' => (float) ($pair->volume_24h * $pair->last_price),
            ];
        })->toArray();
    }

    /**
     * Get order book depth for pair.
     */
    public function getOrderBook(string $symbol): array
    {
        $symbol = strtoupper(str_replace('-', '/', $symbol));
        $pair = TradingPair::where('symbol', $symbol)->first();

        $last = $pair ? (float) $pair->last_price : 67250.0;

        $bids = [];
        $asks = [];

        for ($i = 1; $i <= 15; $i++) {
            $bidPrice = round($last * (1 - ($i * 0.0008)), 2);
            $bidQty = round(0.15 + (sin($i) * 0.1) + ($i * 0.08), 4);
            $bids[] = [$bidPrice, $bidQty, round($bidPrice * $bidQty, 2)];

            $askPrice = round($last * (1 + ($i * 0.0008)), 2);
            $askQty = round(0.12 + (cos($i) * 0.09) + ($i * 0.07), 4);
            $asks[] = [$askPrice, $askQty, round($askPrice * $askQty, 2)];
        }

        return [
            'symbol' => $symbol,
            'timestamp' => now()->timestamp * 1000,
            'bids' => $bids,
            'asks' => $asks,
        ];
    }

    /**
     * Get recent public trades for pair.
     */
    public function getRecentTrades(string $symbol): array
    {
        $symbol = strtoupper(str_replace('-', '/', $symbol));
        $pair = TradingPair::where('symbol', $symbol)->first();
        $basePrice = $pair ? (float) $pair->last_price : 67250.0;

        $trades = [];
        $now = now()->timestamp * 1000;

        for ($i = 0; $i < 20; $i++) {
            $isBuy = ($i % 2 === 0);
            $delta = ($isBuy ? 1 : -1) * ($i * 1.5);
            $trades[] = [
                'id' => 'trd-' . (100000 + $i),
                'price' => round($basePrice + $delta, 2),
                'amount' => round(0.05 + ($i * 0.04), 4),
                'side' => $isBuy ? 'BUY' : 'SELL',
                'time' => $now - ($i * 14000),
            ];
        }

        return $trades;
    }

    /**
     * Get OHLCV candlestick series.
     */
    public function getCandles(string $symbol, string $interval = '1h'): array
    {
        $symbol = strtoupper(str_replace('-', '/', $symbol));
        $pair = TradingPair::where('symbol', $symbol)->first();
        $curr = $pair ? (float) $pair->last_price : 67250.0;

        $candles = [];
        $points = 48;
        $now = now()->timestamp * 1000;
        $step = 3600 * 1000;

        $price = $curr * 0.96;
        for ($i = $points; $i >= 0; $i--) {
            $open = $price;
            $noise = (sin($i * 0.4) * 0.008) + ((($i % 3) - 1) * 0.004);
            $close = round($open * (1 + $noise), 2);
            $high = round(max($open, $close) * 1.004, 2);
            $low = round(min($open, $close) * 0.996, 2);
            $vol = round(120 + abs(sin($i)) * 340, 2);

            $candles[] = [
                'time' => $now - ($i * $step),
                'open' => $open,
                'high' => $high,
                'low' => $low,
                'close' => $close,
                'volume' => $vol,
            ];
            $price = $close;
        }

        return $candles;
    }
}
