<?php

namespace App\Support;

class MailDeliveryConfiguration
{
    public static function isReady(): bool
    {
        if (self::resendIsReady()) {
            return true;
        }

        $mailer = (string) config('mail.default', 'log');

        if (in_array($mailer, ['log', 'array'], true)) {
            return false;
        }

        if ($mailer !== 'smtp') {
            return true;
        }

        $smtp = (array) config('mail.mailers.smtp', []);

        if (filled($smtp['url'] ?? null)) {
            return true;
        }

        return filled($smtp['host'] ?? null)
            && filled($smtp['port'] ?? null)
            && filled($smtp['username'] ?? null)
            && filled($smtp['password'] ?? null);
    }

    public static function resendIsReady(): bool
    {
        return filled(config('services.resend.key'))
            && filled(config('services.resend.from_address'));
    }
}
