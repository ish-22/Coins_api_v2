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

        // 1. Drop PostgreSQL check constraints that restrict category or status
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS support_tickets_category_check");
            DB::statement("ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS support_tickets_status_check");
            DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_status_check");
            DB::statement("ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_check");
        }

        // 2. Add attachment columns to support_tickets if not exists
        if (!Schema::hasColumn('support_tickets', 'attachment_name')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->string('attachment_name')->nullable();
                $table->text('attachment_url')->nullable();
            });
        }

        // 3. Add attachment columns to support_messages if not exists
        if (!Schema::hasColumn('support_messages', 'attachment_name')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->string('attachment_name')->nullable();
                $table->text('attachment_url')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('support_tickets', 'attachment_name')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->dropColumn(['attachment_name', 'attachment_url']);
            });
        }

        if (Schema::hasColumn('support_messages', 'attachment_name')) {
            Schema::table('support_messages', function (Blueprint $table) {
                $table->dropColumn(['attachment_name', 'attachment_url']);
            });
        }
    }
};
