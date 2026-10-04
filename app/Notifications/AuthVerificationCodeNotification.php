<?php

namespace App\Notifications;

use App\Models\AuthVerificationCode;
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
        $subject = match ($this->purpose) {
            AuthVerificationCode::PURPOSE_REGISTRATION => 'Verify your TouchNRelief registration',
            AuthVerificationCode::PURPOSE_EMAIL_CHANGE => 'Verify your new TouchNRelief email address',
            default => 'Your TouchNRelief password reset code',
        };
        $data = [
            'name' => $this->recipientName,
            'code' => $this->code,
            'purposeLabel' => match ($this->purpose) {
                AuthVerificationCode::PURPOSE_REGISTRATION => 'complete your registration',
                AuthVerificationCode::PURPOSE_EMAIL_CHANGE => 'confirm your new email address',
                default => 'reset your password',
            },
            'expiresIn' => 10,
            'logoSrc' => 'cid:touch-n-relief-logo',
        ];

        return (new MailMessage)
            ->subject($subject)
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
