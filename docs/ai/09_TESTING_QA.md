# Testing and Quality Assurance

## Test Philosophy

Tests should protect business behavior and high-risk regressions. Avoid tests that only repeat the implementation or assert decorative CSS values without a user-facing reason.

## Standard Commands

```powershell
.\vendor\bin\pint --dirty
php artisan view:cache
php artisan test
npm run build
git diff --check
```

Run the smallest relevant test set during development. Run broader tests when the change affects shared business logic, authentication, routes, migrations, or deployment assets.

## Existing Feature Coverage

Important suites include:

- `BookingPolicyTest` and `BookingSuggestionsRemovalTest`.
- `StaffAppointmentPaymentTest`, `CustomerPaymentRecoveryTest`, and `PaymongoRefundWebhookTest`.
- `PackageMembershipCatalogTest`.
- `AuthVerificationTest`, `PasswordResetTest`, `RememberMeTest`, and `SocialAuthenticationTest`.
- `SecurityRegressionTest`.
- `StaffNotificationTest`.
- `AccessibilityMarkupTest`.
- `CanonicalHostTest` and `AssetSchemeTest`.
- `ReportingPdfLayoutTest`.
- `ChatbotTest`.

Search tests before adding a new file; extend the closest behavior suite when practical.

## Test Data

- Use factories and explicit domain state.
- Freeze time with Carbon when testing holds, cutoffs, reminders, or schedule logic.
- Mock external PayMongo/email behavior at the service boundary.
- Assert database state as well as redirects or response text for state-changing operations.
- Include ownership and role denial cases for protected actions.

## UI Verification

For UI work, automated PHP tests are not enough. Check:

- Actual rendered component or page.
- Narrow phone and desktop sizes, plus tablet for structural layout changes.
- Long names, emails, service titles, and transaction references.
- Empty, loading, validation, success, disabled, and error states when affected.
- Dark and light themes when supported.
- No horizontal overflow, hidden actions, console errors, or content behind fixed controls.

## Definition of Verified

State exactly what ran and its result. Do not say “fully tested” when only syntax or one viewport was checked. If a production-only integration could not be exercised, identify that limitation.

## Before Commit

1. Review every changed file.
2. Run `git diff --check`.
3. Confirm no temporary screenshots, scripts, generated credentials, or `.env` changes are staged.
4. Confirm documentation matches business or architectural changes.
5. Confirm the working tree contains only intended changes.
