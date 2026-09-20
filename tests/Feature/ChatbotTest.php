<?php

namespace Tests\Feature;

use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_ask_general_questions_but_is_prompted_to_sign_in_for_appointments(): void
    {
        $this->postJson(route('chatbot.reply'), ['message' => 'Services and prices'])
            ->assertOk()->assertJsonStructure(['reply', 'actions'])
            ->assertJsonPath('actions.0.label', 'View services')
            ->assertJsonPath('actions.0.url', route('landing').'#services');

        $this->postJson(route('chatbot.reply'), ['message' => 'My appointments'])
            ->assertOk()->assertJsonPath('actions.0.label', 'Sign in or register');
    }

    public function test_customer_only_receives_their_own_booking_details(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);
        $other = User::factory()->create(['role' => User::ROLE_USER]);
        $own = SpaBooking::create([
            'user_id' => $customer->id,
            'service_name' => 'Own Service',
            'therapist_name' => 'Own Therapist',
            'booking_date' => now()->addDays(3)->toDateString(),
            'time_slot' => '10:00 AM',
        ]);
        $stranger = SpaBooking::create([
            'user_id' => $other->id,
            'service_name' => 'Private Service',
            'therapist_name' => 'Private Therapist',
            'booking_date' => now()->addDays(4)->toDateString(),
            'time_slot' => '11:00 AM',
        ]);

        $this->actingAs($customer)->postJson(route('chatbot.reply'), ['message' => 'My appointments'])
            ->assertOk()->assertSee('Own Service')->assertDontSee('Private Service');

        $this->actingAs($customer)->postJson(route('chatbot.reply'), ['message' => 'booking #'.$stranger->id])
            ->assertOk()->assertSee('could not find')->assertDontSee('Private Service');

        $this->actingAs($customer)->postJson(route('chatbot.reply'), ['message' => 'cancel booking #'.$own->id])
            ->assertOk()->assertSee('eligible to cancel');
    }

    public function test_message_length_is_validated(): void
    {
        $this->postJson(route('chatbot.reply'), ['message' => str_repeat('a', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_chatbot_is_hidden_on_auth_pages_but_present_on_the_public_landing_page(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('chatbot.js');
        $this->get(route('login', ['register' => 1]))->assertOk()->assertDontSee('chatbot.js');
        $this->get(route('password.request'))->assertOk()->assertDontSee('chatbot.js');
        $this->get(route('landing'))->assertOk()->assertSee('data-chatbot-role="guest"', false);
    }

    public function test_staff_get_role_specific_chat_prompts_and_answers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $receptionist = User::factory()->create(['role' => User::ROLE_RECEPTIONIST]);

        $this->actingAs($admin)->get(route('appointments.index'))
            ->assertOk()->assertSee('data-chatbot-role="admin"', false);
        $this->actingAs($admin)->postJson(route('chatbot.reply'), ['message' => 'Reports'])
            ->assertOk()->assertJsonPath('actions.0.url', route('reporting.index'));
        $this->actingAs($admin)->postJson(route('chatbot.reply'), ['message' => "Today's appointments"])
            ->assertOk()->assertJsonPath('actions.0.url', route('appointments.index'));

        $this->actingAs($receptionist)->get(route('appointments.index'))
            ->assertOk()->assertSee('data-chatbot-role="receptionist"', false);
        $this->actingAs($receptionist)->postJson(route('chatbot.reply'), ['message' => 'Ongoing sessions'])
            ->assertOk()->assertJsonPath('actions.0.url', route('ongoing-sessions.index'));
        $this->actingAs($receptionist)->postJson(route('chatbot.reply'), ['message' => 'booking #123'])
            ->assertOk()->assertJsonPath('actions.0.url', route('appointments.index'));
    }

    public function test_customer_pages_use_customer_chat_prompts(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_USER]);

        $this->actingAs($customer)->get(route('booking.index'))
            ->assertOk()->assertSee('data-chatbot-role="customer"', false);
    }
}
