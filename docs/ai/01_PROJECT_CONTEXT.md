# Project Context

## Product

TouchNRelief is the booking and operations system for Buenos Touché Spa. It supports public service discovery, customer appointments, online payments, receptionist operations, therapist scheduling, customer records, and administrative reporting.

The application should feel calm, trustworthy, and spa-specific. Product copy should be clear and practical. Avoid generic software language such as “optimize your journey,” invented statistics, or placeholder wellness claims.

## User Roles

### Customer (`user`)

- Browses services, packages, therapists, membership information, and business details.
- Registers by email or Google and completes wellness onboarding.
- Books an appointment, pays through PayMongo, and manages eligible appointments.
- Reviews profile information, transactions, receipts, and notifications.

### Receptionist (`receptionist`)

- Uses the receptionist dashboard and shared staff appointment tools.
- Creates bookings for registered or walk-in clients.
- Collects balances, starts and completes sessions, reschedules, cancels, and records no-shows where permitted.
- Reads staff notifications and customer records.

### Administrator (`admin`)

- Has staff capabilities plus user, therapist, service, schedule, landing-page, reporting, backup, and activity-log management.

## Main Product Areas

- Public landing page and service catalogue.
- Authentication, verification, password reset, and Google sign-in.
- Customer onboarding and wellness preferences.
- Guided appointment booking and availability.
- PayMongo checkout, payment recovery, balance collection, refunds, and receipts.
- Customer profile, notifications, and appointment management.
- Receptionist and administrator dashboards.
- Services, packages, membership display, therapists, schedules, and store closures.
- Ongoing/completed sessions, customer records, reporting, and activity logs.
- Booking assistant chatbot.

## Product Terminology

Use existing terms consistently:

- Brand/system: **TouchNRelief**.
- Spa: **Buenos Touché Spa**.
- Roles: **Customer**, **Receptionist**, **Administrator**, **Therapist**.
- Catalogue groups: **Individual services**, **THERA Packages**, **Membership**.
- Appointment states: **Pending**, **Confirmed**, **Rescheduled**, **In session**, **Completed**, **Cancelled**, **No show**.
- Payments: **Full payment**, **Downpayment (50%)**, **Balance due**, **Paid**, **Pending**, **Failed**, **Refunded**.

Do not rename statuses or roles without tracing every dependent query, test, label, and report.

## Product Priorities

1. Booking availability and payment state must be correct.
2. Staff must see actionable, accurate appointment information.
3. Customers must understand what is booked, paid, due, or expired.
4. Mobile controls must remain reachable and readable.
5. The interface should reuse the existing visual language.
6. Changes must work with both local development and Heroku production.
