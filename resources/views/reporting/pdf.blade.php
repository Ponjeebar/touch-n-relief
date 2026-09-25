<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>TOUCHnRELIEF Report</title>
    <style>
        @page { margin: 24px 30px; }
        body { margin: 0; color: #203039; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 3px solid #04724d; padding-bottom: 12px; margin-bottom: 16px; }
        .header h1 { color: #04724d; font-size: 22px; }
        .header p { color: #647680; margin-top: 4px; }
        .generated { float: right; text-align: right; color: #647680; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 16px; }
        .summary td { width: 33.33%; padding: 12px; border: 1px solid #d9e5e3; background: #f5faf8; }
        .summary span { display: block; color: #647680; font-size: 9px; text-transform: uppercase; }
        .summary strong { display: block; color: #16382d; font-size: 18px; margin-top: 4px; }
        .columns { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 0 -10px; }
        .columns > tbody > tr > td { width: 50%; vertical-align: top; }
        .charts { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 0 -10px 16px; }
        .charts td { width: 50%; vertical-align: top; border: 1px solid #dfe8eb; }
        .chart-title { padding: 7px 9px; color: #fff; background: #04724d; font-size: 12px; font-weight: bold; }
        .chart-image { padding: 8px; }
        .chart-image img { display: block; width: 100%; height: auto; }
        .section { margin-bottom: 14px; page-break-inside: avoid; }
        .section h2 { padding: 7px 9px; color: #fff; background: #04724d; font-size: 12px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { padding: 6px 8px; border: 1px solid #dfe8eb; text-align: left; }
        table.data th { color: #425761; background: #eef4f4; font-size: 9px; }
        table.data td:last-child, table.data th:last-child { text-align: right; }
        .empty { color: #788991; text-align: center !important; }
        .footer { position: fixed; right: 0; bottom: -12px; left: 0; color: #788991; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    @php
        $trendLabels = $trendLabels ?? [];
        $trendData = $trendData ?? [];
        $serviceLabels = $serviceLabels ?? [];
        $serviceTotals = $serviceTotals ?? [];
        $bestTrend = collect($trendData)->map(fn ($value) => (float) $value)->max() ?? 0;
        $bestTrendIndex = collect($trendData)->search($bestTrend);
        $bestService = collect($serviceTotals)->map(fn ($value) => (float) $value)->max() ?? 0;
        $bestServiceIndex = collect($serviceTotals)->search($bestService);
    @endphp
    <div class="header">
        <div class="generated">Generated {{ now()->format('M j, Y g:i A') }}</div>
        <h1>TOUCHnRELIEF Generated Report</h1>
        <p>{{ $pageSubtitle ?? 'Sales and new user signups' }}</p>
    </div>

    <table class="summary"><tr>
        <td><span>{{ $primaryLabel ?? 'Sales' }}</span><strong>PHP {{ number_format((float) ($primaryAmount ?? 0), 2) }}</strong></td>
        <td><span>{{ $secondaryLabel ?? 'New Users' }}</span><strong>{{ number_format((int) ($secondaryUserCount ?? 0)) }}</strong></td>
        <td><span>{{ $hoursLabel ?? 'Service Hours' }}</span><strong>{{ number_format((float) ($hoursValue ?? 0), 1) }} hrs</strong></td>
    </tr></table>

    <table class="charts"><tr>
        <td>
            <div class="chart-title">Sales Trend</div>
            <div class="chart-image"><img src="{{ $salesTrendChart }}" alt="Sales trend graph"></div>
        </td>
        <td>
            <div class="chart-title">Service Revenue</div>
            <div class="chart-image"><img src="{{ $serviceRevenueChart }}" alt="Service revenue graph"></div>
        </td>
    </tr></table>

    <table class="columns"><tr>
        <td>
            <div class="section">
                <h2>Highlights</h2>
                <table class="data">
                    <tr><th>{{ $insightPeakLabel ?? 'Best period' }}</th><td>{{ $bestTrend > 0 && $bestTrendIndex !== false ? ($trendLabels[$bestTrendIndex] ?? '—') : 'No sales recorded' }}</td></tr>
                    <tr><th>Best service</th><td>{{ $bestService > 0 && $bestServiceIndex !== false ? ($serviceLabels[$bestServiceIndex] ?? '—') : 'No sales recorded' }}</td></tr>
                </table>
            </div>
            <div class="section">
                <h2>Sales Trend</h2>
                <table class="data">
                    <thead><tr><th>Period</th><th>Sales</th></tr></thead>
                    <tbody>
                    @forelse ($trendLabels as $index => $label)
                        <tr><td>{{ $label }}</td><td>PHP {{ number_format((float) ($trendData[$index] ?? 0), 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No sales data</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </td>
        <td>
            <div class="section">
                <h2>Service Revenue</h2>
                <table class="data">
                    <thead><tr><th>Service</th><th>Revenue</th></tr></thead>
                    <tbody>
                    @forelse ($serviceLabels as $index => $label)
                        <tr><td>{{ $label }}</td><td>PHP {{ number_format((float) ($serviceTotals[$index] ?? 0), 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No service revenue</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="section">
                <h2>Therapist Service Hours</h2>
                <table class="data">
                    <thead><tr><th>Therapist</th><th>Hours</th></tr></thead>
                    <tbody>
                    @forelse (($therapistHoursBreakdown ?? []) as $therapist)
                        <tr><td>{{ $therapist['name'] ?? 'Unknown Therapist' }}</td><td>{{ number_format((float) ($therapist['hours'] ?? 0), 1) }} hrs</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No therapist hours</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </td>
    </tr></table>
    <div class="footer">TOUCHnRELIEF Appointment and Record Management System</div>
</body>
</html>
