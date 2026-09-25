<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Tests\TestCase;

class ReportingPdfLayoutTest extends TestCase
{
    public function test_sparse_report_omits_empty_detail_rows_and_fits_on_one_page(): void
    {
        $transparentChart = 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="180"></svg>'
        );
        $payload = [
            'pageSubtitle' => 'September 2026 — sales and new user signups',
            'primaryLabel' => 'Monthly sales',
            'primaryAmount' => 199,
            'secondaryLabel' => 'New users',
            'secondaryUserCount' => 16,
            'hoursLabel' => 'Total service hours',
            'hoursValue' => 4.3,
            'trendLabels' => ['Sep 01', 'Sep 22', 'Sep 23', 'Sep 24'],
            'trendData' => [0, 2, 195, 2],
            'serviceLabels' => ['Deep Tissue', 'Swedish Massage', 'Thai Massage'],
            'serviceTotals' => [0, 85, 110],
            'therapistHoursBreakdown' => [
                ['name' => 'Liza Reyes', 'hours' => 3],
                ['name' => 'Carlos Mendoza', 'hours' => 0],
            ],
            'salesTrendChart' => $transparentChart,
            'serviceRevenueChart' => $transparentChart,
        ];

        $html = view('reporting.pdf', $payload)->render();

        $this->assertStringContainsString('<th>Active sales periods</th><td>3</td>', $html);
        $this->assertStringContainsString('zero-sales period omitted', $html);
        $this->assertStringNotContainsString('Deep Tissue', $html);
        $this->assertStringNotContainsString('Carlos Mendoza', $html);
        $this->assertStringContainsString('Swedish Massage', $html);
        $this->assertStringContainsString('Liza Reyes', $html);

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape')->output();

        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }
}
