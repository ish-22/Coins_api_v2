<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class CoinPlusApiTest extends TestCase
{
    public function test_api_health_endpoint(): void
    {
        $response = $this->getJson('/api/health');
        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'operational']);
    }

    public function test_market_tickers_endpoint(): void
    {
        $response = $this->getJson('/api/markets/tickers');
        $response->assertStatus(200)
            ->assertJsonStructure(['pairs']);
    }

    public function test_trader_login_successful(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo.trader@coinplus.io',
            'password' => 'CoinPlus2026!#',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token', 'message']);
    }

    public function test_login_rejects_invalid_password(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'demo.trader@coinplus.io',
            'password' => 'WrongPassword123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_wallet_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/wallet/summary');
        $response->assertStatus(401);
    }

    public function test_authenticated_wallet_summary(): void
    {
        $user = User::where('email', 'demo.trader@coinplus.io')->first();
        $response = $this->actingAs($user)->getJson('/api/wallet/summary');

        $response->assertStatus(200)
            ->assertJsonStructure(['summary' => ['totalBalanceUsd', 'availableBalanceUsd']]);
    }

    public function test_admin_metrics_forbidden_for_regular_user(): void
    {
        $user = User::where('email', 'demo.trader@coinplus.io')->first();
        $response = $this->actingAs($user)->getJson('/api/admin/metrics');

        $response->assertStatus(403);
    }

    public function test_admin_metrics_allowed_for_operator(): void
    {
        $admin = User::where('email', 'operator@coinplus.io')->first();
        $response = $this->actingAs($admin)->getJson('/api/admin/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure(['metrics' => ['totalUsers', 'volume24hUsd']]);
    }

    public function test_admin_user_list_and_status_update(): void
    {
        $admin = User::where('email', 'operator@coinplus.io')->first();
        $user = User::where('email', 'demo.trader@coinplus.io')->first();

        $listResponse = $this->actingAs($admin)->getJson('/api/admin/users');
        $listResponse->assertStatus(200)
            ->assertJsonStructure(['users' => [['id', 'userRef', 'name', 'email', 'status']]]);

        $suspendResponse = $this->actingAs($admin)->putJson('/api/admin/users/' . $user->id . '/status', [
            'status' => 'suspended',
        ]);

        $suspendResponse->assertStatus(200)
            ->assertJsonFragment(['message' => 'User status updated successfully.']);

        $user->refresh();
        $this->assertSame('suspended', $user->status);

        $restoreResponse = $this->actingAs($admin)->putJson('/api/admin/users/' . $user->id . '/status', [
            'status' => 'active',
        ]);

        $restoreResponse->assertStatus(200);
        $user->refresh();
        $this->assertSame('active', $user->status);
    }
}
