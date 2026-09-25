<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>TOUCHnRELIEF Report</title>
    <style>
        @page { margin: 20px 26px 24px; }
        body { margin: 0; color: #203039; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 3px solid #04724d; padding-bottom: 9px; margin-bottom: 10px; }
        .header h1 { color: #04724d; font-size: 20px; }
        .header p { color: #647680; margin-top: 4px; }
        .generated { float: right; text-align: right; color: #647680; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px 10px; }
        .summary td { width: 33.33%; padding: 9px 11px; border: 1px solid #d9e5e3; background: #f5faf8; }
        .summary span { display: block; color: #647680; font-size: 9px; text-transform: uppercase; }
        .summary strong { display: block; color: #16382d; font-size: 16px; margin-top: 3px; }
        .columns { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px; }
        .columns > tbody > tr > td { width: 50%; vertical-align: top; }
        .charts { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 10px; }
        .charts td { width: 50%; vertical-align: top; border: 1px solid #dfe8eb; }
        .chart-title { padding: 6px 8px; color: #fff; background: #04724d; font-size: 11px; font-weight: bold; }
        .chart-image { padding: 5px 7px; }
        .chart-image img { display: block; width: 100%; height: auto; }
        .section { margin-bottom: 8px; page-break-inside: avoid; }
        .section h2 { padding: 6px 8px; color: #fff; background: #04724d; font-size: 11px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { padding: 4px 7px; border: 1px solid #dfe8eb; text-align: left; }
        table.data th { color: #425761; background: #eef4f4; font-size: 9px; }
        table.data td:last-child, table.data th:last-child { text-align: right; }
        .empty { color: #788991; text-align: center !important; }
        .section-note { padding: 4px 7px; border: 1px solid #dfe8eb; border-top: 0; color: #647680; font-size: 8px; }
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
        $trendRows = collect($trendLabels)->map(fn ($label, $index) => [
            'label' => (string) $label,
            'value' => (float) ($trendData[$index] ?? 0),
        ]);
        $activeTrendRows = $trendRows->filter(fn ($row) => $row['value'] > 0)->values();
        $serviceRows = collect($serviceLabels)->map(fn ($label, $index) => [
            'label' => (string) $label,
            'value' => (float) ($serviceTotals[$index] ?? 0),
        ]);
        $activeServiceRows = $serviceRows->filter(fn ($row) => $row['value'] > 0)->values();
        $therapistRows = collect($therapistHoursBreakdown ?? []);
        $activeTherapistRows = $therapistRows
            ->filter(fn ($therapist) => (float) ($therapist['hours'] ?? 0) > 0)
            ->values();
        $omittedTrendRows = $trendRows->count() - $activeTrendRows->count();
        $omittedServiceRows = $serviceRows->count() - $activeServiceRows->count();
        $omittedTherapistRows = $therapistRows->count() - $activeTherapistRows->count();
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
                    <tr><th>Active sales periods</th><td>{{ $activeTrendRows->count() }}</td></tr>
                    <tr><th>Services with revenue</th><td>{{ $activeServiceRows->count() }}</td></tr>
                </table>
            </div>
            <div class="section">
                <h2>Sales Activity</h2>
                <table class="data">
                    <thead><tr><th>Period</th><th>Sales</th></tr></thead>
                    <tbody>
                    @forelse ($activeTrendRows as $row)
                        <tr><td>{{ $row['label'] }}</td><td>PHP {{ number_format($row['value'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No sales recorded for this period</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if ($omittedTrendRows > 0)
                    <div class="section-note">{{ $omittedTrendRows }} zero-sales {{ Str::plural('period', $omittedTrendRows) }} omitted. The chart retains the complete timeline.</div>
                @endif
            </div>
        </td>
        <td>
            <div class="section">
                <h2>Service Revenue</h2>
                <table class="data">
                    <thead><tr><th>Service</th><th>Revenue</th></tr></thead>
                    <tbody>
                    @forelse ($activeServiceRows as $row)
                        <tr><td>{{ $row['label'] }}</td><td>PHP {{ number_format($row['value'], 2) }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No service revenue for this period</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if ($omittedServiceRows > 0)
                    <div class="section-note">{{ $omittedServiceRows }} {{ Str::plural('service', $omittedServiceRows) }} with no revenue omitted.</div>
                @endif
            </div>
            <div class="section">
                <h2>Therapist Service Hours</h2>
                <table class="data">
                    <thead><tr><th>Therapist</th><th>Hours</th></tr></thead>
                    <tbody>
                    @forelse ($activeTherapistRows as $therapist)
                        <tr><td>{{ $therapist['name'] ?? 'Unknown Therapist' }}</td><td>{{ number_format((float) ($therapist['hours'] ?? 0), 1) }} hrs</td></tr>
                    @empty
                        <tr><td colspan="2" class="empty">No therapist hours for this period</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if ($omittedTherapistRows > 0)
                    <div class="section-note">{{ $omittedTherapistRows }} {{ Str::plural('therapist', $omittedTherapistRows) }} with no recorded hours omitted.</div>
                @endif
            </div>
        </td>
    </tr></table>
    <div class="footer">TOUCHnRELIEF Appointment and Record Management System</div>
</body>
</html>
