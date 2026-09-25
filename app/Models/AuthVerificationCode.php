<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuthVerificationCode extends Model
{
    use HasUuids;

    public const PURPOSE_REGISTRATION = 'registration';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $fillable = [
        'purpose',
        'email',
        'code_hash',
        'payload',
        'failed_attempts',
        'expires_at',
        'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
