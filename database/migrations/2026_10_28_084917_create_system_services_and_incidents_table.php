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
        Schema::create('system_services', function (Blueprint $table) {
            $table->id();
            $table->string('service_id', 64)->unique();
            $table->string('name', 128);
            $table->enum('category', ['core', 'trading', 'finance', 'support'])->default('core');
            $table->enum('status', ['operational', 'degraded', 'maintenance', 'outage'])->default('operational');
            $table->text('description');
            $table->string('uptime', 16)->default('99.99%');
            $table->string('latency', 32)->default('20ms');
            $table->json('daily_uptime_history')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_id', 64)->unique();
            $table->string('title');
            $table->string('date_display', 64);
            $table->enum('severity', ['minor', 'major', 'critical', 'maintenance'])->default('minor');
            $table->enum('status', ['investigating', 'identified', 'monitoring', 'resolved'])->default('resolved');
            $table->json('affected_services')->nullable();
            $table->text('description');
            $table->text('resolution_summary');
            $table->json('updates')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('system_services');
    }
};
