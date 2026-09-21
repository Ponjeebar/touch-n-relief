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
<body>
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
                            <button type="button" class="rep-period-btn {{ ($period ?? 'monthly') === 'monthly' ? 'active' : '' }}" data-period="monthly" aria-pressed="{{ ($period ?? 'monthly') === 'monthly' ? 'true' : 'false' }}">Monthly</button>
                            <button type="button" class="rep-period-btn {{ ($period ?? 'monthly') === 'yearly' ? 'active' : '' }}" data-period="yearly" aria-pressed="{{ ($period ?? 'monthly') === 'yearly' ? 'true' : 'false' }}">Yearly</button>
                            <span class="rep-period-divider" aria-hidden="true"></span>
                            <select id="repPeriodValue" class="rep-period-select" aria-label="Date selection"></select>
                        </div>
                        <a href="{{ route('reporting.export', ['period' => $period ?? 'monthly', 'period_value' => $periodValue ?? null]) }}" class="rep-export-btn" id="repExportLink">
                            <i class="bi bi-download" aria-hidden="true"></i>
                            <span>Export report</span>
                        </a>
                        <button type="button" class="rep-print-btn" id="repPrintBtn">
                            <i class="bi bi-printer" aria-hidden="true"></i>
                            <span>Print report</span>
                        </button>
                        @if (auth()->user()->isAdmin())
                            <a href="{{ route('reporting.backup') }}" class="rep-backup-btn">
                                <i class="bi bi-database-down" aria-hidden="true"></i>
                                <span>Backup data</span>
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
                                <div><dt>Best service</dt><dd id="repPrintBestService">—</dd></div>
                            </dl>
                        </article>
                        <article>
                            <h2>Service revenue</h2>
                            <table>
                                <thead><tr><th>Service</th><th>Amount</th></tr></thead>
                                <tbody id="repPrintServiceRows"><tr><td colspan="2">No service revenue</td></tr></tbody>
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
                                    <h3>Service Sales</h3>
                                    <div class="muted" id="repMixMuted">All services ({{ $serviceLabel ?? 'This month' }})</div>
                                </div>
                                <div class="rep-pill" id="repMixPill">{{ $serviceLabel ?? 'This month' }}</div>
                            </div>
                            <div class="rep-pie-wrap">
                                <canvas id="servicePieChart" aria-label="Service sales pie chart"></canvas>
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
                                    <span class="k">Best service</span>
                                    <span class="v" id="bestService">—</span>
                                </div>
                            </div>
                        </article>
                    </aside>
                </section>
            </main>
        </div>
    </div>

    <script>
        const reportingDataUrl = @json(route('reporting.data'));
        const reportingExportUrl = @json(route('reporting.export'));
        const printReportButton = document.getElementById('repPrintBtn');
        const pieColors = ['#0c9aa6', '#04724d', '#4f6d8c', '#2f9d62', '#c95a7b', '#f0a74d', '#7f8c8d', '#8e44ad'];
        let trendChart = null;
        let pieChart = null;
        let currentPeriod = @json($period ?? 'monthly');
        let currentPeriodValue = @json($periodValue ?? null);
        let availableYears = @json($availableYears ?? []);

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
            fillPrintRows('repPrintServiceRows', serviceRows, 'No service revenue');

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

        printReportButton?.addEventListener('click', () => {
            syncPrintableReport();
            window.print();
        });
        window.addEventListener('beforeprint', syncPrintableReport);

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
            return String(new Date().getFullYear());
        }

        function periodValueOptions(period) {
            if (period === 'daily') return weekdayOptions;
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
            const options = periodValueOptions(period);
            const fallback = defaultPeriodValue(period);
            const value = selectedValue || fallback;
            select.innerHTML = options.map(([val, label]) => {
                const text = period === 'daily' ? dailyOptionLabel(val, label) : label;
                return `<option value="${val}"${val === value ? ' selected' : ''}>${text}</option>`;
            }).join('');
            currentPeriodValue = value;
        }

        function buildReportingQuery(period, periodValue) {
            const params = new URLSearchParams();
            params.set('period', period || 'monthly');
            if (periodValue) params.set('period_value', periodValue);
            return params.toString();
        }

        async function fetchReportingPayload(period, periodValue) {
            const res = await fetch(`${reportingDataUrl}?${buildReportingQuery(period, periodValue)}`, {
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

        function setPeriodButtonsActive(period) {
            document.querySelectorAll('#repPeriod .rep-period-btn').forEach((btn) => {
                const on = btn.dataset.period === period;
                btn.classList.toggle('active', on);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
        }

        function updateExportLink(period, periodValue) {
            const a = document.getElementById('repExportLink');
            if (!a || !reportingExportUrl) return;
            const url = new URL(reportingExportUrl, window.location.origin);
            url.searchParams.set('period', period || 'monthly');
            if (periodValue) {
                url.searchParams.set('period_value', periodValue);
            } else {
                url.searchParams.delete('period_value');
            }
            a.href = url.toString();
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
                            data: st,
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
            if (Array.isArray(d.availableYears) && d.availableYears.length) {
                availableYears = d.availableYears;
            }
            populatePeriodSelect(currentPeriod, currentPeriodValue);
            setText('repPageSubtitle', d.pageSubtitle || '');
            setText('repTrendSubtitle', d.trendSubtitle || '');
            setText('repMixMuted', `All services (${d.serviceLabel || ''})`);
            setText('repMixPill', d.serviceLabel || '');

            setText('repPrimaryBadge', d.primaryBadge || '');
            setText('repPrimaryLabel', d.primaryLabel || '');
            const pv = document.getElementById('repPrimaryValue');
            if (pv) {
                pv.className = 'rep-value';
                pv.textContent = money(d.primaryAmount ?? 0);
            }
            setText('repPrimarySub', d.primarySub || '');
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

            const nextValue = defaultPeriodValue(period);
            await loadReporting(period, nextValue);
        });

        document.getElementById('repPeriodValue')?.addEventListener('change', async (e) => {
            const value = e.target.value;
            if (!value || value === currentPeriodValue) return;
            await loadReporting(currentPeriod, value);
        });

        window.addEventListener('popstate', () => {
            const loc = new URL(window.location.href);
            let p = loc.searchParams.get('period') || 'monthly';
            if (!['daily', 'monthly', 'yearly'].includes(p)) {
                p = 'monthly';
            }
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
