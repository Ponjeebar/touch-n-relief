import { execFileSync } from 'node:child_process';
import { closeSync, openSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const database = join(root, 'database', 'browser-tests.sqlite').replaceAll('\\', '/');
const env = [
    'APP_NAME="TouchNRelief Browser Tests"',
    'APP_ENV=e2e',
    'APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
    'APP_DEBUG=true',
    'APP_URL=http://127.0.0.1:8010',
    'APP_TIMEZONE=Asia/Manila',
    'LOG_CHANNEL=stderr',
    'DB_CONNECTION=sqlite',
    `DB_DATABASE="${database}"`,
    'SESSION_DRIVER=file',
    'CACHE_STORE=file',
    'QUEUE_CONNECTION=sync',
    'MAIL_MAILER=array',
    'FILESYSTEM_DISK=local',
    'MEDIA_DISK=public',
    'PAYMONGO_SECRET_KEY=',
    'PAYMONGO_PUBLIC_KEY=',
    'PAYMONGO_WEBHOOK_SECRET=',
    'GOOGLE_CLIENT_ID=',
    'GOOGLE_CLIENT_SECRET=',
    '',
].join('\n');

writeFileSync(join(root, '.env.e2e'), env, 'utf8');
closeSync(openSync(database, 'w'));

function artisan(...args) {
    execFileSync('php', ['artisan', ...args, '--env=e2e'], {
        cwd: root,
        stdio: 'inherit',
    });
}

artisan('config:clear');
artisan('migrate:fresh', '--force');
artisan('db:seed', '--class=Database\\Seeders\\DatabaseSeeder', '--force');
artisan('db:seed', '--class=Database\\Seeders\\BrowserTestSeeder', '--force');
