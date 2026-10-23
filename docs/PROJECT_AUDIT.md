# CoinPlus & Coins_api_v2 — Full Project Audit

**Date:** October 2026  
**Auditor:** Senior Full-Stack Architect & Security Engineer  
**Scope:** Frontend (`CoinPlus`) and Backend (`Coins_api_v2`)

---

## 1. Executive Summary

CoinPlus is a feature-rich, institutional-grade cryptocurrency spot trading exchange platform prototype with both client-facing trading interfaces and a comprehensive operator administration console. 

The audit established that:
1. **Frontend (`CoinPlus`):** Built with React 18, TypeScript 5.6, Tailwind CSS 3.4, and Vite 5.4. It contains 33+ customer and administration views, complete mock state sets, modal workflows, and responsive layouts. However, it currently operates exclusively against local mock data in `src/mock/` without an integrated HTTP client, environment configuration, or real backend communication.
2. **Backend (`Coins_api_v2`):** Currently a freshly initialized Git repository (`https://github.com/ish-22/Coins_api_v2.git`) with no prior commits or codebase present.
3. **Runtime Environment:** Windows operating system running XAMPP with PHP 8.2.12 (CLI and Apache), Composer 2.8.10, and MariaDB 10.4.32.

---

## 2. Frontend Audit — `CoinPlus`

### 2.1 Technology Stack & Manifest Analysis
- **Core Languages:** TypeScript 5.6.3, HTML5, CSS3.
- **Framework & Build System:** React 18.3.1 with Vite 5.4.11 and `@vitejs/plugin-react` 4.3.3.
- **Routing:** `react-router-dom` 6.28.0 (declarative client-side routing).
- **Styling & Design System:** Tailwind CSS 3.4.15, PostCSS 8.4.49, Autoprefixer 10.4.20, `clsx` 2.1.1, `tailwind-merge` 2.5.5. Custom "Black & Gold" institutional dark theme (`#0B0E14` primary background, `#D4AF37` / `#F0B93A` accents).
- **Icons:** `lucide-react` 0.460.0.
- **Build Output Verification:** `npm run build` (`tsc -b && vite build`) executes cleanly with zero type errors.

### 2.2 Directory & Structural Analysis
- **Nested Folder Anomaly:** The repository is cloned inside `c:\xampp\htdocs\CoinPlus\CoinPlus`. The outer folder contains only a stray `package-lock.json` (87 bytes).
- **Application Structure:**
  - `src/pages/`: 26 customer-facing pages (Landing, Dashboard, Markets, Trade, Assets/Wallet, Orders, Deposit, Withdraw, Watchlist, Account, KYC, Settings, Notifications, Support, Help, Fees, About, Contact, Status, Legal pages, Auth pages).
  - `src/pages/admin/`: 12 administrative console pages (AdminDashboard, AdminUsers, AdminKYC, AdminDeposits, AdminWithdrawals, AdminOrders, AdminSupport, AdminAnnouncements, AdminAssets, AdminSettings, AdminAuditLogs, AdminLogin).
  - `src/components/`: Reusable UI components grouped by feature domain (common design system, trading terminal, deposit, withdraw, KYC, orders, settings, support, admin).
  - `src/mock/`: 19 comprehensive mock datasets defining all domain models and mock states.
  - `src/hooks/useLivePrices.ts`: Simulated WebSocket ticker feeds updating order books and prices via client timers.

### 2.3 Identified Frontend Issues & Deficiencies
1. **No HTTP Client Utility:** No `axios` or configured `fetch` wrapper exists. No centralized request interceptor, token injector, or global error handler is present.
2. **Missing Environment Files:** No `.env` or `.env.example` file exists. API URLs and backend endpoints are not configured.
3. **Incomplete .gitignore:** The frontend `.gitignore` does not exclude `.env` or `.env.*` files. `tsconfig.app.tsbuildinfo` was previously tracked.
4. **Mocked Authentication Flow:** `Login.tsx` and `AdminLogin.tsx` simulate authentication via `setTimeout()` and unauthenticated `sessionStorage.setItem('cp_admin_auth', 'true')` without secure token handling or server validation.
5. **Vulnerable Dependencies (npm audit):** 11 vulnerabilities (5 moderate, 6 high) in dev dependencies (`braces`, `esbuild`, `postcss-selector-parser`, `react-router-dom`).

---

## 3. Backend Audit — `Coins_api_v2`

### 3.1 Initial State
- **Git State:** A Git repository initialized with remote origin `https://github.com/ish-22/Coins_api_v2.git` on branch `main` with 0 commits.
- **Source Code Status:** Empty working tree. No legacy PHP or Laravel code exists in this directory.
- **Required Architecture:** Clean, modular Laravel 11 application architecture implementing RESTful API endpoints for authentication, market data, order placement, wallet transactions, KYC submissions, support ticketing, and administrative controls.

---

## 4. Local Environment & Runtime Verification

| Component | Detected Version | Target Compatibility | Status |
| :--- | :--- | :--- | :--- |
| **Operating System** | Windows 10/11 x64 | Windows (PowerShell) | Verified |
| **Web Server** | Apache (XAMPP 8.2) | Port 80 / Artisan 8000 | Ready |
| **CLI PHP** | PHP 8.2.12 (ZTS VC19 x64) | PHP >= 8.2.0 | Compatible |
| **XAMPP PHP** | PHP 8.2.12 (C:\xampp\php) | PHP >= 8.2.0 | Compatible (Identical) |
| **Composer** | Composer 2.8.10 | Composer 2.x | Compatible |
| **Database** | MariaDB 10.4.32 | MySQL 8.0 / MariaDB 10.4+ | Compatible |
| **Node.js / npm** | Node v20+ / npm v10+ | Node.js LTS | Compatible |

### PHP Extensions Verified
`bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `iconv`, `json`, `libxml`, `mbstring`, `mysqli`, `mysqlnd`, `openssl`, `pcre`, `PDO`, `pdo_mysql`, `session`, `SimpleXML`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zlib`. All required extensions for Laravel 11 are enabled and operational.

---

## 5. Architectural Blueprint

The CoinPlus platform will operate as a decoupled Single Page Application (SPA) with a dedicated RESTful API backend:
- **Frontend:** React + Vite on `http://127.0.0.1:3000` (or local dev port).
- **Backend:** Laravel 11 API on `http://127.0.0.1:8000` with Sanctum Bearer token authentication and CORS configured for the frontend origin.
- **Database:** MySQL / MariaDB database `coinplus` running on `127.0.0.1:3306`.
