<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE transactions ALTER COLUMN type TYPE VARCHAR(32) USING type::VARCHAR(32)");
            DB::statement("ALTER TABLE transactions ALTER COLUMN type SET DEFAULT 'deposit'");
        } else {
            DB::statement("ALTER TABLE transactions MODIFY type VARCHAR(32) NOT NULL DEFAULT 'deposit'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE transactions ALTER COLUMN type TYPE VARCHAR(32)");
        } else {
            DB::statement("ALTER TABLE transactions MODIFY type ENUM('deposit', 'withdrawal', 'trade_buy', 'trade_sell', 'earn_yield') NOT NULL DEFAULT 'deposit'");
        }
    }
};

