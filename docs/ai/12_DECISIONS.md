# Architecture Decision Log

Record durable decisions here so later assistants understand why the project works this way. Keep entries short and link to a commit, issue, or detailed document when available.

## ADR-001: Server-Rendered Laravel Application

- **Status:** Accepted
- **Decision:** Keep the main application server-rendered with Laravel Blade and focused JavaScript.
- **Reason:** The existing system, authentication, forms, staff workflows, and deployment are built around Laravel. Local UI work should not introduce a separate SPA framework.

## ADR-002: Service-Owned Business Logic

- **Status:** Accepted
- **Decision:** Booking, availability, sessions, payments, notifications, and catalogue rules belong in services/model helpers rather than Blade or duplicated controller code.
- **Reason:** The same rules are used across customer, receptionist, administrator, and webhook flows.

## ADR-003: Fifteen-Minute Online Payment Hold

- **Status:** Accepted
- **Decision:** Pending online PayMongo bookings temporarily block availability for 15 minutes and remain hidden from staff until payment confirmation.
- **Reason:** This prevents double booking during checkout without leaving abandoned appointments in operational queues indefinitely.

## ADR-004: Snapshot Names on Bookings

- **Status:** Accepted
- **Decision:** Bookings retain service and therapist names as historical snapshot fields.
- **Reason:** Catalogue or therapist profile changes must not make old appointment records unreadable.

## ADR-005: Shared Catalogue Model for Services and Packages

- **Status:** Accepted
- **Decision:** Individual services and THERA packages share `spa_services` and are separated by `offering_type`. Membership plans use their own model/table.
- **Reason:** Packages use the existing booking/availability workflow, while membership display represents a different product concept.

## ADR-006: Portable Local and Production Databases

- **Status:** Accepted
- **Decision:** Schema and queries must remain compatible with local MySQL and production PostgreSQL.
- **Reason:** The development and Heroku environments use different database engines.

## New Entry Template

```markdown
## ADR-NNN: Short title

- **Status:** Proposed | Accepted | Superseded
- **Date:** YYYY-MM-DD
- **Decision:** What was chosen.
- **Reason:** Why it fits TouchNRelief.
- **Consequences:** Main benefits, limits, and migration effects.
- **References:** Files, tests, commit, or issue.
```

Add an ADR for decisions that future contributors might otherwise reverse because the reason is not obvious.
