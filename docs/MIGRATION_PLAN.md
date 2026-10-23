# CoinPlus Full-Stack Migration & Integration Plan

**Date:** October 2026  
**Implementation Strategy:** Incremental, non-destructive, full-stack integration  
**Backend:** Laravel 11.x on PHP 8.2.12 with MariaDB/MySQL on Windows XAMPP  
**Frontend:** React 18 + Vite + TypeScript in `CoinPlus`

---

## 1. Migration Goals & Principles
1. **Preserve All Existing UI & Logic:** Keep every page, component, style, and business feature intact in `CoinPlus`.
2. **Clean Laravel 11 Foundation:** Initialize the official Laravel 11 application structure in `Coins_api_v2`, fully configured for PHP 8.2 and Sanctum API token authentication.
3. **Database Schema & Seeders:** Model MySQL tables matching all domain entities (Users, Wallets, Assets, Orders, Trades, Deposits, Withdrawals, KYC Submissions, Support Tickets, Announcements, Audit Logs, Settings) and seed initial realistic demonstration records.
4. **Centralized Frontend API Client:** Build a clean TypeScript API service layer with Axios/Fetch in `CoinPlus`, reading `VITE_API_BASE_URL` from `.env`, with automatic Bearer token injection and error handling.
5. **Phase-by-Phase Execution:** Validate each component through automated checks, migrations, and integration testing before declaring completion.

---

## 2. Implementation Roadmap

### Step 1: Initialize Laravel 11 Backend in `Coins_api_v2`
- Verify empty directory and initialize Laravel 11 via Composer.
- Install API scaffolding (`php artisan install:api` / Laravel Sanctum).
- Configure `config/cors.php` for Vite origin (`http://127.0.0.1:3000`, `http://localhost:3000`).
- Configure database credentials in `.env` for XAMPP MySQL.
- Set MariaDB default string length in `AppServiceProvider`.

### Step 2: Database Schema & Migrations
Create robust version-controlled migrations:
- `users`: id, uid, full_name, display_name, email, password, phone, country, role (`user` / `admin`), status, kyc_tier, avatar, preferences (json), timestamps.
- `assets`: id, symbol, name, type, networks (json), price_usd, change_24h, deposit_enabled, withdraw_enabled, trading_enabled, min_deposit, min_withdrawal, withdrawal_fee, color.
- `wallets`: id, user_id, asset_id, available_balance, locked_balance, timestamps.
- `orders`: id, user_id, pair, side (`buy` / `sell`), type (`limit` / `market` / `stop_limit`), price, amount, filled, total, status (`open` / `filled` / `cancelled`), timestamps.
- `trades`: id, order_id, pair, price, amount, side, fee, timestamps.
- `deposits`: id, user_id, asset_symbol, network, address, amount, amount_usd, tx_hash, status, confirmations, required_confirmations, timestamps.
- `withdrawals`: id, user_id, asset_symbol, network, destination_address, amount, fee, risk_score, status, rejection_reason, timestamps.
- `kyc_verifications`: id, user_id, tier, status, document_type, document_number, id_front, id_back, proof_of_address, rejection_reason, timestamps.
- `support_tickets`: id, ticket_ref, user_id, subject, category, priority, status, timestamps.
- `support_messages`: id, ticket_id, sender_type (`user` / `agent`), message, timestamps.
- `announcements`: id, title, content, type, target_audience, status, valid_until, timestamps.
- `audit_logs`: id, user_id, admin_email, admin_name, action, category, ip_address, timestamps.

### Step 3: Implement Backend Models, Controllers, Requests & Resources
- **Models:** User, Asset, Wallet, Order, Trade, Deposit, Withdrawal, KycVerification, SupportTicket, SupportMessage, Announcement, AuditLog.
- **Controllers:**
  - `Api/AuthController`: login, register, adminLogin, logout, user, forgotPassword.
  - `Api/AccountController`: getProfile, updateProfile, changePassword, sessions.
  - `Api/MarketController`: getTickers, getOrderBook, getRecentTrades, getCandles.
  - `Api/TradeController`: getOrders, placeOrder, cancelOrder.
  - `Api/WalletController`: getSummary, getAssets, getTransactions, getDepositAddress, simulateDeposit, withdraw.
  - `Api/KycController`: getStatus, submitTier1, submitTier2.
  - `Api/NotificationController`: list, markRead, markAllRead.
  - `Api/SupportController`: listTickets, createTicket, replyTicket, submitContact.
  - `Api/Admin/AdminController`: metrics, users, kycReview, depositsReview, withdrawalsReview, announcements, assets, auditLogs.
- **Middleware:** `AdminMiddleware` for role-based authorization.

### Step 4: Seed Database with Initial Demonstration & Default Data
- Seed Super Admin user (`operator@coinplus.io` / `SuperAdmin2026!#`).
- Seed Default Demo Trader (`demo.trader@coinplus.io` / `CoinPlus2026!#`).
- Seed 8 Core Assets (BTC, ETH, USDT, SOL, BNB, XRP, DOGE, USDC) with realistic balances.
- Seed trading pairs, initial order book depth, past orders, tickets, and announcements matching the frontend mock data.

### Step 5: Frontend API Integration
- Install Axios in `CoinPlus`.
- Create `src/services/apiClient.ts` (centralized Axios client with base URL from `import.meta.env.VITE_API_BASE_URL` and auth interceptor).
- Create modular API services (`authService.ts`, `walletService.ts`, `tradeService.ts`, `marketService.ts`, `kycService.ts`, `supportService.ts`, `adminService.ts`).
- Create `.env` and `.env.example` in `CoinPlus`.
- Connect authentication (`Login.tsx`, `Register.tsx`, `AdminLogin.tsx`) to real API endpoints.
- Connect dashboard, trading, wallet, orders, KYC, and admin views to live API data with graceful fallback to mock data when API server is unavailable.

### Step 6: Testing, Hardening & Deliverables
- Verify database migrations and seeders execute smoothly.
- Run automated feature tests.
- Validate frontend build (`npm run build`).
- Create Windows + XAMPP setup guide (`docs/LOCAL_SETUP.md`) and API integration guide (`docs/API_INTEGRATION.md`).
