# Business Rules

These rules summarize current behavior. Verify the implementation before changing them.

## Booking Sources

- Online customer bookings use `SpaBooking::SOURCE_ONLINE`.
- Staff-created walk-in bookings use `SpaBooking::SOURCE_WALK_IN`.
- Walk-in users may be identified by the `is_walk_in` field and legacy `@walkin.local` email or `walkin_` username conventions.

## Booking and Session States

- Stored session states are `confirmed`, `in_session`, `completed`, `cancelled`, and `no_show`.
- Display labels such as Pending and Rescheduled may be derived from payment or reschedule data rather than stored as session states.
- A completed or cancelled booking does not block availability.
- Session start and completion rules belong in `SpaSessionService` and related model helpers.

## Online Payment Hold

- An unpaid online PayMongo booking holds availability for 15 minutes.
- During the active hold, the slot is unavailable to another valid booking.
- Pending or failed online checkouts are hidden from staff appointment lists until payment is confirmed.
- An expired hold stops blocking availability.
- If a late payment arrives after another customer took the released slot, the booking is cancelled and the payment follows the refund flow.
- If the slot is still available, a verified late payment can confirm the booking.

## Payment Rules

- Supported customer online processing uses PayMongo channels configured through `PAYMONGO_PAYMENT_METHOD_TYPES`.
- Payment types are full payment and 50% downpayment.
- `payment_status = paid` confirms only the initial paid amount.
- Full payment is determined from total paid amount versus booking amount, including a separately recorded balance.
- A session cannot start or auto-complete while a balance remains.
- Staff may collect the exact remaining balance through the authorized balance endpoint.
- A customer cannot record their own balance payment.
- Never trust redirect query parameters alone as payment proof; retrieve and verify the PayMongo session or webhook event.
- Net sales use `payment_ledger_entries` and the event's `occurred_at` timestamp: initial payment plus balance payment minus processed refunds.
- Completed-session `transactions` remain the source for delivered service duration and are not the source for cash collection totals.
- Membership plans are informational until a membership purchase workflow exists; do not include plan prices in revenue.
- Historical initial payments backfilled into the ledger use the booking creation time and are marked `is_estimated` because the original schema did not store the collection timestamp.

## Availability

- Availability depends on service duration, service time slots, store closures, therapist working days, day-off settings, therapist conflicts, customer conflicts, and active payment holds.
- Use `BookingSlotService` and `TherapistAvailabilityService`; do not reproduce conflict queries in views or JavaScript.
- A therapist may work on an otherwise configured off day only through the existing override behavior.

## Services, Packages, and Membership

- `spa_services.offering_type` separates individual services from packages.
- THERA #1, #2, and #3 are packages and should appear in the package section, not duplicated in the individual service carousel.
- Packages remain bookable through the common service booking flow.
- Membership plans are stored separately in `membership_plans` and displayed as membership offerings.
- Membership display does not by itself prove that subscription billing or member account entitlements exist. Do not invent those flows.

## Customer Wellness Rules

- Profile onboarding records sex, therapist gender preference, pressure preference, and applicable pregnancy state.
- Prenatal services are available only when the customer's stored profile permits them.
- Preserve existing age and contact-number validation.

## Cancellation, Rescheduling, and Refunds

- Use the dedicated cancellation, reschedule, and refund services.
- Eligibility can depend on status, payment confirmation, configured cutoff, date/time, and refund state.
- Do not expose actions merely because a button can be rendered; authorization and service-level checks must agree.

## Source of Truth

- Booking state and holds: `app/Models/SpaBooking.php`.
- Payment names and statuses: `app/Support/PaymentMethodCatalog.php`.
- Sessions and transaction rows: `app/Services/SpaSessionService.php`.
- Availability: `BookingSlotService` and `TherapistAvailabilityService`.
- Catalogue grouping: `SpaServiceCatalog`.
