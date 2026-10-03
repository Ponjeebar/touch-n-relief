<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Reporting">
    <title>Reporting</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/reporting.css') }}?v={{ filemtime(public_path('css/reporting.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @include('partials.staff-mobile-style')
</head>
<body class="reporting-page">
    <div class="app-shell">
        <div class="dashboard">
            <aside class="sidebar">
                <div class="brand">
                    <span class="brand-logo" aria-hidden="false">
                        <img src="{{ asset('images/dashboard/logo.png') }}" alt="TOUCHnRELIEF logo" class="brand-logo-img">
                    </span>
                    <span class="brand-copy">
                        <span class="brand-text">TOUCHnRELIEF</span>
                        <span class="brand-subtext">Appointment and Record Management System</span>
                    </span>
                </div>
                @include('partials.sidebar-nav', ['active' => 'reporting'])
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
                    </form>
                </div>
            </aside>

            <main class="main">
                <div class="topbar">
                    <div class="welcome">
                        @include('partials.topbar-panel-badge')
                        <h1>Reporting</h1>
                        <div class="subtitle" id="repPageSubtitle">{{ $pageSubtitle ?? 'Sales and user signups' }}</div>
                    </div>
                    <div class="rep-toolbar">
                        <div class="rep-period" id="repPeriod">
                            <button type="button" class="rep-period-btn {{ ($period ?? 'monthly') === 'daily' ? 'active' : '' }}" data-period="daily" aria-pressed="{{ ($period ?? 'monthly') === 'daily' ? 'true' : 'false' }}">Daily</button>
                            <button type="button" class="rep-period-btn {{ ($period ?? 'monthly') === 'weekly' ? 'active' : '' }}" data-period="weekly" aria-pressed="{{ ($period ?? 'monthly') === 'weekly' ? 'true' : 'false' }}">Weekly</button>
                            <button type="button" class="rep-period-btn {{ ($period ?? 'monthly') === 'monthly' ? 'active' : '' }}" data-period="monthly" aria-pressed="{{ ($period ?? 'monthly') === 'monthly' ? 'true' : 'false' }}">Monthly</button>
                            <button type="button" class="rep-period-btn {{ ($period ?? 'monthly') === 'yearly' ? 'active' : '' }}" data-period="yearly" aria-pressed="{{ ($period ?? 'monthly') === 'yearly' ? 'true' : 'false' }}">Yearly</button>
                            <button type="button" class="rep-period-btn {{ ($period ?? 'monthly') === 'custom' ? 'active' : '' }}" data-period="custom" aria-pressed="{{ ($period ?? 'monthly') === 'custom' ? 'true' : 'false' }}">Custom</button>
                            <span class="rep-period-divider" aria-hidden="true"></span>
                            <select id="repPeriodValue" class="rep-period-select" aria-label="Date selection"></select>
                        </div>
                        <form method="GET" action="{{ route('reporting.index') }}" class="rep-custom-range {{ ($period ?? '') === 'custom' ? 'is-visible' : '' }}" id="repCustomRange">
                            <input type="hidden" name="period" value="custom">
                            <label>From <input type="date" name="date_from" value="{{ $dateFrom ?? now()->startOfMonth()->toDateString() }}" max="{{ now()->toDateString() }}" required></label>
                            <label>To <input type="date" name="date_to" value="{{ $dateTo ?? now()->toDateString() }}" max="{{ now()->toDateString() }}" required></label>
                            <button type="submit">Apply</button>
                        </form>
                        <a href="{{ route('reporting.export', ['period' => $period ?? 'monthly', 'period_value' => $periodValue ?? null]) }}" class="rep-export-btn" id="repExportLink" data-page-transition="off">
                            <i class="bi bi-download" aria-hidden="true"></i>
                            <span>Export Excel</span>
                        </a>
                        <a href="{{ route('reporting.pdf', ['period' => $period ?? 'monthly', 'period_value' => $periodValue ?? null]) }}" class="rep-print-btn" id="repPdfLink" data-page-transition="off">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                            <span>Generate PDF</span>
                        </a>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('reporting.backup') }}" class="rep-backup-btn" id="repBackupLink" data-page-transition="off" title="Download a sensitive recovery export. Store it securely.">
                                <i class="bi bi-database-down" aria-hidden="true"></i>
                                <span>Recovery export</span>
                            </a>
                        @endif
                    </div>
                    <div class="right">
                        @include('partials.topbar-notifications')
                        @include('partials.topbar-settings')
                        @include('partials.topbar-profile')
                    </div>
                </div>

                @include('partials.status-toast')

                <div class="rep-print-heading" aria-hidden="true">
                    <strong>TOUCHnRELIEF Reporting</strong>
                    <span id="repPrintSubtitle">{{ $pageSubtitle ?? 'Sales and user signups' }}</span>
                    <small id="repPrintTimestamp">Printed {{ now()->format('M j, Y g:i A') }}</small>
                </div>

                <section class="rep-print-summary" aria-label="Printable report summary">
                    <div class="rep-print-stats">
                        <div><span id="repPrintPrimaryLabel">Sales</span><strong id="repPrintPrimaryValue">—</strong></div>
                        <div><span id="repPrintSecondaryLabel">New users</span><strong id="repPrintSecondaryValue">—</strong></div>
                        <div><span id="repPrintHoursLabel">Service hours</span><strong id="repPrintHoursValue">—</strong></div>
                    </div>
                    <div class="rep-print-details">
                        <article>
                            <h2>Highlights</h2>
                            <dl>
                                <div><dt id="repPrintPeakLabel">Best period</dt><dd id="repPrintBestDay">—</dd></div>
                                <div><dt>Top sales source</dt><dd id="repPrintBestService">—</dd></div>
                            </dl>
                        </article>
                        <article>
                            <h2>Sales breakdown</h2>
                            <table>
                                <thead><tr><th>Sales source</th><th>Amount</th></tr></thead>
                                <tbody id="repPrintServiceRows"><tr><td colspan="2">No sales recorded</td></tr></tbody>
                            </table>
                        </article>
                        <article>
                            <h2>Therapist service hours</h2>
                            <table>
                                <thead><tr><th>Therapist</th><th>Hours</th></tr></thead>
                                <tbody id="repPrintTherapistRows"><tr><td colspan="2">No therapist hours</td></tr></tbody>
                            </table>
                        </article>
                    </div>
                </section>

                <section class="rep-grid">
                    <section class="rep-metrics">
                        <article class="rep-metric rep-metric-primary rep-metric-daily">
                            <div class="rep-metric-top">
                                <div class="rep-icon" id="repPrimaryIconWrap"><i class="bi bi-{{ $primaryIcon ?? 'receipt' }}" id="repPrimaryIcon"></i></div>
                                <div class="rep-badge up" id="repPrimaryBadge">{{ $primaryBadge ?? 'Today' }}</div>
                            </div>
                            <div class="rep-label" id="repPrimaryLabel">{{ $primaryLabel ?? 'Daily Sales' }}</div>
                            <div class="rep-value" id="repPrimaryValue">₱{{ number_format((float) ($primaryAmount ?? $dailySales ?? 0), 2) }}</div>
                            <div class="rep-sub" id="repPrimarySub">{{ $primarySub ?? 'Total sales for today' }}</div>
                        </article>

                        <article class="rep-metric rep-metric-yearly">
                            <div class="rep-metric-top">
                                <div class="rep-icon soft" id="repSecondaryIconWrap"><i class="bi bi-{{ $secondaryIcon ?? 'calendar2-range' }}" id="repSecondaryIcon"></i></div>
                                <div class="rep-badge up" id="repSecondaryBadge">{{ $secondaryBadge ?? 'This year' }}</div>
                            </div>
                            <div class="rep-label" id="repSecondaryLabel">{{ $secondaryLabel ?? 'Yearly Users' }}</div>
                            <div class="rep-value rep-value-int" id="repSecondaryValue">{{ number_format((int) ($secondaryUserCount ?? $yearlyUsers ?? 0)) }}</div>
                            <div class="rep-sub" id="repSecondarySub">{{ $secondarySub ?? 'New users year-to-date' }}</div>
                        </article>

                        <article class="rep-metric rep-metric-hours">
                            <div class="rep-metric-top">
                                <div class="rep-icon soft" id="repHoursIconWrap"><i class="bi bi-{{ $hoursIcon ?? 'clock' }}" id="repHoursIcon"></i></div>
                                <div class="rep-badge up" id="repHoursBadge">{{ $hoursBadge ?? 'This month' }}</div>
                            </div>
                            <div class="rep-label" id="repHoursLabel">{{ $hoursLabel ?? 'Total Service Hours' }}</div>
                            <div class="rep-value rep-value-int" id="repHoursValue">{{ number_format((float) ($hoursValue ?? 0), 1) }} hrs</div>
                            <div class="rep-sub" id="repHoursSub">{{ $hoursSub ?? 'Service time logged month-to-date' }}</div>
                            <div class="rep-hours-list" id="repHoursList" aria-label="Therapist service hours">
                                @forelse (($therapistHoursBreakdown ?? []) as $item)
                                    <div class="rep-hours-row">
                                        <span>{{ $item['name'] ?? 'Unknown Therapist' }}</span>
                                        <strong>{{ number_format((float) ($item['hours'] ?? 0), 1) }} hrs</strong>
                                    </div>
                                @empty
                                    <div class="rep-hours-empty">No therapist hours yet</div>
                                @endforelse
                            </div>
                        </article>

                        <article class="rep-card rep-trend">
                            <div class="rep-card-head">
                                <div>
                                    <h3>Sales Trend</h3>
                                    <div class="muted" id="repTrendSubtitle">{{ $trendSubtitle ?? 'Last 30 days (sales)' }}</div>
                                </div>
                            </div>
                            <div class="rep-chart">
                                <canvas id="salesTrendChart" aria-label="Sales trend chart"></canvas>
                                <div class="rep-fallback" id="trendFallback" aria-hidden="true">
                                    @php
                                        $trendArr = $trendData instanceof \Illuminate\Support\Collection
                                            ? $trendData->values()->all()
                                            : (is_array($trendData) ? array_values($trendData) : []);
                                        // Safety: if $trendData is null/missing, ensure we always have an array for count()/foreach().
                                        $trendArr = $trendArr ?? [];
                                        $max = $trendArr === [] ? 0 : max(array_map(static fn ($v) => (float) $v, $trendArr));
                                    @endphp
                                    <div class="rep-bars" id="trendFallbackBars" style="--bars: {{ is_countable($trendArr ?? []) ? count($trendArr ?? []) : 0 }};">
                                        @foreach (($trendArr ?? []) as $i => $v)
                                            @php($h = $max > 0 ? ((float)$v / $max) * 100 : 0)
                                            <div class="rep-bar" title="{{ $trendLabels[$i] }}: ₱{{ number_format((float) $v, 2) }}">
                                                <span style="height: {{ $h }}%"></span>
                                                <small>{{ $trendLabels[$i] }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </article>
                    </section>

                    <aside class="rep-right">
                        <article class="rep-card rep-pie">
                            <div class="rep-card-head">
                                <div>
                                    <h3>Sales Mix</h3>
                                    <div class="muted" id="repMixMuted">Offerings and no-show fees ({{ $serviceLabel ?? 'This month' }})</div>
                                </div>
                                <div class="rep-pill" id="repMixPill">{{ $serviceLabel ?? 'This month' }}</div>
                            </div>
                            <div class="rep-pie-wrap">
                                <canvas id="servicePieChart" aria-label="Sales mix pie chart"></canvas>
                                @php($stops = collect($serviceSegments ?? [])->map(fn($s) => "{$s['color']} {$s['start']}% {$s['end']}%")->implode(', '))
                                <div class="rep-donut" id="pieFallback" style="--donut: conic-gradient({{ $stops ?: '#e7eef2 0% 100%' }});"></div>
                                @php($serviceSum = (float) ($serviceTotalSum ?? collect($serviceTotals ?? [])->sum()))
                                <div class="rep-donut-label" id="pieEmptyState" aria-hidden="true" style="{{ $serviceSum <= 0 ? '' : 'display:none' }}">
                                    <strong>No data</strong>
                                    <span>Add transactions to see breakdown</span>
                                </div>
                            </div>
                            <div class="rep-legend" id="serviceLegend"></div>
                        </article>

                        <article class="rep-card rep-mini">
                            <div class="rep-card-head">
                                <div>
                                    <h3>Quick Insights</h3>
                                    <div class="muted">Auto-generated</div>
                                </div>
                            </div>
                            <div class="rep-insights">
                                <div class="rep-insight">
                                    <span class="k" id="insightPeakLabel">{{ $insightPeakLabel ?? 'Best day' }}</span>
                                    <span class="v" id="bestDay">—</span>
                                </div>
                                <div class="rep-insight">
                                    <span class="k">Top sales source</span>
                                    <span class="v" id="bestService">—</span>
                                </div>
                            </div>
                        </article>
                    </aside>
                </section>

                <section class="rep-sales-monitoring" aria-label="Sales monitoring details">
                    <header class="rep-section-heading">
                        <div>
                            <h2>Collection summary</h2>
                            <p>Cash basis: verified payments collected during the selected period, less processed refunds.</p>
                        </div>
                    </header>
                    <div class="rep-financial-strip">
                        <div><span>Gross collections</span><strong id="repGrossCollections">₱{{ number_format((float) ($grossCollections ?? 0), 2) }}</strong></div>
                        <div><span>Processed refunds</span><strong id="repRefundTotal">₱{{ number_format((float) ($refundTotal ?? 0), 2) }}</strong></div>
                        <div><span>Net collections</span><strong id="repNetCollections">₱{{ number_format((float) ($primaryAmount ?? 0), 2) }}</strong></div>
                        <div><span>No-show fee sales</span><strong id="repNoShowFeeRevenue">₱{{ number_format((float) ($noShowFeeRevenue ?? 0), 2) }}</strong></div>
                        <div><span>Payments</span><strong id="repPaymentCount">{{ number_format((int) ($paymentCount ?? 0)) }}</strong></div>
                        <div><span>Average payment</span><strong id="repAveragePayment">₱{{ number_format((float) ($averagePayment ?? 0), 2) }}</strong></div>
                    </div>
                    <p class="rep-comparison" id="repComparison">
                        @if (($comparisonPercent ?? null) !== null)
                            {{ $comparisonPercent >= 0 ? '+' : '' }}{{ number_format((float) $comparisonPercent, 1) }}% versus {{ $comparisonLabel }}
                        @else
                            No comparable collections in {{ $comparisonLabel ?? 'the previous period' }}.
                        @endif
                    </p>

                    <div class="rep-detail-grid">
                        <article class="rep-data-panel">
                            <h3>Payment method reconciliation</h3>
                            <div class="rep-table-wrap">
                                <table>
                                    <thead><tr><th>Method</th><th>Payments</th><th>Gross</th><th>Refunds</th><th>Net</th></tr></thead>
                                    <tbody id="repPaymentMethods"></tbody>
                                </table>
                            </div>
                        </article>
                        <article class="rep-data-panel">
                            <h3>Outstanding balances</h3>
                            <p><strong id="repOutstandingTotal">₱{{ number_format((float) ($outstandingBalanceTotal ?? 0), 2) }}</strong> across <span id="repOutstandingCount">{{ (int) ($outstandingBalanceCount ?? 0) }}</span> bookings in this appointment range.</p>
                            <div class="rep-table-wrap">
                                <table>
                                    <thead><tr><th>Booking</th><th>Client</th><th>Service</th><th>Date</th><th>Balance</th></tr></thead>
                                    <tbody id="repOutstandingRows"></tbody>
                                </table>
                            </div>
                        </article>
                    </div>

                    <article class="rep-data-panel rep-ledger-panel">
                        <div class="rep-ledger-heading">
                            <div><h3>Payment ledger</h3><p>Every summary amount can be traced to these collected payments and refunds.</p></div>
                            <div class="rep-ledger-filters">
                                <input type="search" id="repLedgerSearch" placeholder="Search client, reference, or service" aria-label="Search payment ledger">
                                <select id="repLedgerType" aria-label="Filter ledger entry type">
                                    <option value="">All entry types</option>
                                    <option value="no_show_fee">No-show fees</option>
                                    <option value="initial_payment">Initial payments</option>
                                    <option value="balance_payment">Balance payments</option>
                                    <option value="membership_payment">Membership payments</option>
                                    <option value="refund">Refunds</option>
                                </select>
                            </div>
                        </div>
                        <div class="rep-table-wrap">
                            <table>
                                <thead><tr><th>Collected / refunded</th><th>Reference</th><th>Client</th><th>Service or membership</th><th>Type</th><th>Method</th><th>Net amount</th><th>Source</th></tr></thead>
                                <tbody id="repLedgerRows"></tbody>
                            </table>
                        </div>
                        <p class="rep-table-empty" id="repLedgerEmpty" hidden>No ledger entries match the selected filters.</p>
                    </article>

                    <details class="rep-calculation-notes">
                        <summary>How this report is calculated</summary>
                        <ul>
                            <li>Gross collections include verified initial, balance, and membership payments collected in the selected period.</li>
                            <li>Net collections equal gross collections minus processed refunds.</li>
                            <li>Pending payments and outstanding balances are excluded from collected sales.</li>
                            <li>Payments retained for no-show appointments are classified as no-show fee sales. Their unpaid balances are not treated as collectible.</li>
                            <li>PayMongo processing fees and payout timing are not deducted because fee data is not imported into this report.</li>
                            <li>Service hours use completed appointment dates; financial figures use payment collection time.</li>
                            <li>Historical entries marked as estimated use the booking creation time because the original collection time was unavailable.</li>
                        </ul>
                    </details>
                </section>
            </main>
        </div>
    </div>

    <script>
        const reportingDataUrl = @json(route('reporting.data'));
        const reportingExportUrl = @json(route('reporting.export'));
        const reportingPdfUrl = @json(route('reporting.pdf'));
        const pieColors = ['#0c9aa6', '#04724d', '#4f6d8c', '#2f9d62', '#c95a7b', '#f0a74d', '#7f8c8d', '#8e44ad'];
        let trendChart = null;
        let pieChart = null;
        let currentPeriod = @json($period ?? 'monthly');
        let currentPeriodValue = @json($periodValue ?? null);
        let currentDateFrom = @json($dateFrom ?? null);
        let currentDateTo = @json($dateTo ?? null);
        let availableYears = @json($availableYears ?? []);
        let availableWeeks = @json($availableWeeks ?? []);
        let currentLedgerRows = [];

        function copyPrintText(targetId, sourceId) {
            const target = document.getElementById(targetId);
            const source = document.getElementById(sourceId);
            if (target && source) target.textContent = source.textContent.trim();
        }

        function fillPrintRows(targetId, rows, emptyText) {
            const target = document.getElementById(targetId);
            if (!target) return;
            target.replaceChildren();
            if (!rows.length) {
                const row = target.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 2;
                cell.textContent = emptyText;
                return;
            }
            rows.forEach(([label, value]) => {
                const row = target.insertRow();
                row.insertCell().textContent = label;
                row.insertCell().textContent = value;
            });
        }

        function syncPrintableReport() {
            const printSubtitle = document.getElementById('repPrintSubtitle');
            const pageSubtitle = document.getElementById('repPageSubtitle');
            const printTimestamp = document.getElementById('repPrintTimestamp');
            if (printSubtitle && pageSubtitle) printSubtitle.textContent = pageSubtitle.textContent.trim();
            copyPrintText('repPrintPrimaryLabel', 'repPrimaryLabel');
            copyPrintText('repPrintPrimaryValue', 'repPrimaryValue');
            copyPrintText('repPrintSecondaryLabel', 'repSecondaryLabel');
            copyPrintText('repPrintSecondaryValue', 'repSecondaryValue');
            copyPrintText('repPrintHoursLabel', 'repHoursLabel');
            copyPrintText('repPrintHoursValue', 'repHoursValue');
            copyPrintText('repPrintPeakLabel', 'insightPeakLabel');
            copyPrintText('repPrintBestDay', 'bestDay');
            copyPrintText('repPrintBestService', 'bestService');

            const serviceRows = Array.from(document.querySelectorAll('#serviceLegend .rep-legend-row')).map((row) => {
                const name = row.querySelector('.name');
                const price = row.querySelector('.price')?.textContent.trim() || '—';
                return [(name?.textContent || '').replace(price, '').trim() || 'Service', price];
            });
            fillPrintRows('repPrintServiceRows', serviceRows, 'No sales recorded');

            const therapistRows = Array.from(document.querySelectorAll('#repHoursList .rep-hours-row')).map((row) => [
                row.querySelector('span')?.textContent.trim() || 'Therapist',
                row.querySelector('strong')?.textContent.trim() || '0.0 hrs',
            ]);
            fillPrintRows('repPrintTherapistRows', therapistRows, 'No therapist hours');

            if (printTimestamp) {
                printTimestamp.textContent = `Printed ${new Intl.DateTimeFormat(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(new Date())}`;
            }
        }

        const weekdayOptions = [
            ['monday', 'Monday'],
            ['tuesday', 'Tuesday'],
            ['wednesday', 'Wednesday'],
            ['thursday', 'Thursday'],
            ['friday', 'Friday'],
            ['saturday', 'Saturday'],
            ['sunday', 'Sunday'],
        ];
        const monthOptions = [
            ['january', 'January'],
            ['february', 'February'],
            ['march', 'March'],
            ['april', 'April'],
            ['may', 'May'],
            ['june', 'June'],
            ['july', 'July'],
            ['august', 'August'],
            ['september', 'September'],
            ['october', 'October'],
            ['november', 'November'],
            ['december', 'December'],
        ];

        function defaultPeriodValue(period) {
            if (period === 'daily') {
                const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                return days[new Date().getDay()];
            }
            if (period === 'monthly') {
                return monthOptions[new Date().getMonth()][0];
            }
            if (period === 'weekly') {
                return availableWeeks[0]?.value || '';
            }
            return String(new Date().getFullYear());
        }

        function periodValueOptions(period) {
            if (period === 'daily') return weekdayOptions;
            if (period === 'weekly') return availableWeeks.map((week) => [week.value, week.label]);
            if (period === 'monthly') return monthOptions;
            const years = Array.isArray(availableYears) && availableYears.length
                ? availableYears
                : [new Date().getFullYear()];
            return years.map((y) => [String(y), String(y)]);
        }

        function resolveDailyDate(weekdayKey) {
            const weekdayMap = {
                sunday: 0,
                monday: 1,
                tuesday: 2,
                wednesday: 3,
                thursday: 4,
                friday: 5,
                saturday: 6,
            };
            const target = weekdayMap[String(weekdayKey || '').toLowerCase()];
            if (target === undefined) return new Date();
            const date = new Date();
            const diff = (date.getDay() - target + 7) % 7;
            date.setHours(0, 0, 0, 0);
            date.setDate(date.getDate() - diff);
            return date;
        }

        function dailyOptionLabel(weekdayKey, weekdayLabel) {
            const date = resolveDailyDate(weekdayKey);
            const dateText = date.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
            return `${weekdayLabel} — ${dateText}`;
        }

        function populatePeriodSelect(period, selectedValue) {
            const select = document.getElementById('repPeriodValue');
            if (!select) return;
            select.hidden = period === 'custom';
            document.getElementById('repCustomRange')?.classList.toggle('is-visible', period === 'custom');
            if (period === 'custom') return;
            const options = periodValueOptions(period);
            const fallback = defaultPeriodValue(period);
            const value = selectedValue || fallback;
            select.innerHTML = options.map(([val, label]) => {
                const text = period === 'daily' ? dailyOptionLabel(val, label) : label;
                return `<option value="${val}"${val === value ? ' selected' : ''}>${text}</option>`;
            }).join('');
            currentPeriodValue = value;
        }

        function buildReportingQuery(period, periodValue, dateFrom = currentDateFrom, dateTo = currentDateTo) {
            const params = new URLSearchParams();
            params.set('period', period || 'monthly');
            if (periodValue) params.set('period_value', periodValue);
            if (period === 'custom' && dateFrom && dateTo) {
                params.set('date_from', dateFrom);
                params.set('date_to', dateTo);
            }
            return params.toString();
        }

        async function fetchReportingPayload(period, periodValue, dateFrom = currentDateFrom, dateTo = currentDateTo) {
            const res = await fetch(`${reportingDataUrl}?${buildReportingQuery(period, periodValue, dateFrom, dateTo)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) throw new Error('Request failed');
            return res.json();
        }

        function pushReportingHistory(period, periodValue) {
            const loc = new URL(window.location.href);
            loc.searchParams.set('period', period);
            if (periodValue) {
                loc.searchParams.set('period_value', periodValue);
            } else {
                loc.searchParams.delete('period_value');
            }
            if (period === 'custom' && currentDateFrom && currentDateTo) {
                loc.searchParams.set('date_from', currentDateFrom);
                loc.searchParams.set('date_to', currentDateTo);
            } else {
                loc.searchParams.delete('date_from');
                loc.searchParams.delete('date_to');
            }
            window.history.pushState({ period, periodValue }, '', loc);
        }

        function setText(id, text) {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        function fmtCount(v) {
            const n = Math.round(Number(v) || 0);
            try {
                return new Intl.NumberFormat('en-PH', { maximumFractionDigits: 0 }).format(n);
            } catch {
                return String(n);
            }
        }

        function money(v) {
            try {
                return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(v) || 0);
            } catch {
                return `₱${Number(v || 0).toFixed(2)}`;
            }
        }

        function hours(v) {
            const n = Number(v) || 0;
            return `${n.toFixed(1)} hrs`;
        }

        function renderTherapistHoursList(items) {
            const listEl = document.getElementById('repHoursList');
            if (!listEl) return;
            const rows = Array.isArray(items) ? items : [];
            if (!rows.length) {
                listEl.innerHTML = '<div class="rep-hours-empty">No therapist hours yet</div>';
                return;
            }
            listEl.innerHTML = rows.map((row) => {
                const name = String(row?.name || 'Unknown Therapist');
                const value = Number(row?.hours || 0).toFixed(1);
                return `<div class="rep-hours-row"><span>${name}</span><strong>${value} hrs</strong></div>`;
            }).join('');
        }

        function appendTableRow(body, values, classes = []) {
            const row = document.createElement('tr');
            values.forEach((value, index) => {
                const cell = document.createElement('td');
                cell.textContent = String(value ?? '—');
                if (classes[index]) cell.className = classes[index];
                row.appendChild(cell);
            });
            body.appendChild(row);
        }

        function renderPaymentMethods(rows) {
            const body = document.getElementById('repPaymentMethods');
            if (!body) return;
            body.replaceChildren();
            if (!Array.isArray(rows) || !rows.length) {
                appendTableRow(body, ['No payment activity', '', '', '', '']);
                return;
            }
            rows.forEach((row) => appendTableRow(body, [
                row.method,
                fmtCount(row.paymentCount),
                money(row.gross),
                money(row.refunds),
                money(row.net),
            ]));
        }

        function renderOutstandingBalances(rows) {
            const body = document.getElementById('repOutstandingRows');
            if (!body) return;
            body.replaceChildren();
            if (!Array.isArray(rows) || !rows.length) {
                appendTableRow(body, ['No outstanding balances', '', '', '', '']);
                return;
            }
            rows.forEach((row) => appendTableRow(body, [
                row.bookingReference,
                row.client,
                row.service,
                row.appointmentDate,
                money(row.balance),
            ], ['', '', '', '', row.isOverdue ? 'is-overdue' : '']));
        }

        function renderLedgerRows() {
            const body = document.getElementById('repLedgerRows');
            const empty = document.getElementById('repLedgerEmpty');
            if (!body) return;
            const query = (document.getElementById('repLedgerSearch')?.value || '').trim().toLowerCase();
            const type = document.getElementById('repLedgerType')?.value || '';
            const rows = currentLedgerRows.filter((row) => {
                const matchesType = !type || row.type === type;
                const haystack = [row.bookingReference, row.client, row.service, row.reference, row.paymentMethod]
                    .join(' ')
                    .toLowerCase();
                return matchesType && (!query || haystack.includes(query));
            });

            body.replaceChildren();
            rows.forEach((row) => appendTableRow(body, [
                row.occurredAt,
                row.bookingReference,
                row.client,
                row.service,
                row.typeLabel,
                row.paymentMethod,
                money(row.netAmount),
                row.isEstimated ? 'Historical estimate' : row.recordedBy,
            ], ['', '', '', '', '', '', Number(row.netAmount) < 0 ? 'is-refund' : '', '']));
            if (empty) empty.hidden = rows.length > 0;
        }

        function setPeriodButtonsActive(period) {
            document.querySelectorAll('#repPeriod .rep-period-btn').forEach((btn) => {
                const on = btn.dataset.period === period;
                btn.classList.toggle('active', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function updateExportLink(period, periodValue) {
            [
                [document.getElementById('repExportLink'), reportingExportUrl],
                [document.getElementById('repPdfLink'), reportingPdfUrl],
            ].forEach(([link, baseUrl]) => {
                if (!link || !baseUrl) return;
                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('period', period || 'monthly');
                if (periodValue) {
                    url.searchParams.set('period_value', periodValue);
                } else {
                    url.searchParams.delete('period_value');
                }
                if (period === 'custom' && currentDateFrom && currentDateTo) {
                    url.searchParams.set('date_from', currentDateFrom);
                    url.searchParams.set('date_to', currentDateTo);
                } else {
                    url.searchParams.delete('date_from');
                    url.searchParams.delete('date_to');
                }
                link.href = url.toString();
            });
        }

        function buildTrendFallbackHtml(labels, data) {
            const nums = data.map((x) => Number(x) || 0);
            const max = Math.max(...nums, 0);
            return labels.map((label, i) => {
                const v = nums[i] || 0;
                const h = max > 0 ? (v / max) * 100 : 0;
                return `<div class="rep-bar" title="${String(label)}: ${money(v)}"><span style="height:${h}%"></span><small>${String(label)}</small></div>`;
            }).join('');
        }

        function applyInsights(trendLabels, trendData, serviceLabels, serviceTotals) {
            const tl = Array.isArray(trendLabels) ? trendLabels : [];
            const td = Array.isArray(trendData) ? trendData : [];
            const sl = Array.isArray(serviceLabels) ? serviceLabels : [];
            const st = Array.isArray(serviceTotals) ? serviceTotals : [];
            const max = td.length ? Math.max(...td.map((x) => Number(x) || 0), 0) : 0;
            const maxIdx = td.findIndex((x) => (Number(x) || 0) === max);
            const bestPeriod = maxIdx >= 0 ? tl[maxIdx] : '—';
            const bestServiceTotal = st.length ? Math.max(...st.map((x) => Number(x) || 0), 0) : 0;
            const bestServiceIdx = st.findIndex((x) => (Number(x) || 0) === bestServiceTotal);
            const bestService = bestServiceIdx >= 0 ? (sl[bestServiceIdx] || '—') : '—';
            setText('bestDay', bestPeriod || '—');
            setText('bestService', bestService || '—');
        }

        function renderPieLegend(legendEl, serviceLabels, serviceTotals) {
            if (!legendEl) return;
            const sl = Array.isArray(serviceLabels) ? serviceLabels : [];
            const st = Array.isArray(serviceTotals) ? serviceTotals : [];
            legendEl.innerHTML = sl.map((label, i) => {
                const val = st[i] || 0;
                return `
                    <div class="rep-legend-row">
                        <span class="dot" style="background:${pieColors[i % pieColors.length]}"></span>
                        <span class="name">${label || 'Service'} <span class="price">${money(val)}</span></span>
                    </div>
                `;
            }).join('');
        }

        function updatePieFallbackStyle(segments) {
            const el = document.getElementById('pieFallback');
            if (!el) return;
            const stops = (segments || []).map((s) => `${s.color} ${s.start}% ${s.end}%`).join(', ');
            el.style.setProperty('--donut', stops ? `conic-gradient(${stops})` : 'conic-gradient(#e7eef2 0% 100%)');
        }

        function renderCharts(trendLabels, trendData, serviceLabels, serviceTotals) {
            const tl = Array.isArray(trendLabels) ? trendLabels : [];
            const td = Array.isArray(trendData) ? trendData : [];
            const sl = Array.isArray(serviceLabels) ? serviceLabels : [];
            const st = Array.isArray(serviceTotals) ? serviceTotals : [];
            const chartTotals = st.map((value) => Math.max(Number(value) || 0, 0));
            const trendEl = document.getElementById('salesTrendChart');
            const trendFallback = document.getElementById('trendFallback');
            const trendFallbackBars = document.getElementById('trendFallbackBars');
            const pieEl = document.getElementById('servicePieChart');
            const legendEl = document.getElementById('serviceLegend');
            const pieFallback = document.getElementById('pieFallback');

            if (trendChart) {
                try {
                    trendChart.destroy();
                } catch (e) { /* ignore */ }
                trendChart = null;
            }
            if (pieChart) {
                try {
                    pieChart.destroy();
                } catch (e) { /* ignore */ }
                pieChart = null;
            }

            if (trendEl && window.Chart) {
                if (trendFallback) trendFallback.style.display = 'none';
                trendChart = new Chart(trendEl, {
                    type: 'bar',
                    data: {
                        labels: tl,
                        datasets: [{
                            label: 'Sales',
                            data: td,
                            backgroundColor: (ctx) => {
                                const value = ctx.raw || 0;
                                return value > 0 ? 'rgba(12, 154, 166, 0.85)' : 'rgba(12, 154, 166, 0.18)';
                            },
                            borderRadius: 10,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                bodyFont: { size: 14, family: 'Poppins' },
                                titleFont: { size: 13, family: 'Poppins' },
                                callbacks: { label: (c) => money(c.raw) }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: {
                                    font: { size: 12, family: 'Poppins' },
                                    maxRotation: 45,
                                    minRotation: 0,
                                    autoSkip: true
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(0,0,0,0.06)' },
                                ticks: {
                                    font: { size: 12, family: 'Poppins' },
                                    callback: (v) => money(v)
                                }
                            }
                        }
                    }
                });
            } else if (trendFallback && trendFallbackBars) {
                trendFallback.style.display = '';
                trendFallbackBars.style.setProperty('--bars', String(tl.length));
                trendFallbackBars.innerHTML = buildTrendFallbackHtml(tl, td);
            }

            if (pieEl && window.Chart) {
                if (pieFallback) pieFallback.style.display = 'none';
                pieChart = new Chart(pieEl, {
                    type: 'doughnut',
                    data: {
                        labels: sl,
                        datasets: [{
                            data: chartTotals,
                            backgroundColor: sl.map((_, i) => pieColors[i % pieColors.length]),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                bodyFont: { size: 14, family: 'Poppins' },
                                titleFont: { size: 13, family: 'Poppins' },
                                callbacks: {
                                    label: (ctx) => {
                                        const v = ctx.raw ?? 0;
                                        return `${ctx.label || 'Service'}: ${money(v)}`;
                                    }
                                }
                            }
                        },
                        cutout: '68%'
                    }
                });
            } else if (pieFallback) {
                pieFallback.style.display = '';
            }

            renderPieLegend(legendEl, sl, st);
        }

        function applyReportingPayload(d) {
            d = d && typeof d === 'object' ? d : {};
            if (d.period) currentPeriod = d.period;
            if (d.periodValue) currentPeriodValue = d.periodValue;
            if (d.dateFrom) currentDateFrom = d.dateFrom;
            if (d.dateTo) currentDateTo = d.dateTo;
            if (Array.isArray(d.availableYears) && d.availableYears.length) {
                availableYears = d.availableYears;
            }
            if (Array.isArray(d.availableWeeks) && d.availableWeeks.length) {
                availableWeeks = d.availableWeeks;
            }
            populatePeriodSelect(currentPeriod, currentPeriodValue);
            setText('repPageSubtitle', d.pageSubtitle || '');
            setText('repTrendSubtitle', d.trendSubtitle || '');
            setText('repMixMuted', `Offerings and no-show fees (${d.serviceLabel || ''})`);
            setText('repMixPill', d.serviceLabel || '');

            setText('repPrimaryBadge', d.primaryBadge || '');
            setText('repPrimaryLabel', d.primaryLabel || '');
            const pv = document.getElementById('repPrimaryValue');
            if (pv) {
                pv.className = 'rep-value';
                pv.textContent = money(d.primaryAmount ?? 0);
            }
            setText('repPrimarySub', d.primarySub || '');
            setText('repGrossCollections', money(d.grossCollections ?? 0));
            setText('repRefundTotal', money(d.refundTotal ?? 0));
            setText('repNetCollections', money(d.primaryAmount ?? 0));
            setText('repNoShowFeeRevenue', money(d.noShowFeeRevenue ?? 0));
            setText('repPaymentCount', fmtCount(d.paymentCount ?? 0));
            setText('repAveragePayment', money(d.averagePayment ?? 0));
            setText('repOutstandingTotal', money(d.outstandingBalanceTotal ?? 0));
            setText('repOutstandingCount', fmtCount(d.outstandingBalanceCount ?? 0));
            const comparisonText = d.comparisonPercent === null || d.comparisonPercent === undefined
                ? 'No comparable collections in ' + (d.comparisonLabel || 'the previous period') + '.'
                : (Number(d.comparisonPercent) >= 0 ? '+' : '')
                    + Number(d.comparisonPercent).toFixed(1)
                    + '% versus '
                    + (d.comparisonLabel || 'the previous period');
            setText('repComparison', comparisonText);
            renderPaymentMethods(d.paymentMethodBreakdown || []);
            renderOutstandingBalances(d.outstandingBalances || []);
            currentLedgerRows = Array.isArray(d.ledgerRows) ? d.ledgerRows : [];
            renderLedgerRows();
            const pi = document.getElementById('repPrimaryIcon');
            if (pi) pi.className = `bi bi-${d.primaryIcon || 'receipt'}`;

            setText('repSecondaryBadge', d.secondaryBadge || '');
            setText('repSecondaryLabel', d.secondaryLabel || '');
            const sv = document.getElementById('repSecondaryValue');
            if (sv) {
                sv.className = 'rep-value rep-value-int';
                sv.textContent = fmtCount(d.secondaryUserCount ?? 0);
            }
            setText('repSecondarySub', d.secondarySub || '');
            const si = document.getElementById('repSecondaryIcon');
            if (si) si.className = `bi bi-${d.secondaryIcon || 'calendar2-range'}`;

            setText('repHoursBadge', d.hoursBadge || '');
            setText('repHoursLabel', d.hoursLabel || '');
            const hv = document.getElementById('repHoursValue');
            if (hv) {
                hv.className = 'rep-value rep-value-int';
                hv.textContent = hours(d.hoursValue ?? 0);
            }
            setText('repHoursSub', d.hoursSub || '');
            const hi = document.getElementById('repHoursIcon');
            if (hi) hi.className = `bi bi-${d.hoursIcon || 'clock'}`;
            renderTherapistHoursList(d.therapistHoursBreakdown || []);

            setText('insightPeakLabel', d.insightPeakLabel || '');

            const pieEmpty = document.getElementById('pieEmptyState');
            if (pieEmpty) {
                pieEmpty.style.display = (d.serviceTotalSum ?? 0) <= 0 ? '' : 'none';
            }

            updatePieFallbackStyle(d.serviceSegments || []);
            const trendLabels = Array.isArray(d.trendLabels) ? d.trendLabels : [];
            const trendData = Array.isArray(d.trendData) ? d.trendData : [];
            const serviceLabels = Array.isArray(d.serviceLabels) ? d.serviceLabels : [];
            const serviceTotals = Array.isArray(d.serviceTotals) ? d.serviceTotals : [];
            applyInsights(trendLabels, trendData, serviceLabels, serviceTotals);
            renderCharts(trendLabels, trendData, serviceLabels, serviceTotals);
            updateExportLink(d.period || currentPeriod, d.periodValue || currentPeriodValue);
        }

        async function loadReporting(period, periodValue, updateHistory = true) {
            const wrap = document.getElementById('repPeriod');
            if (!wrap || !reportingDataUrl) return;

            wrap.classList.add('rep-period--busy');
            try {
                const data = await fetchReportingPayload(period, periodValue);
                setPeriodButtonsActive(data.period || period);
                applyReportingPayload(data);
                if (updateHistory) {
                    pushReportingHistory(currentPeriod, currentPeriodValue);
                }
            } catch (err) {
                console.error(err);
            } finally {
                wrap?.classList.remove('rep-period--busy');
            }
        }

        document.getElementById('repPeriod')?.addEventListener('click', async (e) => {
            const btn = e.target.closest('.rep-period-btn');
            if (!btn) return;
            const period = btn.dataset.period;
            if (!period || period === currentPeriod) return;

            if (period === 'custom') {
                currentPeriod = 'custom';
                setPeriodButtonsActive('custom');
                populatePeriodSelect('custom', '');
                document.querySelector('#repCustomRange input[name="date_from"]')?.focus();
                return;
            }

            const nextValue = defaultPeriodValue(period);
            await loadReporting(period, nextValue);
        });

        document.getElementById('repPeriodValue')?.addEventListener('change', async (e) => {
            const value = e.target.value;
            if (!value || value === currentPeriodValue) return;
            await loadReporting(currentPeriod, value);
        });

        document.getElementById('repLedgerSearch')?.addEventListener('input', renderLedgerRows);
        document.getElementById('repLedgerType')?.addEventListener('change', renderLedgerRows);

        window.addEventListener('popstate', () => {
            const loc = new URL(window.location.href);
            let p = loc.searchParams.get('period') || 'monthly';
            if (!['daily', 'weekly', 'monthly', 'yearly', 'custom'].includes(p)) {
                p = 'monthly';
            }
            currentDateFrom = loc.searchParams.get('date_from');
            currentDateTo = loc.searchParams.get('date_to');
            const pv = loc.searchParams.get('period_value') || defaultPeriodValue(p);
            if (p === currentPeriod && pv === currentPeriodValue) return;
            currentPeriod = p;
            currentPeriodValue = pv;
            setPeriodButtonsActive(p);
            populatePeriodSelect(p, pv);
            if (!reportingDataUrl) return;
            fetchReportingPayload(p, pv)
                .then((data) => applyReportingPayload(data))
                .catch(() => {});
        });

        try {
            const bootstrap = @json($reportingBootstrap ?? []);
            populatePeriodSelect(currentPeriod, bootstrap.periodValue || currentPeriodValue || defaultPeriodValue(currentPeriod));
            applyReportingPayload(bootstrap);
        } catch (err) {
            console.error(err);
        }
    </script>
</body>
</html>
