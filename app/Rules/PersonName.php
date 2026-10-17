<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonName implements ValidationRule
{
    public const HTML_PATTERN = "\\p{L}[\\p{L}\\p{M} .'’\\-‐‑]*";

    public const MESSAGE = 'Enter a name starting with a letter. Use letters, spaces, apostrophes, hyphens, and periods only; numbers are not allowed.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match("/\\A\\p{L}[\\p{L}\\p{M} .'’\\x{2010}\\x{2011}-]*\\z/u", $value) !== 1) {
            $fail(self::MESSAGE);
        }
    }
}
