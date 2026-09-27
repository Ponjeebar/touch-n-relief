# TouchNRelief AI Context Index

## Purpose

This folder gives coding assistants the project context needed to produce specific, consistent work instead of generic code, UI, or advice.

Source code remains the final source of truth. If a document conflicts with the current implementation, inspect the code, report the mismatch, and update the document with the same change.

## Required Reading Order

Read these documents before changing the project:

1. Root [`AGENTS.md`](../../AGENTS.md) for mandatory working rules.
2. [`01_PROJECT_CONTEXT.md`](01_PROJECT_CONTEXT.md) for the product and users.
3. [`02_ARCHITECTURE.md`](02_ARCHITECTURE.md) for application structure.
4. [`03_FEATURE_MAP.md`](03_FEATURE_MAP.md) for feature ownership.
5. [`04_BUSINESS_RULES.md`](04_BUSINESS_RULES.md) for booking and payment constraints.
6. Read the task-specific documents listed below.

## Read by Task Type

| Task | Required documents |
|---|---|
| UI or UX | `05_UI_DESIGN_SYSTEM.md`, `06_RESPONSIVE_ACCESSIBILITY.md` |
| Booking, payment, package, membership | `04_BUSINESS_RULES.md`, `07_DATABASE.md` |
| Authentication or account | `08_SECURITY_AUTH.md`, `07_DATABASE.md` |
| Database or model | `07_DATABASE.md`, root `docs/database-erd-full.md` |
| Tests or bug fix | `09_TESTING_QA.md` |
| Deployment or environment | `10_DEPLOYMENT.md` |
| Planning or implementation | `11_AI_WORKFLOW.md`, `13_TASK_BRIEF_TEMPLATE.md` |
| Architectural change | `12_DECISIONS.md` |

## Existing Reference Documents

- [`TouchNRelief_Mobile_Tablet_Responsive_UI_Audit.md`](../../TouchNRelief_Mobile_Tablet_Responsive_UI_Audit.md): full responsive audit checklist.
- [`docs/database-erd-full.md`](../database-erd-full.md): detailed ERD snapshot. Verify it against migrations before relying on it.
- [`docs/HEROKU_DEPLOYMENT.md`](../HEROKU_DEPLOYMENT.md): Heroku procedure.
- [`LARAVEL_DEPLOYMENT_PORTABILITY.md`](../../LARAVEL_DEPLOYMENT_PORTABILITY.md): platform portability notes.

## Maintenance Rule

When a change alters a documented workflow, state, route group, model, design token, deployment step, or security rule, update the relevant file in this folder in the same commit.
