# Laravel Deployment Portability Guide

Use this guide to keep the project portable between Railway, Heroku, Render, VPS hosting, and other HTTPS hosting providers.

The goal is to avoid common deployment problems such as:

- CSS or JavaScript not loading
- HTTPS mixed-content issues
- Assets using `http://` instead of `https://`
- Hard-coded localhost URLs
- Broken database connections
- Vite assets missing in production
- Environment variables being committed to GitHub
- Hosting-provider-specific code

---

# 1. Configure Trusted Proxies

If Laravel is behind a proxy or load balancer, it may incorrectly detect requests as HTTP instead of HTTPS.

For Laravel 11/12, open:

```text
bootstrap/app.php
```

Make sure the middleware contains:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

Example:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

This helps Laravel correctly recognize HTTPS when deployed behind hosting-provider proxies.

---

# 2. Never Hard-Code Production Domains

Do not hard-code URLs such as:

```text
https://touch-n-relief-production.up.railway.app/css/landing.css
```

or:

```text
http://localhost/css/landing.css
```

Instead, use Laravel helpers.

For CSS:

```blade
<link rel="stylesheet" href="{{ asset('css/landing.css') }}">
```

For images:

```blade
<img src="{{ asset('images/logo.png') }}">
```

For routes:

```blade
<a href="{{ route('login') }}">Login</a>
```

Do not write:

```blade
<a href="http://localhost/login">Login</a>
```

The same code should work on localhost, Railway, Heroku, Render, VPS hosting, and a custom domain.

---

# 3. Control the Domain Through APP_URL

Keep your local environment separate from production.

Local `.env`:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```

Railway:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://touch-n-relief-production.up.railway.app
```

Custom domain later:

```env
APP_URL=https://yourdomain.app
```

If you change hosting providers, update `APP_URL` in the hosting provider's environment variables instead of changing application code.

---

# 4. Never Commit .env to GitHub

GitHub should contain:

```text
.env.example
```

It should NOT contain:

```text
.env
```

Make sure `.gitignore` includes:

```gitignore
.env
```

Each environment should manage its own configuration:

```text
GitHub
   |
   +-- Application code
        |
        +-- Local computer -> local .env
        +-- Railway -> Railway Variables
        +-- Heroku -> Config Vars
        +-- Other host -> Environment Variables
```

Never place passwords, secret keys, API keys, database credentials, payment secrets, or production tokens in GitHub.

---

# 5. Keep Database Configuration Environment-Based

Do not hard-code database settings in PHP files.

Avoid:

```php
'host' => 'localhost',
'username' => 'root',
'password' => '',
```

Laravel should read database values from environment variables:

```php
env('DB_HOST')
env('DB_PORT')
env('DB_DATABASE')
env('DB_USERNAME')
env('DB_PASSWORD')
```

Local configuration may use:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=db_bueno
DB_USERNAME=root
DB_PASSWORD=
```

Railway can use:

```env
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

A future hosting provider can use different database credentials without changing the Laravel source code.

---

# 6. Make Frontend Builds Reproducible

Keep these files in GitHub when using Vite:

```text
package.json
package-lock.json
vite.config.js
resources/
```

Make sure this works locally:

```bash
npm ci
npm run build
```

For Laravel Vite, use:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

A production build process should include:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Do not depend on a local development server for production assets.

---

# 7. Do Not Use the Vite Development Server in Production

During local development:

```bash
npm run dev
```

is fine.

For production:

```bash
npm run build
```

must be used.

The deployed website must never try to load assets from:

```text
http://localhost:5173
```

because visitors cannot access the developer's local computer.

---

# 8. Avoid Hard-Coded Local Paths in CSS

Do not use:

```css
background-image: url("http://localhost/myApp/public/images/background.jpg");
```

Prefer portable paths such as:

```css
background-image: url("../images/background.jpg");
```

When appropriate, generate paths through Blade:

```blade
style="background-image: url('{{ asset('images/background.jpg') }}')"
```

---

# 9. Clear Laravel Caches During Deployment

Environment changes can be hidden by old cached configuration.

A production deployment can run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
```

Only use:

```bash
php artisan route:cache
```

if all routes in the project support route caching.

Do not blindly cache everything without testing first.

---

# 10. HTTPS Fallback

Correct trusted-proxy configuration should be the first solution.

If a hosting provider still causes Laravel to generate HTTP URLs in production, HTTPS can be forced conditionally.

Open:

```text
app/Providers/AppServiceProvider.php
```

Add:

```php
use Illuminate\Support\Facades\URL;
```

Then inside `boot()`:

```php
public function boot(): void
{
    if (app()->environment('production')) {
        URL::forceScheme('https');
    }
}
```

Use this as a fallback, not as the first fix.

---

# 11. Production Environment Checklist

Before deploying to any hosting provider, verify:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` uses the correct HTTPS domain
- `APP_KEY` is present
- Trusted proxies are configured
- Database settings come from environment variables
- `.env` is excluded from GitHub
- No localhost URLs are hard-coded
- No Railway-specific URL is hard-coded
- No XAMPP path is hard-coded
- CSS and JS use Laravel/Vite helpers
- `npm run build` succeeds
- Composer dependencies install successfully
- Production caches are cleared/rebuilt
- HTTPS assets load without mixed-content errors
- Database connection works
- Login works
- Public pages work
- Private pages remain protected

---

# 12. Search the Codebase Before Deployment

Before final deployment or before moving to another hosting provider, search the entire project for:

```text
http://localhost
https://localhost
127.0.0.1
localhost:
xampp
C:\xampp
/public/
touch-n-relief-production.up.railway.app
http://
```

Review every match.

Replace hard-coded environment-specific values with:

- `asset()`
- `route()`
- `url()`
- `config()`
- environment variables
- Vite helpers

Do not replace valid third-party API URLs just because they contain `https://`.

---

# 13. Recommended Project Structure

The project should follow this principle:

```text
Laravel Application
|
+-- No localhost URLs hard-coded
+-- No hosting-provider domain hard-coded
+-- asset() / route() / @vite used correctly
+-- Trusted proxies configured
+-- .env excluded from Git
+-- APP_URL controlled by hosting environment
+-- Database credentials controlled by hosting environment
+-- npm run build used for production
+-- Secrets stored only in environment variables
```

---

# 14. Moving to Another Hosting Provider

When moving the project later, the process should mostly be:

1. Deploy the GitHub repository.
2. Configure production environment variables.
3. Create or connect the database.
4. Import or migrate database data.
5. Set the correct `APP_URL`.
6. Install Composer dependencies.
7. Run `npm ci`.
8. Run `npm run build`.
9. Clear/rebuild Laravel caches.
10. Configure the public HTTPS domain.
11. Test the entire system.
12. Check the browser console and Network tab for failed assets.

You should not need to rewrite the application just because the hosting provider changed.

---

# 15. Important Rule for AI Coding Assistants

Before making deployment-related changes:

1. Inspect the existing project first.
2. Search for hard-coded URLs and local paths.
3. Check the current Laravel version.
4. Check the current asset setup.
5. Check `package.json`.
6. Check `vite.config.js`.
7. Check `bootstrap/app.php`.
8. Check `config/database.php`.
9. Check `.gitignore`.
10. Check `.env.example`.
11. Explain the problem before changing files.
12. Make the smallest safe change.

Do not redesign, rewrite, or refactor unrelated parts of the system while fixing deployment configuration.

---

## Core Principle

**Keep code environment-independent.**

Hosting-specific values belong in environment variables, not in source code.

A correctly configured Laravel project should be able to move between hosting providers with minimal code changes.
