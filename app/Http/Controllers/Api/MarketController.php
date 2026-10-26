<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketController extends Controller
{
    protected MarketService $marketService;

    public function __construct(MarketService $marketService)
    {
        $this->marketService = $marketService;
    }

    /**
     * Get 24h ticker feed with real live market prices.
     */
    public function getTickers(Request $request): JsonResponse
    {
        $pairs = $this->marketService->getLiveTickers();

        return response()->json([
            'pairs' => $pairs,
        ]);
    }

    /**
     * Get live order book depth.
     */
    public function getOrderBook(string $pair): JsonResponse
    {
        $data = $this->marketService->getLiveOrderBook($pair);

        return response()->json($data);
    }

    /**
     * Get live recent executed trades.
     */
    public function getRecentTrades(string $pair): JsonResponse
    {
        $trades = $this->marketService->getLiveRecentTrades($pair);

        return response()->json([
            'trades' => $trades,
        ]);
    }

    /**
     * Get real live candlestick series.
     */
    public function getCandles(Request $request, string $pair): JsonResponse
    {
        $timeframe = $request->query('timeframe', '1h');
        $candles = $this->marketService->getLiveCandles($pair, $timeframe);

        return response()->json([
            'candles' => $candles,
        ]);
    }
}
