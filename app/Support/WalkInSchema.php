<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class WalkInSchema
{
    private static ?bool $hasWalkInColumn = null;

    public static function hasWalkInColumn(): bool
    {
        if (self::$hasWalkInColumn === null) {
            self::$hasWalkInColumn = Schema::hasTable('users')
                && Schema::hasColumn('users', 'is_walk_in');
        }

        return self::$hasWalkInColumn;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function markWalkIn(array $attributes): array
    {
        if (! self::hasWalkInColumn()) {
            return $attributes;
        }

        $attributes['is_walk_in'] = true;

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function markRegistered(array $attributes): array
    {
        if (! self::hasWalkInColumn()) {
            return $attributes;
        }

        $attributes['is_walk_in'] = false;

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function stripWalkInIfMissing(array $attributes): array
    {
        if (! self::hasWalkInColumn()) {
            unset($attributes['is_walk_in']);
        }

        return $attributes;
    }
}
