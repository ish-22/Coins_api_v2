# CoinPlus & Coins_api_v2 — Windows + XAMPP Local Setup Guide

This guide provides beginner-friendly, exact step-by-step instructions to run both the Laravel 11 API backend (`Coins_api_v2`) and the React + Vite frontend (`CoinPlus`) on Windows with XAMPP.

---

## 1. Prerequisites Checklist

Before running the application, ensure the following are installed and operational on Windows:
1. **XAMPP for Windows** (with Apache, PHP 8.2+, and MySQL/MariaDB).
2. **Composer 2.x** installed globally (`composer -v`).
3. **Node.js 20+ & npm 10+** installed globally (`node -v`, `npm -v`).
4. **Git for Windows** (`git --version`).

---

## 2. Verify PHP & MySQL Configuration

### Check CLI PHP vs XAMPP Apache PHP
Open PowerShell and verify:
```powershell
php -v
C:\xampp\php\php.exe -v
```
Both should report PHP 8.2.12 or higher.

### Required PHP Extensions
Ensure the `zip`, `pdo_mysql`, and `bcmath` extensions are enabled in `C:\xampp\php\php.ini`:
```ini
extension=zip
extension=pdo_mysql
extension=bcmath
extension=fileinfo
extension=mbstring
extension=openssl
```

---

## 3. Start XAMPP Services

1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** (runs on port 80/443).
3. Click **Start** next to **MySQL** (runs on port 3306).

---

## 4. MySQL Database Setup

A dedicated MySQL database `coinplus` and user `coinplus_app` have been configured:
- **Host:** `127.0.0.1`
- **Port:** `3306`
- **Database:** `coinplus`
- **Username:** `coinplus_app`
- **Password:** `CoinPlusSecurePass2026!`

To verify or recreate manually using the MySQL client:
```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS coinplus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE USER IF NOT EXISTS 'coinplus_app'@'127.0.0.1' IDENTIFIED BY 'CoinPlusSecurePass2026!';"
C:\xampp\mysql\bin\mysql.exe -u root -e "GRANT ALL PRIVILEGES ON coinplus.* TO 'coinplus_app'@'127.0.0.1';"
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE USER IF NOT EXISTS 'coinplus_app'@'localhost' IDENTIFIED BY 'CoinPlusSecurePass2026!';"
C:\xampp\mysql\bin\mysql.exe -u root -e "GRANT ALL PRIVILEGES ON coinplus.* TO 'coinplus_app'@'localhost';"
C:\xampp\mysql\bin\mysql.exe -u root -e "FLUSH PRIVILEGES;"
```

---

## 5. Backend Setup (`Coins_api_v2`)

Open a new PowerShell terminal and navigate to the backend directory:
```powershell
cd C:\xampp\htdocs\Coins_api_v2
```

### Install Dependencies
If not already installed:
```powershell
composer install
```

### Environment Configuration
Ensure `.env` exists with the following configuration:
```env
APP_NAME=CoinPlus
APP_ENV=local
APP_KEY=base64:4YDwM2Y2ac+YCzULHNviGfgeK/+OAJ7NyWRA6i2sbtg=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coinplus
DB_USERNAME=coinplus_app
DB_PASSWORD=CoinPlusSecurePass2026!
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

FRONTEND_URL=http://127.0.0.1:3000,http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:3000,localhost:3000
```

### Run Migrations & Seeders
```powershell
php artisan migrate --force
php artisan db:seed --force
```

### Run Automated Backend Tests
Verify the entire API pipeline:
```powershell
php artisan test tests/Feature/CoinPlusApiTest.php
```

### Start the Laravel API Server
```powershell
php artisan serve --port=8000
```
The API is now live at `http://127.0.0.1:8000/api`.

---

## 6. Frontend Setup (`CoinPlus`)

Open a second PowerShell terminal and navigate to the frontend app directory:
```powershell
cd C:\xampp\htdocs\CoinPlus
```

### Install Dependencies
```powershell
npm install
```

### Environment Configuration
Ensure `.env` exists:
```env
VITE_API_BASE_URL=http://127.0.0.1:8000/api
VITE_APP_NAME=CoinPlus
```

### Verify Production Build
```powershell
npm run build
```

### Start the Frontend Development Server
```powershell
npm run dev
```
Open your browser and navigate to:
**`http://localhost:3000`**

---

## 7. Pre-Configured Test Accounts

| Role | Email | Password | Access / Scope |
| :--- | :--- | :--- | :--- |
| **Trader / User** | `demo.trader@coinplus.io` | `CoinPlus2026!#` | Customer Portal, Dashboard, Spot Trading, Wallet, KYC, Settings |
| **Operator / Admin** | `operator@coinplus.io` | `SuperAdmin2026!#` | `/admin/login`, Operator Dashboard, KYC Queue, Risk Management |

---

## 8. Troubleshooting Common Errors

### 1. `SQLSTATE[HY000]: General error: 1273 Unknown collation: 'utf8mb4_0900_ai_ci'`
- **Cause:** MariaDB does not support the MySQL 8.0 `utf8mb4_0900_ai_ci` collation.
- **Fix:** In `.env`, ensure `DB_COLLATION=utf8mb4_unicode_ci` is set.

### 2. CORS `Cross-Origin Request Blocked`
- **Cause:** Frontend origin does not match `allowed_origins` in `config/cors.php`.
- **Fix:** Ensure `FRONTEND_URL=http://127.0.0.1:3000,http://localhost:3000` is set in backend `.env`.

### 3. Zip Archive Error During `composer install`
- **Cause:** `extension=zip` is commented out in `C:\xampp\php\php.ini`.
- **Fix:** Uncomment `extension=zip` in `C:\xampp\php\php.ini` and restart Apache/CLI.
