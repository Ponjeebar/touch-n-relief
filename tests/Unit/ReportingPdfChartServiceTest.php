<?php

namespace Tests\Unit;

use App\Services\ReportingPdfChartService;
use PHPUnit\Framework\TestCase;

class ReportingPdfChartServiceTest extends TestCase
{
    public function test_it_generates_embedded_svg_charts_from_report_data(): void
    {
        $charts = new ReportingPdfChartService;

        $trend = $charts->salesTrend(['Sep 24', 'Sep 25'], [85, 114]);
        $services = $charts->serviceRevenue(['Swedish Massage', 'Thai Massage'], [85, 114]);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $trend);
        $this->assertStringContainsString('<svg', $this->decode($trend));
        $this->assertStringContainsString('Sep 25', $this->decode($trend));
        $this->assertStringContainsString('Swedish Massage', $this->decode($services));
        $this->assertStringContainsString('PHP 114.00', $this->decode($services));
    }

    public function test_negative_refund_values_are_preserved_in_both_charts(): void
    {
        $charts = new ReportingPdfChartService;
        foreach ([$charts->salesTrend(['Refund day', 'Collection day'], [-100, 50]), $charts->serviceRevenue(['Swedish Massage', 'Foot Reflexology'], [-100, 50])] as $uri) {
            $svg = $this->decode($uri);
            $this->assertStringContainsString('PHP -100', $svg);
            $this->assertDoesNotMatchRegularExpression('/(?:width|height)="-/', $svg);
        }
    }

    private function decode(string $uri): string
    {
        return (string) base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
    }
}
