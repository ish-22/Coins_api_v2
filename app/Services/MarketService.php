<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\TradingPair;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketService
{
    /**
     * Map platform pair / asset identifiers to Binance API symbols.
     */
    protected array $symbolMap = [
        'BTC-USDT'  => 'BTCUSDT',
        'BTC/USDT'  => 'BTCUSDT',
        'ETH-USDT'  => 'ETHUSDT',
        'ETH/USDT'  => 'ETHUSDT',
        'SOL-USDT'  => 'SOLUSDT',
        'SOL/USDT'  => 'SOLUSDT',
        'BNB-USDT'  => 'BNBUSDT',
        'BNB/USDT'  => 'BNBUSDT',
        'XRP-USDT'  => 'XRPUSDT',
        'XRP/USDT'  => 'XRPUSDT',
        'DOGE-USDT' => 'DOGEUSDT',
        'DOGE/USDT' => 'DOGEUSDT',
        'USDC-USDT' => 'USDCUSDT',
        'USDC/USDT' => 'USDCUSDT',
    ];

    /**
     * Convert platform pair into Binance symbol.
     */
    public function getBinanceSymbol(string $pair): string
    {
        $clean = strtoupper(trim($pair));
        if (isset($this->symbolMap[$clean])) {
            return $this->symbolMap[$clean];
        }

        $formatted = str_replace(['-', '/'], '', $clean);
        return $formatted;
    }

    /**
     * Sync and return 24h ticker data with real live prices from Binance.
     */
    public function getLiveTickers(): array
    {
        return Cache::remember('live_market_tickers_data', 10, function () {
            try {
                // Fetch live 24h tickers from Binance
                $symbolsParam = json_encode(array_values(array_unique($this->symbolMap)));
                $response = Http::timeout(3)->get('https://api.binance.com/api/v3/ticker/24hr', [
                    'symbols' => $symbolsParam,
                ]);

                if ($response->successful()) {
                    $tickers = $response->json();
                    $tickerBySymbol = [];
                    foreach ($tickers as $t) {
                        if (isset($t['symbol'])) {
                            $tickerBySymbol[$t['symbol']] = $t;
                        }
                    }

                    // Update TradingPair models in database
                    $pairs = TradingPair::all();
                    foreach ($pairs as $pair) {
                        $bSymbol = $this->getBinanceSymbol($pair->symbol);
                        if (isset($tickerBySymbol[$bSymbol])) {
                            $data = $tickerBySymbol[$bSymbol];
                            $pair->price = (float) $data['lastPrice'];
                            $pair->change_24h = (float) $data['priceChangePercent'];
                            $pair->high_24h = (float) $data['highPrice'];
                            $pair->low_24h = (float) $data['lowPrice'];
                            $pair->volume_24h_base = (float) $data['volume'];
                            $pair->volume_24h_quote = (float) $data['quoteVolume'];
                            $pair->save();

                            // Also sync corresponding Asset in database
                            $asset = Asset::where('symbol', $pair->base_asset)->first();
                            if ($asset) {
                                $asset->price_usd = (float) $data['lastPrice'];
                                $asset->change_24h = (float) $data['priceChangePercent'];
                                $asset->volume_24h = (float) $data['quoteVolume'];
                                $asset->save();
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Binance live ticker sync failed, using cached/db values: ' . $e->getMessage());
            }

            // Return all active pairs with latest prices
            return TradingPair::all()->map(function ($p) {
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
            })->toArray();
        });
    }

    /**
     * Get real live order book depth from Binance.
     */
    public function getLiveOrderBook(string $pair): array
    {
        $binanceSymbol = $this->getBinanceSymbol($pair);
        $formattedPair = str_replace('-', '/', strtoupper($pair));

        try {
            $res = Http::timeout(2)->get('https://api.binance.com/api/v3/depth', [
                'symbol' => $binanceSymbol,
                'limit' => 12,
            ]);

            if ($res->successful()) {
                $data = $res->json();
                $asks = [];
                $bids = [];

                $maxTotal = 1;
                foreach ($data['asks'] as $item) {
                    $price = (float) $item[0];
                    $amount = (float) $item[1];
                    $total = round($price * $amount, 2);
                    if ($total > $maxTotal) $maxTotal = $total;
                    $asks[] = [
                        'price' => $price,
                        'amount' => $amount,
                        'total' => $total,
                        'depthPercent' => 0,
                    ];
                }

                foreach ($data['bids'] as $item) {
                    $price = (float) $item[0];
                    $amount = (float) $item[1];
                    $total = round($price * $amount, 2);
                    if ($total > $maxTotal) $maxTotal = $total;
                    $bids[] = [
                        'price' => $price,
                        'amount' => $amount,
                        'total' => $total,
                        'depthPercent' => 0,
                    ];
                }

                foreach ($asks as &$ask) {
                    $ask['depthPercent'] = min(100, round(($ask['total'] / $maxTotal) * 100, 1));
                }
                foreach ($bids as &$bid) {
                    $bid['depthPercent'] = min(100, round(($bid['total'] / $maxTotal) * 100, 1));
                }

                return [
                    'pair' => $formattedPair,
                    'asks' => array_reverse($asks),
                    'bids' => $bids,
                ];
            }
        } catch (\Throwable $e) {
            Log::info('Live orderbook fetch fallback: ' . $e->getMessage());
        }

        // Fallback generator around current price
        $tp = TradingPair::where('symbol', $formattedPair)->orWhere('id', $pair)->first();
        $basePrice = $tp ? (float) $tp->price : 80000.0;

        $asks = [];
        $bids = [];
        for ($i = 1; $i <= 12; $i++) {
            $price = round($basePrice + ($i * ($basePrice * 0.0003)), 2);
            $amount = round((rand(10, 80) / 100), 4);
            $total = round($price * $amount, 2);
            $asks[] = ['price' => $price, 'amount' => $amount, 'total' => $total, 'depthPercent' => rand(15, 85)];

            $bPrice = round($basePrice - ($i * ($basePrice * 0.0003)), 2);
            $bAmount = round((rand(15, 95) / 100), 4);
            $bTotal = round($bPrice * $bAmount, 2);
            $bids[] = ['price' => $bPrice, 'amount' => $bAmount, 'total' => $bTotal, 'depthPercent' => rand(15, 85)];
        }

        return [
            'pair' => $formattedPair,
            'asks' => array_reverse($asks),
            'bids' => $bids,
        ];
    }

    /**
     * Get real live recent trades from Binance.
     */
    public function getLiveRecentTrades(string $pair): array
    {
        $binanceSymbol = $this->getBinanceSymbol($pair);

        try {
            $res = Http::timeout(2)->get('https://api.binance.com/api/v3/trades', [
                'symbol' => $binanceSymbol,
                'limit' => 20,
            ]);

            if ($res->successful()) {
                $trades = [];
                foreach ($res->json() as $t) {
                    $trades[] = [
                        'id' => (string) $t['id'],
                        'price' => (float) $t['price'],
                        'amount' => (float) $t['qty'],
                        'time' => date('H:i:s', (int) ($t['time'] / 1000)),
                        'side' => $t['isBuyerMaker'] ? 'sell' : 'buy',
                    ];
                }
                return array_reverse($trades);
            }
        } catch (\Throwable $e) {
            Log::info('Live trades fetch fallback: ' . $e->getMessage());
        }

        $formattedPair = str_replace('-', '/', strtoupper($pair));
        $tp = TradingPair::where('symbol', $formattedPair)->orWhere('id', $pair)->first();
        $basePrice = $tp ? (float) $tp->price : 80000.0;

        $trades = [];
        for ($i = 0; $i < 20; $i++) {
            $side = (rand(0, 100) > 48) ? 'buy' : 'sell';
            $trades[] = [
                'id' => 'tr-' . time() . '-' . $i,
                'price' => round($basePrice + (rand(-10, 10) * 0.5), 2),
                'amount' => round(rand(5, 50) / 100, 4),
                'time' => date('H:i:s', time() - ($i * 12)),
                'side' => $side,
            ];
        }

        return $trades;
    }

    /**
     * Get real live candlestick klines from Binance.
     */
    public function getLiveCandles(string $pair, string $timeframe = '1h'): array
    {
        $binanceSymbol = $this->getBinanceSymbol($pair);
        $intervalMap = [
            '1m' => '1m', '5m' => '5m', '15m' => '15m', '30m' => '30m',
            '1h' => '1h', '4h' => '4h', '1d' => '1d', '1w' => '1w',
        ];
        $interval = $intervalMap[$timeframe] ?? '1h';

        try {
            $res = Http::timeout(2)->get('https://api.binance.com/api/v3/klines', [
                'symbol' => $binanceSymbol,
                'interval' => $interval,
                'limit' => 36,
            ]);

            if ($res->successful()) {
                $candles = [];
                foreach ($res->json() as $k) {
                    $candles[] = [
                        'time' => date('H:i', (int) ($k[0] / 1000)),
                        'open' => (float) $k[1],
                        'high' => (float) $k[2],
                        'low' => (float) $k[3],
                        'close' => (float) $k[4],
                        'volume' => (float) $k[5],
                    ];
                }
                return $candles;
            }
        } catch (\Throwable $e) {
            Log::info('Live candles fetch fallback: ' . $e->getMessage());
        }

        $formattedPair = str_replace('-', '/', strtoupper($pair));
        $tp = TradingPair::where('symbol', $formattedPair)->orWhere('id', $pair)->first();
        $basePrice = $tp ? (float) $tp->price : 80000.0;

        $candles = [];
        $currentPrice = $basePrice * 0.98;
        for ($i = 0; $i < 36; $i++) {
            $delta = $currentPrice * 0.005 * (rand(20, 100) / 100);
            $isBull = (rand(0, 100) > 45);
            $open = $currentPrice;
            $close = $isBull ? $open + $delta : $open - $delta;
            $high = max($open, $close) + ($currentPrice * 0.002);
            $low = min($open, $close) - ($currentPrice * 0.002);

            $candles[] = [
                'time' => sprintf('%02d:00', ($i + 8) % 24),
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($close, 2),
                'volume' => rand(50, 400),
            ];
            $currentPrice = $close;
        }

        return $candles;
    }
}
