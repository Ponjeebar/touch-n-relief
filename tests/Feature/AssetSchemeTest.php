<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AssetSchemeTest extends TestCase
{
    public function test_configured_public_path_remains_available_through_laravel_config(): void
    {
        config()->set('app.public_path_url', 'nested/public');

        $this->assertSame('/nested/public', app_public_base_path());
    }

    public function test_assets_use_https_when_a_trusted_proxy_forwards_an_https_request(): void
    {
        Route::middleware('web')->get('/test-asset-url', fn (): string => asset('css/landing.css'));

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'untrusted.example',
        ])->get('http://example.test/test-asset-url')
            ->assertOk()
            ->assertSee('https://example.test/css/landing.css', false);
    }

    public function test_local_http_assets_remain_http_without_a_proxy_header(): void
    {
        Route::middleware('web')->get('/test-asset-url', fn (): string => asset('css/landing.css'));

        $this->get('http://example.test/test-asset-url')
            ->assertOk()
            ->assertSee('http://example.test/css/landing.css', false);
    }
}
