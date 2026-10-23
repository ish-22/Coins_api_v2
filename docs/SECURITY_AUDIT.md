# CoinPlus & Coins_api_v2 — Full-Stack Security Audit

**Date:** October 2026  
**Standards:** OWASP Top 10 API Security Risks (2023), OWASP ASVS 4.0, NIST SP 800-63B  
**Target Systems:** Frontend Client (`CoinPlus`) and Backend Architecture (`Coins_api_v2`)

---

## 1. Vulnerability Findings & Risk Matrix

| Risk ID | Vulnerability / Threat | Component | Severity | Current Status | Remediation Plan |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **SEC-01** | Simulated In-Memory Authentication | Frontend | **HIGH** | Vulnerable | Replace simulated timeouts with Laravel Sanctum Bearer tokens and HTTP authorization headers. |
| **SEC-02** | Insecure Unauthenticated Admin Access | Frontend | **HIGH** | Vulnerable | Enforce backend role-based access control (`role === 'admin'`) and server-side token validation on all `/api/admin/*` routes. |
| **SEC-03** | Missing CORS Configuration | Backend | **HIGH** | Requires Setup | Configure Laravel CORS middleware to allow only trusted origins (`http://localhost:3000`, `http://127.0.0.1:3000`). Forbid wildcard `*` with credentials. |
| **SEC-04** | Vulnerable NPM Dev Dependencies | Frontend | **MEDIUM** | Identified | 11 vulnerabilities (braces, esbuild, postcss-selector-parser, react-router). Address with safe `npm audit fix` while locking major versions. |
| **SEC-05** | Missing API Rate Limiting | Backend | **HIGH** | Requires Setup | Implement Laravel throttle middleware on authentication endpoints (`throttle:5,1` on login/register/password-reset) and `throttle:60,1` on general endpoints. |
| **SEC-06** | Mass Assignment & Unvalidated Input | Backend | **HIGH** | Requires Setup | Implement Form Request Validation classes (`app/Http/Requests/*`) with strict validation rules and explicit `$fillable` Eloquent guards. |
| **SEC-07** | Broken Object Level Authorization (BOLA / IDOR) | Backend | **CRITICAL** | Requires Setup | Enforce user ownership verification on all order cancellation, ticket reply, and wallet queries (`Auth::id() === $model->user_id`). |
| **SEC-08** | Sensitive Financial Field Exposure | Backend | **MEDIUM** | Requires Setup | Use API Resources (`app/Http/Resources/*`) to sanitize and mask sensitive data (e.g. password hashes, secret keys). |
| **SEC-09** | Missing Centralized Exception Masking | Backend | **MEDIUM** | Requires Setup | In production, disable `APP_DEBUG=false` and return normalized JSON error envelopes without database stack traces or local paths. |
| **SEC-10** | Missing Environment Files & Ignore Rules | Both | **MEDIUM** | Mitigated | Create comprehensive `.gitignore` and `.env.example` templates in both projects without committing real credentials. |

---

## 2. Detailed OWASP API Security Review

### API1:2023 — Broken Object Level Authorization (BOLA)
- **Risk:** In trading and asset platforms, attackers modify record IDs (e.g., `order_id`, `wallet_id`, `ticket_id`) to view or cancel other users' trades.
- **Protection:** All resource controllers will query records scoped to the authenticated user via Eloquent relationships (e.g. `$request->user()->orders()->findOrFail($id)`).

### API2:2023 — Broken Authentication
- **Risk:** Weak password policies, credential stuffing, or leaked sessions.
- **Protection:** 
  - Password strength validation in Laravel (`Password::min(8)->letters()->mixedCase()->numbers()->symbols()`).
  - Passwords hashed using Argon2id or Bcrypt with cost factor 12.
  - Sanctum token revocation on logout.

### API3:2023 — Broken Object Property Level Authorization (Mass Assignment)
- **Risk:** Attackers submitting payload fields like `is_admin: true`, `balance: 999999`, or `kyc_tier: 3`.
- **Protection:** Strict whitelist validation in Laravel Form Requests. Explicit `$fillable` arrays on all Eloquent models.

### API4:2023 — Unrestricted Resource Consumption
- **Risk:** DoS attacks on order book endpoints or brute force on login.
- **Protection:** Strict rate limiting via Laravel's built-in `RateLimiter`:
  - `auth`: 5 attempts per minute per IP.
  - `api`: 60 requests per minute per IP.

### API5:2023 — Broken Function Level Authorization
- **Risk:** Standard customers invoking administrative endpoints (`/api/admin/users`, `/api/admin/withdrawals/review`).
- **Protection:** Custom `AdminMiddleware` verifying `$request->user()->role === 'admin'`.

### API6:2023 — Unrestricted Access to Sensitive Business Flows
- **Risk:** Automated bots spamming deposit confirmations or rapid-fire trade cancellations.
- **Protection:** Database transactions (`DB::transaction`) to ensure atomic balance updates and atomic order matching.

### API7:2023 — Server Side Request Forgery (SSRF)
- **Risk:** User-provided webhook URLs or external links in support messages.
- **Protection:** Disallow internal network requests, restrict URL validation to external HTTPS domains.

### API8:2023 — Security Misconfiguration
- **Risk:** `APP_DEBUG=true` in production, default encryption keys, verbose CORS.
- **Protection:** Generate strong 32-character application key (`php artisan key:generate`), configure precise CORS origins, ensure `APP_DEBUG=false` in production.

### API9:2023 — Improper Inventory Management
- **Risk:** Undocumented or unversioned endpoints.
- **Protection:** Standardized `/api/` routing structure documented in `docs/API_INVENTORY.md`.

### API10:2023 — Unsafe Consumption of APIs
- **Risk:** Trusting external data feeds without sanitization.
- **Protection:** Strict parsing and type casting of numeric balances, currency decimals, and dates.

---

## 3. Git History & Secret Leaks Audit
- Frontend Git log (`dd1cc90e352ee9bbe88b84cfd405253597a6ce37`) inspected for leaked private keys, API tokens, or production passwords.
- No live secrets were detected in the repository commit history. Only demonstration dummy credentials (`demo.trader@coinplus.io` / `CoinPlus2026!#`) were found in test files.
- `.gitignore` will be updated immediately to prevent future commits of `.env` files.
