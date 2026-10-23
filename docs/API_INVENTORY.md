# CoinPlus — Comprehensive API Inventory & Contract Specification

This document maps all required REST API endpoints corresponding to the CoinPlus customer interface and operator administration console.

---

## 1. Authentication & Security Endpoints (`/api/auth/*`)

| Endpoint | Method | Auth Required | Description | Request Payload / Params | Response Format |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/auth/register` | `POST` | Public | Register new customer account | `fullName`, `email`, `country`, `password`, `password_confirmation`, `acceptTerms` | `{ user, token, message }` |
| `/api/auth/login` | `POST` | Public | Customer authentication & Sanctum token generation | `email`, `password`, `rememberMe` | `{ user, token, message }` |
| `/api/auth/admin-login` | `POST` | Public | Administrative console sign-in | `email`, `password` | `{ user, token, role: 'admin', message }` |
| `/api/auth/logout` | `POST` | Sanctum Bearer | Revoke current access token | None | `{ message: 'Logged out successfully' }` |
| `/api/auth/user` | `GET` | Sanctum Bearer | Retrieve current authenticated user profile | None | `{ user: { uid, displayName, fullName, email, phone, role, status, kycTier, ... } }` |
| `/api/auth/forgot-password` | `POST` | Public | Trigger password reset link | `email` | `{ message: 'Reset instructions dispatched' }` |
| `/api/auth/reset-password` | `POST` | Public | Submit new password with reset token | `token`, `email`, `password`, `password_confirmation` | `{ message: 'Password updated successfully' }` |

---

## 2. Customer Profile & Settings Endpoints (`/api/account/*`)

| Endpoint | Method | Auth Required | Description | Request Payload / Params | Response Format |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/account/profile` | `GET` | Sanctum Bearer | Fetch detailed account settings & preferences | None | `{ profile: UserAccountProfile }` |
| `/api/account/profile` | `PUT` | Sanctum Bearer | Update profile info & preferences | `displayName`, `phone`, `country`, `language`, `timeZone`, `displayCurrency`, `dateFormat`, `notifications` | `{ profile, message }` |
| `/api/account/change-password` | `POST` | Sanctum Bearer | Change account password | `current_password`, `password`, `password_confirmation` | `{ message }` |
| `/api/account/2fa/status` | `GET` | Sanctum Bearer | Get 2FA configuration status | None | `{ enabled: boolean, passkeysCount: number }` |
| `/api/account/2fa/toggle` | `POST` | Sanctum Bearer | Enable or disable two-factor authentication | `enabled: boolean`, `code?: string` | `{ enabled, message }` |
| `/api/account/sessions` | `GET` | Sanctum Bearer | List active user login sessions | None | `{ sessions: Array<{ id, device, ip, location, isCurrent, lastActive }> }` |
| `/api/account/sessions/{id}` | `DELETE` | Sanctum Bearer | Revoke a specific session | None | `{ message }` |

---

## 3. KYC Verification Endpoints (`/api/kyc/*`)

| Endpoint | Method | Auth Required | Description | Request Payload / Params | Response Format |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/kyc/status` | `GET` | Sanctum Bearer | Get user KYC tier and submission history | None | `{ currentTier, status, limits, submissions }` |
| `/api/kyc/tier1` | `POST` | Sanctum Bearer | Submit Tier 1 Basic Identity Data | `fullName`, `dateOfBirth`, `nationality`, `idType`, `idNumber` | `{ status: 'approved'\|'pending', kycTier: 'Tier 1' }` |
| `/api/kyc/tier2` | `POST` | Sanctum Bearer | Submit Tier 2 Documents (Photo ID & Proof of Address) | `documentType`, `documentNumber`, `idFrontFile`, `idBackFile`, `proofOfAddressFile` | `{ status: 'pending', message }` |

---

## 4. Market & Trading Endpoints (`/api/markets/*` & `/api/trade/*`)

| Endpoint | Method | Auth Required | Description | Request Payload / Params | Response Format |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/markets/tickers` | `GET` | Public | Real-time 24h market ticker overview for all pairs | Query: `category` | `{ pairs: TradingPairConfig[] }` |
| `/api/markets/{pair}/orderbook` | `GET` | Public | Order book depth for a specific pair | None | `{ asks: OrderBookEntry[], bids: OrderBookEntry[] }` |
| `/api/markets/{pair}/trades` | `GET` | Public | Recent market executions stream | None | `{ trades: MarketTrade[] }` |
| `/api/markets/{pair}/candles` | `GET` | Public | OHLCV historical candle data | Query: `timeframe` (`1m`, `15m`, `1h`, `1D`, etc.) | `{ candles: CandleData[] }` |
| `/api/trade/orders` | `GET` | Sanctum Bearer | User's active open orders and past history | Query: `pair`, `status`, `side` | `{ openOrders: ActiveOrder[], history: ActiveOrder[] }` |
| `/api/trade/orders` | `POST` | Sanctum Bearer | Place limit or market order | `pair`, `side`, `type`, `price`, `amount` | `{ order: ActiveOrder, message }` |
| `/api/trade/orders/{id}/cancel` | `POST` | Sanctum Bearer | Cancel open order and release locked funds | None | `{ order: ActiveOrder, message }` |

---

## 5. Wallet & Treasury Endpoints (`/api/wallet/*`)

| Endpoint | Method | Auth Required | Description | Request Payload / Params | Response Format |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/wallet/summary` | `GET` | Sanctum Bearer | Aggregate portfolio balances and 24h metrics | None | `{ totalBalanceUsd, availableUsd, lockedUsd, change24h, ... }` |
| `/api/wallet/assets` | `GET` | Sanctum Bearer | Asset breakdown by cryptocurrency | None | `{ assets: WalletAssetItem[] }` |
| `/api/wallet/transactions` | `GET` | Sanctum Bearer | User ledger of deposits, withdrawals, and trades | Query: `type`, `asset`, `status` | `{ transactions: WalletTransactionItem[] }` |
| `/api/wallet/deposit/address` | `GET` | Sanctum Bearer | Generate or retrieve deposit address & QR details | Query: `asset`, `network` | `{ address, network, qrCodeUrl, minDeposit, confirmations }` |
| `/api/wallet/deposit/simulate`| `POST` | Sanctum Bearer | Simulate incoming deposit (development/demo) | `asset`, `network`, `amount` | `{ deposit, message }` |
| `/api/wallet/withdraw` | `POST` | Sanctum Bearer | Request crypto withdrawal | `asset`, `network`, `address`, `amount`, `memo` | `{ withdrawal, message }` |
| `/api/wallet/watchlist` | `GET` | Sanctum Bearer | User cryptocurrency watchlist | None | `{ watchlist: string[] }` |
| `/api/wallet/watchlist` | `POST` | Sanctum Bearer | Add or remove crypto from watchlist | `assetSymbol`, `action: 'add'\|'remove'` | `{ watchlist, message }` |

---

## 6. Notifications & Support Endpoints (`/api/notifications/*` & `/api/support/*`)

| Endpoint | Method | Auth Required | Description | Request Payload / Params | Response Format |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/notifications` | `GET` | Sanctum Bearer | Fetch notification feed | Query: `filter: 'all'\|'unread'\|'trades'` | `{ notifications: NotificationItem[] }` |
| `/api/notifications/{id}/read`| `PUT` | Sanctum Bearer | Mark notification as read | None | `{ success: true }` |
| `/api/notifications/read-all` | `PUT` | Sanctum Bearer | Mark all notifications as read | None | `{ success: true }` |
| `/api/support/tickets` | `GET` | Sanctum Bearer | List user support tickets | None | `{ tickets: SupportTicketItem[] }` |
| `/api/support/tickets` | `POST` | Sanctum Bearer | Create new support ticket | `subject`, `category`, `priority`, `message` | `{ ticket: SupportTicketItem, message }` |
| `/api/support/tickets/{id}/reply` | `POST` | Sanctum Bearer | Send reply message on ticket | `message` | `{ reply, message }` |
| `/api/support/contact` | `POST` | Public | Submit public contact form | `name`, `email`, `subject`, `message` | `{ message: 'Inquiry received' }` |

---

## 7. Administrative Console Endpoints (`/api/admin/*`)

| Endpoint | Method | Auth Required | Description | Request Payload / Params | Response Format |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `/api/admin/metrics` | `GET` | Sanctum Bearer (Admin) | Platform KPI overview | None | `{ totalUsers, volume24h, totalDeposits, totalWithdrawals, pendingKyc, ... }` |
| `/api/admin/users` | `GET` | Sanctum Bearer (Admin) | List registered users with filters | Query: `search`, `status`, `kycTier` | `{ users: AdminUser[] }` |
| `/api/admin/users/{id}/status`| `PUT` | Sanctum Bearer (Admin) | Update user account status | `status: 'active'\|'suspended'` | `{ user, message }` |
| `/api/admin/kyc` | `GET` | Sanctum Bearer (Admin) | List KYC verification queue | Query: `status` | `{ cases: AdminKycCase[] }` |
| `/api/admin/kyc/{id}/review` | `POST` | Sanctum Bearer (Admin) | Approve or reject KYC case | `action: 'approve'\|'reject'`, `rejectionReason?: string` | `{ case, message }` |
| `/api/admin/deposits` | `GET` | Sanctum Bearer (Admin) | List platform deposits | Query: `status`, `asset` | `{ deposits: AdminDepositRecord[] }` |
| `/api/admin/deposits/{id}/status` | `PUT` | Sanctum Bearer (Admin) | Confirm deposit | `status: 'completed'` | `{ deposit, message }` |
| `/api/admin/withdrawals` | `GET` | Sanctum Bearer (Admin) | List platform withdrawals | Query: `status` | `{ withdrawals: AdminWithdrawalRecord[] }` |
| `/api/admin/withdrawals/{id}/review` | `POST` | Sanctum Bearer (Admin) | Approve or reject withdrawal | `action: 'approve'\|'reject'`, `reason?: string` | `{ withdrawal, message }` |
| `/api/admin/orders` | `GET` | Sanctum Bearer (Admin) | Global order audit table | Query: `pair`, `status` | `{ orders: AdminOrderRecord[] }` |
| `/api/admin/announcements` | `GET` | Public / Admin | List platform announcements | None | `{ announcements: AdminAnnouncement[] }` |
| `/api/admin/announcements` | `POST` | Sanctum Bearer (Admin) | Create new announcement | `title`, `content`, `type`, `targetAudience`, `validUntil` | `{ announcement, message }` |
| `/api/admin/announcements/{id}` | `DELETE` | Sanctum Bearer (Admin) | Delete announcement | None | `{ message }` |
| `/api/admin/assets` | `GET` | Public / Admin | List configured assets & network rules | None | `{ assets: AdminAssetRecord[] }` |
| `/api/admin/assets/{id}` | `PUT` | Sanctum Bearer (Admin) | Toggle deposit/withdraw/trade or update fees | `depositEnabled`, `withdrawEnabled`, `tradingEnabled`, `withdrawalFee` | `{ asset, message }` |
| `/api/admin/audit-logs` | `GET` | Sanctum Bearer (Admin) | Operator audit trail | Query: `category`, `adminEmail` | `{ logs: AdminAuditLog[] }` |
| `/api/admin/settings` | `GET` | Sanctum Bearer (Admin) | System configurations | None | `{ settings }` |
| `/api/admin/settings` | `PUT` | Sanctum Bearer (Admin) | Update system configurations | `settings` object | `{ settings, message }` |
