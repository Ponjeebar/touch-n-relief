# Deployment and Operations

## Environments

- Local development commonly uses XAMPP, MySQL, and the repository `.env`.
- Production runs on Heroku using the `heroku/php` buildpack and Heroku PostgreSQL.
- The public application uses the configured custom domain; `APP_URL` and OAuth/payment callback URLs must match it.

## Heroku Process Types

From `Procfile`:

```text
release: php artisan migrate --force
web: heroku-php-apache2 public/
```

A release is not successful until the release command and deploy verification succeed.

## Required Production Configuration

Categories include:

- Laravel application key, URL, environment, debug setting, timezone, and logging.
- PostgreSQL database URL/connection.
- Database session and cache configuration.
- Google OAuth credentials and callback.
- PayMongo public, secret, webhook, and payment-method configuration.
- Resend or mail delivery credentials.
- S3-compatible media storage credentials when persistent uploads are enabled.

Never place actual values in documentation or source control.

## Deployment Workflow

1. Verify relevant tests, Blade compilation, formatting, and asset build.
2. Review migrations for production safety and PostgreSQL compatibility.
3. Commit the intended files.
4. Push the same commit to the GitHub `main` branch when authorized.
5. Push the same commit to the configured Heroku remote when authorized.
6. Wait for build, release command, and release status to succeed.
7. Confirm GitHub and Heroku reference the intended commit.
8. Smoke-test the affected public flow without exposing secrets or modifying live customer data.

## Deployment Reporting

Report:

- Commit hash and message.
- Git branch pushed.
- Heroku release number and status.
- Tests/builds run before deployment.
- Any production verification limitation.

## Rollback Considerations

- Application rollback may not safely reverse a database migration.
- Inspect migrations and data compatibility before rolling back a release.
- Prefer a forward fix for migrated production data unless a reviewed rollback is safe.
- Confirm payment/webhook compatibility across old and new releases.

See [`docs/HEROKU_DEPLOYMENT.md`](../HEROKU_DEPLOYMENT.md) and [`LARAVEL_DEPLOYMENT_PORTABILITY.md`](../../LARAVEL_DEPLOYMENT_PORTABILITY.md) for detailed platform notes.
