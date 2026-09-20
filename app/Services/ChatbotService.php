<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class ChatbotService
{
    public function __construct(
        private readonly SpaServiceCatalog $catalog,
        private readonly SiteSettingsService $settings,
        private readonly BookingCancellationService $cancellations,
        private readonly BookingRescheduleService $reschedules,
    ) {}

    /**
     * @return array{reply: string, actions: list<array{label: string, url: string}>}
     */
    public function answer(string $message, ?User $user): array
    {
        $question = mb_strtolower(trim($message));
        $staff = $user !== null && ($user->isAdmin() || $user->isReceptionist());

        if (in_array($question, ['hello', 'hi', 'hey', 'help', 'menu'], true)) {
            if ($staff) {
                return $this->roleHelp($user);
            }

            return $this->respond(
                'Hi! I can help with services, prices, hours, location, booking, and account questions.'
                .($user ? ' You can also ask about your appointments or account.' : ' Sign in to ask about your own appointments.'),
                $user ? [$this->action('Book an appointment', 'booking.index')] : $this->guestActions(),
            );
        }

        if ($staff && ($staffReply = $this->staffHelp($question, $user)) !== null) {
            return $staffReply;
        }

        if ($this->has($question, ['resched', 'change my date', 'change my time', 'move my appointment'])) {
            return $this->changeRules($question, $user, false);
        }

        if ($this->has($question, ['cancel', 'refund'])) {
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
                $user ? [$this->action('View my bookings', 'profile.edit')] : $this->guestActions(),
            );
        }

        if ($this->has($question, ['my account', 'my profile', 'account information', 'my information', 'my role', 'my email'])) {
            if (! $user) {
                return $this->signInFirst();
            }

            $role = $user->isAdmin() ? 'administrator' : ($user->isReceptionist() ? 'receptionist' : 'customer');

            return $this->respond('Your account is '.$user->name.' ('.$user->email.'), role: '.$role.'. '
                .'Open your profile to review or update your details.', [$this->action('Open profile', 'profile.edit')]);
        }

        if ($this->has($question, ['hour', 'open', 'closing', 'when are you'])) {
            $details = $this->settings->footer();

            return $this->respond('The website currently lists these hours: '.$details['hours_weekday'].'; '
                .$details['hours_weekend'].'; '.$details['hours_holidays'].'.');
        }

        if ($this->has($question, ['location', 'address', 'where are you', 'directions', 'contact', 'phone', 'email'])) {
            $details = $this->settings->footer();

            return $this->respond('The website lists our address as '.$details['contact_address']
                .'. You can contact the spa at '.$details['contact_phone'].' or '.$details['contact_email'].'.');
        }

        if ($this->has($question, ['service', 'massage', 'treatment', 'price', 'cost', 'how much', 'rate'])) {
            return $this->services($question);
        }

        if ($this->has($question, ['register', 'sign up', 'create an account', 'new account'])) {
            return $this->respond(
                $user ? 'You are already signed in. Your profile is available in your account.'
                    : 'Choose Sign Up on the login page. Enter your name, contact number, email, birthday, and password. After registering, you can book online.',
                $user ? [$this->action('Open profile', 'profile.edit')] : [['label' => 'Create an account', 'url' => route('login', ['register' => 1])]],
            );
        }

        if ($this->has($question, ['login', 'log in', 'sign in', 'password', 'forgot'])) {
            return $this->respond(
                $user ? 'You are signed in. You can manage your account from your profile.'
                    : 'Sign in with your username, name, or email and password. If you forgot your password, use the reset link on the login page.',
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
            'I can help with services and prices, hours, location, booking, policies, and account questions. '
            .($user ? 'You can also ask “What are my appointments?”' : 'Sign in for help with your own appointments.'),
            $user ? [$this->action('Book an appointment', 'booking.index')] : $this->guestActions(),
        );
    }

    private function services(string $question): array
    {
        $services = $this->catalog->all();
        $specific = collect($services)->first(fn (array $service): bool => str_contains($question, mb_strtolower((string) $service['name'])));

        if ($specific) {
            return $this->respond($specific['name'].' costs '.$specific['price'].' and lasts '.$specific['duration'].'. '
                .trim((string) ($specific['desc'] ?? '')),
                [$this->servicesAction()]);
        }

        $list = collect($services)->map(fn (array $service): string => '• '.$service['name'].' — '.$service['price'])->implode("\n");

        return $this->respond("Current services and prices:\n".$list."\nSelect a service on the website for details.",
            [$this->servicesAction()]);
    }

    private function appointments(string $question, ?User $user): array
    {
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

            return $this->respond($this->bookingSummary($booking), [$this->action('View booking', 'profile.edit')]);
        }

        $bookings = SpaBooking::query()->where('user_id', $user->id)
            ->whereDate('booking_date', '>=', now()->toDateString())
            ->whereNull('cancelled_at')
            ->orderBy('booking_date')->orderBy('time_slot')->limit(3)->get();

        if ($bookings->isEmpty()) {
            return $this->respond('I found no upcoming appointments in your account. You can review past bookings in your profile or make a new booking.',
                [$this->action('Open profile', 'profile.edit'), $this->action('Book now', 'booking.index')]);
        }

        return $this->respond('Your next appointments: '.$bookings->map(fn (SpaBooking $booking): string => $this->bookingSummary($booking))->implode(' '),
            [$this->action('Manage bookings', 'profile.edit')]);
    }

    private function changeRules(string $question, ?User $user, bool $cancel): array
    {
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
            return $this->respond($general.'I found no upcoming booking in your account.', [$this->action('Open profile', 'profile.edit')]);
        }

        $allowed = $cancel ? $this->cancellations->canCancel($booking) : $this->reschedules->canReschedule($booking);
        $reply = $allowed
            ? 'Booking #'.$booking->id.' is currently eligible to '.$operation.'. Use the action in your profile to confirm it.'
            : 'Booking #'.$booking->id.' cannot currently be '.$operation.'d. It may have started, finished, been cancelled, or reached its appointment time.';

        if ($cancel && $allowed) {
            $reply .= ' If you paid, any refund depends on the payment method and will be shown during cancellation.';
        }

        return $this->respond($reply, [$this->action('Manage bookings', 'profile.edit')]);
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

    private function has(string $question, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($question, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function respond(string $reply, array $actions = []): array
    {
        return ['reply' => $reply, 'actions' => $actions];
    }

    private function action(string $label, string $route): array
    {
        return ['label' => $label, 'url' => route($route)];
    }

    private function servicesAction(): array
    {
        return ['label' => 'View services', 'url' => route('landing').'#services'];
    }

    private function guestActions(): array
    {
        return [$this->action('Sign in or register', 'login')];
    }
}
