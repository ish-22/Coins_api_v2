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
            DB::statement("ALTER TABLE transactions ALTER COLUMN status TYPE VARCHAR(32) USING status::VARCHAR(32)");
            DB::statement("ALTER TABLE transactions ALTER COLUMN status SET DEFAULT 'pending'");
        } else {
            DB::statement("ALTER TABLE transactions MODIFY status VARCHAR(32) NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE transactions ALTER COLUMN status TYPE VARCHAR(32)");
        } else {
            DB::statement("ALTER TABLE transactions MODIFY status ENUM('completed', 'processing', 'failed') NOT NULL DEFAULT 'completed'");
        }
    }
};

