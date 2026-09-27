# TouchNRelief

TouchNRelief is the customer booking and spa operations system for Buenos Touché Spa. It combines a public service catalogue, guided appointment booking, PayMongo payments, customer account tools, receptionist workflows, therapist scheduling, and administrator reporting.

## Main Capabilities

- Individual services, THERA packages, membership information, and therapist profiles.
- Email and Google account registration with verification and wellness onboarding.
- Appointment availability based on service duration, therapist schedules, closures, conflicts, and payment holds.
- PayMongo full/downpayment checkout, payment recovery, balance collection, refunds, and branded receipts.
- Customer profile, appointment management, notifications, and transaction history.
- Receptionist appointment, session, client record, and payment operations.
- Administrator user, therapist, service, schedule, landing content, reporting, backup, and audit-log tools.
- Responsive customer and staff interfaces plus a booking assistant chatbot.

## Technology

- Laravel 12 / PHP 8.2+
- Blade, JavaScript, Vite, and Tailwind CSS 4
- MySQL for common local development and PostgreSQL on Heroku
- PayMongo, Google OAuth through Socialite, Resend-compatible email, Dompdf, and S3-compatible media storage
- PHPUnit feature and unit tests

## Local Setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Configure the local database and optional integrations in `.env`. Never commit real credentials.

## Quality Checks

```powershell
.\vendor\bin\pint --dirty
php artisan view:cache
php artisan test
npm run build
git diff --check
```

## Documentation

- Start with [`AGENTS.md`](AGENTS.md) for mandatory contribution rules.
- Coding assistants must read [`docs/ai/00_READ_FIRST.md`](docs/ai/00_READ_FIRST.md).
- Database snapshot: [`docs/database-erd-full.md`](docs/database-erd-full.md).
- Responsive audit: [`TouchNRelief_Mobile_Tablet_Responsive_UI_Audit.md`](TouchNRelief_Mobile_Tablet_Responsive_UI_Audit.md).
- Heroku deployment: [`docs/HEROKU_DEPLOYMENT.md`](docs/HEROKU_DEPLOYMENT.md).
- Platform portability: [`LARAVEL_DEPLOYMENT_PORTABILITY.md`](LARAVEL_DEPLOYMENT_PORTABILITY.md).

## Deployment

The `Procfile` runs database migrations during the Heroku release phase and serves Laravel from `public/`. Review the deployment documents and migration safety before releasing production changes.
