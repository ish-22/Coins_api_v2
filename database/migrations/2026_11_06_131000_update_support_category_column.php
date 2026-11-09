<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE support_tickets ALTER COLUMN category TYPE VARCHAR(32) USING category::VARCHAR(32)");
            DB::statement("ALTER TABLE support_tickets ALTER COLUMN category SET DEFAULT 'trading'");
        } else {
            DB::statement("ALTER TABLE support_tickets MODIFY category VARCHAR(32) NOT NULL DEFAULT 'trading'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            DB::statement("ALTER TABLE support_tickets MODIFY category ENUM('trading', 'account', 'deposits', 'withdrawals', 'kyc', 'security') NOT NULL DEFAULT 'trading'");
        }
    }
};

