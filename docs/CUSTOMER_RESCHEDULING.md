# Customer rescheduling and cancellation

Customers use the existing My Appointments rescheduling modal to change the therapist, date, or time while retaining the booked service and duration. The booking must have a verified `paid` initial payment with a positive collected amount and remain eligible under the customer cancellation cutoff. Full payments and downpayments are supported. Staff eligibility remains separate.

Rescheduling updates the existing booking. It does not reprice the service or membership discount, create checkout, change payment references, collect a balance, write a payment ledger entry, or issue a refund. Normal balance collection rules still apply before starting the session.

`BookingRescheduleService` checks schedule eligibility, service slots, customer conflicts, therapist conflicts, and customer lead time. Inside the transaction it locks the customer, locks the source and target therapist rows in ID order, and locks the booking before checking again. A rejected change leaves the original reservation and payment intact.

## Cancellation deadline and history

Migration `2026_11_02_120000_preserve_reschedule_history_and_cancellation_deadline` adds two nullable fields to `spa_bookings`:

- `rescheduled_from_therapist_name`: therapist before the latest move.
- `cancellation_deadline_at`: earliest preserved customer cancellation deadline.

The cancellation service enforces the earliest of the saved deadline, the current appointment cutoff, and the previous stored appointment cutoff. Staff moving an appointment after its cutoff cannot restore customer cancellation eligibility. Customer activity logs retain the previous and new therapist and schedule for each move.

Existing rows are not rewritten. Older reschedules use their stored previous date/time as a fallback; missing earlier history cannot be reconstructed by this migration. Removing the migration removes the new history fields, so an application rollback should normally retain them.

## Customer endpoints

- `PATCH /bookings/{spaBooking}/reschedule`: optional `therapist` plus required `booking_date` and `time_slot`. Omitting the therapist retains the current assignment for existing callers.
- `GET /bookings/{spaBooking}/reschedule/availability`: booking-specific availability using the stored service duration and selected therapist.
- `GET /bookings/{spaBooking}/cancellation-preview`: current appointment, previous appointment when recorded, paid amount, and eligible refund amount. It does not create a refund or change the booking.

All three routes require the authenticated booking owner. The cancellation modal waits for a successful preview before enabling confirmation. Actual cancellation rechecks eligibility while locked and uses the existing refund service, including separate initial/balance components and duplicate refund protection.

## Verification

- `tests/Feature/CustomerRescheduleTest.php`: payment preservation, invalid payment states, target availability, ownership, cutoff preservation, and cancellation/refund behavior.
- `tests/Integration/PostgresBookingConcurrencyTest.php`: concurrent therapist changes compete for one target slot without additional financial records.
- `tests/Browser/customer-reschedule.spec.js`: therapist selection, rejected/successful changes, unchanged payment displays, updated appointment cards, and cancellation preview across phone, tablet, and desktop.

Browser tests use isolated fixtures and intercepted responses; feature and PostgreSQL tests exercise the server behavior. They do not perform live gateway payments or payouts.
