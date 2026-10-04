<?php

namespace App\Support;

use Illuminate\Http\Request;

class SensitiveInput
{
    /** @var list<string> */
    public const KEYS = [
        '_token',
        'api_key',
        'client_secret',
        'code',
        'current_password',
        'email_current_password',
        'new_password',
        'new_password_confirmation',
        'password',
        'password_confirmation',
        'private_key',
        'refresh_token',
        'secret',
        'token',
        'verification_code',
        'access_token',
    ];

    /** @return array<string, mixed> */
    public static function safeForFlash(Request $request): array
    {
        return $request->except(self::KEYS);
    }
}
