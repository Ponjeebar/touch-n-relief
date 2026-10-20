<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ChatbotService
{
    /** @var array{topic?: string, subject?: string} */
    private array $conversationContext = [];

    public function __construct(
        private readonly SpaServiceCatalog $catalog,
        private readonly SiteSettingsService $settings,
        private readonly BookingCancellationService $cancellations,
        private readonly BookingRescheduleService $reschedules,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{reply: string, actions: list<array{label: string, url: string, external?: bool}>, suggestions: list<string>, context: array<string, string>}
     */
    public function answer(string $message, ?User $user, array $context = []): array
    {
        $this->conversationContext = $this->sanitizeContext($context);
        $question = preg_replace('/\s+/u', ' ', mb_strtolower(trim($message))) ?? mb_strtolower(trim($message));
        $staff = $user !== null && ($user->isAdmin() || $user->isReceptionist());

        if ($publicAnswer = $this->businessInformation($question)) {
            return $publicAnswer;
        }

        if ($this->has($question, ['thank you', 'thanks', 'thankyou', 'salamat'])) {
            return $this->respond(
                'You’re welcome! Is there anything else I can help you with?',
                [],
                ($this->conversationContext['topic'] ?? null) === 'appointments'
                    ? ['Can I reschedule?', 'Can I cancel?', 'Book another appointment']
                    : ['Services and prices', 'Business hours', 'How do I book?'],
            );
        }

        if ($this->has($question, ['goodbye', 'bye', 'see you'])) {
            return $this->respond('Take care! We’ll be here whenever you’re ready to plan your next visit.', [], []);
        }

        if ($followUp = $this->followUp($question, $user)) {
            return $followUp;
        }

        if ($this->isGreeting($question)) {
            if ($staff) {
                return $this->roleHelp($user);
            }

            return $this->respond(
                $this->greeting($user).' How can I help you today? I can answer questions about services, prices, hours, location, and booking.'
                .($user ? ' I can also check your upcoming appointments.' : ' Sign in when you need help with your own appointments.'),
                $user ? [$this->action('Book an appointment', 'booking.index')] : $this->guestActions(),
                $user
                    ? ['My appointments', 'Services and prices', 'Can I reschedule?', 'Business hours']
                    : ['Services and prices', 'How do I book?', 'Business hours', 'Where are you located?'],
            );
        }

        if ($staff && ($staffReply = $this->staffHelp($question, $user)) !== null) {
            return $staffReply;
        }

        if ($this->has($question, ['resched', 'change my date', 'change my time', 'move my appointment', 'lipat appointment'])) {
            return $this->changeRules($question, $user, false);
        }

        if ($this->has($question, ['cancel', 'refund', 'kansela'])) {
            return $this->changeRules($question, $user, true);
        }

        if ($this->has($question, ['my appointment', 'my booking', 'upcoming appointment', 'upcoming booking', 'appointment status', 'booking status'])
            || preg_match('/(?:booking|appointment)\s*#?\s*\d+/u', $question) === 1) {
            return $this->appointments($question, $user);
        }

        if ($this->has($question, ['policy', 'policies', 'rules'])) {
            return $this->respond(
                'Customers can cancel or reschedule a booking before its appointment time, provided the session has not started or finished. '
                .'Paid cancellations may need a refund; the method and processing status depend on the payment channel. '
                .'For a specific booking, sign in and ask about your appointment.',
                $user ? [$this->appointmentsAction('View my bookings')] : $this->guestActions(),
            );
        }

        if ($this->has($question, ['my account', 'my profile', 'account information', 'my information', 'my role', 'my email', 'my name', 'who am i'])) {
            if (! $user) {
                return $this->signInFirst();
            }

            $role = $user->isAdmin() ? 'administrator' : ($user->isReceptionist() ? 'receptionist' : 'customer');

            return $this->respond('Your account is '.$user->name.' ('.$user->email.'), role: '.$role.'. '
                .'Open your profile to review or update your details.', [$this->action('Open profile', 'profile.edit')]);
        }

        if ($this->has($question, ['hour', 'open', 'closing', 'when are you', 'oras', 'bukas ba'])) {
            $this->remember('hours');
            $details = $this->settings->footer();

            return $this->respond("Here are our current spa hours:\n• ".$details['hours_weekday']."\n• "
                .$details['hours_weekend']."\n• ".$details['hours_holidays'].'.', [],
                ['Where are you located?', 'Services and prices', 'How do I book?']);
        }

        if ($this->has($question, ['location', 'address', 'where are you', 'directions', 'contact', 'phone', 'email', 'saan'])) {
            $this->remember('location');
            $details = $this->settings->footer();

            return $this->respond('You can find us at '.$details['contact_address'].'. You may also reach the spa at '
                .$details['contact_phone'].' or '.$details['contact_email'].'.', [],
                ['Business hours', 'Services and prices', 'How do I book?']);
        }

        if ($this->has($question, ['package', 'thera'])) {
            return $this->packages($question);
        }

        if ($this->has($question, ['membership', 'member price', 'member rate'])) {
            return $this->membership();
        }

        if ($this->has($question, ['service', 'massage', 'treatment', 'price', 'cost', 'how much', 'rate', 'magkano', 'presyo'])) {
            return $this->services($question);
        }

        if ($this->has($question, ['register', 'sign up', 'create an account', 'new account'])) {
            return $this->respond(
                $user ? 'You are already signed in. Your profile is available in your account.'
                    : 'Choose Sign Up on the login page. Enter your details and a strong password, then verify the six digit code sent to your email. After verification, you can book online.',
                $user ? [$this->action('Open profile', 'profile.edit')] : [['label' => 'Create an account', 'url' => route('login', ['register' => 1])]],
            );
        }

        if ($this->has($question, ['login', 'log in', 'sign in', 'password', 'forgot'])) {
            return $this->respond(
                $user ? 'You are signed in. You can manage your account from your profile.'
                    : 'Sign in with your username, name, or email and password. If you forgot your password, request the six digit verification code on the login page.',
                $user ? [$this->action('Open profile', 'profile.edit')]
                    : [$this->action('Sign in', 'login'), $this->action('Reset password', 'password.request')],
            );
        }

        if ($this->has($question, ['dashboard', 'report', 'client record', 'staff', 'receptionist', 'manage service', 'manage user'])) {
            return $this->roleHelp($user);
        }

        if ($this->has($question, ['book', 'appointment', 'schedule', 'pay', 'payment'])) {
            return $this->respond(
                'To book online, select a service, therapist, date, and available time, then complete PayMongo checkout. '
                .($user ? 'Your booking and payment status will appear in your account.' : 'You need an account to finish booking.'),
                $user ? [$this->action('Book now', 'booking.index')] : $this->guestActions(),
            );
        }

        if ($staff) {
            return $this->roleHelp($user);
        }

        return $this->respond(
            'I can help with Bueno’s Touché services, prices, booking, business hours, location, ownership, and official social media. '
            .'For a question that needs staff assistance, use the spa contact details below.',
            [$this->contactAction()],
            ['Who owns the spa?', 'Social media accounts', 'Services and prices', 'How do I book?'],
        );
    }

    private function businessInformation(string $question): ?array
    {
        $plain = trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $question) ?? $question);
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        if (preg_match('/\b(private|personal|home|bahay|personal na)\b/u', $plain)
            && preg_match('/\b(phone|number|email|address|contact|tirahan|numero)\b/u', $plain)) {
            $this->remember('business');
            $details = $this->settings->footer();

            return $this->respond('I can share the spa’s published contact information, not private personal details. '
                .'Contact Bueno’s Touché at '.$details['contact_phone'].' or '.$details['contact_email'].'.',
                [$this->contactAction()], ['Who owns the spa?', 'Social media accounts', 'Business hours']);
        }

        if (preg_match('/\b(who are you|what is your name|what s your name|whats your name|your name|sino ka|sino po kayo|ano pangalan mo|anong pangalan mo|are you (?:a |an )?(?:bot|robot|ai|human|real person)|what can you do)\b/u', $plain)) {
            $this->remember('business');

            return $this->respond('I’m the TouchNRelief virtual assistant for Bueno’s Touché. I’m an automated assistant, not a person. '
                .'I can help with services and prices, booking, business hours, location, the spa owner, and official social media. '
                .'Sign in only when you want to check your own appointment records.',
                [$this->contactAction()], ['Who owns the spa?', 'Social media accounts', 'Services and prices', 'How do I book?']);
        }

        if (preg_match('/\b(facebook|instagram|social media|socials|social account|messenger|fb|ig)\b/u', $plain)) {
            $this->remember('social');
            $platform = preg_match('/\b(instagram|ig)\b/u', $plain) ? 'instagram'
                : (preg_match('/\b(facebook|fb|messenger)\b/u', $plain) ? 'facebook' : null);
            $links = $this->socialActions($platform);
            $name = $platform === 'instagram' ? 'Instagram' : ($platform === 'facebook' ? 'Facebook' : 'social media');

            return $this->respond($links
                ? 'Here '.(count($links) === 1 ? 'is the official '.$name.' link' : 'are the official social media links').' published on the Bueno’s Touché landing page. Open a link below to visit the account.'
                : 'An official '.$name.' link is not published on the landing page right now. Please use the spa’s contact details to confirm the account.',
                $links ?: [$this->contactAction()], ['Who owns the spa?', 'Contact the spa', 'Business hours']);
        }

        if (preg_match('/\b(owner|owns|owned|ownership|proprietor|may ari|mayari|nagmamay ari|sino si ronald|ronald bueno)\b/u', $plain)) {
            $this->remember('business');
            $details = $this->settings->footer();

            return $this->respond('The owner of Bueno’s Touché is '.$details['owner_name'].'. '
                .'For business inquiries, contact the spa at '.$details['contact_phone'].' or '.$details['contact_email'].'.',
                [$this->contactAction(), ...$this->socialActions()], ['Social media accounts', 'Business hours', 'Where are you located?']);
        }

        if (preg_match('/\b(about the spa|about your spa|about the business|about buenos|about bueno|what is touchnrelief|what is touch n relief|tell me about yourself|what kind of business)\b/u', $plain)) {
            $this->remember('business');
            $details = $this->settings->footer();

            return $this->respond('Bueno’s Touché offers massage services and is owned by '.$details['owner_name'].'. '
                .'TouchNRelief is its online appointment management system. You can browse services, choose an available therapist and schedule, and complete the required payment through checkout. '
                .'The spa’s published address is '.$details['contact_address'].'.',
                [['label' => 'About the spa', 'url' => route('landing').'#about']],
                ['Services and prices', 'Who owns the spa?', 'Social media accounts', 'Business hours']);
        }

        if (preg_match('/\b(how are you|how s it going|kamusta ka|kumusta ka)\b/u', $plain)) {
            $this->remember('business');

            return $this->respond('I’m here and ready to help with your visit to Bueno’s Touché. What would you like to know?',
                [], ['Who are you?', 'Services and prices', 'Social media accounts', 'How do I book?']);
        }

        if (preg_match('/\b(speak to|talk to|human|real person|kausapin|customer support)\b/u', $plain)) {
            $this->remember('business');
            $details = $this->settings->footer();

            return $this->respond('For help from the spa staff, contact '.$details['contact_phone'].' or '.$details['contact_email'].'. '
                .'This chat provides automated guidance and does not send your message to a staff member.',
                [$this->contactAction(), ...$this->socialActions()], ['Business hours', 'Where are you located?']);
        }

        return null;
    }

    /** @return list<array{label: string, url: string, external: bool}> */
    private function socialActions(?string $platform = null): array
    {
        $details = $this->settings->footer();
        $links = [];
        foreach (['facebook' => ['facebook.com', 'fb.com', 'fb.me'], 'instagram' => ['instagram.com']] as $name => $hosts) {
            if ($platform !== null && $platform !== $name) {
                continue;
            }
            $url = trim($details[$name.'_url']);
            $parts = parse_url($url);
            $host = strtolower($parts['host'] ?? '');
            $trusted = collect($hosts)->contains(fn (string $allowed): bool => $host === $allowed || str_ends_with($host, '.'.$allowed));
            if (($parts['scheme'] ?? '') !== 'https' || ! $trusted || isset($parts['user']) || isset($parts['pass'])
                || (isset($parts['port']) && $parts['port'] !== 443)) {
                continue;
            }
            $links[] = ['label' => 'Open '.ucfirst($name), 'url' => $url, 'external' => true];
        }

        return $links;
    }

    private function contactAction(): array
    {
        return ['label' => 'Contact the spa', 'url' => route('landing').'#contact'];
    }

    private function services(string $question): array
    {
        $services = $this->catalog->all();
        $specific = collect($services)->first(fn (array $service): bool => str_contains($question, mb_strtolower((string) $service['name'])));

        if ($specific) {
            $this->remember('services', (string) $specific['name']);

            return $this->respond($specific['name'].' costs '.$specific['price'].' and lasts '.$specific['duration'].'. '
                .trim((string) ($specific['desc'] ?? '')),
                [$this->action('Book this service', 'booking.index', ['service' => $specific['name']])],
                ['How do I book?', 'Show me the packages', 'What are your hours?']);
        }

        $this->remember('services');
        $list = collect($services)->map(fn (array $service): string => '• '.$service['name'].' — '.$service['price'])->implode("\n");

        return $this->respond("Current services and prices:\n".$list."\nSelect a service on the website for details.",
            [$this->servicesAction()], ['Show me the packages', 'How do I book?', 'Business hours']);
    }

    private function packages(string $question): array
    {
        $packages = $this->catalog->packages();
        $specific = collect($packages)->first(fn (array $package): bool => str_contains($question, mb_strtolower((string) $package['name'])));

        if ($specific) {
            $this->remember('packages', (string) $specific['name']);
            $memberPrice = ! empty($specific['member_price']) ? ' Member price: '.$specific['member_price'].'.' : '';

            return $this->respond($specific['name'].' is '.$specific['price'].' for '.$specific['duration'].'.'.$memberPrice.' Includes: '.$specific['inclusions'].'.',
                [$this->action('Book this package', 'booking.index', ['service' => $specific['name']])],
                ['How do I book?', 'Show individual services', 'Tell me about membership']);
        }

        $this->remember('packages');
        $list = collect($packages)->map(fn (array $package): string => 'â€¢ '.$package['name'].' — '.$package['price'].' ('.$package['duration'].')')->implode("\n");

        return $this->respond("Current THERA packages:\n".$list, [$this->servicesAction()],
            ['Tell me about THERA #1', 'Tell me about membership', 'How do I book?']);
    }

    private function membership(): array
    {
        $this->remember('membership');

        if (! Schema::hasTable('membership_plans')) {
            return $this->respond('Membership details are temporarily unavailable.');
        }

        $plan = MembershipPlan::query()->where('is_active', true)->orderBy('sort_order')->first();
        if (! $plan) {
            return $this->respond('There is no active membership offer right now.');
        }

        $benefits = collect($plan->benefits ?? [])->implode('; ');

        return $this->respond($plan->name.' costs PHP '.number_format((float) $plan->price_amount, 2).' for '.(int) ($plan->validity_days ?? 365).' days. '.$plan->description
            .($benefits !== '' ? ' Benefits: '.$benefits.'.' : '').' Sign in and use the membership section to purchase or renew through PayMongo.', [$this->servicesAction()],
            ['Show me the packages', 'Services and prices', 'How do I book?']);
    }

    private function appointments(string $question, ?User $user): array
    {
        $this->remember('appointments');

        if (! $user) {
            return $this->signInFirst();
        }

        if (! Schema::hasTable('spa_bookings')) {
            return $this->respond('Appointment records are temporarily unavailable. Please try again later.');
        }

        if (preg_match('/(?:booking|appointment)\s*#?\s*(\d+)/u', $question, $matches) === 1) {
            $booking = SpaBooking::query()->where('user_id', $user->id)->find((int) $matches[1]);
            if (! $booking) {
                return $this->respond('I could not find that booking in your account.', [$this->action('Open profile', 'profile.edit')]);
            }

            return $this->respond($this->bookingSummary($booking), [$this->appointmentsAction('View booking')]);
        }

        $bookings = SpaBooking::query()->where('user_id', $user->id)
            ->whereDate('booking_date', '>=', now()->toDateString())
            ->whereNull('cancelled_at')
            ->orderBy('booking_date')->orderBy('time_slot')->limit(3)->get();

        if ($bookings->isEmpty()) {
            return $this->respond('I found no upcoming appointments in your account. You can review past bookings in your profile or make a new booking.',
                [$this->appointmentsAction('View appointments'), $this->action('Book now', 'booking.index')]);
        }

        $summaries = $bookings
            ->map(fn (SpaBooking $booking): string => '• '.$this->bookingSummary($booking))
            ->implode("\n");

        return $this->respond("Here’s what I found:\n".$summaries."\nWould you like help changing one?",
            [$this->appointmentsAction('Manage bookings')]);
    }

    private function changeRules(string $question, ?User $user, bool $cancel): array
    {
        $this->remember('appointments');
        $operation = $cancel ? 'cancel' : 'reschedule';
        $general = 'You can '.$operation.' a booking before its appointment time if the session has not started or finished. ';

        if (! $user) {
            return $this->respond($general.'Sign in to check your booking and use the action in your profile.', $this->guestActions());
        }

        if (! Schema::hasTable('spa_bookings')) {
            return $this->respond('Appointment records are temporarily unavailable. Please try again later.');
        }

        $booking = null;
        if (preg_match('/(?:booking|appointment)\s*#?\s*(\d+)/u', $question, $matches) === 1) {
            $booking = SpaBooking::query()->where('user_id', $user->id)->find((int) $matches[1]);
            if (! $booking) {
                return $this->respond('I could not find that booking in your account.', [$this->action('Open profile', 'profile.edit')]);
            }
        } else {
            $booking = SpaBooking::query()->where('user_id', $user->id)
                ->whereDate('booking_date', '>=', now()->toDateString())
                ->whereNull('cancelled_at')
                ->orderBy('booking_date')->orderBy('time_slot')->first();
        }

        if (! $booking) {
            return $this->respond($general.'I found no upcoming booking in your account.', [$this->appointmentsAction('View appointments')]);
        }

        $allowed = $cancel ? $this->cancellations->canCancel($booking) : $this->reschedules->canReschedule($booking);
        $reply = $allowed
            ? 'Booking #'.$booking->id.' is currently eligible to '.$operation.'. Use the action in your profile to confirm it.'
            : 'Booking #'.$booking->id.' cannot currently be '.$operation.'d. It may have started, finished, been cancelled, or reached its appointment time.';

        if ($cancel && $allowed) {
            $reply .= ' If you paid, any refund depends on the payment method and will be shown during cancellation.';
        }

        return $this->respond($reply, [$this->appointmentsAction('Manage bookings')]);
    }

    private function roleHelp(?User $user): array
    {
        if (! $user) {
            return $this->signInFirst();
        }

        if ($user->isAdmin()) {
            return $this->respond('As an administrator, you can use the dashboard, appointments, reporting, services, client records, and user management.',
                [$this->action('Dashboard', 'dashboard'), $this->action('Appointments', 'appointments.index')]);
        }

        if ($user->isReceptionist()) {
            return $this->respond('As a receptionist, you can manage appointments, services, sessions, and client records from your panel.',
                [$this->action('Receptionist panel', 'receptionist.dashboard'), $this->action('Appointments', 'appointments.index')]);
        }

        return $this->respond('Your customer account lets you book sessions and review or manage your own appointments and profile.',
            [$this->action('My profile', 'profile.edit'), $this->action('Book now', 'booking.index')]);
    }

    private function staffHelp(string $question, User $user): ?array
    {
        if ($this->has($question, ['today', "today's", 'todays'])
            && $this->has($question, ['appointment', 'booking'])) {
            $count = Schema::hasTable('spa_bookings')
                ? SpaBooking::query()->whereDate('booking_date', now()->toDateString())->whereNull('cancelled_at')->count()
                : null;

            return $this->respond(
                $count === null
                    ? 'Appointment records are temporarily unavailable. Open the appointments page to try again.'
                    : 'There are '.$count.' appointments scheduled today. Open the appointments page to review clients and statuses.',
                [$this->action('View appointments', 'appointments.index')],
            );
        }

        if ($this->has($question, ['client record', 'client list'])) {
            return $this->respond('Open client records to review registered clients and their appointment history.',
                [$this->action('Client records', 'client-records.index')]);
        }

        if ($this->has($question, ['manage service', 'edit service', 'update service'])) {
            return $this->respond('Open services to review and update the treatments offered by the spa.',
                [$this->action('Manage services', 'services.index')]);
        }

        if ($this->has($question, ['ongoing session', 'current session'])) {
            return $this->respond('Open ongoing sessions to review sessions that are in progress.',
                [$this->action('Ongoing sessions', 'ongoing-sessions.index')]);
        }

        if ($user->isAdmin() && $this->has($question, ['report', 'analytics'])) {
            return $this->respond('Open reporting to review the spa’s activity and results.',
                [$this->action('Reporting', 'reporting.index')]);
        }

        if ($this->has($question, [
            'my appointment', 'my booking', 'upcoming appointment', 'upcoming booking',
            'appointment status', 'booking status', 'manage appointment',
            'cancel appointment', 'cancel booking', 'reschedule appointment', 'reschedule booking',
        ]) || preg_match('/(?:booking|appointment)\s*#?\s*\d+/u', $question) === 1) {
            return $this->respond('Staff can review and manage bookings on the appointments page.',
                [$this->action('View appointments', 'appointments.index')]);
        }

        return null;
    }

    private function bookingSummary(SpaBooking $booking): string
    {
        $status = $booking->cancelled_at !== null ? 'cancelled'
            : ($booking->completed_at !== null ? 'completed' : (string) ($booking->session_status ?: 'confirmed'));
        $payment = (string) ($booking->payment_status ?? '');
        $paymentText = $payment !== '' ? ', payment '.str_replace('_', ' ', $payment) : '';

        return 'Booking #'.$booking->id.': '.$booking->service_name.' with '.$booking->therapist_name.' on '
            .$booking->booking_date?->format('M j, Y').' at '.$booking->time_slot.' — '.str_replace('_', ' ', $status).$paymentText.'.';
    }

    private function signInFirst(): array
    {
        return $this->respond('Please sign in to view information specific to your account or appointments.', $this->guestActions());
    }

    private function followUp(string $question, ?User $user): ?array
    {
        $topic = $this->conversationContext['topic'] ?? null;
        $subject = $this->conversationContext['subject'] ?? null;
        $compact = trim($question, " \t\n\r\0\x0B?!.");
        $refersToPrevious = $this->has($question, [' it', 'it ', 'that one', 'this one', 'does that', 'is that'])
            || in_array($compact, ['how much', 'how long', 'what is included', 'what does it include', 'tell me more'], true);

        if (in_array($topic, ['business', 'social'], true) && $compact === 'tell me more') {
            return $this->businessInformation($topic === 'social' ? 'social media accounts' : 'about the spa');
        }

        if ($subject && $refersToPrevious && $topic === 'services') {
            return $this->services(mb_strtolower($subject));
        }

        if ($subject && $refersToPrevious && $topic === 'packages') {
            return $this->packages(mb_strtolower($subject));
        }

        if ($topic === 'appointments' && in_array($compact, ['what time', 'when is it', 'which therapist', 'tell me more'], true)) {
            return $this->appointments('my appointments', $user);
        }

        if ($subject && in_array($compact, ['yes', 'yes please', 'sure', 'okay', 'ok'], true)
            && in_array($topic, ['services', 'packages'], true)) {
            return $this->respond(
                'Great choice. You can open the booking page with '.$subject.' selected, then choose your therapist, date, and available time.',
                [$this->action('Book '.$subject, 'booking.index', ['service' => $subject])],
                ['Business hours', 'Can I pay a downpayment?', 'Show my appointments'],
            );
        }

        return null;
    }

    private function isGreeting(string $question): bool
    {
        return preg_match('/^(hello|hi|hey|good morning|good afternoon|good evening|kumusta|help|menu)[!. ]*$/u', $question) === 1;
    }

    private function greeting(?User $user): string
    {
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
        $nameParts = $user ? (preg_split('/\s+/u', trim($user->name)) ?: []) : [];
        $firstName = trim((string) ($nameParts[0] ?? ''));

        return $greeting.($firstName !== '' ? ', '.$firstName.'!' : '!');
    }

    /** @param array<string, mixed> $context */
    private function sanitizeContext(array $context): array
    {
        $allowedTopics = ['services', 'packages', 'membership', 'appointments', 'hours', 'location', 'business', 'social'];
        $topic = is_string($context['topic'] ?? null) && in_array($context['topic'], $allowedTopics, true)
            ? $context['topic']
            : null;
        $subject = is_string($context['subject'] ?? null)
            ? mb_substr(trim($context['subject']), 0, 120)
            : null;

        return array_filter(['topic' => $topic, 'subject' => $subject]);
    }

    private function remember(string $topic, ?string $subject = null): void
    {
        $this->conversationContext = array_filter([
            'topic' => $topic,
            'subject' => $subject ? mb_substr(trim($subject), 0, 120) : null,
        ]);
    }

    /** @return list<string> */
    private function suggestionsForTopic(): array
    {
        return match ($this->conversationContext['topic'] ?? null) {
            'services' => ['Show me the packages', 'How do I book?', 'Business hours'],
            'packages' => ['Tell me about membership', 'Show individual services', 'How do I book?'],
            'appointments' => ['Can I reschedule?', 'Can I cancel?', 'Book another appointment'],
            'hours' => ['Where are you located?', 'Services and prices', 'How do I book?'],
            'location' => ['Business hours', 'Services and prices', 'How do I book?'],
            'business', 'social' => ['Who owns the spa?', 'Social media accounts', 'Business hours'],
            default => [],
        };
    }

    private function has(string $question, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($question, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function respond(string $reply, array $actions = [], ?array $suggestions = null): array
    {
        return [
            'reply' => $reply,
            'actions' => $actions,
            'suggestions' => array_values($suggestions ?? $this->suggestionsForTopic()),
            'context' => $this->conversationContext,
        ];
    }

    private function action(string $label, string $route, array $parameters = []): array
    {
        return ['label' => $label, 'url' => route($route, $parameters)];
    }

    private function servicesAction(): array
    {
        return ['label' => 'View services', 'url' => route('landing').'#services'];
    }

    private function appointmentsAction(string $label): array
    {
        return ['label' => $label, 'url' => route('profile.edit', ['appointments' => 1])];
    }

    private function guestActions(): array
    {
        return [$this->action('Sign in or register', 'login')];
    }
}
