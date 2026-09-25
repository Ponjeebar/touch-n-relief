<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class NotRecentlyUsedPassword implements ValidationRule
{
    public function __construct(private readonly User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $hashes = collect([$this->user->getAuthPassword()])
            ->merge($this->user->passwordHistories()->latest()->limit(5)->pluck('password'));

        if ($hashes->contains(fn (string $hash): bool => Hash::check((string) $value, $hash))) {
            $fail('Choose a password you have not used for this account before.');
        }
    }
}
