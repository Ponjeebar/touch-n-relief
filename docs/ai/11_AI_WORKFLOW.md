# AI Coding Workflow

This document supplements the mandatory root `AGENTS.md`.

## Before Answering or Editing

1. Read `00_READ_FIRST.md` and the task-specific documents.
2. Inspect the actual implementation and git status.
3. Search for existing routes, services, components, styles, and tests.
4. Restate the current behavior using evidence from the repository.
5. Identify the smallest set of files and likely side effects.
6. Plan the change before editing.

## During Implementation

- Preserve architecture, route names, JavaScript hooks, status values, and shared component contracts.
- Prefer a small extension to an existing service or partial.
- Keep unrelated cleanup out of the task.
- Do not invent fields, endpoints, product rules, or third-party capabilities.
- Do not silently overwrite user changes or commit unrelated files.
- Keep the user informed during long-running work.

## Avoiding Generic Results

Every proposal should answer these questions:

1. Which TouchNRelief user is affected?
2. What exact workflow are they completing?
3. Which existing component or service should own it?
4. Which current business rule constrains it?
5. How will it behave on phone and desktop?
6. Which existing tests or states prove it works?

If an answer could be copied unchanged into an unrelated salon, ecommerce, or SaaS project, inspect more project context before proceeding.

## Communication Standard

Before implementation, provide:

- **Inspection**: current behavior and evidence.
- **Relevant Files**: exact owners and shared dependencies.
- **Proposed Changes**: concrete behavior and UI changes.
- **Risks / Considerations**: regressions, data, security, or deployment concerns.
- **Implementation Plan**: short ordered steps.

After implementation, provide:

- **Changes Made**.
- **Files Modified**.
- **Verification** with exact commands/results.
- **Notes** for limitations or follow-up.
- **Deployment** only when a deploy was requested and completed.

## Decision Thresholds

Ask before implementing when the change is destructive, changes production data, alters authentication/security architecture, replaces a major library, or makes an unclear product decision with materially different outcomes.

Proceed with routine, reversible implementation details when the user's intent and project patterns are clear.

## Completion Rule

Do not stop after editing. Review the diff, run appropriate checks, inspect UI changes visually, remove temporary artifacts, and complete authorized commit/deployment work.
