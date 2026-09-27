# Responsive and Accessibility Standard

The full audit checklist is in [`TouchNRelief_Mobile_Tablet_Responsive_UI_Audit.md`](../../TouchNRelief_Mobile_Tablet_Responsive_UI_Audit.md). This file defines the minimum implementation and verification standard.

## Target Widths

At minimum, inspect or test:

- 320px: narrow phone.
- 390px: common phone.
- 768px: portrait tablet.
- 1024px: landscape tablet/small desktop.
- 1440px: desktop.

Use content-driven breakpoints where the layout fails rather than adding device-specific rules by default.

## Layout Requirements

- No unintended document-level horizontal scrolling.
- Fixed/sticky headers, bottom navigation, chat controls, and action bars must not cover required content.
- Use `min-width: 0` on flexible children that contain long text.
- Use `overflow-wrap: anywhere` for references, emails, and generated identifiers where needed.
- Forms should collapse to one column when two-column labels or controls become cramped.
- Data tables may use a controlled scroll container or purpose-built mobile row treatment.
- Modals must fit within the dynamic viewport and keep close/primary actions reachable.
- Account for mobile safe-area insets on fixed bottom controls.

## Touch and Keyboard

- Aim for controls at least 44 by 44 CSS pixels on touch surfaces.
- Keep visible focus styles.
- Do not require hover to reveal essential actions or information.
- Buttons should be buttons; navigation should be links.
- Escape and close behavior should follow existing modal scripts.

## Semantics

- Every form control needs an associated label.
- Use heading levels in logical order.
- Icon-only controls need `aria-label` or equivalent accessible text.
- Dialogs need a name, `role="dialog"`, and `aria-modal="true"` where appropriate.
- Validation and status messages should be readable by assistive technology.
- Do not communicate state through color alone.

## Contrast and Themes

- Verify text, placeholders, borders, focus rings, disabled states, dropdowns, and overlays in every supported theme.
- Avoid white panels with white text or dark text on dark overlays.
- Theme fixes should use scoped selectors or existing variables and must not damage the opposite theme.

## Visual Verification

For meaningful UI changes:

1. Render the actual page or component.
2. Check representative mobile and desktop viewports.
3. Inspect screenshots.
4. Check `scrollWidth` versus viewport width.
5. Check console/page errors.
6. Exercise the main interaction, not just initial rendering.
7. Remove temporary preview files before committing.
