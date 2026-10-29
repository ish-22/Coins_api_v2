<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = env('FRONTEND_APP_URL', 'http://localhost:3000');
        if (!$frontendUrl || $frontendUrl === 'http://localhost:3000') {
            $configured = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
            if (str_contains($configured, 'http://localhost:3000')) {
                $frontendUrl = 'http://localhost:3000';
            } elseif (str_contains($configured, ',')) {
                $parts = explode(',', $configured);
                $frontendUrl = trim($parts[count($parts) - 1]);
            } else {
                $frontendUrl = trim($configured);
            }
        }
        $frontendUrl = rtrim($frontendUrl, '/');

        $resetUrl = $frontendUrl . '/reset-password?token=' . urlencode($this->token) . '&email=' . urlencode($notifiable->getEmailForPasswordReset());


        return (new MailMessage)
            ->subject('CoinPlus - Reset Your Password')
            ->greeting('Hello ' . ($notifiable->name ?? 'Trader') . ',')
            ->line('You are receiving this email because we received a password reset request for your CoinPlus account.')
            ->action('Reset Password', $resetUrl)
            ->line('This password reset link will expire in ' . config('auth.passwords.users.expire', 60) . ' minutes.')
            ->line('If you did not request a password reset, no further action is required and your account remains secure.');
    }
}
