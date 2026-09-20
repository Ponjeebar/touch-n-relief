<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ConfigurePaymongo extends Command
{
    protected $signature = 'paymongo:configure
                            {--secret= : PayMongo secret key (sk_test_... or sk_live_...)}
                            {--public= : PayMongo public key (pk_test_... or pk_live_...)}
                            {--webhook= : PayMongo webhook secret (whsk_...)}';

    protected $description = 'Save PayMongo API keys to the .env file';

    public function handle(): int
    {
        $secret = (string) ($this->option('secret') ?: $this->ask('Secret key (sk_test_... or sk_live_...)'));

        if ($this->option('public') !== null) {
            $public = (string) $this->option('public');
        } elseif ($this->option('secret')) {
            $public = '';
        } else {
            $public = (string) $this->ask('Public key (pk_test_... or pk_live_..., optional)', '');
        }

        if ($this->option('webhook') !== null) {
            $webhook = (string) $this->option('webhook');
        } elseif ($this->option('secret')) {
            $webhook = '';
        } else {
            $webhook = (string) $this->ask('Webhook secret (whsk_..., optional)', '');
        }

        if ($secret === '' || (! str_starts_with($secret, 'sk_test_') && ! str_starts_with($secret, 'sk_live_'))) {
            $this->error('A valid PayMongo secret key is required.');

            return self::FAILURE;
        }

        if ($public !== '' && ! str_starts_with($public, 'pk_test_') && ! str_starts_with($public, 'pk_live_')) {
            $this->error('Public key must start with pk_test_ or pk_live_.');

            return self::FAILURE;
        }

        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            $this->error('.env file not found.');

            return self::FAILURE;
        }

        $contents = file_get_contents($envPath);

        if ($contents === false) {
            $this->error('Unable to read .env file.');

            return self::FAILURE;
        }

        $contents = $this->upsertEnv($contents, 'PAYMONGO_SECRET_KEY', $secret);
        $contents = $this->upsertEnv($contents, 'PAYMONGO_PUBLIC_KEY', $public);
        $contents = $this->upsertEnv($contents, 'PAYMONGO_WEBHOOK_SECRET', $webhook);

        if (! array_key_exists('PAYMONGO_PAYMENT_METHOD_TYPES', $this->parseEnv($contents))) {
            $contents = rtrim($contents).PHP_EOL.PHP_EOL.'PAYMONGO_PAYMENT_METHOD_TYPES=gcash,qrph'.PHP_EOL;
        }

        file_put_contents($envPath, $contents);

        $this->call('config:clear');
        $this->info('PayMongo keys saved. Run php artisan paymongo:test to verify.');

        return self::SUCCESS;
    }

    private function upsertEnv(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            return (string) preg_replace($pattern, $line, $contents);
        }

        return rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $contents): array
    {
        $values = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $row) {
            if ($row === '' || str_starts_with(trim($row), '#') || ! str_contains($row, '=')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $row, 2), 2, '');
            $values[trim($key)] = trim($value);
        }

        return $values;
    }
}
