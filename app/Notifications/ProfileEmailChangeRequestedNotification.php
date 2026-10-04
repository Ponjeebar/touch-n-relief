<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfileEmailChangeRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $newEmail,
        public readonly string $recipientName = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('TouchNRelief email change requested')
            ->greeting($this->recipientName !== '' ? 'Hello '.$this->recipientName.',' : 'Hello,')
            ->line('A request was made to change your TouchNRelief account email to '.$this->newEmail.'.')
            ->line('Your current email remains active until the new address is verified.')
            ->line('If you did not request this change, keep your current email and contact the spa administrator.');
    }
}
