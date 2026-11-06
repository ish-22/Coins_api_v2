<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_name')->default('Administrator');
            $table->string('admin_email')->default('admin@coinplus.io');
            $table->string('title');
            $table->text('message');
            $table->string('type', 32)->default('system'); // deposit, kyc, trading, account, security, system
            $table->string('priority', 16)->default('normal'); // normal, high, urgent
            $table->string('target_type', 32)->default('all'); // all, individual, selected
            $table->integer('target_count')->default(1);
            $table->string('target_summary');
            $table->json('target_user_ids')->nullable();
            $table->string('link_text')->nullable();
            $table->string('link_href')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_dispatches');
    }
};

