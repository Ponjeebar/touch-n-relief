# UI Design System

## Design Direction

TouchNRelief combines a calm spa presentation for customers with dense, efficient operational screens for staff. Extend the visual language already present on the page being changed.

Do not solve every interface with a large hero, a grid of rounded cards, gradients, glass effects, floating pills, or generic dashboard statistics.

## Brand Character

- Calm, warm, trustworthy, and professional.
- Spa-specific editorial details are acceptable on public/customer surfaces.
- Staff surfaces prioritize fast scanning and clear actions.
- Use real service, therapist, schedule, and payment content.

## Existing Visual Foundations

Inspect `public/css/theme.css` and the affected page style sheet before selecting exact values.

Common visual cues include:

- Deep green and near-black navigation.
- Teal/green primary actions and state accents.
- Warm cream or off-white backgrounds.
- Gold used sparingly for emphasis.
- Serif display type for selected brand headings.
- Sans-serif type for controls, forms, metadata, and operational screens.
- Moderate borders and shadows with readable contrast.

These are directions, not permission to hard-code new tokens without checking existing variables.

## Page-Specific Behavior

### Public and Customer Pages

- Maintain the established TouchNRelief header and mobile bottom navigation.
- Use spa imagery only where it helps service or therapist selection.
- Keep booking, payment, and appointment states explicit.
- Use warm editorial typography selectively; form text must remain highly readable.

### Staff and Admin Pages

- Prefer tables, lists, timelines, filters, and compact panels suited to the task.
- Preserve sidebar/topbar patterns and staff mobile adaptations.
- Keep frequent actions visible and status differences easy to scan.
- Avoid turning data-heavy screens into repetitive marketing cards.

## Components

- Reuse existing Blade partials and CSS classes when possible.
- Primary buttons must be visually dominant only when one primary action exists.
- Destructive actions need a clear label and confirmation pattern already used by the project.
- Icon-only controls need accessible names and at least a 44px mobile target where practical.
- Modals need a visible title, close control, keyboard focus behavior, and mobile viewport handling.
- Status must use text in addition to color.
- Long names, emails, references, and service titles must wrap or truncate intentionally.

## Content Rules

- Write concrete labels: “Continue payment,” “Collect balance,” or “Choose a therapist.”
- Explain unavailable actions with a useful reason.
- Avoid filler subtitles that repeat the title.
- Do not expose framework, API, or database language to customers.
- Keep currency as Philippine pesos and preserve the project's existing formatting style.

## UI Review Questions

1. Does the hierarchy match the user's next decision?
2. Is the primary action visible without competing decorative elements?
3. Does the interface use real project terminology?
4. Is the same pattern already implemented elsewhere?
5. Are empty, loading, success, and error states understandable?
6. Does dark mode preserve contrast if the affected page supports it?
7. Does the page still work at mobile, tablet, desktop, and browser zoom?
