<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\KycVerification;
use App\Models\Notification;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TradingPair;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Watchlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoinPlusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Super Admin Operator
        $admin = User::updateOrCreate(
            ['email' => 'operator@coinplus.io'],
            [
                'uid' => 'UID-ADM-001',
                'name' => 'System Operator',
                'display_name' => 'CoinPlus Operator',
                'full_name' => 'CoinPlus Super Administrator',
                'password' => Hash::make('SuperAdmin2026!#'),
                'phone' => '+1 555-010-0001',
                'country' => 'United States',
                'country_code' => 'US',
                'role' => 'admin',
                'status' => 'active',
                'kyc_tier' => 'Tier 2',
                'vip_level' => 'VIP 9',
                'avatar_initials' => 'OP',
                'language' => 'English (US)',
                'time_zone' => 'UTC+00:00 (UTC / London)',
                'display_currency' => 'USD',
                'date_format' => 'YYYY-MM-DD',
                'default_dashboard_view' => 'pro_terminal',
                'theme_preference' => 'black_gold',
                'two_factor_enabled' => true,
                'passkeys_count' => 1,
            ]
        );

        // 2. Create Demo Trader (matching Alex Vance UID-884920)
        $trader = User::updateOrCreate(
            ['email' => 'demo.trader@coinplus.io'],
            [
                'uid' => 'UID-884920',
                'name' => 'Alex Vance',
                'display_name' => 'Alex Vance',
                'full_name' => 'Alexander Vance',
                'password' => Hash::make('CoinPlus2026!#'),
                'phone' => '+1 555-019-8921',
                'country' => 'United States',
                'country_code' => 'US',
                'role' => 'user',
                'status' => 'active',
                'kyc_tier' => 'Tier 2 (Verified)',
                'vip_level' => 'VIP 1',
                'avatar_initials' => 'AV',
                'language' => 'English (US)',
                'time_zone' => 'UTC-05:00 (Eastern Time)',
                'display_currency' => 'USD',
                'date_format' => 'YYYY-MM-DD',
                'default_dashboard_view' => 'pro_terminal',
                'theme_preference' => 'black_gold',
                'two_factor_enabled' => true,
                'passkeys_count' => 1,
                'notifications_config' => [
                    'tradeFills' => true,
                    'securityAlerts' => true,
                    'priceAlerts' => true,
                    'marketingNews' => false,
                ],
            ]
        );

        // 3. Seed 8 Core Assets
        $assetsData = [
            [
                'id' => 'bitcoin',
                'symbol' => 'BTC',
                'name' => 'Bitcoin',
                'type' => 'crypto',
                'color' => '#F0B93A',
                'icon_bg' => '#F0B93A',
                'networks' => ['Bitcoin Native (SegWit)', 'Lightning Network'],
                'price_usd' => 68450.25,
                'change_24h' => 3.42,
                'volume_24h' => 1948500000,
                'min_deposit' => 0.0001,
                'min_withdrawal' => 0.001,
                'withdrawal_fee' => 0.0002,
            ],
            [
                'id' => 'ethereum',
                'symbol' => 'ETH',
                'name' => 'Ethereum',
                'type' => 'crypto',
                'color' => '#38BDF8',
                'icon_bg' => '#38BDF8',
                'networks' => ['Ethereum (ERC-20)', 'Arbitrum', 'Optimism'],
                'price_usd' => 3624.80,
                'change_24h' => -1.18,
                'volume_24h' => 515800000,
                'min_deposit' => 0.005,
                'min_withdrawal' => 0.01,
                'withdrawal_fee' => 0.002,
            ],
            [
                'id' => 'tether',
                'symbol' => 'USDT',
                'name' => 'Tether USD',
                'type' => 'stablecoin',
                'color' => '#00D6A0',
                'icon_bg' => '#00D6A0',
                'networks' => ['Tron (TRC-20)', 'Ethereum (ERC-20)', 'Solana Native', 'BNB Smart Chain (BEP-20)'],
                'price_usd' => 1.0002,
                'change_24h' => 0.02,
                'volume_24h' => 4580000000,
                'min_deposit' => 10.0,
                'min_withdrawal' => 20.0,
                'withdrawal_fee' => 1.0,
            ],
            [
                'id' => 'solana',
                'symbol' => 'SOL',
                'name' => 'Solana',
                'type' => 'crypto',
                'color' => '#A855F7',
                'icon_bg' => '#A855F7',
                'networks' => ['Solana Native'],
                'price_usd' => 184.65,
                'change_24h' => 8.75,
                'volume_24h' => 1074000000,
                'min_deposit' => 0.1,
                'min_withdrawal' => 0.2,
                'withdrawal_fee' => 0.01,
            ],
            [
                'id' => 'binancecoin',
                'symbol' => 'BNB',
                'name' => 'BNB',
                'type' => 'crypto',
                'color' => '#F59E0B',
                'icon_bg' => '#F59E0B',
                'networks' => ['BNB Smart Chain (BEP-20)'],
                'price_usd' => 592.10,
                'change_24h' => 0.85,
                'volume_24h' => 675000000,
                'min_deposit' => 0.05,
                'min_withdrawal' => 0.1,
                'withdrawal_fee' => 0.005,
            ],
            [
                'id' => 'ripple',
                'symbol' => 'XRP',
                'name' => 'Ripple',
                'type' => 'crypto',
                'color' => '#3B82F6',
                'icon_bg' => '#3B82F6',
                'networks' => ['XRPL Native'],
                'price_usd' => 0.5842,
                'change_24h' => 4.12,
                'volume_24h' => 1104000000,
                'min_deposit' => 10.0,
                'min_withdrawal' => 20.0,
                'withdrawal_fee' => 0.25,
            ],
            [
                'id' => 'dogecoin',
                'symbol' => 'DOGE',
                'name' => 'Dogecoin',
                'type' => 'crypto',
                'color' => '#EAB308',
                'icon_bg' => '#EAB308',
                'networks' => ['Dogecoin Native'],
                'price_usd' => 0.1284,
                'change_24h' => -4.65,
                'volume_24h' => 143800000,
                'min_deposit' => 50.0,
                'min_withdrawal' => 100.0,
                'withdrawal_fee' => 2.0,
            ],
            [
                'id' => 'usd-coin',
                'symbol' => 'USDC',
                'name' => 'USD Coin',
                'type' => 'stablecoin',
                'color' => '#2775CA',
                'icon_bg' => '#2775CA',
                'networks' => ['Ethereum (ERC-20)', 'Solana Native', 'Base'],
                'price_usd' => 1.0000,
                'change_24h' => 0.01,
                'volume_24h' => 1890000000,
                'min_deposit' => 10.0,
                'min_withdrawal' => 20.0,
                'withdrawal_fee' => 1.0,
            ],
        ];

        foreach ($assetsData as $data) {
            Asset::updateOrCreate(['id' => $data['id']], $data);
        }

        // 4. Seed Wallets for Demo Trader
        $walletsData = [
            ['asset_id' => 'bitcoin', 'available_balance' => 0.2245, 'locked_balance' => 0.0800],
            ['asset_id' => 'ethereum', 'available_balance' => 2.8500, 'locked_balance' => 0.5000],
            ['asset_id' => 'tether', 'available_balance' => 5850.00, 'locked_balance' => 1575.00],
            ['asset_id' => 'solana', 'available_balance' => 22.50, 'locked_balance' => 5.00],
            ['asset_id' => 'binancecoin', 'available_balance' => 2.80, 'locked_balance' => 0.60],
            ['asset_id' => 'ripple', 'available_balance' => 1200.0, 'locked_balance' => 300.0],
            ['asset_id' => 'dogecoin', 'available_balance' => 4200.0, 'locked_balance' => 0.0],
            ['asset_id' => 'usd-coin', 'available_balance' => 2500.0, 'locked_balance' => 0.0],
        ];

        foreach ($walletsData as $w) {
            Wallet::updateOrCreate(
                ['user_id' => $trader->id, 'asset_id' => $w['asset_id']],
                [
                    'available_balance' => $w['available_balance'],
                    'locked_balance' => $w['locked_balance'],
                ]
            );
        }

        // 5. Seed Trading Pairs
        $pairs = [
            [
                'id' => 'BTC-USDT',
                'symbol' => 'BTC/USDT',
                'base_asset' => 'BTC',
                'quote_asset' => 'USDT',
                'price' => 68450.25,
                'change_24h' => 3.42,
                'high_24h' => 69120.0,
                'low_24h' => 66890.5,
                'volume_24h_base' => 28450.21,
                'volume_24h_quote' => 1948500000,
                'price_decimals' => 2,
                'amount_decimals' => 4,
                'min_amount' => 0.0001,
            ],
            [
                'id' => 'ETH-USDT',
                'symbol' => 'ETH/USDT',
                'base_asset' => 'ETH',
                'quote_asset' => 'USDT',
                'price' => 3624.80,
                'change_24h' => -1.18,
                'high_24h' => 3710.0,
                'low_24h' => 3580.2,
                'volume_24h_base' => 14230.11,
                'volume_24h_quote' => 515800000,
                'price_decimals' => 2,
                'amount_decimals' => 3,
                'min_amount' => 0.001,
            ],
            [
                'id' => 'SOL-USDT',
                'symbol' => 'SOL/USDT',
                'base_asset' => 'SOL',
                'quote_asset' => 'USDT',
                'price' => 184.65,
                'change_24h' => 8.75,
                'high_24h' => 188.2,
                'low_24h' => 169.5,
                'volume_24h_base' => 5820.4,
                'volume_24h_quote' => 1074000000,
                'price_decimals' => 2,
                'amount_decimals' => 2,
                'min_amount' => 0.01,
            ],
            [
                'id' => 'BNB-USDT',
                'symbol' => 'BNB/USDT',
                'base_asset' => 'BNB',
                'quote_asset' => 'USDT',
                'price' => 592.10,
                'change_24h' => 0.85,
                'high_24h' => 598.0,
                'low_24h' => 585.3,
                'volume_24h_base' => 1140.0,
                'volume_24h_quote' => 675000000,
                'price_decimals' => 1,
                'amount_decimals' => 2,
                'min_amount' => 0.01,
            ],
            [
                'id' => 'XRP-USDT',
                'symbol' => 'XRP/USDT',
                'base_asset' => 'XRP',
                'quote_asset' => 'USDT',
                'price' => 0.5842,
                'change_24h' => 4.12,
                'high_24h' => 0.601,
                'low_24h' => 0.562,
                'volume_24h_base' => 1890.3,
                'volume_24h_quote' => 1104000000,
                'price_decimals' => 4,
                'amount_decimals' => 1,
                'min_amount' => 1.0,
            ],
            [
                'id' => 'DOGE-USDT',
                'symbol' => 'DOGE/USDT',
                'base_asset' => 'DOGE',
                'quote_asset' => 'USDT',
                'price' => 0.1284,
                'change_24h' => -4.65,
                'high_24h' => 0.136,
                'low_24h' => 0.127,
                'volume_24h_base' => 1120.0,
                'volume_24h_quote' => 143800000,
                'price_decimals' => 4,
                'amount_decimals' => 0,
                'min_amount' => 10.0,
            ],
        ];

        foreach ($pairs as $p) {
            TradingPair::updateOrCreate(['id' => $p['id']], $p);
        }

        // 6. Seed Open Orders & History
        $orders = [
            [
                'id' => 'ord-881',
                'user_id' => $trader->id,
                'pair' => 'BTC/USDT',
                'side' => 'buy',
                'type' => 'limit',
                'price' => 67200.0,
                'amount' => 0.15,
                'filled' => 0.05,
                'total' => 10080.0,
                'status' => 'open',
            ],
            [
                'id' => 'ord-882',
                'user_id' => $trader->id,
                'pair' => 'BTC/USDT',
                'side' => 'sell',
                'type' => 'limit',
                'price' => 69250.0,
                'amount' => 0.1,
                'filled' => 0.0,
                'total' => 6925.0,
                'status' => 'open',
            ],
            [
                'id' => 'ord-750',
                'user_id' => $trader->id,
                'pair' => 'BTC/USDT',
                'side' => 'buy',
                'type' => 'limit',
                'price' => 66800.0,
                'amount' => 0.25,
                'filled' => 0.25,
                'total' => 16700.0,
                'status' => 'filled',
            ],
            [
                'id' => 'ord-751',
                'user_id' => $trader->id,
                'pair' => 'BTC/USDT',
                'side' => 'sell',
                'type' => 'market',
                'price' => 68100.0,
                'amount' => 0.12,
                'filled' => 0.12,
                'total' => 8172.0,
                'status' => 'filled',
            ],
            [
                'id' => 'ord-752',
                'user_id' => $trader->id,
                'pair' => 'ETH/USDT',
                'side' => 'buy',
                'type' => 'stop_limit',
                'price' => 3550.0,
                'amount' => 1.5,
                'filled' => 0.0,
                'total' => 5325.0,
                'status' => 'cancelled',
            ],
        ];

        foreach ($orders as $o) {
            Order::updateOrCreate(['id' => $o['id']], $o);
        }

        // 7. Seed Transactions
        $transactions = [
            [
                'id' => 'tx-w1',
                'user_id' => $trader->id,
                'type' => 'deposit',
                'asset' => 'USDT',
                'amount' => 2000.0,
                'value_usd' => 2000.4,
                'fee_usd' => 0.0,
                'status' => 'completed',
                'tx_hash' => '0x8f3c71a4819d9b2e04f7c11a2245bb318029e1f5',
                'network' => 'TRC-20 (Tron)',
                'address' => 'TX9Qe...48zK',
                'confirmations' => '32/32 Confirmed',
                'notes' => 'Direct Tron network deposit credited to Spot Account',
            ],
            [
                'id' => 'tx-w2',
                'user_id' => $trader->id,
                'type' => 'trade_buy',
                'asset' => 'ETH',
                'amount' => 1.5,
                'value_usd' => 5437.2,
                'fee_usd' => 1.08,
                'status' => 'completed',
                'tx_hash' => '0x44a1b029ff8812c37719d2a019b8821948ac9120',
                'network' => 'Internal Settlement Engine',
                'address' => 'Match ID: MT-991420',
                'confirmations' => 'Instant Settlement',
                'notes' => 'Spot Order Fill: Bought 1.5 ETH at $3,624.80 USDT',
            ],
            [
                'id' => 'tx-w3',
                'user_id' => $trader->id,
                'type' => 'trade_sell',
                'asset' => 'BTC',
                'amount' => 0.05,
                'value_usd' => 3422.5,
                'fee_usd' => 0.68,
                'status' => 'completed',
                'tx_hash' => '0x71e8821199a0b1298811d7f4ac91882019482011',
                'network' => 'Internal Settlement Engine',
                'address' => 'Match ID: MT-991204',
                'confirmations' => 'Instant Settlement',
                'notes' => 'Spot Order Fill: Sold 0.05 BTC at $68,450.00 USDT',
            ],
            [
                'id' => 'tx-w4',
                'user_id' => $trader->id,
                'type' => 'earn_yield',
                'asset' => 'USDT',
                'amount' => 14.25,
                'value_usd' => 14.25,
                'fee_usd' => 0.0,
                'status' => 'completed',
                'tx_hash' => 'cp-yield-dist-88194',
                'network' => 'CoinPlus Staking Vault',
                'address' => 'Vault ID: CP-EARN-USDT-01',
                'confirmations' => 'Automated Payout',
                'notes' => 'Daily flexible staking distribution (8.2% APY)',
            ],
            [
                'id' => 'tx-w5',
                'user_id' => $trader->id,
                'type' => 'withdrawal',
                'asset' => 'USDT',
                'amount' => 500.0,
                'value_usd' => 500.1,
                'fee_usd' => 1.0,
                'status' => 'completed',
                'tx_hash' => '0x2e11a88b65ff99bc88110948ac91208477192019',
                'network' => 'TRC-20 (Tron)',
                'address' => 'TV42j...88wM',
                'confirmations' => '32/32 Confirmed',
                'notes' => 'Withdrawal to whitelisted cold storage address',
            ],
        ];

        foreach ($transactions as $t) {
            Transaction::updateOrCreate(['id' => $t['id']], $t);
        }

        // 8. Seed Announcements
        $announcements = [
            [
                'title' => 'Scheduled System Maintenance: Oct 28, 02:00-04:00 UTC',
                'content' => 'CoinPlus matching engine will undergo a scheduled infrastructure optimization. Spot trading will pause briefly.',
                'type' => 'maintenance',
                'target_audience' => 'All Users',
                'status' => 'active',
                'valid_until' => '2026-10-28 04:00:00',
            ],
            [
                'title' => 'Zero-Fee Spot Trading on all USDC Pairs',
                'content' => 'Enjoy 0% maker and taker fees on all USDC spot pairs throughout this quarter.',
                'type' => 'feature',
                'target_audience' => 'All Users',
                'status' => 'active',
                'valid_until' => '2026-12-31 23:59:59',
            ],
            [
                'title' => 'Security Advisory: Beware of Impersonation Phishing',
                'content' => 'CoinPlus staff will never ask for your password, two-factor authentication codes, or private keys.',
                'type' => 'security',
                'target_audience' => 'All Users',
                'status' => 'active',
                'valid_until' => '2026-11-30 23:59:59',
            ],
        ];

        foreach ($announcements as $a) {
            Announcement::updateOrCreate(['title' => $a['title']], $a);
        }

        // 9. Seed Notifications
        $notifications = [
            [
                'user_id' => $trader->id,
                'title' => 'Order Filled: Buy 1.5 ETH',
                'message' => 'Your limit order to buy 1.5 ETH at $3,624.80 USDT was completely filled.',
                'type' => 'trade',
                'read' => false,
            ],
            [
                'user_id' => $trader->id,
                'title' => 'Deposit Confirmed: 2,000 USDT',
                'message' => 'Your Tron TRC-20 deposit of 2,000 USDT has reached 32 network confirmations and is available for trading.',
                'type' => 'deposit',
                'read' => true,
            ],
            [
                'user_id' => $trader->id,
                'title' => 'Security Alert: New IP Login',
                'message' => 'A new browser session was authenticated from Chrome on Windows (127.0.0.1).',
                'type' => 'security',
                'read' => true,
            ],
        ];

        foreach ($notifications as $n) {
            Notification::updateOrCreate(
                ['user_id' => $n['user_id'], 'title' => $n['title']],
                $n
            );
        }

        // 10. Seed Watchlist
        Watchlist::updateOrCreate(['user_id' => $trader->id, 'asset_symbol' => 'BTC']);
        Watchlist::updateOrCreate(['user_id' => $trader->id, 'asset_symbol' => 'ETH']);
        Watchlist::updateOrCreate(['user_id' => $trader->id, 'asset_symbol' => 'SOL']);

        // 11. Seed Support Ticket
        $ticket = SupportTicket::updateOrCreate(
            ['ticket_ref' => 'TCK-88214'],
            [
                'user_id' => $trader->id,
                'subject' => 'Trading API WebSocket latency inquiry',
                'category' => 'trading',
                'priority' => 'medium',
                'status' => 'open',
            ]
        );

        SupportMessage::updateOrCreate(
            ['support_ticket_id' => $ticket->id, 'message' => 'What is the default order book push frequency for the public spot WebSocket API on BTC/USDT?'],
            [
                'sender_type' => 'user',
                'sender_name' => 'Alex Vance',
            ]
        );

        // 12. Seed System Settings
        SystemSetting::updateOrCreate(
            ['key' => 'general'],
            [
                'value' => [
                    'platform_name' => 'CoinPlus Institutional Exchange',
                    'maintenance_mode' => false,
                    'kyc_enforced' => true,
                    'max_daily_withdrawal_tier1' => 10000,
                    'max_daily_withdrawal_tier2' => 500000,
                    'auto_approve_withdrawal_threshold' => 1000,
                ],
            ]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'about_content'],
            [
                'value' => [
                    'brand_values' => [
                        'platformName' => 'CoinPlus',
                        'tagline' => 'Precision Cryptocurrency Exchange Interface',
                        'conceptDefinition' => 'CoinPlus is a conceptual next-generation cryptocurrency trading platform engineered to unite institutional-grade market depth, sub-millisecond visual latency, and transparent fee architecture in a modern black-and-gold design system.',
                        'mission' => 'To establish a cleaner, more reliable standard for digital asset trading interfaces — removing visual clutter, eliminating hidden costs, and presenting real-time market data with utmost visual clarity.',
                        'vision' => 'A financial ecosystem where traders at every experience level enjoy equal access to professional order books, transparent liquidity routing, and frictionless asset management without cognitive overload.',
                    ],
                    'features' => [
                        [
                            'id' => 'market-intel',
                            'title' => 'Real-Time Market Intelligence',
                            'subtitle' => 'High-Density Live Pricing & Metrics',
                            'description' => 'Continuous price updates, 24-hour volume distribution, dynamic SVG sparklines, and filterable gainers/losers rankings across leading cryptocurrency assets including BTC, ETH, SOL, BNB, and XRP.',
                            'iconName' => 'TrendingUp',
                            'tags' => ['Live Sparklines', '24h Volume', 'Market Dominance'],
                            'routeHref' => '/markets',
                        ],
                        [
                            'id' => 'trading-terminal',
                            'title' => 'Advanced Trading Terminal',
                            'subtitle' => 'Institutional Order Book Depth',
                            'description' => 'Multi-timeframe candlestick engine with volume histograms, interactive depth ladders, limit/market/stop-limit order types, and real-time trade execution simulation.',
                            'iconName' => 'BarChart3',
                            'tags' => ['Multi-Timeframe', 'Depth Ladder', 'Stop-Limit Orders'],
                            'routeHref' => '/trade',
                        ],
                        [
                            'id' => 'asset-custody',
                            'title' => 'Comprehensive Asset Overview',
                            'subtitle' => 'Portfolio Valuation & Allocation',
                            'description' => 'Detailed balance tracking, portfolio asset distribution charts, available vs. locked balance distinctions, and comprehensive deposit/withdrawal history logging.',
                            'iconName' => 'Wallet',
                            'tags' => ['Portfolio Allocation', 'Locked Balances', 'Audit Trails'],
                            'routeHref' => '/wallet',
                        ],
                        [
                            'id' => 'account-tools',
                            'title' => 'Account & Compliance Tools',
                            'subtitle' => 'Tiered Limits & Fee Discounter',
                            'description' => 'Transparent verification workflows, tiered daily withdrawal quotas up to 100 BTC, personalized regional preferences, and VIP fee discount milestones.',
                            'iconName' => 'UserCheck',
                            'tags' => ['Tiered KYC', 'VIP Discounts', 'Session Timeout'],
                            'routeHref' => '/account',
                        ],
                        [
                            'id' => 'security-design',
                            'title' => 'Security-First Architecture',
                            'subtitle' => 'FIDO2 Passkeys & Allowlisting',
                            'description' => 'Biometric authentication via WebAuthn passkeys, two-factor authentication (TOTP), withdrawal address allowlists, and automated suspicious session detection.',
                            'iconName' => 'ShieldCheck',
                            'tags' => ['FIDO2 Passkeys', '2FA TOTP', 'Address Allowlist'],
                            'routeHref' => '/settings',
                        ],
                    ],
                    'principles' => [
                        [
                            'id' => 'clarity',
                            'title' => 'Information Clarity',
                            'lead' => 'High signal, zero unnecessary noise.',
                            'description' => 'Every decimal point, order depth layer, and status indicator is intentionally positioned to maximize readability in fast-moving market conditions.',
                            'badgeText' => 'Legibility First',
                        ],
                        [
                            'id' => 'usability',
                            'title' => 'Fluid Usability',
                            'lead' => 'Desktop power meets mobile precision.',
                            'description' => 'Engineered with responsive layouts, touch-friendly touch targets (≥44px), and adaptive table drawers that prevent accidental mis-clicks.',
                            'badgeText' => 'Responsive Design',
                        ],
                        [
                            'id' => 'transparency',
                            'title' => 'Upfront Transparency',
                            'lead' => 'No hidden spreads or ambiguous fees.',
                            'description' => 'Transparent maker/taker fee tiers, clear blockchain network gas breakdowns, and accessible fee calculators ensure traders know costs in advance.',
                            'badgeText' => 'Transparent Limits',
                        ],
                        [
                            'id' => 'responsible',
                            'title' => 'Responsible Access',
                            'lead' => 'Safety warnings and environment boundaries.',
                            'description' => 'Unambiguous demonstration notices, two-step withdrawal reviews, and strict simulated boundaries ensure safe exploration without risk to real capital.',
                            'badgeText' => 'Safety Conscious',
                        ],
                    ],
                    'faqs' => [
                        [
                            'question' => 'What is CoinPlus and how does it operate?',
                            'answer' => 'CoinPlus is a cryptocurrency trading platform design and technology showcase. It demonstrates how modern exchange frontends can provide institutional order book depth, responsive charts, and transparent fee architectures in a secure demonstration environment.',
                        ],
                        [
                            'question' => 'Are the order books and trades connected to real live blockchains?',
                            'answer' => 'No. In this version, all price feeds, order books, and transactions are simulated demonstration data. No real cryptocurrencies or fiat funds are deposited, withdrawn, or traded.',
                        ],
                        [
                            'question' => 'What security standards are demonstrated in CoinPlus?',
                            'answer' => 'CoinPlus models industry security patterns including FIDO2/WebAuthn biometric passkeys, Google Authenticator 2FA, withdrawal address allowlists, session revocation, and tiered identity verification.',
                        ],
                        [
                            'question' => 'Can I test all platform features without creating an external account?',
                            'answer' => 'Yes! You can explore all routes, including Live Markets, the Trading Terminal, Deposit/Withdraw simulators, and the Settings suite directly in your browser.',
                        ],
                    ],
                ],
            ]
        );

        // 13. Seed Audit Log
        AuditLog::create([
            'event_id' => 'EVT-9901',
            'user_id' => $admin->id,
            'admin_email' => $admin->email,
            'admin_name' => $admin->name,
            'action' => 'System Initialized',
            'category' => 'System Settings',
            'ip_address' => '127.0.0.1',
            'details' => 'CoinPlus platform database schema migrated and initial seeders loaded.',
        ]);

        // 14. Seed System Services Telemetry
        $services = [
            [
                'service_id' => 'public-website',
                'name' => 'Public Website & Web Portal',
                'category' => 'core',
                'status' => 'operational',
                'description' => 'Public landing pages, marketing portal, and institutional brand showcase',
                'uptime' => '99.99%',
                'latency' => '18ms',
                'daily_uptime_history' => array_fill(0, 30, 'operational'),
                'sort_order' => 1,
            ],
            [
                'service_id' => 'user-dashboard',
                'name' => 'User Account & Portfolio Dashboard',
                'category' => 'core',
                'status' => 'operational',
                'description' => 'Personalized portfolio cards, analytics chart engine, and asset balances',
                'uptime' => '99.98%',
                'latency' => '24ms',
                'daily_uptime_history' => array_fill(0, 30, 'operational'),
                'sort_order' => 2,
            ],
            [
                'service_id' => 'markets-interface',
                'name' => 'Live Markets Depth & Order Book Feed',
                'category' => 'trading',
                'status' => 'operational',
                'description' => 'Real-time spot price ticker streaming, 24h volume calculations, and sparklines',
                'uptime' => '99.95%',
                'latency' => '31ms',
                'daily_uptime_history' => array_merge(array_fill(0, 15, 'operational'), ['degraded'], array_fill(0, 14, 'operational')),
                'sort_order' => 3,
            ],
            [
                'service_id' => 'trading-interface',
                'name' => 'Spot Order Matching Engine',
                'category' => 'trading',
                'status' => 'operational',
                'description' => 'High-frequency spot order book depth ladder, candlestick charting, and order execution',
                'uptime' => '99.99%',
                'latency' => '12ms',
                'daily_uptime_history' => array_fill(0, 30, 'operational'),
                'sort_order' => 4,
            ],
            [
                'service_id' => 'wallet-interface',
                'name' => 'Treasury, Deposit & Custody Gateway',
                'category' => 'finance',
                'status' => 'operational',
                'description' => 'Cryptocurrency on-ramp deposit generation, simulated network gas fees, and withdrawals',
                'uptime' => '99.92%',
                'latency' => '45ms',
                'daily_uptime_history' => array_merge(array_fill(0, 21, 'operational'), ['degraded'], array_fill(0, 8, 'operational')),
                'sort_order' => 5,
            ],
            [
                'service_id' => 'account-services',
                'name' => 'Identity (KYC) & Security Gatekeeper',
                'category' => 'core',
                'status' => 'operational',
                'description' => 'Automated KYC verification, biometric passkey authentications, and AML scanners',
                'uptime' => '99.99%',
                'latency' => '22ms',
                'daily_uptime_history' => array_fill(0, 30, 'operational'),
                'sort_order' => 6,
            ],
            [
                'service_id' => 'support-centre',
                'name' => 'Customer Relations & Help Desk API',
                'category' => 'support',
                'status' => 'operational',
                'description' => 'Customer relations ticketing desk, real-time message exchange, and knowledgebase',
                'uptime' => '99.99%',
                'latency' => '19ms',
                'daily_uptime_history' => array_fill(0, 30, 'operational'),
                'sort_order' => 7,
            ],
        ];

        foreach ($services as $srv) {
            \App\Models\SystemService::updateOrCreate(
                ['service_id' => $srv['service_id']],
                $srv
            );
        }

        // 15. Seed Incident History
        $incidents = [
            [
                'incident_id' => 'inc-2026-10-14',
                'title' => 'Scheduled WebSocket Edge Gateway Scale-out',
                'date_display' => 'October 14, 2026',
                'severity' => 'minor',
                'status' => 'resolved',
                'affected_services' => ['markets-interface', 'trading-interface'],
                'description' => 'Periodic cluster re-balancing and container auto-scale expansion for high-throughput WebSocket price pushers.',
                'resolution_summary' => 'All nodes updated successfully without client connection drops. Network latency normalized under 15ms.',
                'updates' => [
                    [
                        'id' => 'upd-1',
                        'timestamp' => '10:45 AM UTC',
                        'status' => 'resolved',
                        'message' => 'Node re-balancing concluded. All health probes green.',
                    ],
                    [
                        'id' => 'upd-2',
                        'timestamp' => '10:15 AM UTC',
                        'status' => 'monitoring',
                        'message' => 'Traffic routed to newly scaled gateway pods.',
                    ],
                ],
            ],
            [
                'incident_id' => 'inc-2026-10-06',
                'title' => 'Hot Wallet Node Re-synchronization',
                'date_display' => 'October 06, 2026',
                'severity' => 'maintenance',
                'status' => 'resolved',
                'affected_services' => ['wallet-interface'],
                'description' => 'Routine ledger pruning and UTXO balance re-indexing on internal custody signer nodes.',
                'resolution_summary' => 'Node synchronizations concluded cleanly. Deposit addresses and withdrawal queues operating at nominal throughput.',
                'updates' => [
                    [
                        'id' => 'upd-3',
                        'timestamp' => '04:30 AM UTC',
                        'status' => 'resolved',
                        'message' => 'Signer nodes synched to current block height.',
                    ],
                ],
            ],
        ];

        foreach ($incidents as $inc) {
            \App\Models\Incident::updateOrCreate(
                ['incident_id' => $inc['incident_id']],
                $inc
            );
        }
    }
}
