<?php

namespace App\Support;

use Carbon\CarbonInterface;

class CustomerEligibility
{
    public const MINIMUM_AGE = 15;

    public static function latestEligibleBirthday(?CarbonInterface $now = null): string
    {
        return ($now ?? now())->copy()->subYears(self::MINIMUM_AGE)->toDateString();
    }

    public static function birthdayRule(): string
    {
        return 'before_or_equal:'.self::latestEligibleBirthday();
    }

    public static function birthdayMessage(): string
    {
        return 'You must be at least '.self::MINIMUM_AGE.' years old.';
    }
}
