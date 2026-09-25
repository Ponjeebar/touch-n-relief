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
}
