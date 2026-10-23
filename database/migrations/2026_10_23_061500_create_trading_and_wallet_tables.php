<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->string('id', 32)->primary(); // e.g. bitcoin, ethereum, tether
            $table->string('symbol', 16)->unique(); // e.g. BTC, ETH, USDT
            $table->string('name', 64);
            $table->string('type', 20)->default('crypto'); // crypto, stablecoin
            $table->string('color', 16)->default('#F0B93A');
            $table->string('icon_bg', 16)->default('#F0B93A');
            $table->json('networks')->nullable();
            $table->decimal('price_usd', 18, 8)->default(0);
            $table->decimal('change_24h', 8, 4)->default(0);
            $table->decimal('volume_24h', 24, 4)->default(0);
            $table->boolean('deposit_enabled')->default(true);
            $table->boolean('withdraw_enabled')->default(true);
            $table->boolean('trading_enabled')->default(true);
            $table->decimal('min_deposit', 18, 8)->default(0.0001);
            $table->decimal('min_withdrawal', 18, 8)->default(0.001);
            $table->decimal('withdrawal_fee', 18, 8)->default(0.0005);
            $table->timestamps();
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset_id', 32);
            $table->decimal('available_balance', 24, 8)->default(0);
            $table->decimal('locked_balance', 24, 8)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'asset_id']);
            $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();
        });

        Schema::create('trading_pairs', function (Blueprint $table) {
            $table->string('id', 32)->primary(); // e.g. BTC-USDT
            $table->string('symbol', 32)->unique(); // e.g. BTC/USDT
            $table->string('base_asset', 16);
            $table->string('quote_asset', 16);
            $table->decimal('price', 18, 8)->default(0);
            $table->decimal('change_24h', 8, 4)->default(0);
            $table->decimal('high_24h', 18, 8)->default(0);
            $table->decimal('low_24h', 18, 8)->default(0);
            $table->decimal('volume_24h_base', 24, 4)->default(0);
            $table->decimal('volume_24h_quote', 24, 4)->default(0);
            $table->integer('price_decimals')->default(2);
            $table->integer('amount_decimals')->default(4);
            $table->decimal('min_amount', 18, 8)->default(0.0001);
            $table->decimal('maker_fee', 8, 6)->default(0.0002);
            $table->decimal('taker_fee', 8, 6)->default(0.0004);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 32)->primary(); // e.g. ord-881
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pair', 32); // BTC/USDT
            $table->enum('side', ['buy', 'sell']);
            $table->enum('type', ['limit', 'market', 'stop_limit']);
            $table->decimal('price', 18, 8)->default(0);
            $table->decimal('amount', 18, 8)->default(0);
            $table->decimal('filled', 18, 8)->default(0);
            $table->decimal('total', 24, 8)->default(0);
            $table->enum('status', ['open', 'pending', 'filled', 'cancelled'])->default('open');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->string('id', 32)->primary(); // e.g. tx-w1
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'withdrawal', 'trade_buy', 'trade_sell', 'earn_yield']);
            $table->string('asset', 16);
            $table->decimal('amount', 24, 8);
            $table->decimal('value_usd', 24, 8);
            $table->decimal('fee_usd', 18, 8)->default(0);
            $table->enum('status', ['completed', 'processing', 'failed'])->default('completed');
            $table->string('tx_hash')->nullable();
            $table->string('network')->nullable();
            $table->string('address')->nullable();
            $table->string('confirmations')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('trading_pairs');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('assets');
    }
};
