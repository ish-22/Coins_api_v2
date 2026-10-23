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
        Schema::table('users', function (Blueprint $table) {
            $table->string('uid', 32)->nullable()->unique()->after('id');
            $table->string('display_name')->nullable()->after('name');
            $table->string('full_name')->nullable()->after('display_name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('country', 64)->nullable()->after('phone');
            $table->string('country_code', 8)->nullable()->after('country');
            $table->string('role', 20)->default('user')->after('country_code');
            $table->string('status', 20)->default('active')->after('role');
            $table->string('kyc_tier', 32)->default('Tier 0')->after('status');
            $table->string('vip_level', 20)->default('VIP 1')->after('kyc_tier');
            $table->string('avatar_initials', 8)->nullable()->after('vip_level');
            $table->string('language', 32)->default('English (US)')->after('avatar_initials');
            $table->string('time_zone', 64)->default('UTC-05:00 (Eastern Time)')->after('language');
            $table->string('display_currency', 10)->default('USD')->after('time_zone');
            $table->string('date_format', 20)->default('YYYY-MM-DD')->after('display_currency');
            $table->string('default_dashboard_view', 32)->default('pro_terminal')->after('date_format');
            $table->string('theme_preference', 32)->default('black_gold')->after('default_dashboard_view');
            $table->boolean('two_factor_enabled')->default(false)->after('theme_preference');
            $table->integer('passkeys_count')->default(0)->after('two_factor_enabled');
            $table->string('anti_phishing_code', 32)->nullable()->after('passkeys_count');
            $table->json('notifications_config')->nullable()->after('anti_phishing_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uid', 'display_name', 'full_name', 'phone', 'country', 'country_code',
                'role', 'status', 'kyc_tier', 'vip_level', 'avatar_initials',
                'language', 'time_zone', 'display_currency', 'date_format',
                'default_dashboard_view', 'theme_preference', 'two_factor_enabled',
                'passkeys_count', 'anti_phishing_code', 'notifications_config'
            ]);
        });
    }
};
