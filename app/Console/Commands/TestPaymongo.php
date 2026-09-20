<?php

namespace App\Console\Commands;

use App\Services\PaymongoService;
use Illuminate\Console\Command;

class TestPaymongo extends Command
{
    protected $signature = 'paymongo:test';

    protected $description = 'Verify PayMongo API credentials';

    public function handle(PaymongoService $paymongo): int
    {
        if (! $paymongo->isConfigured()) {
            $this->error('PayMongo is not configured. Run: php artisan paymongo:configure --secret=sk_test_xxx');

            return self::FAILURE;
        }

        $mode = str_starts_with($paymongo->secretKey(), 'sk_live_') ? 'live' : 'test';

        try {
            $session = $paymongo->createCheckoutSession([
                'line_items' => [[
                    'name' => 'Connection test',
                    'amount' => 10000,
                    'currency' => 'PHP',
                    'quantity' => 1,
                ]],
                'payment_method_types' => ['gcash', 'qrph'],
                'success_url' => url('/'),
                'cancel_url' => url('/'),
            ]);

            $checkoutUrl = (string) ($session['attributes']['checkout_url'] ?? '');

            $sessionId = (string) ($session['id'] ?? '');

            $this->info('PayMongo connection OK ('.$mode.' mode).');
            $this->line('Session ID: '.($sessionId !== '' ? $sessionId : 'n/a'));

            if ($checkoutUrl !== '') {
                $this->line('Checkout URL: '.$checkoutUrl);
            }

            if ($sessionId !== '') {
                try {
                    $retrieved = $paymongo->retrieveCheckoutSession($sessionId);
                    $this->line('Retrieve OK — session status: '.($retrieved['attributes']['status'] ?? 'n/a'));
                } catch (\Throwable $retrieveException) {
                    $this->warn('Checkout session retrieve check failed: '.$retrieveException->getMessage());
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('PayMongo connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
