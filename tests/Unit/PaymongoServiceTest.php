<?php

namespace Tests\Unit;

use App\Services\PaymongoService;
use Tests\TestCase;

class PaymongoServiceTest extends TestCase
{
    public function test_payment_methods_accept_spaces_and_commas_without_duplicates(): void
    {
        config()->set('services.paymongo.payment_method_types', ['gcash qrph', 'qrph,gcash']);

        $this->assertSame(['gcash', 'qrph'], app(PaymongoService::class)->paymentMethodTypes());
    }

    public function test_checkout_session_must_contain_a_configured_payment_method(): void
    {
        config()->set('services.paymongo.payment_method_types', ['gcash qrph']);
        $service = app(PaymongoService::class);

        $this->assertFalse($service->checkoutSessionHasUsablePaymentMethods([
            'attributes' => ['payment_method_types' => ['gcash qrph']],
        ]));
        $this->assertTrue($service->checkoutSessionHasUsablePaymentMethods([
            'attributes' => ['payment_method_types' => ['gcash']],
        ]));
        $this->assertFalse($service->checkoutSessionHasUsablePaymentMethods([
            'attributes' => ['payment_method_types' => []],
        ]));
    }
}
