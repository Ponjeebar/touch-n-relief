<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    public function test_customers_cannot_access_staff_pages_or_mutations(): void
    {
        $customer = new User(['role' => User::ROLE_USER]);
        $customer->id = 123;

        $this->actingAs($customer)->get('/client-records')->assertForbidden();
        $this->actingAs($customer)->get('/appointments')->assertForbidden();
        $this->actingAs($customer)->post('/services', [])->assertForbidden();
    }

    public function test_registration_return_url_rejects_protocol_relative_redirects(): void
    {
        $controller = app(AuthController::class);
        $method = new \ReflectionMethod($controller, 'isSafeReturnTo');
        $request = Request::create('https://spa.example/register');

        $this->assertFalse($method->invoke($controller, $request, '//evil.example/path'));
        $this->assertFalse($method->invoke($controller, $request, '/\\evil.example/path'));
        $this->assertTrue($method->invoke($controller, $request, '/booking'));
    }
}
