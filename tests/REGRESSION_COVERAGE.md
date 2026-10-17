# Audit regression coverage and operational checks

## Regression evidence

Keep these tests when changing appointment or sales behavior. For a new confirmed defect, add a behavioral test that reproduces the defect before changing production code, then retain it after the correction. Assert the authoritative record and affected financial, notification, and displayed outcomes. A successful HTTP response alone is insufficient.

| Finding / workflow | Permanent tests | Outcomes protected |
| --- | --- | --- |
| Customer intervals across different therapists | `Integration/PostgresBookingConcurrencyTest.php` — actual late start versus creation; concurrent rescheduling | One valid interval; rejected request creates no additional collection or start activity |
| Cancellation versus session start | Same PostgreSQL file — cancellation/start and controlled queued-cancellation tests | Consistent lock order; only one outcome commits; no session or refund for a rejected action |
| Separate and repeated gateway payments | `Feature/GatewayPaymentReconciliationTest.php`; concurrent distinct-payment PostgreSQL test | Original allocation and membership expiry retained; separate reference stored once; staff alert, report, exports, and backup agree |
| Therapist rename, deactivation, and deletion | `Feature/SystemAuditFollowUpTest.php` | Appointment associations and legacy transactions retained; rejected edits create no payment, refund, transaction, or success activity |
| Service rename | Same feature file | Enabled and disabled date overrides preserved; linked booking renamed without changing payment amount/status or adding ledger entries |
| Scheduled completion | Same feature file | Two command runs produce one linked transaction, one completion notification, one customer-history entry, and correct service hours and collections |
| Historical ledger years | Same feature file | Paid No Show collection remains available in report year choices without requiring a completed visit |
| Cross-period refunds | Same feature file; `Unit/ReportingPdfChartServiceTest.php`; `Browser/zz-audit-follow-up.spec.js` | Negative service totals survive API, charts, PDF detail, and chart-library fallback; no zero-sale offering is named as the top sales source |
| Refund correctness and evidence | `Feature/BookingRefundAccountingTest.php`, `Feature/PaymongoRefundWebhookTest.php`, `Feature/ManualRefundEvidenceTest.php`; PostgreSQL refund races | Gateway outcomes, immutable processed amounts, source limits, duplicate references, evidence, and disputes |
| No Show connected results | `Feature/AutomaticNoShowTest.php`, `Feature/AuditWorkflowRegressionTest.php`, `Browser/client-history.spec.js` | Committed state, labels, counts, filters, customer history, notifications, and replay behavior |
| Operational monitoring | `Feature/OperationalMonitoringTest.php`; reporting browser tests | Read-only detection, current System Settings, exact deadline allowance, stale/failed/unknown processors, alerts without duplicates, permission checks, unchanged sales, and cache failure isolation |

PostgreSQL tests use only the dedicated `touchnrelief_test` database. SQLite results are not row-locking evidence. Browser fixtures use `database/browser-tests.sqlite`; do not point them at production or the normal local database.

## Operational monitoring

Administrators can open **Sales Monitoring → Operational checks**. The display and reporting AJAX data inspect current conditions across all dates, independently of the selected financial period.

- Automatic No Show and completion commands record their last successful and failed attempts in the existing **database cache**. A successful zero-record run counts as a successful check.
- A processor with no recorded run is **unverified**, not healthy. A failed attempt remains visible until a later successful run. A success older than ten minutes is flagged as stale.
- Unstarted appointments are checked against the current System Settings grace and review windows, with a further five-minute monitoring allowance. This allowance does not change eligibility or business deadlines.
- Started sessions use their actual start plus duration and must be fully paid before they count as overdue completion work.
- Open gateway-payment reconciliation cases and pending gateway refunds are flagged after 24 hours. Manual refunds and unpaid checkout holds are not treated as uncertain gateway refunds.
- Monitoring never changes booking/session states, payment allocations, refund status, source limits, receipts, or ledger entries.

The scheduled command `operations:check --notify` runs every five minutes. It sends administrator notifications at most once per issue per calendar day, and records attention events in the activity/application logs when new notifications are created. Notifications describe the observation time; the reporting display shows current results. A recovered issue can recur without another same-day notification, so use the current display for its status.

For a read-only machine-readable check:

```text
php artisan operations:check --json
```

Exit code `0` means recent successful processor runs and no detected overdue/aged cases. Exit code `1` means attention is needed or processor history is not available. The command checks status; it does not run payment verification or issue payouts. Add `--notify` only when administrator notifications are intended.

Configuration defaults:

| Environment variable | Default | Purpose |
| --- | --- | --- |
| `MONITOR_PROCESSING_DELAY_MINUTES` | 5 | Additional allowance before overdue work is flagged |
| `MONITOR_PROCESSOR_STALE_MINUTES` | 10 | Maximum age of a processor success before a stale warning |
| `MONITOR_GATEWAY_REVIEW_HOURS` | 24 | Age at which an unresolved gateway case is highlighted |

No migration is needed. The existing cache and staff-notification tables hold operational metadata. Clearing the database cache removes processor history until the next successful run. Cache failures are logged and must not prevent appointment processing. Fixing cache access restores verification; missing history must not be interpreted as successful processing.

If the scheduler stops, the reporting display can still expose old processor results and overdue records when an administrator opens it. An independent external check must invoke the read-only command or inspect application availability to detect a stopped application without anyone opening it. This change does not provision an external monitoring provider or send external email.

Browser CI, appointment/ledger pagination, and a real offsite-backup restoration drill remain separate recommendations.

## Files in this change

- `.env.example`
- `app/Console/Commands/CheckOperations.php`
- `app/Console/Commands/CompleteExpiredSessions.php`
- `app/Console/Commands/ProcessAutomaticNoShows.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Services/OperationalMonitoringService.php`
- `config/touchnrelief.php`
- `public/css/reporting.css`
- `resources/views/reporting/index.blade.php`
- `routes/console.php`
- `tests/Browser/zz-audit-follow-up.spec.js`
- `tests/Feature/GatewayPaymentReconciliationTest.php`
- `tests/Feature/OperationalMonitoringTest.php`
- `tests/Feature/SystemAuditFollowUpTest.php`
- This coverage and operating guide.
