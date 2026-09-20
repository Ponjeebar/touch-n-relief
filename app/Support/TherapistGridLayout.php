<?php

namespace App\Support;

class TherapistGridLayout
{
    /**
     * Pick a column count that fills rows evenly and avoids a single orphan card.
     */
    public static function columnCount(int $count, int $maxPerRow = 5): int
    {
        if ($count <= 0) {
            return 1;
        }

        if ($count <= $maxPerRow) {
            return $count;
        }

        $cap = min($count, $maxPerRow);
        $best = $cap;
        $bestScore = PHP_INT_MAX;

        for ($cols = 2; $cols <= $cap; $cols++) {
            $remainder = $count % $cols;
            $rows = (int) ceil($count / $cols);

            if ($remainder === 1 && $rows > 1) {
                $score = 10_000 + $rows;
            } elseif ($remainder === 0) {
                $score = ($rows * 10) + $cols;
            } else {
                $score = 1_000 + (abs($cols - $remainder) * 10) + $rows;
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $cols;
            }
        }

        return $best;
    }

    /**
     * @return array{desktop: int, tablet: int, mobile: int}
     */
    public static function responsiveColumns(int $count, int $maxPerRow = 5): array
    {
        $desktop = self::columnCount($count, $maxPerRow);

        return [
            'desktop' => $desktop,
            'tablet' => min($desktop, 3),
            'mobile' => $count <= 1 ? 1 : min($desktop, 2),
        ];
    }
}
