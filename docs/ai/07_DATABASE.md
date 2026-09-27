# Database and Data Guidance

## Sources of Truth

1. Current migrations in `database/migrations`.
2. Current model casts, fillable fields, scopes, and relationships.
3. [`docs/database-erd-full.md`](../database-erd-full.md) as a human-readable snapshot.

The ERD may lag recent migrations. Packages, memberships, payment balances, social accounts, password history, staff notifications, and other later additions must be confirmed in migrations.

## Core Domain Models

- `User`: login identity, role, wellness preferences, profile state, walk-in compatibility.
- `SpaBooking`: appointment, payment, balance, refund, and session state.
- `SpaService`: individual service or package catalogue entry.
- `MembershipPlan`: membership display plan and benefits.
- `Therapist`: profile, specialties, working days, and day-off controls.
- `TimeSlot`: canonical slot label and ordering.
- `Transaction`: completed/recorded transaction linked to a booking where available.
- `PaymentLedgerEntry`: one initial payment, balance payment, or processed refund event for a booking; drives collection-time sales reporting and exports.
- Customer/staff notification models: persisted notification feeds.
- `SocialAccount`: external Google identity linked to a user.
- `SiteSetting`: editable business and schedule settings.

## Relationship Cautions

- Some historical relationships use names or email as logical links instead of foreign keys.
- `spa_bookings.service_name` and `therapist_name` preserve booking snapshots.
- Do not casually replace snapshot strings with live joins; historical records must remain understandable after catalogue edits.
- Walk-in clients have compatibility logic for older schemas and synthetic identifiers.

## Schema Change Rules

Before adding or changing a field:

1. Search migrations and models for an existing equivalent.
2. Explain affected tables and existing data.
3. Use a new reversible migration; never edit an already-deployed migration to change production history.
4. Support MySQL locally and PostgreSQL on Heroku unless the project explicitly changes that requirement.
5. Avoid database-specific SQL where Eloquent or portable schema operations work.
6. Add indexes or uniqueness constraints based on an actual query/integrity need.
7. Backfill existing rows safely and in bounded chunks when data volume may grow.
8. Update casts, validation, fillable fields, services, tests, ERD, and these docs as applicable.

## Production Safety

- Never run `migrate:fresh`, reset, drop, truncate, or destructive data scripts against production.
- The Heroku release command runs `php artisan migrate --force`.
- Review migration `down()` behavior but do not assume production rollback is harmless.
- Backups and recovery steps belong in deployment planning for high-risk changes.

## Data Formatting

- Store money in decimal database fields and calculate using controlled rounding.
- Store dates/timestamps in database-compatible types and format them only at presentation boundaries.
- Use model casts instead of repeated manual conversions.
- Preserve canonical status values; localize or prettify only for display.
