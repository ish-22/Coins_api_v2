<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `support_tickets` MODIFY `category` VARCHAR(32) NOT NULL DEFAULT 'trading'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `support_tickets` MODIFY `category` ENUM('trading', 'account', 'deposits', 'withdrawals', 'kyc', 'security') NOT NULL DEFAULT 'trading'");
    }
};

