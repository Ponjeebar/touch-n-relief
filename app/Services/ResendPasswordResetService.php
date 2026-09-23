<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ResendPasswordResetService
{
    public function send(User $user, string $token): void
    {
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], false));
        $expiresIn = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $logoPath = public_path('images/dashboard/logo.png');
        $hasLogo = is_file($logoPath) && is_readable($logoPath);
        $data = [
            'name' => trim((string) $user->name),
            'resetUrl' => $resetUrl,
            'expiresIn' => $expiresIn,
            'logoSrc' => $hasLogo ? 'cid:touch-n-relief-logo' : null,
        ];
        $fromAddress = trim((string) config('services.resend.from_address'));
        $fromName = trim((string) config('services.resend.from_name', 'TouchNRelief'));
        $from = $fromName !== '' ? $fromName.' <'.$fromAddress.'>' : $fromAddress;

        $payload = [
            'from' => $from,
            'to' => [$user->getEmailForPasswordReset()],
            'subject' => 'Reset your TouchNRelief password',
            'html' => view('emails.password-reset', $data)->render(),
            'text' => view('emails.password-reset-text', $data)->render(),
        ];

        if ($hasLogo) {
            $payload['attachments'] = [[
                'filename' => 'touch-n-relief-logo.png',
                'content' => base64_encode((string) file_get_contents($logoPath)),
                'content_type' => 'image/png',
                'content_id' => 'touch-n-relief-logo',
            ]];
        }

        $response = Http::withToken((string) config('services.resend.key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://api.resend.com/emails', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Email provider rejected the password reset request with status '.$response->status().'.');
        }
    }
}
