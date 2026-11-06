<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify type column from enum to VARCHAR(32) to support balance_adjustment, deposit, withdrawal, etc.
        DB::statement("ALTER TABLE `transactions` MODIFY `type` VARCHAR(32) NOT NULL DEFAULT 'deposit'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `transactions` MODIFY `type` ENUM('deposit', 'withdrawal', 'trade_buy', 'trade_sell', 'earn_yield') NOT NULL DEFAULT 'deposit'");
    }
};
