# Integration fixes — 17 October 2026

## Scope and evidence

The seven findings below are fixed within the described scope. Regression tests reproduced the scheduling races, unsafe therapist changes, lost service overrides, missing scheduled completion, missing ledger years, and clipped negative report values before their corrections. These results do not establish that every system workflow is defect-free.

| Finding | Correction | Regression evidence |
| --- | --- | --- |
| Customer appointment intervals could race across different therapists | Serialize changes on the customer row, then lock the current therapist and booking before checking and committing the interval | PostgreSQL tests exercise the actual staff creation, rescheduling, and session-start controllers |
| A distinct additional gateway payment was silently ignored | Preserve its reference and supplied amount in a separate reconciliation record; retain the original allocation and membership expiry | Signed webhook replay tests, membership replay tests, contradictory evidence tests, and concurrent distinct-payment PostgreSQL test |
| Therapist profile changes could orphan appointments or remove history | Reject renaming a therapist with linked appointments; reject deleting linked appointments/history; reject deactivation while reservations remain | Feature tests verify database retention, availability, permitted profile edits, and deactivation after cancellation |
| Renaming a service lost date-specific slot exceptions | Update weekly mappings, date overrides, and booking names within the same transaction | Enabled and disabled date overrides remain attached after rename |
| Expired sessions required browser traffic to complete | Schedule the existing completion service every minute with overlap prevention | Command run twice produces one completed booking and one linked transaction without polling |
| Ledger-only collection years were absent from report choices | Include the earliest ledger collection year | Paid No Show collection without a completed visit remains selectable and counted |
| Negative service totals were clipped or omitted | Use a signed bar chart when refunds create negative totals; preserve signed SVG/PDF values and detail rows; do not name a zero-sale offering as the top sales source | Cross-period refund feature test and PDF chart unit test; browser checks cover signed charts, insights, and unavailable chart-library fallback |

## Financial handling and migration

The explicitly approved migration creates only `gateway_payment_reconciliations`. It does not repair, backfill, or rewrite existing financial records. Each gateway payment reference is unique; contradictory replays retain the original amount, currency, and booking/membership association and emit a reconciliation warning.

Open records appear under **Sales Monitoring → Gateway payments requiring review**, in PDF/Excel exports, staff notifications, activity logs, and JSON backups. Unknown amounts remain unknown. The displayed PHP subtotal excludes unknown amounts and other currencies. Unallocated payments are explicitly separate from allocated sales and do not enlarge booking refund limits or authorize another payout.

This is a review queue, not an automatic extra-payment refund mechanism. Staff must verify the gateway record before deciding how to reconcile it. Existing receipts and sales ledger allocations retain their original source/payment identity. Historical extra payments are not automatically reconstructed.

Foreign keys retain reconciliation evidence by preventing deletion of linked bookings or membership purchases. Older JSON backups remain supported. A code rollback should retain this table; running the migration's `down()` would delete the new reconciliation evidence.

Therapist names remain the existing booking association. Renaming a therapist with appointment records is deliberately rejected; this change does not introduce a therapist-ID migration.

A final PostgreSQL run exposed an intermittent cancellation/start deadlock. A controlled checkpoint test reproduced it. Cancellation, balance collection, completion, and refund mutations now acquire the customer lock before the booking lock, matching scheduling and No Show processing. Financial validation and payout rules are unchanged.

## Exact files changed

- `app/Console/Commands/CompleteExpiredSessions.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/PaymongoController.php`
- `app/Http/Controllers/StaffAppointmentController.php`
- `app/Models/GatewayPaymentReconciliation.php`
- `app/Services/BackupRecoveryService.php`
- `app/Services/BookingCancellationService.php`
- `app/Services/BookingRefundService.php`
- `app/Services/BookingRescheduleService.php`
- `app/Services/BookingSlotService.php`
- `app/Services/GatewayPaymentReconciliationService.php`
- `app/Services/MembershipPurchaseService.php`
- `app/Services/ReportingPdfChartService.php`
- `app/Services/ReportingSpreadsheetService.php`
- `app/Services/SpaSessionService.php`
- `database/migrations/2026_10_17_120000_create_gateway_payment_reconciliations_table.php`
- `public/css/reporting.css`
- `resources/views/reporting/index.blade.php`
- `resources/views/reporting/pdf.blade.php`
- `resources/views/therapist-tracking/index.blade.php`
- `routes/console.php`
- `tests/Browser/zz-audit-follow-up.spec.js`
- `tests/Feature/GatewayPaymentReconciliationTest.php`
- `tests/Feature/SystemAuditFollowUpTest.php`
- `tests/Integration/PostgresBookingConcurrencyTest.php`
- `tests/Support/postgres-customer-interval-worker.php`
- `tests/Support/postgres-workflow-worker.php`
- `tests/Support/prepare-browser-audit-follow-up.php`
- `tests/Unit/ReportingPdfChartServiceTest.php`
- This report.

Preexisting edits to `AGENTS.md` and `TouchNRelief_Mobile_Tablet_Responsive_UI_Audit.md` are outside this change.

## Verification

| Check | Actual result |
| --- | --- |
| Full PHPUnit suite | 288 passed, 2,262 assertions; 21 skipped |
| Dedicated PostgreSQL suite | 20 passed, 120 assertions; includes the controlled cancellation/start regression |
| Skips in default suite | 20 PostgreSQL checks run separately above; one opt-in MySQL normalization migration check not run |
| Browser suite | All 98 existing tests passed; four added tests passed separately. The final six-test report run also passed, including existing period controls and downloads |
| Blade compilation | Passed |
| PHP syntax | Passed for changed PHP source and test files |
| JavaScript syntax | 36 files passed |
| Vite production build | Passed |
| Composer locked dependency audit | No advisories found |
| npm audit | Zero vulnerabilities |
| Pint | Task-modified PHP files passed; full repository check has preexisting formatting failures |
| Git diff validation | Passed |

An intermediate PostgreSQL run failed on cancellation/start deadlock. The controlled regression reproduced it before the lock-order correction, and the final 20-test PostgreSQL run passed. This failure was corrected rather than skipped or accepted as an intermittent result.

Initial added browser checks failed because their historical fixture used a current-year month selection and assumed a different currency format. Corrected checks use the supported custom date range and signed PHP display. Repeated logins during reruns also hit the existing five-per-minute limit; the four checks now reuse one authenticated session. Production rate limits remain enforced. Visual review caught a wide grid panel clipping mobile content; `min-width: 0` now confines scrolling to the table, and browser assertions check the panel bounds.

## Limits

- Tests use isolated fixtures and mocked gateway responses, not live payments, refunds, email, or OAuth.
- PostgreSQL locking evidence comes from the dedicated `touchnrelief_test` database, not SQLite.
- The optional MySQL normalization migration check is not enabled in the default PHP suite.
- Repository-wide Pint has existing formatting failures outside these changes; task-modified PHP files are checked separately.
- Existing small desktop sidebar overflow recorded in the earlier responsive audit is outside these seven fixes.
- Automatic completion depends on the deployed scheduler running; it checks actual session end time and full payment rather than assuming a scheduled appointment has completed.
- Changes for the same customer serialize. Slow gateway calls within the existing transaction boundaries can increase waiting time for that customer's concurrent actions.
