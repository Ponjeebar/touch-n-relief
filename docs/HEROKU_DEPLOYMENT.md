# Heroku deployment

This application runs as a PHP application on Heroku. Its browser assets are committed under `public/`, so it does not require the Node.js buildpack.

## One-time app setup

Replace `APP_NAME_HERE` with the Heroku application name.

```powershell
heroku buildpacks:clear --app APP_NAME_HERE
heroku buildpacks:add heroku/php --app APP_NAME_HERE
heroku addons:create heroku-postgresql:essential-0 --app APP_NAME_HERE
heroku config:set APP_ENV=production APP_DEBUG=false APP_TIMEZONE=Asia/Manila DB_CONNECTION=pgsql LOG_CHANNEL=stderr SESSION_DRIVER=database CACHE_STORE=database QUEUE_CONNECTION=sync SESSION_SECURE_COOKIE=true --app APP_NAME_HERE
heroku config:set APP_KEY="$(php artisan key:generate --show)" --app APP_NAME_HERE
```

Set the public application URL and private service credentials separately:

```powershell
heroku config:set APP_URL=https://APP_NAME_HERE.herokuapp.com --app APP_NAME_HERE
heroku config:set MAIL_MAILER=log RESEND_API_KEY=YOUR_KEY RESEND_FROM_ADDRESS=YOUR_VERIFIED_SENDER RESEND_FROM_NAME=TouchNRelief --app APP_NAME_HERE
heroku config:set PAYMONGO_SECRET_KEY=YOUR_SECRET PAYMONGO_PUBLIC_KEY=YOUR_PUBLIC_KEY PAYMONGO_WEBHOOK_SECRET=YOUR_WEBHOOK_SECRET PAYMONGO_PAYMENT_METHOD_TYPES=gcash,qrph --app APP_NAME_HERE
```

Do not place production credentials in Git or copy the local `.env` file to Heroku.

## Deploy

For GitHub automatic deploys, deploy the `main` branch from the Heroku Deploy tab. For Heroku Git:

```powershell
heroku git:remote --app APP_NAME_HERE
git push heroku main
```

The `release` process in `Procfile` runs `php artisan migrate --force`. A failed migration prevents the new release from replacing the currently running release.

## Verify

```powershell
heroku ps --app APP_NAME_HERE
heroku releases --app APP_NAME_HERE
heroku logs --tail --app APP_NAME_HERE
heroku run php artisan about --app APP_NAME_HERE
heroku run php artisan migrate:status --app APP_NAME_HERE
```

Open `https://APP_NAME_HERE.herokuapp.com/up`. A successful response confirms Laravel booted and can serve requests.

## File uploads

Heroku's local filesystem is temporary. Profile pictures and therapist images uploaded to the local `public` disk can disappear after a restart or deploy. Configure durable object storage before relying on uploads in production.
