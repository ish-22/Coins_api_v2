<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\KycController;
use App\Http\Controllers\Api\MarketController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TradeController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CoinPlus REST API Routes
|--------------------------------------------------------------------------
*/

// Platform Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'operational',
        'timestamp' => now()->toIso8601String(),
        'platform' => 'CoinPlus Institutional Exchange',
        'database' => 'connected',
    ]);
});

// ── Public Authentication ──
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/admin-login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
});

// ── Public Market Feeds ──
Route::prefix('markets')->group(function () {
    Route::get('/tickers', [MarketController::class, 'getTickers']);
    Route::get('/{pair}/orderbook', [MarketController::class, 'getOrderBook']);
    Route::get('/{pair}/trades', [MarketController::class, 'getRecentTrades']);
    Route::get('/{pair}/candles', [MarketController::class, 'getCandles']);
});

// ── Public Informational & Inquiries ──
Route::get('/announcements', [AdminController::class, 'getAnnouncements']);
Route::post('/support/contact', [SupportController::class, 'submitContact'])->middleware('throttle:5,1');
Route::get('/wallet/deposit/address', [WalletController::class, 'getDepositAddress']);

// ── Protected Customer Routes (Sanctum Authenticated) ──
Route::middleware('auth:sanctum')->group(function () {
    // Auth Session
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Account & Preferences
    Route::prefix('account')->group(function () {
        Route::get('/profile', [AccountController::class, 'getProfile']);
        Route::put('/profile', [AccountController::class, 'updateProfile']);
        Route::post('/change-password', [AccountController::class, 'changePassword']);
        Route::post('/2fa/toggle', [AccountController::class, 'toggle2FA']);
        Route::get('/sessions', [AccountController::class, 'getSessions']);
    });

    // KYC Verification
    Route::prefix('kyc')->group(function () {
        Route::get('/status', [KycController::class, 'getStatus']);
        Route::post('/tier1', [KycController::class, 'submitTier1']);
        Route::post('/tier2', [KycController::class, 'submitTier2']);
    });

    // Spot Trading Engine
    Route::prefix('trade')->group(function () {
        Route::get('/orders', [TradeController::class, 'getOrders']);
        Route::post('/orders', [TradeController::class, 'placeOrder']);
        Route::post('/orders/{id}/cancel', [TradeController::class, 'cancelOrder']);
    });

    // Wallet, Treasury & Balances
    Route::prefix('wallet')->group(function () {
        Route::get('/summary', [WalletController::class, 'getSummary']);
        Route::get('/assets', [WalletController::class, 'getAssets']);
        Route::get('/transactions', [WalletController::class, 'getTransactions']);
        Route::post('/deposit/simulate', [WalletController::class, 'simulateDeposit']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/watchlist', [WalletController::class, 'getWatchlist']);
        Route::post('/watchlist', [WalletController::class, 'toggleWatchlist']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'list']);
        Route::put('/{id}/read', [NotificationController::class, 'markRead']);
        Route::put('/{id}/toggle-read', [NotificationController::class, 'toggleRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllRead']);
        Route::delete('/{id}', [NotificationController::class, 'delete']);
        Route::delete('/clear/read', [NotificationController::class, 'deleteRead']);
    });

    // Customer Support Desk
    Route::prefix('support')->group(function () {
        Route::get('/tickets', [SupportController::class, 'listTickets']);
        Route::post('/tickets', [SupportController::class, 'createTicket']);
        Route::post('/tickets/{id}/reply', [SupportController::class, 'replyTicket']);
    });
});

// ── Administrative Console Routes (Admin Role Only) ──
Route::middleware(['auth:sanctum', AdminMiddleware::class])->prefix('admin')->group(function () {
    Route::get('/metrics', [AdminController::class, 'getMetrics']);
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::put('/users/{id}/status', [AdminController::class, 'updateUserStatus']);
    Route::get('/kyc', [AdminController::class, 'getKycCases']);
    Route::post('/kyc/{id}/review', [AdminController::class, 'reviewKycCase']);
    Route::get('/deposits', [AdminController::class, 'getDeposits']);
    Route::get('/withdrawals', [AdminController::class, 'getWithdrawals']);
    Route::get('/orders', [AdminController::class, 'getOrders']);
    Route::post('/announcements', [AdminController::class, 'createAnnouncement']);
    Route::delete('/announcements/{id}', [AdminController::class, 'deleteAnnouncement']);
    Route::get('/assets', [AdminController::class, 'getAssets']);
    Route::put('/assets/{id}', [AdminController::class, 'updateAsset']);
    Route::get('/support-tickets', [AdminController::class, 'getSupportTickets']);
    Route::post('/support-tickets/{id}/reply', [AdminController::class, 'replySupportTicket']);
    Route::get('/audit-logs', [AdminController::class, 'getAuditLogs']);
    Route::get('/settings', [AdminController::class, 'getSettings']);
    Route::put('/settings', [AdminController::class, 'updateSettings']);
});
