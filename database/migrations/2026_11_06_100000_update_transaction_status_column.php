<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change status column to VARCHAR(32) to support pending, confirming, completed, failed, rejected
        DB::statement("ALTER TABLE `transactions` MODIFY `status` VARCHAR(32) NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `transactions` MODIFY `status` ENUM('completed', 'processing', 'failed') NOT NULL DEFAULT 'completed'");
    }
};

