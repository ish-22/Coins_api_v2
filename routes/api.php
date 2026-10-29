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

// Platform Health & Infrastructure Telemetry Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'operational',
        'timestamp' => now()->toIso8601String(),
        'platform' => 'CoinPlus Institutional Exchange',
        'database' => 'connected',
    ]);
});

Route::get('/system-status', function () {
    $dbConnected = true;
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable $e) {
        $dbConnected = false;
    }

    $services = \App\Models\SystemService::orderBy('sort_order', 'asc')->get()->map(function ($s) {
        return [
            'id' => $s->service_id,
            'name' => $s->name,
            'category' => $s->category,
            'status' => $s->status,
            'description' => $s->description,
            'simulatedUptime' => $s->uptime,
            'simulatedLatency' => $s->latency,
            'dailyUptimeHistory' => $s->daily_uptime_history ?: array_fill(0, 30, 'operational'),
        ];
    });

    $incidents = \App\Models\Incident::orderBy('created_at', 'desc')->get()->map(function ($inc) {
        return [
            'id' => $inc->incident_id,
            'title' => $inc->title,
            'date' => $inc->date_display,
            'severity' => $inc->severity,
            'status' => $inc->status,
            'affectedServices' => $inc->affected_services ?: [],
            'description' => $inc->description,
            'resolutionSummary' => $inc->resolution_summary,
            'updates' => $inc->updates ?: [],
        ];
    });

    $activeAnnouncements = \App\Models\Announcement::where('type', 'maintenance')
        ->where('status', 'active')
        ->get();

    return response()->json([
        'overallStatus' => $dbConnected ? 'operational' : 'degraded',
        'timestamp' => now()->toIso8601String(),
        'services' => $services,
        'incidents' => $incidents,
        'announcements' => $activeAnnouncements,
    ]);
});

Route::get('/fees', function () {
    $assets = \App\Models\Asset::all()->map(function ($a) {
        return [
            'id' => strtolower($a->symbol),
            'name' => $a->name,
            'symbol' => $a->symbol,
            'network' => $a->network ?? 'Mainnet',
            'depositFee' => '0.00 % (Free)',
            'minDeposit' => '0.0001 ' . $a->symbol,
            'withdrawalFee' => $a->withdrawal_fee . ' ' . $a->symbol,
            'minWithdrawal' => (floatval($a->withdrawal_fee) * 2) . ' ' . $a->symbol,
            'status' => 'Active',
        ];
    });

    return response()->json([
        'makerTakerTiers' => [
            ['tier' => 'VIP 0', 'volume30d' => '< $10,000', 'maker' => '0.100%', 'taker' => '0.100%'],
            ['tier' => 'VIP 1', 'volume30d' => '≥ $10,000', 'maker' => '0.080%', 'taker' => '0.090%'],
            ['tier' => 'VIP 2', 'volume30d' => '≥ $50,000', 'maker' => '0.060%', 'taker' => '0.080%'],
            ['tier' => 'VIP 3', 'volume30d' => '≥ $250,000', 'maker' => '0.040%', 'taker' => '0.060%'],
            ['tier' => 'VIP 4', 'volume30d' => '≥ $1,000,000', 'maker' => '0.020%', 'taker' => '0.040%'],
            ['tier' => 'Institutional', 'volume30d' => '≥ $10,000,000', 'maker' => '0.000%', 'taker' => '0.020%'],
        ],
        'assets' => $assets,
        'limits' => [
            ['tier' => 'Tier 0 (Unverified)', 'dailyLimit' => '$0 USD', 'monthlyLimit' => '$0 USD', 'fiatSupport' => 'Disabled'],
            ['tier' => 'Tier 1 (Basic Identity)', 'dailyLimit' => '$10,000 USD', 'monthlyLimit' => '$100,000 USD', 'fiatSupport' => 'Cryptocurrency Only'],
            ['tier' => 'Tier 2 (Full KYC Verified)', 'dailyLimit' => '$500,000 USD', 'monthlyLimit' => '$5,000,000 USD', 'fiatSupport' => 'Full Bank Wire / SEPA'],
            ['tier' => 'Tier 3 (Institutional Corporate)', 'dailyLimit' => 'Unlimited', 'monthlyLimit' => 'Unlimited', 'fiatSupport' => 'Dedicated Prime Brokerage'],
        ],
    ]);
});

// ── Public Authentication ──
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/admin-login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
});


// ── Public Market Feeds ──
Route::prefix('markets')->group(function () {
    Route::get('/tickers', [MarketController::class, 'getTickers']);
    Route::get('/{pair}/orderbook', [MarketController::class, 'getOrderBook']);
    Route::get('/{pair}/trades', [MarketController::class, 'getRecentTrades']);
    Route::get('/{pair}/candles', [MarketController::class, 'getCandles']);
});

// ── Public Informational & Inquiries ──
Route::get('/about', function () {
    $aboutSetting = \App\Models\SystemSetting::where('key', 'about_content')->first();
    if ($aboutSetting && !empty($aboutSetting->value)) {
        return response()->json($aboutSetting->value);
    }

    return response()->json([
        'brand_values' => [
            'platformName' => 'CoinPlus',
            'tagline' => 'Precision Cryptocurrency Exchange Interface',
            'conceptDefinition' => 'CoinPlus is a conceptual next-generation cryptocurrency trading platform engineered to unite institutional-grade market depth, sub-millisecond visual latency, and transparent fee architecture in a modern black-and-gold design system.',
            'mission' => 'To establish a cleaner, more reliable standard for digital asset trading interfaces — removing visual clutter, eliminating hidden costs, and presenting real-time market data with utmost visual clarity.',
            'vision' => 'A financial ecosystem where traders at every experience level enjoy equal access to professional order books, transparent liquidity routing, and frictionless asset management without cognitive overload.',
        ],
        'features' => [],
        'principles' => [],
        'faqs' => [],
    ]);
});
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

    // User CRUD & Management
    Route::get('/users', [AdminController::class, 'getUsers']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
    Route::put('/users/{id}/status', [AdminController::class, 'updateUserStatus']);

    // KYC Management
    Route::get('/kyc', [AdminController::class, 'getKycCases']);
    Route::post('/kyc/{id}/review', [AdminController::class, 'reviewKycCase']);

    // Financial Operations (Deposits & Withdrawals)
    Route::get('/deposits', [AdminController::class, 'getDeposits']);
    Route::get('/withdrawals', [AdminController::class, 'getWithdrawals']);
    Route::post('/withdrawals/{id}/review', [AdminController::class, 'reviewWithdrawal']);

    // Orders Management
    Route::get('/orders', [AdminController::class, 'getOrders']);
    Route::delete('/orders/{id}', [AdminController::class, 'cancelOrderAdmin']);

    // Announcements CRUD
    Route::post('/announcements', [AdminController::class, 'createAnnouncement']);
    Route::put('/announcements/{id}', [AdminController::class, 'updateAnnouncement']);
    Route::delete('/announcements/{id}', [AdminController::class, 'deleteAnnouncement']);

    // Assets CRUD
    Route::get('/assets', [AdminController::class, 'getAssets']);
    Route::post('/assets', [AdminController::class, 'createAsset']);
    Route::put('/assets/{id}', [AdminController::class, 'updateAsset']);
    Route::delete('/assets/{id}', [AdminController::class, 'deleteAsset']);

    // Support Desk
    Route::get('/support-tickets', [AdminController::class, 'getSupportTickets']);
    Route::post('/support-tickets/{id}/reply', [AdminController::class, 'replySupportTicket']);
    Route::put('/support-tickets/{id}/status', [AdminController::class, 'updateTicketStatus']);

    // Audit & System Settings
    Route::get('/audit-logs', [AdminController::class, 'getAuditLogs']);
    Route::get('/settings', [AdminController::class, 'getSettings']);
    Route::put('/settings', [AdminController::class, 'updateSettings']);
});
