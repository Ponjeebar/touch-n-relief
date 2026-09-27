# Authentication and Security

## Current Authentication

- Email/username login uses Laravel authentication through `AuthController`.
- Registration and sensitive recovery flows use verification codes and throttling.
- Google is the only enabled social provider in routes.
- Google users complete required account details through the social completion flow.
- Password reset uses Laravel's broker plus branded delivery through Resend or the configured mailer.
- Password rules and password history are centralized; do not duplicate weaker validation.

## Roles and Authorization

- `user`: customer routes.
- `receptionist`: staff routes.
- `admin`: staff routes plus administrator routes.
- Route middleware is necessary, and domain operations may require additional authorization checks.
- Never authorize an operation based only on a hidden button or submitted role field.

## Session Rules

- Sessions use the configured Laravel driver; production uses database sessions.
- Respect `SESSION_EXPIRE_ON_CLOSE` and remember-me behavior.
- Regenerate the session after authentication and invalidate it on logout using established controller behavior.
- Do not expose staff landing access when role-based dashboard redirection is required.

## Payment Security

- Keep PayMongo secret keys and webhook secrets in environment configuration.
- Verify webhook signatures using the existing service.
- Verify checkout state with PayMongo before marking a booking paid.
- Store only the references needed for reconciliation; never log secret keys or full sensitive payloads.
- Do not let customers set payment status, amount paid, balance collection, or refund state directly.

## Input and Output

- Use Laravel request validation and authorization.
- Escape user-controlled content in Blade with `{{ }}` unless reviewed sanitized HTML is required.
- Validate uploaded files by type, size, and destination through existing patterns.
- Do not return stack traces, credentials, raw gateway errors, or database details to users.
- Apply throttling to authentication, chatbot, checkout recovery, and other abuse-prone endpoints.

## Secrets

- Never commit `.env`, keys, tokens, database URLs, or signed temporary URLs.
- Document variable names and purpose in `.env.example` with blank values.
- Use Heroku config vars for production secrets.
- If a secret appears in source control or output, treat it as compromised and rotate it.

## Security Change Checklist

1. Trace routes, middleware, controller checks, model ownership, and service rules.
2. Test unauthenticated, wrong-role, wrong-owner, invalid-token, and valid cases.
3. Confirm throttling and error messages.
4. Confirm logs do not contain secrets or sensitive personal data.
5. Run `SecurityRegressionTest` plus feature-specific tests.
