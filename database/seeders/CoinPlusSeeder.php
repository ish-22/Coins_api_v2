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
    }
}
