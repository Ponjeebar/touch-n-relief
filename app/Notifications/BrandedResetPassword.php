<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class BrandedResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $resetUrl = $this->resetUrl($notifiable);
        $expiresIn = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $data = [
                'name' => trim((string) ($notifiable->name ?? '')),
                'resetUrl' => $resetUrl,
                'expiresIn' => $expiresIn,
                'logoSrc' => 'cid:touch-n-relief-logo',
        ];

        return (new MailMessage)
            ->subject('Reset your TouchNRelief password')
            ->view('emails.password-reset', $data)
            ->text('emails.password-reset-text', $data)
            ->withSymfonyMessage(static function ($message): void {
                $message->embedFromPath(public_path('images/dashboard/logo.png'), 'touch-n-relief-logo');
            });
    }
}
