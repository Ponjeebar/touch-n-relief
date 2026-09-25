<?php

namespace App\Services;

use App\Models\AuthVerificationCode;
use App\Notifications\AuthVerificationCodeNotification;
use App\Support\MailDeliveryConfiguration;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthVerificationCodeService
{
    public const EXPIRY_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    /** @param array<string, mixed> $payload */
    public function issue(string $email, string $purpose, array $payload, string $recipientName = ''): AuthVerificationCode
    {
        $email = strtolower(trim($email));
        $code = (string) random_int(100000, 999999);
        $verification = AuthVerificationCode::create([
            'purpose' => $purpose,
            'email' => $email,
            'code_hash' => Hash::make($code),
            'payload' => $payload,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
            'last_sent_at' => now(),
        ]);

        try {
            $this->deliver($email, $code, $purpose, $recipientName);
        } catch (\Throwable $exception) {
            $verification->delete();
            throw $exception;
        }

        AuthVerificationCode::query()
            ->where('purpose', $purpose)
            ->where('email', $email)
            ->whereKeyNot($verification->getKey())
            ->delete();

        return $verification;
    }

    public function verify(string $id, string $purpose, string $code): AuthVerificationCode
    {
        $verification = AuthVerificationCode::query()->find($id);

        if (! $verification || $verification->purpose !== $purpose || $verification->expires_at->isPast()) {
            $verification?->delete();
            throw ValidationException::withMessages(['code' => 'This verification code has expired. Request a new code.']);
        }

        if ($verification->failed_attempts >= self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => 'Too many incorrect attempts. Request a new code.']);
        }

        if (! Hash::check($code, $verification->code_hash)) {
            $verification->increment('failed_attempts');
            $remaining = max(0, self::MAX_ATTEMPTS - $verification->fresh()->failed_attempts);
            throw ValidationException::withMessages(['code' => $remaining > 0
                ? 'Incorrect code. '.$remaining.' attempt'.($remaining === 1 ? '' : 's').' remaining.'
                : 'Too many incorrect attempts. Request a new code.']);
        }

        return $verification;
    }

    private function deliver(string $email, string $code, string $purpose, string $recipientName): void
    {
        if (! MailDeliveryConfiguration::resendIsReady()) {
            Notification::route('mail', $email)
                ->notify(new AuthVerificationCodeNotification($code, $purpose, $recipientName));

            return;
        }

        $isRegistration = $purpose === AuthVerificationCode::PURPOSE_REGISTRATION;
        $data = [
            'name' => $recipientName,
            'code' => $code,
            'purposeLabel' => $isRegistration ? 'complete your registration' : 'reset your password',
            'expiresIn' => self::EXPIRY_MINUTES,
            'logoSrc' => null,
        ];
        $fromAddress = trim((string) config('services.resend.from_address'));
        $fromName = trim((string) config('services.resend.from_name', 'TouchNRelief'));

        $response = Http::withToken((string) config('services.resend.key'))
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->post('https://api.resend.com/emails', [
                'from' => $fromName !== '' ? $fromName.' <'.$fromAddress.'>' : $fromAddress,
                'to' => [$email],
                'subject' => $isRegistration ? 'Verify your TouchNRelief registration' : 'Your TouchNRelief password reset code',
                'html' => view('emails.auth-verification-code', $data)->render(),
                'text' => view('emails.auth-verification-code-text', $data)->render(),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Email provider rejected the verification message with status '.$response->status().'.');
        }
    }
}
