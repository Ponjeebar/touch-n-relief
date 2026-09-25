<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuthVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
        public readonly string $recipientName = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isRegistration = $this->purpose === 'registration';
        $data = [
            'name' => $this->recipientName,
            'code' => $this->code,
            'purposeLabel' => $isRegistration ? 'complete your registration' : 'reset your password',
            'expiresIn' => 10,
            'logoSrc' => 'cid:touch-n-relief-logo',
        ];

        return (new MailMessage)
            ->subject($isRegistration ? 'Verify your TouchNRelief registration' : 'Your TouchNRelief password reset code')
            ->view('emails.auth-verification-code', $data)
            ->text('emails.auth-verification-code-text', $data)
            ->withSymfonyMessage(static function ($message): void {
                $logo = public_path('images/dashboard/logo.png');
                if (is_file($logo)) {
                    $message->embedFromPath($logo, 'touch-n-relief-logo');
                }
            });
    }
}
