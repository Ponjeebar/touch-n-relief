<?php

namespace App\Support;

class TherapistSpecialtyDisplay
{
    /**
     * @param  array<int, string>  $specialties
     * @return array{visible: array<int, string>, overflow: int}
     */
    public static function forCard(array $specialties, int $maxVisible = 2): array
    {
        $tags = array_values(array_filter(
            $specialties,
            static fn (mixed $tag): bool => trim((string) $tag) !== '' && ! str_starts_with(trim((string) $tag), '+'),
        ));

        $visible = array_slice($tags, 0, $maxVisible);
        $overflow = max(count($tags) - count($visible), 0);

        return [
            'visible' => $visible,
            'overflow' => $overflow,
        ];
    }

    /**
     * @param  array<int, string>  $specialties
     */
    public static function summaryLine(array $specialties, int $maxVisible = 2): string
    {
        $display = self::forCard($specialties, $maxVisible);
        $parts = $display['visible'];

        if ($display['overflow'] > 0) {
            $parts[] = '+'.$display['overflow'];
        }

        return implode(' · ', $parts);
    }
}
