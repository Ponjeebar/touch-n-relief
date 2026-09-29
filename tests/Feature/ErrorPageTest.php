<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_missing_page_uses_the_branded_safe_error_page(): void
    {
        $response = $this->get('/this-touch-n-relief-page-does-not-exist');

        $response->assertNotFound()
            ->assertSee('We could not find that page')
            ->assertSee('Return to home')
            ->assertHeader('X-Request-ID');
    }

    public function test_server_error_hides_exception_details_and_shows_reference_id(): void
    {
        Route::get('/test-server-error', static function (): never {
            throw new RuntimeException('private database connection detail');
        });

        $response = $this->get('/test-server-error');

        $response->assertStatus(500)
            ->assertSee('We could not complete your request')
            ->assertSee('Reference ID:')
            ->assertDontSee('private database connection detail')
            ->assertDontSee('RuntimeException');
    }

    public function test_forbidden_and_expired_responses_give_a_next_action(): void
    {
        Route::get('/test-forbidden-error', static fn () => abort(403, 'private authorization detail'));
        Route::get('/test-expired-error', static fn () => abort(419, 'private session detail'));

        $this->get('/test-forbidden-error')
            ->assertForbidden()
            ->assertSee('Return to the home page')
            ->assertDontSee('private authorization detail');

        $this->get('/test-expired-error')
            ->assertStatus(419)
            ->assertSee('Return and sign in')
            ->assertDontSee('private session detail');
    }

    public function test_json_errors_remain_json_responses(): void
    {
        $this->getJson('/this-json-page-does-not-exist')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }
}
