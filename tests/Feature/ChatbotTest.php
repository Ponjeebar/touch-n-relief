<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\SpaBooking;
use App\Models\User;
use App\Services\SiteSettingsService;
use Database\Seeders\SpaServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SpaServiceSeeder::class);
    }

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
            ->assertOk()
            ->assertSee('Own Service')
            ->assertDontSee('Private Service')
            ->assertJsonPath('actions.0.label', 'Manage bookings')
            ->assertJsonPath('actions.0.url', route('profile.edit', ['appointments' => 1]));

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

    public function test_chatbot_explains_its_identity_and_answers_business_questions_without_sign_in(): void
    {
        foreach (['who are you', 'What is your name?', 'Are you a real person?', 'Sino ka?', 'ano pangalan mo'] as $question) {
            $this->postJson(route('chatbot.reply'), ['message' => $question])
                ->assertOk()->assertSee('virtual assistant')->assertSee('TouchNRelief')
                ->assertDontSee('didn’t quite understand')->assertDontSee('Sign in or register');
        }

        foreach (['Who owns the spa?', 'Sino ang may-ari?', 'Who is the owner of Buenos Touche?'] as $question) {
            $this->postJson(route('chatbot.reply'), ['message' => $question])
                ->assertOk()->assertSee('Ronald Bueno')->assertDontSee('Sign in or register');
        }

        $this->postJson(route('chatbot.reply'), ['message' => 'Tell me about the spa'])
            ->assertOk()->assertSee('Ronald Bueno')->assertSee('massage');
    }

    public function test_business_questions_override_an_old_service_topic_and_use_public_settings(): void
    {
        SiteSetting::put('business_owner_name', 'Published Owner');
        SiteSetting::put(SiteSettingsService::KEY_CONTACT_PHONE, '09170000001');
        SiteSetting::put(SiteSettingsService::KEY_FACEBOOK_URL, 'https://www.facebook.com/published-spa');
        SiteSetting::put(SiteSettingsService::KEY_INSTAGRAM_URL, 'https://www.instagram.com/published-spa');
        User::factory()->create(['role' => User::ROLE_ADMIN, 'name' => 'Private Admin', 'email' => 'private@example.test']);

        $this->postJson(route('chatbot.reply'), ['message' => 'How much is Hot Stone?'])->assertOk();
        $this->postJson(route('chatbot.reply'), ['message' => 'Who owns it?'])
            ->assertOk()->assertSee('Published Owner')->assertDontSee('Private Admin')->assertDontSee('PHP 145.00');
        $this->postJson(route('chatbot.reply'), ['message' => 'What is your Facebook page?'])
            ->assertOk()->assertJsonPath('actions.0.url', 'https://www.facebook.com/published-spa')
            ->assertJsonPath('actions.0.external', true);
        $this->postJson(route('chatbot.reply'), ['message' => 'Do you have Instagram?'])
            ->assertOk()->assertJsonPath('actions.0.url', 'https://www.instagram.com/published-spa');
        $this->postJson(route('chatbot.reply'), ['message' => 'What is his private phone number?'])
            ->assertOk()->assertSee('09170000001')->assertDontSee('private@example.test');
    }

    public function test_missing_or_unsafe_social_links_are_not_invented_or_returned(): void
    {
        SiteSetting::put(SiteSettingsService::KEY_FACEBOOK_URL, 'https://facebook.com.evil.test/spa');
        SiteSetting::put(SiteSettingsService::KEY_INSTAGRAM_URL, 'javascript:alert(1)');
        $this->postJson(route('chatbot.reply'), ['message' => 'social media accounts'])
            ->assertOk()->assertSee('not published')->assertDontSee('evil.test')->assertDontSee('javascript:');
    }

    public function test_small_talk_and_unknown_questions_offer_relevant_help_without_demanding_login(): void
    {
        $this->postJson(route('chatbot.reply'), ['message' => 'How are you?'])
            ->assertOk()->assertSee('help')->assertDontSee('didn’t quite understand');
        $this->postJson(route('chatbot.reply'), ['message' => 'What is the weather on Mars?'])
            ->assertOk()->assertSee('spa')->assertDontSee('didn’t quite understand')->assertDontSee('Sign in or register');
    }

    public function test_public_owner_name_can_be_maintained_through_landing_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $footer = app(SiteSettingsService::class)->footer();
        $this->actingAs($admin)->put(route('landing-settings.update'), [...$footer, 'owner_name' => 'Updated Public Owner'])
            ->assertRedirect(route('landing-settings.edit'));
        $this->postJson(route('chatbot.reply'), ['message' => 'Who owns the business?'])
            ->assertOk()->assertSee('Updated Public Owner');
        $this->actingAs($admin)->put(route('landing-settings.update'), [...$footer, 'owner_name' => str_repeat('a', 121)])
            ->assertSessionHasErrors('owner_name');
    }

    public function test_chatbot_remembers_a_service_for_natural_follow_up_questions(): void
    {
        $this->postJson(route('chatbot.reply'), ['message' => 'How much is Hot Stone?'])
            ->assertOk()
            ->assertSee('Hot Stone')
            ->assertSee('PHP 145.00')
            ->assertJsonPath('actions.0.label', 'Book this service')
            ->assertJsonStructure(['suggestions']);

        $this->postJson(route('chatbot.reply'), ['message' => 'How long is it?'])
            ->assertOk()
            ->assertSee('Hot Stone')
            ->assertSee('90 min')
            ->assertJsonMissingPath('context');
    }

    public function test_chatbot_handles_local_phrasing_and_personalized_conversation(): void
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_USER,
            'name' => 'Maria Santos',
        ]);

        $this->postJson(route('chatbot.reply'), ['message' => 'Magkano ang Thai Massage?'])
            ->assertOk()
            ->assertSee('Thai Massage')
            ->assertSee('PHP 110.00');

        $this->actingAs($customer)->postJson(route('chatbot.reply'), ['message' => 'Hello'])
            ->assertOk()
            ->assertSee('Maria')
            ->assertJsonPath('suggestions.0', 'My appointments');

        $this->actingAs($customer)->postJson(route('chatbot.reply'), ['message' => 'Salamat'])
            ->assertOk()
            ->assertSee('welcome');
    }

    public function test_chatbot_session_endpoint_returns_a_fresh_csrf_token(): void
    {
        $this->getJson(route('chatbot.session'))
            ->assertOk()
            ->assertJsonStructure(['token']);
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

        $profile = $this->actingAs($customer)->get(route('profile.edit'));
        $profile->assertOk()->assertSee('data-chatbot-role="customer"', false);
        $this->assertSame(1, substr_count($profile->getContent(), 'src="'.asset('js/chatbot.js')));
    }
}
