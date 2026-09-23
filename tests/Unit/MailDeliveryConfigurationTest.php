<?php

namespace Tests\Unit;

use App\Support\MailDeliveryConfiguration;
use Tests\TestCase;

class MailDeliveryConfigurationTest extends TestCase
{
    public function test_non_delivery_mailers_are_not_ready(): void
    {
        config()->set('mail.default', 'log');

        $this->assertFalse(MailDeliveryConfiguration::isReady());
    }

    public function test_smtp_url_is_accepted_without_separate_credentials(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.url', 'smtp://user:password@example.test:587');
        config()->set('mail.mailers.smtp.username', null);
        config()->set('mail.mailers.smtp.password', null);

        $this->assertTrue(MailDeliveryConfiguration::isReady());
    }

    public function test_smtp_requires_complete_connection_details_without_url(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.url', null);
        config()->set('mail.mailers.smtp.host', 'smtp.gmail.com');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.username', 'mailer@example.test');
        config()->set('mail.mailers.smtp.password', null);

        $this->assertFalse(MailDeliveryConfiguration::isReady());

        config()->set('mail.mailers.smtp.password', 'app-password');

        $this->assertTrue(MailDeliveryConfiguration::isReady());
    }
}
