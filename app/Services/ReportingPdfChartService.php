<?php

namespace App\Services;

class ReportingPdfChartService
{
    /** @param list<string> $labels @param list<int|float|string|null> $values */
    public function salesTrend(array $labels, array $values): string
    {
        $width = 720;
        $height = 230;
        $left = 58;
        $top = 18;
        $right = 18;
        $bottom = 42;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $numbers = $this->numbers($values, count($labels));
        $maximum = max(array_merge($numbers, [1.0]));
        $count = max(count($labels), 1);
        $slotWidth = $plotWidth / $count;
        $barWidth = max(min($slotWidth * 0.62, 34), 2);
        $labelEvery = max((int) ceil($count / 12), 1);

        $svg = $this->startSvg($width, $height);
        for ($step = 0; $step <= 4; $step++) {
            $y = $top + ($plotHeight * $step / 4);
            $value = $maximum * (1 - $step / 4);
            $svg .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#dce8e5" stroke-width="1"/>', $left, $y, $width - $right, $y);
            $svg .= sprintf('<text x="%d" y="%.1f" text-anchor="end" font-size="9" fill="#647680">%s</text>', $left - 7, $y + 3, $this->moneyTick($value));
        }

        foreach ($labels as $index => $label) {
            $value = $numbers[$index] ?? 0;
            $barHeight = $maximum > 0 ? ($value / $maximum) * $plotHeight : 0;
            $x = $left + ($slotWidth * $index) + (($slotWidth - $barWidth) / 2);
            $y = $top + $plotHeight - $barHeight;
            $svg .= sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="2" fill="#0c9aa6"/>', $x, $y, $barWidth, $barHeight);
            if ($index % $labelEvery === 0 || $index === count($labels) - 1) {
                $svg .= sprintf('<text x="%.1f" y="%d" text-anchor="middle" font-size="8" fill="#52666f">%s</text>', $x + $barWidth / 2, $height - 18, $this->escape((string) $label));
            }
        }

        if (count($labels) === 0) {
            $svg .= '<text x="360" y="116" text-anchor="middle" font-size="13" fill="#788991">No sales data for this period</text>';
        }

        return $this->dataUri($svg.'</svg>');
    }

    /** @param list<string> $labels @param list<int|float|string|null> $values */
    public function serviceRevenue(array $labels, array $values): string
    {
        $width = 720;
        $rowHeight = 28;
        $top = 16;
        $bottom = 12;
        $labelWidth = 170;
        $right = 70;
        $height = max(110, $top + max(count($labels), 1) * $rowHeight + $bottom);
        $numbers = $this->numbers($values, count($labels));
        $maximum = max(array_merge($numbers, [1.0]));
        $plotWidth = $width - $labelWidth - $right;
        $colors = ['#04724d', '#0c9aa6', '#4f6d8c', '#2f9d62', '#c95a7b', '#f0a74d', '#7f8c8d', '#8e44ad'];
        $svg = $this->startSvg($width, $height);

        foreach ($labels as $index => $label) {
            $value = $numbers[$index] ?? 0;
            $y = $top + ($index * $rowHeight);
            $barWidth = $maximum > 0 ? ($value / $maximum) * $plotWidth : 0;
            $svg .= sprintf('<text x="%d" y="%.1f" text-anchor="end" font-size="9" fill="#425761">%s</text>', $labelWidth - 8, $y + 14, $this->escape((string) $label));
            $svg .= sprintf('<rect x="%d" y="%.1f" width="%d" height="17" rx="3" fill="#eef4f4"/>', $labelWidth, $y, $plotWidth);
            $svg .= sprintf('<rect x="%d" y="%.1f" width="%.1f" height="17" rx="3" fill="%s"/>', $labelWidth, $y, $barWidth, $colors[$index % count($colors)]);
            $svg .= sprintf('<text x="%d" y="%.1f" font-size="9" fill="#16382d">PHP %s</text>', $width - $right + 8, $y + 13, number_format($value, 2));
        }

        if (count($labels) === 0) {
            $svg .= '<text x="360" y="58" text-anchor="middle" font-size="13" fill="#788991">No service revenue for this period</text>';
        }

        return $this->dataUri($svg.'</svg>');
    }

    private function startSvg(int $width, int $height): string
    {
        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"><rect width="100%%" height="100%%" fill="#ffffff"/>', $width, $height, $width, $height);
    }

    /** @param list<int|float|string|null> $values @return list<float> */
    private function numbers(array $values, int $count): array
    {
        $numbers = [];
        for ($index = 0; $index < $count; $index++) {
            $numbers[] = max((float) ($values[$index] ?? 0), 0);
        }

        return $numbers;
    }

    private function moneyTick(float $value): string
    {
        return $value >= 1000 ? 'PHP '.number_format($value / 1000, 1).'k' : 'PHP '.number_format($value, 0);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function dataUri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
