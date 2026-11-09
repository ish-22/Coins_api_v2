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
            DB::statement("ALTER TABLE support_tickets ALTER COLUMN status TYPE VARCHAR(32) USING status::VARCHAR(32)");
            DB::statement("ALTER TABLE support_tickets ALTER COLUMN status SET DEFAULT 'open'");
            DB::statement("ALTER TABLE support_tickets ALTER COLUMN category TYPE VARCHAR(32) USING category::VARCHAR(32)");
            DB::statement("ALTER TABLE support_tickets ALTER COLUMN category SET DEFAULT 'trading'");
        } else {
            DB::statement("ALTER TABLE support_tickets MODIFY status VARCHAR(32) NOT NULL DEFAULT 'open'");
            DB::statement("ALTER TABLE support_tickets MODIFY category VARCHAR(32) NOT NULL DEFAULT 'trading'");
        }

        // 2. Add is_read column to support_messages if it doesn't already exist
        if (!Schema::hasColumn('support_messages', 'is_read')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->boolean('is_read')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_messages', 'is_read')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->dropColumn('is_read');
            });
        }
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            DB::statement("ALTER TABLE support_tickets MODIFY status ENUM('open', 'in_progress', 'waiting_user', 'resolved') NOT NULL DEFAULT 'open'");
        }
    }
};
