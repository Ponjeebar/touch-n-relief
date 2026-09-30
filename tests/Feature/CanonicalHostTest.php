<?php

namespace Tests\Feature;

use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    public function test_www_requests_redirect_to_the_configured_root_domain(): void
    {
        config()->set('app.url', 'https://touchnrelief.app');

        $this->get('https://www.touchnrelief.app/login')
            ->assertStatus(301)
            ->assertRedirect('https://touchnrelief.app/login');
    }

    public function test_non_safe_www_requests_preserve_the_http_method(): void
    {
        config()->set('app.url', 'https://touchnrelief.app');

        $this->post('https://www.touchnrelief.app/login')
            ->assertStatus(308)
            ->assertRedirect('https://touchnrelief.app/login');
    }

    public function test_root_domain_requests_are_not_redirected(): void
    {
        config()->set('app.url', 'https://touchnrelief.app');

        $this->get('https://touchnrelief.app/login')->assertOk();
    }

    public function test_local_ip_does_not_receive_an_invalid_shared_domain_cookie(): void
    {
        config()->set('app.url', 'http://127.0.0.1:8010');

        $response = $this->get('http://127.0.0.1:8010/login')->assertOk();

        $domains = collect($response->headers->getCookies())
            ->map(fn ($cookie): ?string => $cookie->getDomain())
            ->filter()
            ->all();

        $this->assertNotContains('.127.0.0.1', $domains);
    }
}
