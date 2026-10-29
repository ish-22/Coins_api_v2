<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Tests\TestCase;

class PasswordResetWorkflowTest extends TestCase
{
    public function test_forgot_password_validates_email_input(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_sends_notification_for_registered_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset_test_' . uniqid() . '@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user) {
                $mailMessage = $notification->toMail($user);
                $this->assertStringContainsString('/reset-password?token=', $mailMessage->actionUrl);
                return !empty($notification->token);
            }
        );
    }

    public function test_forgot_password_does_not_reveal_unregistered_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'unregistered_' . uniqid() . '@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        Notification::assertNothingSent();
    }

    public function test_reset_password_workflow_success_and_login(): void
    {
        $user = User::factory()->create([
            'email' => 'reset_flow_' . uniqid() . '@example.com',
            'password' => Hash::make('InitialPassword123!'),
        ]);

        // Generate valid token via Laravel PasswordBroker
        $token = PasswordBroker::broker()->createToken($user);

        // Attempt password reset with matching confirmation
        $newPassword = 'NewSecretPassword2026!';
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Verify password is updated in database
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));

        // Verify user can now log in with the new password
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ]);
        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        // Verify old password no longer works
        $failedLogin = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'InitialPassword123!',
        ]);
        $failedLogin->assertStatus(422);

        // Verify token cannot be reused
        $reuseResponse = $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'AnotherPassword2026!',
            'password_confirmation' => 'AnotherPassword2026!',
        ]);
        $reuseResponse->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'This password reset link is invalid or has expired.',
            ]);
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'bad_token_' . uniqid() . '@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'invalid-or-fake-token',
            'email' => $user->email,
            'password' => 'NewPassword2026!',
            'password_confirmation' => 'NewPassword2026!',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'This password reset link is invalid or has expired.',
            ]);
    }

    public function test_reset_password_validates_password_confirmation_and_min_length(): void
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'token' => 'some-token',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
