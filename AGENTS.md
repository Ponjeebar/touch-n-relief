# AGENTS.md

## Purpose

These instructions define how AI coding assistants should work inside this project.

The goal is to avoid generic designs, unnecessary rewrites, blind code changes, duplicated components, and changes that do not match the existing project.

The assistant must always inspect the project first, understand how it currently works, plan the change, and only then modify the code.

---

# 1. Inspect Before Editing

Before making ANY code change:

- Inspect the relevant files first.
- Understand how the current feature works.
- Check related components, pages, routes, APIs, database models, styles, utilities, and dependencies.
- Search the project for existing implementations before creating something new.
- Determine whether reusable components, functions, services, hooks, or utilities already exist.
- Do not immediately rewrite or replace working code.
- Do not assume the project structure without checking it.

Always understand the existing implementation before editing it.

---

# 2. Plan Before Making Changes

Before modifying files:

1. Explain what currently exists.
2. Identify the files related to the request.
3. Explain what needs to change.
4. Mention possible side effects or dependencies.
5. Provide a short implementation plan.

For large, risky, architectural, database, authentication, destructive, or system-wide changes, ask for approval before implementation.

For small and clearly safe changes, implementation may proceed after inspection and planning.

---

# 3. Never Make Blind Changes

Do not assume how the application works.

Never:

- Guess file locations.
- Invent APIs that have not been checked.
- Invent database fields.
- Invent routes.
- Create duplicate components.
- Replace working architecture unnecessarily.
- Remove existing behavior without checking its purpose.
- Change unrelated code.
- Perform broad refactoring when only a small change is required.
- Rewrite an entire page when a smaller fix will work.

Prefer the smallest correct change.

---

# 4. Avoid Generic AI-Generated UI

Do not generate generic dashboard, SaaS, admin panel, landing-page, or template-style interfaces unless that style is explicitly requested.

Avoid automatically using:

- Repetitive rounded cards everywhere.
- Excessive card grids.
- Huge border radiuses.
- Random gradients.
- Excessive shadows.
- Excessive glassmorphism.
- Generic blue/purple SaaS color schemes.
- Oversized hero sections when unnecessary.
- Random decorative icons.
- Emojis as interface icons.
- Generic "Welcome Back" dashboard layouts.
- Identical cards for unrelated information.
- Excessive whitespace that wastes screen space.
- Generic placeholder statistics.
- Generic stock content.
- Decorative elements with no functional purpose.
- Unnecessary animations.
- Excessive floating elements.
- Designs that look copied from common AI-generated dashboards.

The interface should look intentionally designed for THIS application's actual purpose.

---

# 5. Study the Existing Design First

Before designing or redesigning any page, inspect the existing:

- Colors.
- Typography.
- Spacing.
- Buttons.
- Inputs.
- Forms.
- Tables.
- Navigation.
- Sidebars.
- Modals.
- Cards.
- Icons.
- Page widths.
- Border radius.
- Shadows.
- Layout structure.
- Responsive behavior.
- Loading states.
- Empty states.
- Error states.

Continue the established visual language unless the task specifically requests a redesign.

Do not introduce a completely different visual style into one page.

---

# 6. Design With Purpose

Every UI element should have a reason for existing.

Prioritize:

- Clear visual hierarchy.
- Readability.
- Logical grouping.
- Consistent spacing.
- Proper alignment.
- Practical use of screen space.
- Accessible contrast.
- Clear primary actions.
- Clear secondary actions.
- Useful empty states.
- Loading states.
- Error states.
- Success states.
- Responsive behavior.
- Consistent interaction patterns.
- Good usability over decoration.

Design for the real workflow of the application.

Do not design only to make the interface look "modern."

---

# 7. Do Not Overdesign

Prefer interfaces that are:

- Clean.
- Professional.
- Functional.
- Intentional.
- Easy to understand.

Do not add unnecessary visual complexity.

If the current page already communicates information effectively, improve it instead of completely redesigning it.

---

# 8. Reuse Existing Components

Before creating a new:

- Button.
- Input.
- Form.
- Modal.
- Dialog.
- Table.
- Card.
- Dropdown.
- Navigation component.
- Sidebar.
- Notification.
- Alert.
- Hook.
- Utility.
- Service.
- API helper.
- Layout component.

Search the project for an existing implementation first.

Reuse or extend existing components when appropriate.

Do not create nearly identical duplicate components.

---

# 9. Preserve Project Consistency

Follow the project's existing:

- Folder organization.
- Naming conventions.
- Architecture.
- Formatting.
- Coding style.
- State-management approach.
- API patterns.
- Validation approach.
- Error-handling approach.
- Authentication approach.
- Styling system.
- Component structure.
- Database access patterns.

Do not introduce another library, architecture, styling system, or pattern unless there is a clear technical reason.

---

# 10. Check Dependencies First

Before installing any package:

- Check the existing dependency files.
- Check whether the project already has a dependency that solves the problem.
- Avoid unnecessary dependencies.
- Explain why a new dependency is necessary.

Do not install a package simply because it makes implementation easier.

---

# 11. Protect Existing Functionality

When changing existing code:

- Preserve current behavior unless the task explicitly requests changing it.
- Search for references before renaming or deleting anything.
- Check where components, functions, routes, models, or services are used.
- Consider effects on other pages.
- Avoid breaking APIs.
- Avoid changing existing data structures unnecessarily.
- Avoid destructive database changes without explicit approval.
- Do not remove features because they appear unused without checking first.

---

# 12. Verify After Implementation

After making changes:

- Review every modified file.
- Check imports.
- Check references.
- Check for syntax errors.
- Check for type errors.
- Check for duplicate logic.
- Check responsive behavior when UI is involved.
- Check loading states.
- Check error states.
- Check empty states.
- Check affected user flows.
- Run available linting when appropriate.
- Run type checking when appropriate.
- Run tests when appropriate.
- Run the build when appropriate.

Do not claim something works unless it has actually been checked.

If something cannot be verified, clearly say so.

---

# 13. Do Not Hide Problems

If you discover:

- Existing bugs.
- Conflicting implementations.
- Missing dependencies.
- Security concerns.
- Broken references.
- Architectural issues.
- Inconsistent code.
- Requirements that conflict with the current system.

Report them instead of silently working around them.

Separate issues related to the requested task from unrelated issues.

Do not modify unrelated issues unless specifically requested.

---

# 14. UI Change Workflow

For UI work, follow this order:

1. Inspect the current page.
2. Inspect related pages and components.
3. Understand the page's purpose.
4. Identify the main user actions.
5. Identify existing design patterns.
6. Identify usability problems.
7. Propose the layout or design approach.
8. Implement the smallest appropriate change.
9. Review visual consistency.
10. Check responsive behavior.
11. Remove unnecessary decorative elements.
12. Verify existing functionality still works.

Do not immediately generate a completely new layout.

---

# 15. Feature Change Workflow

For feature work:

1. Trace the current feature.
2. Inspect the UI involved.
3. Inspect related APIs.
4. Inspect services or business logic.
5. Inspect database interaction if applicable.
6. Identify every affected file.
7. Search for reusable logic.
8. Determine the smallest safe implementation.
9. Implement.
10. Test the affected flow.
11. Review for regressions.

---

# 16. Database Changes

Never modify database schemas, migrations, tables, relationships, or production data without first explaining:

- What will change.
- Why the change is required.
- Which tables or models are affected.
- Whether existing data may be affected.
- Whether the operation is reversible.
- Whether a migration is required.
- Whether existing code depends on the current structure.

Do not delete data automatically.

Do not reset, drop, or recreate databases without explicit approval.

---

# 17. Authentication and Security Changes

Before changing authentication, authorization, permissions, sessions, tokens, passwords, or user roles:

- Inspect the existing implementation first.
- Trace how authentication currently works.
- Identify all affected routes and middleware.
- Identify permission checks.
- Explain possible security effects.
- Ask for approval before major changes.

Never weaken security just to make a feature easier to implement.

---

# 18. Keep Scope Controlled

Only modify files necessary for the requested task.

Do not use a small request as an opportunity to:

- Rewrite unrelated code.
- Rename many files.
- Restructure folders.
- Replace libraries.
- Redesign unrelated pages.
- Refactor the whole application.

If unrelated improvements are discovered, mention them separately.

---

# 19. Preserve Existing Content

Do not replace real application content with generic placeholder content.

Do not change existing:

- Labels.
- Terminology.
- Business rules.
- User roles.
- Data fields.
- Status names.
- Workflow names.

unless the request specifically requires it.

Use terminology already used by the application.

---

# 20. Responsive Design

For UI changes:

- Check desktop layouts.
- Check tablet layouts when applicable.
- Check mobile layouts.
- Avoid fixed widths that break smaller screens.
- Avoid unnecessary horizontal scrolling.
- Make tables usable on smaller screens.
- Keep important actions accessible.
- Preserve readable spacing.

Do not make desktop-only UI unless explicitly requested.

---

# 21. Accessibility

When creating or modifying UI:

- Use proper labels.
- Keep text readable.
- Maintain sufficient contrast.
- Do not rely only on color to communicate meaning.
- Keep keyboard interaction in mind.
- Use semantic elements where appropriate.
- Add meaningful accessible labels when icons are used as controls.

---

# 22. Icons

Use icons only when they improve clarity.

Prefer the icon library already installed in the project.

Do not:

- Mix several icon libraries unnecessarily.
- Use emojis as application icons.
- Add decorative icons to every label.
- Add icons where text alone is clearer.

---

# 23. Forms

When working with forms:

- Inspect existing validation patterns.
- Preserve validation rules.
- Display useful validation messages.
- Preserve entered data when reasonable after validation errors.
- Handle loading/submission states.
- Prevent accidental duplicate submissions when appropriate.
- Keep labels clear and specific.

---

# 24. Tables and Data Displays

For tables:

- Prioritize readability.
- Use clear column names.
- Keep actions obvious.
- Avoid unnecessary columns.
- Preserve sorting/filtering behavior if it exists.
- Handle empty data properly.
- Handle loading states.
- Consider responsive behavior.

Do not convert everything into cards simply to make it look modern.

---

# 25. Error Handling

Do not silently ignore errors.

Use the project's existing error-handling approach.

Errors shown to users should:

- Be understandable.
- Avoid exposing sensitive technical details.
- Explain what happened when possible.
- Give the user a useful next action when appropriate.

---

# 26. Comments and Documentation

Do not add excessive comments explaining obvious code.

Add comments only when they explain:

- Non-obvious logic.
- Important constraints.
- Workarounds.
- Business rules.
- Technical decisions that would otherwise be difficult to understand.

Keep comments accurate after changes.

---

# 27. Code Quality

Prefer:

- Simple code.
- Clear naming.
- Small focused functions.
- Reusable logic.
- Existing project patterns.
- Readable implementation.

Avoid:

- Clever code that is difficult to maintain.
- Premature abstraction.
- Duplicate logic.
- Very large components when separation is clearly needed.
- Unnecessary refactors.

---

# 28. Before Deleting Anything

Before deleting a:

- File.
- Component.
- Function.
- Route.
- API endpoint.
- Database field.
- Table.
- Dependency.
- CSS class.
- Asset.

Search the project for all references first.

Explain why deletion is safe.

For significant deletion, ask for approval first.

---

# 29. Required Communication Format

Before implementation, provide:

## Inspection

Explain what currently exists and how the relevant part works.

## Relevant Files

List the files, components, routes, APIs, or models related to the task.

## Proposed Changes

Explain exactly what should change.

## Risks / Considerations

Mention anything that could affect existing functionality.

## Implementation Plan

Provide a short ordered plan.

Then proceed according to the risk level of the change.

---

# 30. After Implementation

After making changes, provide:

## Changes Made

Explain what was actually changed.

## Files Modified

List the exact files modified.

## Verification

Explain what was checked, tested, linted, built, or verified.

## Notes

Mention anything important about the implementation or anything that could not be verified.

---

# 31. Analysis-Only Requests

If the user says:

- "check first"
- "analyze first"
- "inspect only"
- "don't change anything yet"
- "analysis only"

then DO NOT:

- Edit files.
- Create files.
- Delete files.
- Rename files.
- Move files.
- Install packages.
- Run destructive commands.

Only inspect the project and provide findings and recommendations.

Wait for explicit approval before changing anything.

---

# 32. Important Principle

Do not optimize for producing the most code.

Optimize for:

- Correctness.
- Maintainability.
- Consistency.
- Usability.
- Simplicity.
- Existing project architecture.
- Intentional design.
- Minimum necessary changes.

Always follow this order:

**Inspect first → Understand second → Plan third → Modify last.**
