<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Customer;
use App\Models\Receptionist;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\Therapist;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BookingSlotService;
use App\Services\NotificationFeedService;
use App\Services\ReportingPdfChartService;
use App\Services\SiteSettingsService;
use App\Services\SpaServiceCatalog;
use App\Services\SpaSessionService;
use App\Services\TherapistAvailabilityService;
use App\Services\TherapistCatalog;
use App\Services\WalkInClientService;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request, SpaSessionService $sessions): View
    {
        $receptionists = Receptionist::query()
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
        $notifications = app(NotificationFeedService::class)->recentBookingNotifications(6);
        $live = $sessions->dashboardSnapshot();

        return view('dashboard', [
            'receptionists' => $receptionists,
            'totalUsers' => $receptionists->count(),
            'notifications' => $notifications,
            'ongoingSessions' => $live['ongoing_sessions'],
            'currentSessions' => collect($live['current_sessions']),
            'todayAppointments' => collect($live['today_appointments']),
            'appointmentsToday' => $live['appointments_today'],
            'upcomingAppointments' => $live['upcoming_appointments'],
            'todaySales' => $live['today_sales'],
            'todayTransactions' => $live['today_transactions'],
            'activeTherapists' => $live['active_therapists'],
            'therapistCount' => $live['therapist_count'],
            'serverNowIso' => $live['server_now_iso'],
            'appTimezone' => $live['timezone'],
        ]);
    }

    public function users(): View
    {
        $this->ensureReceptionistsTableExists();
        $this->removeWalkInCustomerRecords();

        $usersByEmail = User::query()
            ->whereNotNull('email')
            ->get()
            ->keyBy(fn (User $user): string => strtolower(trim((string) $user->email)));

        $receptionists = Receptionist::query()
            ->active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Receptionist $receptionist) use ($usersByEmail): Receptionist {
                $emailKey = strtolower(trim((string) $receptionist->email));
                $linkedUser = $usersByEmail->get($emailKey);
                $receptionist->profile_photo_url = $this->profilePhotoUrlFor($linkedUser)
                    ?? public_storage_url($receptionist->profile_picture);

                return $receptionist;
            });

        $customers = Customer::query()
            ->active()
            ->registered()
            ->orderByDesc('created_at')
            ->paginate(7, ['*'], 'customer_page')
            ->withQueryString();

        $customers->setCollection(
            $customers->getCollection()
                ->reject(function (Customer $customer) use ($usersByEmail): bool {
                    $linkedUser = $usersByEmail->get(strtolower(trim((string) $customer->email)));

                    return $customer->isWalkIn() || ($linkedUser instanceof User && $linkedUser->isWalkIn());
                })
                ->values()
                ->map(function (Customer $customer) use ($usersByEmail) {
                    $source = trim($customer->full_name ?: $customer->email);
                    $initials = collect(preg_split('/\s+/', $source) ?: [])
                        ->filter()
                        ->take(2)
                        ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
                        ->implode('');

                    $customer->initials = $initials ?: 'CU';
                    $linkedUser = $usersByEmail->get(strtolower(trim((string) $customer->email)));
                    $customer->profile_photo_url = $this->profilePhotoUrlFor($linkedUser);

                    return $customer;
                })
        );

        $activeTab = in_array(request('tab'), ['receptionists', 'customers'], true)
            ? request('tab')
            : 'receptionists';

        return view('users', [
            'receptionists' => $receptionists,
            'customers' => $customers,
            'receptionistCount' => $receptionists->count(),
            'customerTotal' => $customers->total(),
            'activeTab' => $activeTab,
        ]);
    }

    public function appointmentsExport(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? now()->toDateString();
        $bookings = SpaBooking::query()
            ->with('user:id,name,email')
            ->whereDate('booking_date', $date)
            ->orderBy('time_slot')
            ->orderBy('id')
            ->get();
        $filename = 'appointments-'.$date.'.csv';

        ActivityLogger::log(
            'appointment.export',
            'Exported appointment records',
            ['filename' => $filename, 'date' => $date, 'rows' => $bookings->count()],
            request: $request,
        );

        return response()->streamDownload(function () use ($bookings): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Booking ID', 'Client', 'Email', 'Service', 'Therapist', 'Date', 'Time',
                'Session Status', 'Service Price', 'Total Paid', 'Balance', 'Payment Status', 'Source',
            ]);
            foreach ($bookings as $booking) {
                fputcsv($out, [
                    $booking->id,
                    $booking->client_name ?: $booking->user?->name,
                    $booking->user?->email,
                    $booking->service_name,
                    $booking->therapist_name,
                    optional($booking->booking_date)->format('Y-m-d'),
                    $booking->time_slot,
                    $booking->session_status,
                    number_format((float) ($booking->amount ?? 0), 2, '.', ''),
                    number_format($booking->totalPaidAmount(), 2, '.', ''),
                    number_format($booking->remainingBalance(), 2, '.', ''),
                    $booking->isFullyPaid() ? 'Fully Paid' : PaymentMethodCatalog::statusLabelFor($booking->payment_status),
                    $booking->booking_source,
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'number' => ['nullable', 'regex:/^09\d{9}$/'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'password' => ['required', 'string', 'min:8'],
            'return_to' => ['nullable', 'string', 'in:users.index,client-records.index,client-records.show'],
        ], [
            'number.regex' => 'Phone number must be 11 digits starting with 09.',
        ]);

        $returnTo = $validated['return_to'] ?? 'users.index';
        unset($validated['return_to']);

        $validated['customer_id'] = Customer::nextCustomerId();

        $customer = Customer::create($validated);

        ActivityLogger::log(
            'customer.created',
            'Added customer '.$customer->full_name,
            ['email' => $customer->email, 'customer_id' => $customer->customer_id],
            subject: $customer,
            request: $request,
        );

        return $this->redirectAfterCustomerWrite($returnTo, $customer, 'Customer added successfully.');
    }

    public function updateCustomer(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'number' => ['nullable', 'regex:/^09\d{9}$/'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email,'.$customer->id],
            'password' => ['nullable', 'string', 'min:8'],
            'return_to' => ['nullable', 'string', 'in:users.index,client-records.index,client-records.show'],
        ], [
            'number.regex' => 'Phone number must be 11 digits starting with 09.',
        ]);

        $returnTo = $validated['return_to'] ?? 'users.index';
        unset($validated['return_to']);

        $customer->full_name = $validated['full_name'];
        $customer->birthday = $validated['birthday'] ?? null;
        $customer->number = $validated['number'] ?? null;
        $customer->email = $validated['email'];
        if (! empty($validated['password'])) {
            $customer->password = $validated['password'];
        }
        $customer->save();

        ActivityLogger::log(
            'customer.updated',
            'Updated customer '.$customer->full_name,
            ['email' => $customer->email, 'customer_id' => $customer->customer_id],
            subject: $customer,
            request: $request,
        );

        return $this->redirectAfterCustomerWrite($returnTo, $customer, 'Customer updated successfully.');
    }

    public function destroyCustomer(Request $request, Customer $customer): RedirectResponse
    {
        if ($customer->isArchived()) {
            return back()->with('status', 'This customer is already archived.');
        }

        $validated = $request->validate([
            'return_to' => ['nullable', 'string', 'in:users.index,client-records.index'],
        ]);
        $returnTo = $validated['return_to'] ?? 'users.index';

        $name = $customer->full_name;
        $email = $customer->email;
        $customerId = $customer->customer_id;

        $customer->archive();
        $this->archiveLinkedUserByEmail($email);

        ActivityLogger::log(
            'customer.archived',
            'Archived customer '.$name,
            ['email' => $email, 'customer_id' => $customerId],
            request: $request,
        );

        return $this->redirectAfterCustomerWrite($returnTo, null, 'Customer archived successfully. Their records are kept but hidden from active lists.');
    }

    private function ensureCatalogSeeded(): void
    {
        app(TherapistCatalog::class)->ensureSeeded();
        app(SpaServiceCatalog::class)->ensureSeeded();
        app(SiteSettingsService::class)->ensureSeeded();

        $slots = app(BookingSlotService::class);
        if ($slots->tablesReady() && TimeSlot::query()->count() === 0) {
            $slots->seedDefaults();
        }
    }

    private function redirectAfterCustomerWrite(string $returnTo, ?Customer $customer, string $message): RedirectResponse
    {
        return match ($returnTo) {
            'client-records.index' => redirect()->route('client-records.index')->with('status', $message),
            'client-records.show' => $customer !== null
                ? redirect()->route('client-records.show', $customer)->with('status', $message)
                : redirect()->route('client-records.index')->with('status', $message),
            default => redirect()->route('users.index', ['tab' => 'customers'])->with('status', $message),
        };
    }

    public function receptionistDashboard(Request $request, SpaSessionService $sessions): View
    {
        $notifications = app(NotificationFeedService::class)->recentBookingNotifications(6);
        $live = $sessions->dashboardSnapshot();

        return view('receptionist-dashboard', [
            'notifications' => $notifications,
            'currentSessions' => collect($live['current_sessions']),
            'todayAppointments' => collect($live['today_appointments']),
            'ongoingSessions' => $live['ongoing_sessions'],
            'appointmentsToday' => $live['appointments_today'],
            'upcomingAppointments' => $live['upcoming_appointments'],
            'todaySales' => $live['today_sales'],
            'todayTransactions' => $live['today_transactions'],
            'activeTherapists' => $live['active_therapists'],
            'therapistCount' => $live['therapist_count'],
            'serverNowIso' => $live['server_now_iso'],
            'appTimezone' => $live['timezone'],
        ]);
    }

    public function ongoingSessions(SpaSessionService $sessions): View
    {
        $sessionCards = $sessions->ongoingSessions()
            ->map(fn (SpaBooking $booking): array => $sessions->toOngoingCard($booking));

        return view('ongoing-sessions', [
            'sessions' => $this->paginateCollection($sessionCards, 3),
            'pollUrl' => route('ongoing-sessions.poll'),
            'completedUrl' => route('completed-sessions.index'),
            'serverNowIso' => now()->toIso8601String(),
            'appTimezone' => config('app.timezone'),
        ]);
    }

    public function ongoingSessionsPoll(SpaSessionService $sessions): JsonResponse
    {
        $autoCompleted = $sessions->autoCompleteAllExpired();

        $activeSessions = $sessions->ongoingQuery()
            ->get()
            ->filter(fn (SpaBooking $booking): bool => $sessions->isOngoing($booking))
            ->map(fn (SpaBooking $booking): array => $sessions->toOngoingMonitorPayload($booking))
            ->values();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'sessions' => $activeSessions,
            'auto_completed' => $autoCompleted->map(fn (SpaBooking $booking): array => [
                'id' => $booking->id,
                'client' => (string) ($booking->user?->name ?: ($booking->client_name ?: 'Client #'.$booking->user_id)),
            ])->values(),
        ]);
    }

    public function staffFeedPoll(SpaSessionService $sessions): JsonResponse
    {
        $live = $sessions->dashboardSnapshot();
        $notificationFeed = app(NotificationFeedService::class);

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'notifications' => $notificationFeed->recentBookingNotifications(6),
            'notification_unread_count' => $notificationFeed->unreadCount(),
            'today_appointments' => $live['today_appointments'],
            'appointments_today' => $live['appointments_today'],
            'upcoming_appointments' => $live['upcoming_appointments'],
            'ongoing_sessions' => $live['ongoing_sessions'],
        ]);
    }

    public function completeSession(Request $request, SpaBooking $spaBooking, SpaSessionService $sessions): RedirectResponse
    {
        if ($spaBooking->isCancelled()) {
            return redirect()
                ->route('ongoing-sessions.index')
                ->with('error', 'This session was cancelled and cannot be completed.');
        }

        if ($spaBooking->completed_at !== null) {
            return redirect()
                ->route('completed-sessions.index')
                ->with('status', 'This session is already completed.');
        }

        if (! $spaBooking->isFullyPaid()) {
            return redirect()
                ->route('ongoing-sessions.index')
                ->with('error', 'Collect the remaining balance before completing this session.');
        }

        if ($sessions->hasExpired($spaBooking)) {
            $sessions->autoCompleteIfExpired($spaBooking);

            return redirect()
                ->route('completed-sessions.index')
                ->with('status', 'Session ended and was moved to completed transactions.');
        }

        if (! $sessions->canComplete($spaBooking)) {
            return redirect()
                ->route('ongoing-sessions.index')
                ->with('error', 'This session is not active right now.');
        }

        $sessions->complete($spaBooking);

        ActivityLogger::log(
            'session.completed',
            sprintf(
                'Completed session for %s (%s)',
                (string) ($spaBooking->user?->name ?: ($spaBooking->client_name ?: 'client')),
                (string) $spaBooking->service_name,
            ),
            [
                'booking_id' => $spaBooking->id,
                'therapist' => $spaBooking->therapist_name,
            ],
            subject: $spaBooking,
            request: $request,
        );

        return redirect()
            ->route('completed-sessions.index')
            ->with('status', 'Session marked as completed.');
    }

    public function startSession(Request $request, SpaBooking $spaBooking, SpaSessionService $sessions): RedirectResponse
    {
        $sessions->releaseAvailabilityBlocks();
        $spaBooking->refresh();

        if (! $spaBooking->isFullyPaid()) {
            return redirect()
                ->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
                ->with('error', 'Collect the remaining balance before starting this session.');
        }

        if (! $sessions->canStart($spaBooking)) {
            if ($spaBooking->session_started_at !== null && $spaBooking->completed_at === null) {
                return redirect()
                    ->route('ongoing-sessions.index')
                    ->with('status', 'This session is already in progress.');
            }

            return redirect()
                ->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
                ->with('error', $sessions->startEligibilityMessage($spaBooking));
        }

        $sessions->start($spaBooking);

        ActivityLogger::log(
            'session.started',
            sprintf(
                'Started session for %s (%s)',
                (string) ($spaBooking->user?->name ?: ($spaBooking->client_name ?: 'client')),
                (string) $spaBooking->service_name,
            ),
            [
                'booking_id' => $spaBooking->id,
                'therapist' => $spaBooking->therapist_name,
            ],
            subject: $spaBooking,
            request: $request,
        );

        return redirect()
            ->route('ongoing-sessions.index')
            ->with('status', 'Session started. You can monitor it below.');
    }

    public function collectBalance(Request $request, SpaBooking $spaBooking): RedirectResponse
    {
        $validated = $request->validateWithBag('balance', [
            'balance_payment_method' => ['required', Rule::in([PaymentMethodCatalog::METHOD_CASH_COUNTER])],
            'balance_payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $collected = DB::transaction(function () use ($spaBooking, $validated, $request): SpaBooking {
                $booking = SpaBooking::query()->lockForUpdate()->findOrFail($spaBooking->id);

                if ($booking->isCancelled() || $booking->completed_at !== null || $booking->session_started_at !== null) {
                    throw ValidationException::withMessages([
                        'balance_payment_method' => 'The balance can only be collected before the session starts.',
                    ]);
                }

                if ($booking->payment_status !== PaymentMethodCatalog::STATUS_PAID) {
                    throw ValidationException::withMessages([
                        'balance_payment_method' => 'The initial payment must be confirmed before collecting the balance.',
                    ]);
                }

                $remaining = $booking->remainingBalance();
                if ($remaining < 0.01) {
                    throw ValidationException::withMessages([
                        'balance_payment_method' => 'This appointment is already fully paid.',
                    ]);
                }

                $booking->forceFill([
                    'balance_amount' => $remaining,
                    'balance_payment_method' => $validated['balance_payment_method'],
                    'balance_payment_reference' => trim((string) ($validated['balance_payment_reference'] ?? '')) ?: null,
                    'balance_collected_by' => $request->user()?->id,
                    'balance_paid_at' => now(),
                ])->save();

                return $booking->fresh();
            });
        } catch (ValidationException $exception) {
            return redirect()
                ->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
                ->withErrors($exception->errors(), 'balance')
                ->withInput();
        }

        ActivityLogger::log(
            'payment.balance_collected',
            sprintf('Collected ₱%s remaining balance for %s', number_format((float) $collected->balance_amount, 2), (string) $collected->service_name),
            [
                'booking_id' => $collected->id,
                'amount' => (float) $collected->balance_amount,
                'method' => $collected->balance_payment_method,
                'reference' => $collected->balance_payment_reference,
            ],
            subject: $collected,
            request: $request,
        );

        return redirect()
            ->route('appointments.index', ['date' => $collected->booking_date?->format('Y-m-d')])
            ->with('status', 'Remaining balance collected. The appointment is now fully paid and ready to start.');
    }

    public function completedSessions(Request $request, SpaSessionService $sessions): View
    {
        $search = trim($request->string('search')->toString());
        $scope = $request->string('scope')->lower()->value() === 'all' ? 'all' : 'daily';

        $transactionPool = $sessions->completedQuery()
            ->get()
            ->map(fn (SpaBooking $booking): array => $sessions->toCompletedRow($booking));

        if ($transactionPool->isEmpty()) {
            return view('completed-sessions', [
                'transactions' => collect(),
                'totalSales' => 0,
                'totalServiceLogged' => 0,
                'totalHours' => 0,
                'scope' => $scope,
                'scopeLabel' => $scope === 'all' ? 'All Records' : now()->format('M d, Y'),
                'dateFrom' => '',
                'dateTo' => '',
                'minDate' => now()->format('Y-m-d'),
                'maxDate' => now()->format('Y-m-d'),
                'search' => $search,
            ]);
        }

        $latestDate = $transactionPool->max('parsed_date');
        $oldestDate = $transactionPool->min('parsed_date');
        $defaultRangeLabel = sprintf(
            '%s - %s',
            Carbon::parse($oldestDate)->format('M d, Y'),
            Carbon::parse($latestDate)->format('M d, Y')
        );
        $dateFromInput = $request->string('date_from')->toString();
        $dateToInput = $request->string('date_to')->toString();
        $dateFrom = Carbon::hasFormat($dateFromInput, 'Y-m-d')
            ? Carbon::createFromFormat('Y-m-d', $dateFromInput)->startOfDay()
            : null;
        $dateTo = Carbon::hasFormat($dateToInput, 'Y-m-d')
            ? Carbon::createFromFormat('Y-m-d', $dateToInput)->endOfDay()
            : null;

        if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        if ($scope === 'all') {
            $transactions = $transactionPool
                ->filter(function (array $transaction) use ($dateFrom, $dateTo): bool {
                    if ($dateFrom && $transaction['parsed_date']->lt($dateFrom)) {
                        return false;
                    }

                    if ($dateTo && $transaction['parsed_date']->gt($dateTo)) {
                        return false;
                    }

                    return true;
                })
                ->sortByDesc('parsed_date')
                ->map(function (array $transaction): array {
                    unset($transaction['parsed_date']);

                    return $transaction;
                })
                ->values();
            $scopeLabel = ($dateFrom || $dateTo)
                ? sprintf(
                    '%s - %s',
                    $dateFrom ? $dateFrom->format('M d, Y') : Carbon::parse($oldestDate)->format('M d, Y'),
                    $dateTo ? $dateTo->format('M d, Y') : Carbon::parse($latestDate)->format('M d, Y'),
                )
                : $defaultRangeLabel;
        } else {
            $today = now()->startOfDay();
            $transactions = $transactionPool
                ->filter(fn (array $transaction) => $transaction['parsed_date']->isSameDay($today))
                ->map(function (array $transaction): array {
                    unset($transaction['parsed_date']);

                    return $transaction;
                })
                ->values();
            $scopeLabel = now()->format('M d, Y');
        }

        $totalSales = $transactions->sum(fn (array $row): float => (float) str_replace(',', '', (string) $row['amount']));
        $totalServiceLogged = $transactions->count();
        $totalHours = round($transactions->sum('duration_minutes') / 60, 1);

        return view('completed-sessions', [
            'transactions' => $transactions,
            'totalSales' => fmod($totalSales, 1.0) === 0.0 ? number_format($totalSales, 0) : number_format($totalSales, 2),
            'totalServiceLogged' => $totalServiceLogged,
            'totalHours' => $totalHours,
            'scope' => $scope,
            'scopeLabel' => $scopeLabel,
            'dateFrom' => $dateFrom ? $dateFrom->format('Y-m-d') : '',
            'dateTo' => $dateTo ? $dateTo->format('Y-m-d') : '',
            'minDate' => Carbon::parse($oldestDate)->format('Y-m-d'),
            'maxDate' => Carbon::parse($latestDate)->format('Y-m-d'),
            'search' => $search,
        ]);
    }

    public function appointments(Request $request, SpaSessionService $sessions): View
    {
        $this->ensureCatalogSeeded();
        $sessions->releaseAvailabilityBlocks();

        $notifications = app(NotificationFeedService::class)->recentBookingNotifications(6);

        $appointments = collect([
            [
                'client' => 'Otis Wright',
                'service' => 'Deep Tissue',
                'therapist' => 'Maria Santos',
                'date' => 'Jun 10, 2026',
                'time' => '08:00 AM - 09:30 AM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Zayd Wilson',
                'service' => 'Swedish Massage',
                'therapist' => 'Angela Fernandez',
                'date' => 'Jun 10, 2026',
                'time' => '10:00 AM - 11:00 AM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Penny Taylor',
                'service' => 'Aromatherapy',
                'therapist' => 'Liza Reyes',
                'date' => 'Jun 10, 2026',
                'time' => '11:30 AM - 01:00 PM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Francisca Davis',
                'service' => 'Sports Massage',
                'therapist' => 'Carlo Ramos',
                'date' => 'Jun 10, 2026',
                'time' => '09:00 AM - 10:10 AM',
                'status' => 'Cancelled',
            ],
            [
                'client' => 'Norman Thomas',
                'service' => 'Hot Stone',
                'therapist' => 'Maria Santos',
                'date' => 'Jun 10, 2026',
                'time' => '10:30 AM - 12:00 PM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Elliot Price',
                'service' => 'Foot Reflexology',
                'therapist' => 'Angela Fernandez',
                'date' => 'Jun 10, 2026',
                'time' => '01:20 PM - 02:00 PM',
                'status' => 'Cancelled',
            ],
            [
                'client' => 'Rosie Jenkins',
                'service' => 'Prenatal Massage',
                'therapist' => 'Liza Reyes',
                'date' => 'Jun 10, 2026',
                'time' => '09:30 AM - 11:20 AM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Camila Torres',
                'service' => 'Aromatherapy',
                'therapist' => 'Angela Fernandez',
                'date' => 'Jun 11, 2026',
                'time' => '09:00 AM - 10:00 AM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Leo Fernandez',
                'service' => 'Thai Massage',
                'therapist' => 'Carlo Ramos',
                'date' => 'Jun 11, 2026',
                'time' => '01:30 PM - 02:45 PM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Maya Liu',
                'service' => 'Hot Stone',
                'therapist' => 'Maria Santos',
                'date' => 'Jun 12, 2026',
                'time' => '10:15 AM - 11:45 AM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Noel Ramirez',
                'service' => 'Deep Tissue',
                'therapist' => 'Liza Reyes',
                'date' => 'Jun 12, 2026',
                'time' => '03:00 PM - 04:30 PM',
                'status' => 'Cancelled',
            ],
            [
                'client' => 'Iris Gomez',
                'service' => 'Swedish Massage',
                'therapist' => 'Angela Fernandez',
                'date' => 'Jun 13, 2026',
                'time' => '08:30 AM - 09:30 AM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Theo Hall',
                'service' => 'Sports Massage',
                'therapist' => 'Carlo Ramos',
                'date' => 'Jun 13, 2026',
                'time' => '11:00 AM - 12:00 PM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Nina Clarke',
                'service' => 'Foot Reflexology',
                'therapist' => 'Maria Santos',
                'date' => 'Jun 14, 2026',
                'time' => '02:00 PM - 02:45 PM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Owen Patel',
                'service' => 'Prenatal Massage',
                'therapist' => 'Liza Reyes',
                'date' => 'Jun 14, 2026',
                'time' => '04:15 PM - 05:15 PM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Sara Miles',
                'service' => 'Hot Stone',
                'therapist' => 'Angela Fernandez',
                'date' => 'Jun 15, 2026',
                'time' => '09:45 AM - 11:15 AM',
                'status' => 'Confirmed',
            ],
            [
                'client' => 'Victor Khan',
                'service' => 'Deep Tissue',
                'therapist' => 'Carlo Ramos',
                'date' => 'Jun 15, 2026',
                'time' => '01:00 PM - 02:30 PM',
                'status' => 'Cancelled',
            ],
        ]);

        $bookings = SpaBooking::query()
            ->with('user')
            ->orderBy('booking_date')
            ->orderBy('time_slot')
            ->get();

        $usesDatabase = $bookings->isNotEmpty();

        if ($usesDatabase) {
            $now = now();
            $appointments = $bookings
                ->map(fn (SpaBooking $booking): array => $sessions->toAppointmentRow($booking, $now))
                ->values();
        }

        // Calendar "today" is based on the earliest appointment date in this mock dataset.
        $appointmentsWithParsedDate = $appointments->map(function (array $appointment): array {
            if (! isset($appointment['parsed_date'])) {
                $appointment['parsed_date'] = Carbon::createFromFormat('M d, Y', (string) ($appointment['date'] ?? ''));
            }

            return $appointment;
        });

        $calendarToday = $usesDatabase
            ? now()->startOfDay()
            : $appointmentsWithParsedDate
                ->sortBy(fn (array $a) => $a['parsed_date']->getTimestamp())
                ->first()['parsed_date'];

        // Mock-only clock for demo appointments.
        $demoNow = $calendarToday->copy()->setTime(12, 0, 0);

        $selectedDateIso = (string) $request->string('date')->toString();
        $selectedDate = null;
        if ($selectedDateIso !== '' && Carbon::hasFormat($selectedDateIso, 'Y-m-d')) {
            $selectedDate = Carbon::createFromFormat('Y-m-d', $selectedDateIso)->startOfDay();
        } else {
            $selectedDate = now()->startOfDay();
            $selectedDateIso = $selectedDate->format('Y-m-d');
        }

        $calendarMonth = (int) $selectedDate->month;
        $calendarYear = (int) $selectedDate->year;

        // When a day is chosen in the calendar, show appointments for that date only.
        $appointments = $appointmentsWithParsedDate
            ->filter(fn (array $a) => $a['parsed_date']->isSameDay($selectedDate))
            ->values();

        // If the selected date has no rows in the mock dataset (e.g. earlier days
        // in the calendar), generate sample "past day" content so the UI isn't empty.
        if (! $usesDatabase && $appointments->isEmpty() && $selectedDate->lt($calendarToday->copy()->startOfDay())) {
            $dateLabel = $selectedDate->format('M d, Y');
            $timeSlots = [
                '09:00 AM - 10:00 AM',
                '10:30 AM - 11:30 AM',
                '01:00 PM - 02:00 PM',
                '03:00 PM - 04:00 PM',
            ];

            $seed = $appointmentsWithParsedDate->values()->take(4)->values();

            $appointments = $seed->map(function (array $a, int $i) use ($dateLabel, $timeSlots): array {
                $a['date'] = $dateLabel;
                $a['time'] = $timeSlots[$i] ?? ($a['time'] ?? '09:00 AM - 10:00 AM');
                $a['status'] = $i % 3 === 0 ? 'Cancelled' : 'Completed';
                $a['parsed_date'] = Carbon::createFromFormat('M d, Y', $dateLabel);

                return $a;
            })->values();
        }

        $dateSort = $request->string('date_sort')->lower()->value() === 'desc' ? 'desc' : 'asc';
        $statusSort = $request->string('status_sort')->lower()->value() === 'desc' ? 'desc' : 'asc';
        $statusFilter = $request->string('status_filter')->lower()->value();
        $statusFilter = in_array($statusFilter, ['confirmed', 'pending', 'rescheduled', 'completed', 'cancelled', 'no-show', 'in-session'], true) ? $statusFilter : '';
        $search = trim($request->string('search')->toString());
        $nextDateSort = $dateSort === 'asc' ? 'desc' : 'asc';
        $nextStatusSort = $statusSort === 'asc' ? 'desc' : 'asc';

        $appointments = $appointments
            ->when(! $usesDatabase, function (Collection $rows) use ($selectedDate, $calendarToday, $demoNow) {
                return $rows->map(function (array $appointment) use ($selectedDate, $calendarToday, $demoNow): array {
                    if (! isset($appointment['starts_at'])) {
                        [$startTime] = explode('-', $appointment['time']);
                        $appointment['starts_at'] = Carbon::createFromFormat(
                            'M d, Y h:i A',
                            sprintf('%s %s', $appointment['date'], trim($startTime))
                        );
                    }

                    $appointment['late_cutoff_at'] = $appointment['starts_at']->copy()->addMinutes(10);

                    if (($appointment['status'] ?? '') !== 'Cancelled') {
                        if ($selectedDate->lt($calendarToday->copy()->startOfDay())) {
                            $appointment['status'] = 'Completed';
                        } elseif (
                            ($appointment['status'] ?? '') === 'Confirmed'
                            && $selectedDate->isSameDay($calendarToday)
                            && $appointment['late_cutoff_at']->lte($demoNow)
                        ) {
                            $appointment['status'] = 'Cancelled';
                        } elseif ($selectedDate->isSameDay($calendarToday) && $appointment['starts_at']->lt($demoNow)) {
                            $appointment['status'] = 'Completed';
                        }
                    }

                    return $appointment;
                });
            })
            ->when($usesDatabase, function (Collection $rows) {
                return $rows->map(function (array $appointment): array {
                    if (! isset($appointment['starts_at'])) {
                        [$startTime] = explode('-', (string) ($appointment['time'] ?? ''));
                        $appointment['starts_at'] = Carbon::createFromFormat(
                            'M d, Y h:i A',
                            sprintf('%s %s', $appointment['date'], trim($startTime))
                        );
                    }

                    return $appointment;
                });
            });

        $statusRankAsc = [
            'In Session' => 0,
            'Pending' => 1,
            'Rescheduled' => 2,
            'Confirmed' => 3,
            'Completed' => 4,
            'Cancelled' => 5,
            'No Show' => 6,
        ];

        $statusRankDesc = [
            'Cancelled' => 0,
            'No Show' => 1,
            'Completed' => 2,
            'Confirmed' => 3,
            'Rescheduled' => 4,
            'Pending' => 5,
            'In Session' => 6,
        ];

        $appointments = $appointments
            ->sortBy(function (array $appointment) use ($dateSort, $statusSort, $statusRankAsc, $statusRankDesc) {
                $rankMap = $statusSort === 'desc' ? $statusRankDesc : $statusRankAsc;
                $rank = $rankMap[$appointment['status']] ?? 99;
                $timestamp = $appointment['starts_at']->getTimestamp();
                $dateKey = $dateSort === 'desc' ? -$timestamp : $timestamp;

                return [$rank, $dateKey];
            })
            ->values();

        $statusCounts = $appointments->countBy(function (array $appointment): string {
            $status = strtolower(trim((string) ($appointment['status'] ?? '')));

            return $status === 'in session' ? 'in-session' : $status;
        });

        $activeClientsThisMonth = SpaBooking::query()
            ->whereYear('booking_date', $selectedDate->year)
            ->whereMonth('booking_date', $selectedDate->month)
            ->distinct('user_id')
            ->count('user_id');

        $stats = [
            'active_clients' => $activeClientsThisMonth,
            'total' => $appointments->count(),
            'pending' => (int) ($statusCounts->get('pending', 0)),
            'rescheduled' => (int) ($statusCounts->get('rescheduled', 0)),
            'confirmed' => (int) ($statusCounts->get('confirmed', 0)),
            'in_session' => (int) ($statusCounts->get('in-session', 0)),
            'completed' => (int) ($statusCounts->get('completed', 0)),
            'cancelled' => (int) ($statusCounts->get('cancelled', 0)),
            'no_show' => (int) ($statusCounts->get('no show', 0)),
        ];

        if ($statusFilter !== '') {
            $statusFilterLabel = match ($statusFilter) {
                'in-session' => 'In Session',
                'rescheduled' => 'Rescheduled',
                'no-show' => 'No Show',
                default => ucfirst($statusFilter),
            };
            $appointments = $appointments
                ->filter(fn (array $appointment) => strcasecmp((string) ($appointment['status'] ?? ''), $statusFilterLabel) === 0)
                ->values();
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $appointments = $appointments
                ->filter(function (array $appointment) use ($needle): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        (string) ($appointment['client'] ?? ''),
                        (string) ($appointment['service'] ?? ''),
                        (string) ($appointment['therapist'] ?? ''),
                        (string) ($appointment['date'] ?? ''),
                        (string) ($appointment['time'] ?? ''),
                        (string) ($appointment['status'] ?? ''),
                        (string) ($appointment['notes'] ?? ''),
                        (string) ($appointment['booking_id'] ?? ''),
                    ], fn (string $part): bool => $part !== '')));

                    return str_contains($haystack, $needle);
                })
                ->values();
        }

        $appointments = $appointments
            ->map(function (array $appointment): array {
                unset($appointment['starts_at'], $appointment['late_cutoff_at'], $appointment['parsed_date']);

                return $appointment;
            });

        $appointmentsPaginator = $this->paginateCollection($appointments, 6);

        $activeServices = SpaService::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'price_amount']);
        $serviceOptions = $activeServices->pluck('name');
        $servicePriceMap = $activeServices->mapWithKeys(function (SpaService $service): array {
            return [(string) $service->name => (float) ($service->price_amount ?? 0)];
        })->all();
        $therapistOptions = Therapist::query()
            ->orderBy('name')
            ->pluck('name');
        $hasPaymentFields = Schema::hasColumn('spa_bookings', 'payment_method');

        $user = $request->user();
        $isStaff = $user instanceof User && ($user->isAdmin() || $user->isReceptionist());
        $slots = app(BookingSlotService::class);

        if ($request->boolean('ajax')) {
            return view('appointments.partials.list', [
                'appointments' => $appointmentsPaginator,
                'stats' => $stats,
                'isPastDay' => $selectedDate->lt(now()->startOfDay()),
                'isStaff' => $isStaff,
            ]);
        }

        return view('appointments.index', [
            'appointments' => $appointmentsPaginator,
            'stats' => $stats,
            'notifications' => $notifications,
            'dateSort' => $dateSort,
            'statusSort' => $statusSort,
            'nextDateSort' => $nextDateSort,
            'nextStatusSort' => $nextStatusSort,
            'calendarMonth' => $calendarMonth,
            'calendarYear' => $calendarYear,
            'selectedDateIso' => $selectedDateIso,
            'isPastDay' => $selectedDate->lt(now()->startOfDay()),
            'search' => $search,
            'serverNowIso' => now()->toIso8601String(),
            'appTimezone' => config('app.timezone'),
            'serviceOptions' => $serviceOptions,
            'therapistOptions' => $therapistOptions,
            'isStaff' => $isStaff,
            'staffAvailabilityUrl' => route('appointments.availability'),
            'appointmentStoreUrl' => route('appointments.store'),
            'clientSearchUrl' => route('appointments.clients.search'),
            'minimumBirthday' => now()->subYears(15)->toDateString(),
            'slotMap' => $slots->slotMapByService(),
            'allSlots' => $slots->allSlotLabels(),
            'today' => now()->toDateString(),
            'openAddAppointment' => session('open_add_appointment', false),
            'servicePriceMap' => $servicePriceMap,
            'paymentMethods' => $hasPaymentFields ? PaymentMethodCatalog::staffMethods() : [],
            'hasPaymentFields' => $hasPaymentFields,
        ]);
    }

    public function services(Request $request, BookingSlotService $slots, SpaServiceCatalog $catalog): View
    {
        $catalog->ensureSeeded();

        if ($slots->tablesReady() && TimeSlot::query()->count() === 0) {
            $slots->seedDefaults();
        }

        $slotMap = $slots->slotMapByService();
        $bookingCounts = SpaBooking::query()
            ->selectRaw('service_name, COUNT(*) as total')
            ->groupBy('service_name')
            ->pluck('total', 'service_name');

        $search = trim($request->string('search')->toString());
        $availability = $request->string('availability')->lower()->value();
        $availability = in_array($availability, ['all', 'available', 'unavailable'], true) ? $availability : 'available';

        $servicesQuery = SpaService::query()->orderBy('name');

        if ($availability === 'available') {
            $servicesQuery->where('is_active', true);
        } elseif ($availability === 'unavailable') {
            $servicesQuery->where('is_active', false);
        }

        $services = $servicesQuery
            ->get()
            ->map(function (SpaService $service) use ($slotMap, $bookingCounts): array {
                $name = (string) $service->name;
                $image = (string) ($service->image ?? '');

                return array_merge($service->toCatalogArray(), [
                    'id' => $service->id,
                    'description' => (string) $service->description,
                    'duration_minutes' => (int) $service->duration_minutes,
                    'is_active' => (bool) $service->is_active,
                    'image_url' => file_exists(public_path('images/landing/'.$image))
                        ? asset('images/landing/'.$image)
                        : asset('images/login/background.jpg'),
                    'time_slots_count' => count($slotMap[$name] ?? []),
                    'total_bookings' => (int) ($bookingCounts[$name] ?? 0),
                ]);
            });

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $services = $services
                ->filter(function (array $service) use ($needle): bool {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        (string) ($service['name'] ?? ''),
                        (string) ($service['desc'] ?? ''),
                        (string) ($service['best_for'] ?? ''),
                        (string) ($service['duration'] ?? ''),
                        (string) ($service['price'] ?? ''),
                    ], fn (string $part): bool => $part !== '')));

                    return str_contains($haystack, $needle);
                })
                ->values();
        }

        return view('services.index', [
            'services' => $services,
            'search' => $search,
            'availability' => $availability,
            'totalServices' => $services->count(),
            'availableCount' => SpaService::query()->where('is_active', true)->count(),
            'unavailableCount' => SpaService::query()->where('is_active', false)->count(),
        ]);
    }

    public function storeService(Request $request, BookingSlotService $slots, SpaServiceCatalog $catalog): RedirectResponse
    {
        $catalog->ensureSeeded();
        $validated = $this->validateServiceRequest($request);

        $service = SpaService::query()->create([
            'name' => $validated['name'],
            'price_amount' => $validated['price_amount'],
            'duration_minutes' => $validated['duration_minutes'],
            'best_for' => $validated['best_for'],
            'description' => $validated['description'],
            'image' => $validated['image'] ?? null,
            'prenatal_only' => $request->boolean('prenatal_only'),
            'is_active' => true,
        ]);

        if ($slots->tablesReady() && TimeSlot::query()->count() === 0) {
            $slots->seedDefaults();
        }

        $slots->attachDefaultSlotsForService($service->name);

        ActivityLogger::log(
            'service.created',
            'Added service '.$service->name,
            ['service_name' => $service->name],
            subject: $service,
            request: $request,
        );

        return redirect()
            ->route('services.index')
            ->with('status', 'Service added successfully.');
    }

    public function updateService(Request $request, SpaService $spaService, BookingSlotService $slots): RedirectResponse
    {
        $validated = $this->validateServiceRequest($request, $spaService);
        $oldName = (string) $spaService->name;

        $spaService->update([
            'name' => $validated['name'],
            'price_amount' => $validated['price_amount'],
            'duration_minutes' => $validated['duration_minutes'],
            'best_for' => $validated['best_for'],
            'description' => $validated['description'],
            'image' => $validated['image'] ?? null,
            'prenatal_only' => $request->boolean('prenatal_only'),
        ]);

        if ($oldName !== $spaService->name) {
            DB::table('service_time_slots')
                ->where('service_name', $oldName)
                ->update(['service_name' => $spaService->name]);

            SpaBooking::query()
                ->where('service_name', $oldName)
                ->update(['service_name' => $spaService->name]);
        }

        if ($slots->tablesReady() && count($slots->slotMapByService()[$spaService->name] ?? []) === 0) {
            $slots->attachDefaultSlotsForService($spaService->name);
        }

        ActivityLogger::log(
            'service.updated',
            'Updated service '.$spaService->name,
            ['service_name' => $spaService->name, 'previous_name' => $oldName],
            subject: $spaService,
            request: $request,
        );

        return redirect()
            ->route('services.index')
            ->with('status', 'Service updated successfully.');
    }

    public function serviceTimeSlotsData(Request $request, SpaService $spaService, BookingSlotService $slots): JsonResponse
    {
        if ($slots->tablesReady() && TimeSlot::query()->count() === 0) {
            $slots->seedDefaults();
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'mode' => ['nullable', 'in:weekly,date'],
        ]);

        $dateYmd = isset($validated['date'])
            ? Carbon::parse($validated['date'])->toDateString()
            : now()->toDateString();
        $mode = $validated['mode'] ?? 'weekly';

        return response()->json(
            $slots->adminSlotEditorPayload($spaService->name, $dateYmd, $mode),
        );
    }

    public function updateServiceTimeSlots(Request $request, SpaService $spaService, BookingSlotService $slots): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:weekly,date'],
            'date' => ['required_if:mode,date', 'nullable', 'date'],
            'enabled_slot_ids' => ['required', 'array'],
            'enabled_slot_ids.*' => ['integer', 'exists:time_slots,id'],
            'store_closed' => ['sometimes', 'boolean'],
        ]);

        $mode = (string) $validated['mode'];
        $enabledSlotIds = array_map('intval', $validated['enabled_slot_ids']);

        if ($mode === 'weekly') {
            $slots->syncWeeklySlotsForService($spaService->name, $enabledSlotIds);

            ActivityLogger::log(
                'service.timeslots_updated',
                'Updated weekly time slots for '.$spaService->name,
                ['service_name' => $spaService->name, 'mode' => 'weekly', 'enabled_count' => count($enabledSlotIds)],
                subject: $spaService,
                request: $request,
            );

            return response()->json([
                'ok' => true,
                'message' => 'Weekly time slots saved.',
                'payload' => $slots->adminSlotEditorPayload($spaService->name, now()->toDateString(), 'weekly'),
            ]);
        }

        $dateYmd = Carbon::parse((string) $validated['date'])->toDateString();
        $storeClosed = $request->boolean('store_closed');

        if ($storeClosed) {
            $slots->setStoreClosed($dateYmd, true, 'Closed from services panel');
        } else {
            $slots->setStoreClosed($dateYmd, false);
            $slots->syncDateSlotOverrides($spaService->name, $dateYmd, $enabledSlotIds);
        }

        ActivityLogger::log(
            'service.timeslots_updated',
            $storeClosed
                ? 'Marked store closed on '.$dateYmd.' while editing '.$spaService->name
                : 'Updated date-specific time slots for '.$spaService->name.' on '.$dateYmd,
            [
                'service_name' => $spaService->name,
                'mode' => 'date',
                'date' => $dateYmd,
                'store_closed' => $storeClosed,
                'enabled_count' => count($enabledSlotIds),
            ],
            subject: $spaService,
            request: $request,
        );

        return response()->json([
            'ok' => true,
            'message' => $storeClosed ? 'Store marked closed for this date.' : 'Date-specific time slots saved.',
            'payload' => $slots->adminSlotEditorPayload($spaService->name, $dateYmd, 'date'),
        ]);
    }

    public function storeServiceTimeSlot(Request $request, SpaService $spaService, BookingSlotService $slots): JsonResponse
    {
        if ($slots->tablesReady() && TimeSlot::query()->count() === 0) {
            $slots->seedDefaults();
        }

        $validated = $request->validate([
            'time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'mode' => ['nullable', 'in:weekly,date'],
            'date' => ['required_if:mode,date', 'nullable', 'date'],
        ]);

        [$hours, $minutes] = array_map('intval', explode(':', $validated['time']));
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            return response()->json(['message' => 'Enter a valid time.'], 422);
        }

        $mode = (string) ($validated['mode'] ?? 'weekly');
        $dateYmd = isset($validated['date'])
            ? Carbon::parse($validated['date'])->toDateString()
            : now()->toDateString();

        $label = Carbon::createFromTime($hours, $minutes)->format('g:i A');
        $slot = $slots->addTimeSlotForService($spaService->name, $label, $mode, $dateYmd);

        if ($slot === null) {
            return response()->json(['message' => 'Could not add that time slot.'], 422);
        }

        ActivityLogger::log(
            'service.timeslot_added',
            'Added time slot '.$slot->label.' for '.$spaService->name,
            [
                'service_name' => $spaService->name,
                'time_slot_id' => $slot->id,
                'time_slot_label' => $slot->label,
                'mode' => $mode,
                'date' => $mode === 'date' ? $dateYmd : null,
            ],
            subject: $spaService,
            request: $request,
        );

        return response()->json([
            'ok' => true,
            'message' => $mode === 'date'
                ? 'Time slot saved for '.$dateYmd.'.'
                : 'Time slot saved to weekly schedule.',
            'payload' => $slots->adminSlotEditorPayload($spaService->name, $dateYmd, $mode),
        ]);
    }

    public function toggleServiceAvailability(Request $request, SpaService $spaService): RedirectResponse
    {
        $spaService->is_active = ! $spaService->is_active;
        $spaService->save();

        $isAvailable = (bool) $spaService->is_active;
        $action = $isAvailable ? 'service.available' : 'service.unavailable';
        $message = $isAvailable
            ? $spaService->name.' is now available for booking.'
            : $spaService->name.' is now unavailable and hidden from booking.';

        ActivityLogger::log(
            $action,
            $message,
            ['service_name' => $spaService->name, 'is_active' => $isAvailable],
            subject: $spaService,
            request: $request,
        );

        return redirect()
            ->route('services.index', [
                'availability' => $isAvailable ? 'available' : 'unavailable',
                'search' => trim($request->string('search')->toString()),
            ])
            ->with('status', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateServiceRequest(Request $request, ?SpaService $spaService = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('spa_services', 'name')->ignore($spaService?->id),
            ],
            'price_amount' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'best_for' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'string', 'max:255'],
            'prenatal_only' => ['sometimes', 'boolean'],
        ]);
    }

    public function therapistTracking(TherapistCatalog $catalog): View
    {
        $this->ensureCatalogSeeded();

        $therapists = collect($catalog->forTracking());

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'therapist_id') && Schema::hasColumn('transactions', 'duration')) {
            $minutesByTherapistId = DB::table('transactions')
                ->selectRaw('therapist_id, SUM(duration) as total_minutes')
                ->groupBy('therapist_id')
                ->pluck('total_minutes', 'therapist_id')
                ->map(fn ($minutes) => (int) $minutes);

            $therapists = $therapists->map(function (array $therapist) use ($minutesByTherapistId): array {
                $therapistId = (string) ($therapist['id'] ?? '');
                $numericId = preg_replace('/\D+/', '', $therapistId);
                $dbId = $numericId !== '' ? (int) $numericId : null;

                $minutes = 0;
                if ($dbId !== null) {
                    $minutes = (int) ($minutesByTherapistId->get($dbId) ?? 0);
                }

                $therapist['total_hours'] = (int) round($minutes / 60);

                return $therapist;
            });

            $fullServiceHoursWindow = 240;
            $therapists = $therapists->map(function (array $therapist) use ($fullServiceHoursWindow): array {
                $hours = (int) ($therapist['total_hours'] ?? 0);
                $therapist['service_hours_pct'] = (int) round((max($hours, 0) / $fullServiceHoursWindow) * 100);
                if ($therapist['service_hours_pct'] > 100) {
                    $therapist['service_hours_pct'] = 100;
                }

                return $therapist;
            });
        }

        $stats = [
            'total_therapists' => $therapists->count(),
            'available_now' => $therapists->where('status', 'available')->count(),
            'currently_busy' => $therapists->where('status', 'busy')->count(),
            'avg_service_hours' => (int) round($therapists->avg('total_hours') ?? 0),
        ];

        return view('therapist-tracking.index', [
            'therapists' => $therapists,
            'stats' => $stats,
            'scheduleSettings' => app(SiteSettingsService::class)->therapistSchedule(),
            'specializationOptions' => collect(app(SpaServiceCatalog::class)->all())
                ->pluck('name')
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all(),
        ]);
    }

    public function updateTherapistScheduleSettings(Request $request, SiteSettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'automation_enabled' => ['sometimes', 'boolean'],
            'default_working_days' => ['nullable', 'array'],
            'default_working_days.*' => ['integer', 'min:0', 'max:6'],
            'apply_working_days_to_all' => ['sometimes', 'boolean'],
        ]);

        $settings->updateTherapistSchedule([
            'automation_enabled' => $request->boolean('automation_enabled'),
            'default_working_days' => $validated['default_working_days'] ?? [],
            'apply_working_days_to_all' => $request->boolean('apply_working_days_to_all'),
        ]);

        $therapists = Therapist::query()->get()->map(
            fn (Therapist $therapist): array => $therapist->fresh()->toTrackingArray()
        );

        ActivityLogger::log(
            'therapist.schedule.settings',
            'Updated therapist working-day automation settings',
            [],
            request: $request,
        );

        return response()->json([
            'ok' => true,
            'schedule' => $settings->therapistSchedule(),
            'therapists' => $therapists,
        ]);
    }

    public function updateTherapistAvailability(
        Request $request,
        string $therapistCode,
        TherapistAvailabilityService $availability,
    ): JsonResponse {
        $therapist = Therapist::query()->where('therapist_code', $therapistCode)->firstOrFail();

        $validated = $request->validate([
            'availability_type' => ['required', Rule::in(['available', 'off-duty', 'day-off'])],
            'day_off_until' => ['nullable', 'date', 'after_or_equal:today', 'required_if:availability_type,day-off'],
            'work_on_off_day' => ['sometimes', 'boolean'],
            'use_clinic_working_days' => ['sometimes', 'boolean'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['integer', 'min:0', 'max:6'],
        ]);

        $type = (string) $validated['availability_type'];
        $therapist->work_on_off_day = $request->boolean('work_on_off_day');

        if ($type === 'available') {
            $therapist->status = 'available';
            $therapist->day_off_until = null;
        } elseif ($type === 'off-duty') {
            $therapist->status = 'off-duty';
            $therapist->day_off_until = null;
        } else {
            $therapist->status = 'available';
            $therapist->day_off_until = $validated['day_off_until'] ?? null;
        }

        if ($request->boolean('use_clinic_working_days')) {
            $therapist->working_days = null;
        } else {
            $days = $availability->workingDaysFromRequest($validated['working_days'] ?? []);
            $therapist->working_days = $days !== [] ? $days : null;
        }

        $therapist->save();

        ActivityLogger::log(
            'therapist.availability.updated',
            'Updated availability for '.$therapist->name,
            [
                'therapist_code' => $therapist->therapist_code,
                'availability_type' => $type,
            ],
            subject: $therapist,
            request: $request,
        );

        return response()->json([
            'ok' => true,
            'therapist' => $therapist->fresh()->toTrackingArray(),
        ]);
    }

    public function storeTherapist(Request $request): JsonResponse
    {
        $validated = $this->validateTherapistProfile($request);

        $photoUrl = null;
        if ($request->hasFile('photo_file')) {
            $path = $request->file('photo_file')->store('therapists', 'public');
            $photoUrl = public_storage_url($path, false);
        }

        $name = (string) $validated['name'];
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->values();
        $initials = strtoupper(substr((string) ($parts[0] ?? $name), 0, 1).substr((string) ($parts->last() ?? ''), 0, 1));

        $therapist = Therapist::query()->create([
            'therapist_code' => trim((string) ($validated['therapist_code'] ?? '')) !== ''
                ? (string) $validated['therapist_code']
                : $this->nextTherapistCode(),
            'name' => $name,
            'role' => $validated['role'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'contact_number' => $validated['contact_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'email' => $validated['email'] ?? null,
            'birthday' => $validated['birthday'] ?? null,
            'avatar_initials' => $initials !== '' ? $initials : 'TT',
            'photo_url' => $photoUrl,
            'landing_photo' => $photoUrl === null ? ($validated['landing_photo'] ?? null) : null,
            'specializations' => $this->parseCommaList($validated['specializations'] ?? ''),
            'certifications' => $this->parseCommaList($validated['certifications'] ?? ''),
            'sessions_label' => $validated['sessions_label'] ?? null,
            'accent_color' => $validated['accent_color'] ?? '#8fa89a',
            'status' => 'available',
            'total_hours' => 0,
            'service_hours_pct' => 0,
            'rating' => 0,
            'is_active' => $this->therapistIsActiveFromRequest($request, defaultActive: true),
            'sort_order' => (int) ($validated['sort_order'] ?? ((int) Therapist::query()->max('sort_order') + 1)),
        ]);

        ActivityLogger::log(
            'therapist.created',
            'Added therapist '.$therapist->name,
            ['therapist_code' => $therapist->therapist_code, 'name' => $therapist->name],
            subject: $therapist,
            request: $request,
        );

        return response()->json([
            'ok' => true,
            'therapist' => $therapist->fresh()->toTrackingArray(),
        ]);
    }

    private function nextTherapistCode(): string
    {
        $latest = Therapist::query()
            ->whereNotNull('therapist_code')
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return 'T001';
        }

        $raw = (string) ($latest->therapist_code ?? '');
        $digits = preg_replace('/\D+/', '', $raw);
        $next = ((int) ($digits !== '' ? $digits : '0')) + 1;

        return 'T'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    public function updateTherapist(Request $request, string $therapistCode): JsonResponse
    {
        $therapist = Therapist::query()->where('therapist_code', $therapistCode)->firstOrFail();

        $validated = $this->validateTherapistProfile($request, $therapist->id);

        if ($request->hasFile('photo_file')) {
            $path = $request->file('photo_file')->store('therapists', 'public');
            $therapist->photo_url = public_storage_url($path, false);
            $therapist->landing_photo = null;
        }

        $name = (string) $validated['name'];
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->values();
        $initials = strtoupper(substr((string) ($parts[0] ?? $name), 0, 1).substr((string) ($parts->last() ?? ''), 0, 1));

        $therapist->name = $name;
        $therapist->role = $validated['role'] ?? null;
        $therapist->bio = $validated['bio'] ?? null;
        $therapist->contact_number = $validated['contact_number'] ?? null;
        $therapist->address = $validated['address'] ?? null;
        $therapist->email = $validated['email'] ?? null;
        $therapist->birthday = $validated['birthday'] ?? null;
        $therapist->avatar_initials = $initials !== '' ? $initials : 'TT';
        $therapist->specializations = $this->parseCommaList($validated['specializations'] ?? '');
        $therapist->certifications = $this->parseCommaList($validated['certifications'] ?? '');
        $therapist->sessions_label = $validated['sessions_label'] ?? null;
        $therapist->accent_color = $validated['accent_color'] ?? $therapist->accent_color;
        $therapist->is_active = $this->therapistIsActiveFromRequest($request, defaultActive: (bool) $therapist->is_active);
        if (isset($validated['sort_order'])) {
            $therapist->sort_order = (int) $validated['sort_order'];
        }
        $therapist->save();

        ActivityLogger::log(
            'therapist.updated',
            'Updated therapist '.$therapist->name,
            ['therapist_code' => $therapist->therapist_code, 'name' => $therapist->name],
            subject: $therapist,
            request: $request,
        );

        return response()->json([
            'ok' => true,
            'therapist' => $therapist->fresh()->toTrackingArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTherapistProfile(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'therapist_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('therapists', 'therapist_code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'contact_number' => ['nullable', 'regex:/^09\d{9}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'specializations' => ['nullable', 'string'],
            'certifications' => ['nullable', 'string'],
            'sessions_label' => ['nullable', 'string', 'max:30'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'landing_photo' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            'photo_file' => ['nullable', 'image', 'max:3072'],
        ], [
            'contact_number.regex' => 'Phone number must be 11 digits starting with 09.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function parseCommaList(?string $raw): array
    {
        return collect(explode(',', (string) $raw))
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function therapistIsActiveFromRequest(Request $request, bool $defaultActive = true): bool
    {
        if (! $request->has('is_active')) {
            return $defaultActive;
        }

        return $request->boolean('is_active');
    }

    public function destroyTherapist(string $therapistCode): JsonResponse
    {
        $therapist = Therapist::query()->where('therapist_code', $therapistCode)->firstOrFail();

        $photoUrl = trim((string) ($therapist->photo_url ?? ''));
        if ($photoUrl !== '') {
            $photoPath = parse_url($photoUrl, PHP_URL_PATH);
            if (is_string($photoPath) && str_contains($photoPath, '/storage/')) {
                $relative = ltrim(substr($photoPath, strpos($photoPath, '/storage/') + 9), '/');
                if ($relative !== '') {
                    Storage::disk('public')->delete($relative);
                }
            }
        }

        $therapist->delete();

        return response()->json(['ok' => true]);
    }

    public function clientRecords(Request $request): View
    {
        if (! $this->clientRecordsTablesReady()) {
            return view('client-records.list', [
                'customers' => $this->paginateCollection(collect(), 7),
                'sort' => 'all',
                'activeView' => 'registered',
            ]);
        }

        $this->syncCustomersFromRegisteredUsers();

        $sort = (string) $request->query('sort', 'all');
        $allowedSorts = ['all', 'active', 'inactive', 'new_user'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'all';
        }

        $usersByEmail = User::query()
            ->when(Schema::hasColumn('users', 'role'), fn ($query) => $query->where('role', User::ROLE_USER))
            ->whereNotNull('email')
            ->get()
            ->keyBy(fn (User $user): string => strtolower(trim((string) $user->email)));

        $lastBookingByUserId = collect();
        if (Schema::hasTable('spa_bookings') && Schema::hasColumn('spa_bookings', 'user_id')) {
            $lastBookingByUserId = SpaBooking::query()
                ->whereNotNull('user_id')
                ->selectRaw('user_id, MAX(booking_date) as last_booking_date')
                ->groupBy('user_id')
                ->get()
                ->mapWithKeys(function ($row): array {
                    $userId = (int) ($row->user_id ?? 0);
                    if ($userId <= 0 || empty($row->last_booking_date)) {
                        return [];
                    }

                    return [$userId => Carbon::parse((string) $row->last_booking_date)->startOfDay()];
                });
        }

        $lastBookingByClientName = collect();
        if (Schema::hasTable('spa_bookings') && Schema::hasColumn('spa_bookings', 'client_name')) {
            $lastBookingByClientName = SpaBooking::query()
                ->whereNotNull('client_name')
                ->selectRaw('LOWER(TRIM(client_name)) as client_name_key, MAX(booking_date) as last_booking_date')
                ->groupBy(DB::raw('LOWER(TRIM(client_name))'))
                ->get()
                ->mapWithKeys(function ($row): array {
                    $nameKey = strtolower(trim((string) ($row->client_name_key ?? '')));
                    if ($nameKey === '' || empty($row->last_booking_date)) {
                        return [];
                    }

                    return [$nameKey => Carbon::parse((string) $row->last_booking_date)->startOfDay()];
                });
        }

        $customers = Customer::query()
            ->active()
            ->registered()
            ->orderBy('full_name')
            ->get()
            ->reject(function (Customer $customer) use ($usersByEmail): bool {
                $emailKey = strtolower(trim((string) $customer->email));
                $linkedUser = $usersByEmail->get($emailKey);

                return $customer->isWalkIn() || ($linkedUser instanceof User && $linkedUser->isWalkIn());
            })
            ->values()
            ->map(function (Customer $customer) use ($usersByEmail, $lastBookingByUserId, $lastBookingByClientName): Customer {
                $emailKey = strtolower(trim((string) $customer->email));
                $linkedUser = $usersByEmail->get($emailKey);
                $customer->profile_photo_url = $this->profilePhotoUrlFor($linkedUser);

                $lastBookingAt = null;
                if ($linkedUser !== null) {
                    $byUserId = $lastBookingByUserId->get((int) $linkedUser->id);
                    if ($byUserId instanceof Carbon) {
                        $lastBookingAt = $byUserId;
                    }
                }

                if ($lastBookingAt === null && $lastBookingByClientName->isNotEmpty()) {
                    $nameCandidates = collect([$customer->full_name, $linkedUser?->name])
                        ->filter()
                        ->map(fn (string $name): string => strtolower(trim($name)))
                        ->filter(fn (string $name): bool => $name !== '')
                        ->unique()
                        ->values();

                    foreach ($nameCandidates as $nameKey) {
                        $candidate = $lastBookingByClientName->get($nameKey);
                        if ($candidate instanceof Carbon && ($lastBookingAt === null || $candidate->gt($lastBookingAt))) {
                            $lastBookingAt = $candidate;
                        }
                    }
                }

                $activeCutoff = now()->subMonth()->startOfDay();
                $customer->is_active = $lastBookingAt !== null && $lastBookingAt->gte($activeCutoff);
                $inactivityStart = null;
                if ($lastBookingAt !== null) {
                    $inactivityStart = $lastBookingAt->copy()->addMonth()->startOfDay();
                }
                $customer->inactivity_duration = $customer->is_active
                    ? null
                    : $this->formatInactivityDuration($inactivityStart);
                $customer->is_new_user = $customer->created_at !== null
                    ? $customer->created_at->greaterThanOrEqualTo(now()->subDays(30))
                    : false;

                return $customer;
            });

        $customers = match ($sort) {
            'active' => $customers->where('is_active', true)->values(),
            'inactive' => $customers->where('is_active', false)->values(),
            'new_user' => $customers
                ->where('is_new_user', true)
                ->sortByDesc('created_at')
                ->values(),
            default => $customers,
        };

        $customers = $this->paginateCollection($customers, 7);

        return view('client-records.list', [
            'customers' => $customers,
            'sort' => $sort,
        ]);
    }

    private function syncCustomersFromRegisteredUsers(): void
    {
        if (! Schema::hasTable('customers') || ! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        $this->removeWalkInCustomerRecords();

        $existingEmails = Customer::query()
            ->select('email')
            ->pluck('email')
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->flip();

        User::query()
            ->active()
            ->when(Schema::hasColumn('users', 'role'), fn ($query) => $query->where('role', User::ROLE_USER))
            ->orderBy('id')
            ->get()
            ->each(function (User $user) use ($existingEmails): void {
                $normalizedEmail = strtolower(trim((string) $user->email));
                if ($normalizedEmail === '' || $user->isWalkIn() || $existingEmails->has($normalizedEmail)) {
                    return;
                }

                Customer::query()->create([
                    'customer_id' => Customer::nextCustomerId(),
                    'full_name' => trim((string) $user->name) !== '' ? $user->name : $user->email,
                    'birthday' => null,
                    'number' => $user->contact_number ?: null,
                    'email' => $user->email,
                    'password' => $user->password,
                ]);

                $existingEmails->put($normalizedEmail, true);
            });
    }

    private function formatInactivityDuration(?Carbon $fromDate): ?string
    {
        if ($fromDate === null) {
            return null;
        }

        $today = now()->startOfDay();
        $start = $fromDate->copy()->startOfDay();
        if ($start->gt($today)) {
            $start = $today->copy();
        }

        $diff = $start->diff($today);

        return sprintf(
            '%d year%s, %d month%s, %d day%s',
            $diff->y,
            $diff->y === 1 ? '' : 's',
            $diff->m,
            $diff->m === 1 ? '' : 's',
            $diff->d,
            $diff->d === 1 ? '' : 's'
        );
    }

    private function profilePhotoUrlFor(?User $user): ?string
    {
        $path = trim((string) ($user?->profile_photo_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return public_storage_url($path, false);
    }

    public function showClientRecord(Customer $customer): View
    {
        if ($customer->isWalkIn()) {
            abort(404);
        }

        $linkedUser = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($customer->email)])
            ->first();

        $accountCreated = $linkedUser?->created_at ?? $customer->created_at;

        $client = [
            'full_name' => $customer->full_name,
            'tagline' => 'Client',
            'dob' => optional($customer->birthday)->format('m/d/Y') ?? '—',
            'age' => $customer->birthday ? $customer->birthday->age.'y' : '—',
            'photo_url' => $this->profilePhotoUrlFor($linkedUser),
        ];

        $userProfile = [
            'name' => $linkedUser?->name ?? $customer->full_name,
            'username' => $linkedUser?->username ?? '—',
            'email' => $customer->email,
            'contact_number' => $linkedUser?->contact_number ?: ($customer->number ?: '—'),
            'account_created' => $accountCreated?->format('M d, Y') ?? '—',
            'sex' => $linkedUser?->sexLabel() ?? '—',
            'therapist_gender_preference' => $linkedUser?->therapistGenderPreferenceLabel() ?? '—',
            'pregnancy' => $linkedUser?->pregnancyLabel() ?? '—',
            'pressure_preference' => $linkedUser?->pressurePreferenceLabel() ?? '—',
        ];

        $transactions = $this->clientTransactionsFor($customer, $linkedUser);

        return view('client-records.show', compact(
            'customer',
            'client',
            'userProfile',
            'transactions',
        ));
    }

    /**
     * @return list<array{transaction_id: ?string, service: string, therapist: string, date: string, time: string, duration: string, amount: string, status: string, status_key: string, notes: ?string}>
     */
    private function clientTransactionsFor(Customer $customer, ?User $linkedUser): array
    {
        $clientNames = collect([$customer->full_name, $linkedUser?->name])
            ->filter()
            ->map(fn (string $name): string => strtolower(trim($name)))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->values();

        $transactions = DB::table('transactions')
            ->leftJoin('therapists', 'transactions.therapist_id', '=', 'therapists.id')
            ->where(function ($query) use ($linkedUser, $clientNames) {
                if ($linkedUser !== null) {
                    $query->where('user_id', $linkedUser->id);
                }

                foreach ($clientNames as $name) {
                    $query->orWhere(function ($inner) use ($name) {
                        $inner->whereNull('user_id')
                            ->whereRaw('LOWER(client_name) = ?', [$name]);
                    });
                }
            })
            ->select([
                'transactions.transaction_id',
                'transactions.service_name',
                'transactions.date',
                'transactions.time',
                'transactions.duration',
                'transactions.amount',
                'transactions.notes',
                DB::raw('COALESCE(therapists.name, "—") as therapist_name'),
            ])
            ->orderByDesc('date')
            ->orderByDesc('time')
            ->get()
            ->map(function ($row) {
                $duration = (int) ($row->duration ?? 0);
                $date = (string) $row->date;
                $time = Carbon::parse((string) $row->time)->format('g:i A');

                return [
                    'transaction_id' => $row->transaction_id !== null ? (string) $row->transaction_id : null,
                    'service' => (string) $row->service_name,
                    'therapist' => (string) ($row->therapist_name ?? '—'),
                    'date' => $date,
                    'time' => $time,
                    'duration' => $duration > 0 ? $duration.' min' : '—',
                    'amount' => '₱'.number_format((float) $row->amount, 2),
                    'status' => 'Completed',
                    'status_key' => 'completed',
                    'notes' => $row->notes !== null ? trim((string) $row->notes) : null,
                    'history_at' => Carbon::parse($date.' '.$time)->timestamp,
                ];
            });

        $bookingRows = collect();
        if ($linkedUser !== null) {
            $bookingRows = collect($this->bookingHistoryForUser($linkedUser));
        }

        return $transactions
            ->concat($bookingRows)
            ->sortByDesc('history_at')
            ->values()
            ->map(function (array $row): array {
                unset($row['history_at']);

                return $row;
            })
            ->all();
    }

    /**
     * @return list<array{transaction_id: string, service: string, therapist: string, date: string, time: string, duration: string, amount: string, status: string, status_key: string, notes: ?string, history_at: int}>
     */
    private function bookingHistoryForUser(User $user): array
    {
        if (! Schema::hasTable('spa_bookings') || ! Schema::hasColumn('spa_bookings', 'user_id')) {
            return [];
        }

        return SpaBooking::query()
            ->where('user_id', $user->id)
            ->orderByDesc('booking_date')
            ->orderByDesc('time_slot')
            ->get()
            ->map(function (SpaBooking $booking): array {
                $date = $booking->booking_date->format('Y-m-d');
                $time = Carbon::parse((string) $booking->time_slot)->format('g:i A');
                $status = $this->spaBookingStatusLabel($booking);

                return [
                    'transaction_id' => 'BKG-'.str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT),
                    'service' => (string) $booking->service_name,
                    'therapist' => $booking->therapist_name ?: '—',
                    'date' => $date,
                    'time' => $time,
                    'duration' => $booking->duration_minutes !== null ? ((int) $booking->duration_minutes).' min' : '—',
                    'amount' => $booking->amount !== null ? '₱'.number_format((float) $booking->amount, 2) : '—',
                    'status' => $status['label'],
                    'status_key' => $status['key'],
                    'notes' => $booking->notes !== null ? trim((string) $booking->notes) : null,
                    'history_at' => Carbon::parse($date.' '.$time)->timestamp,
                ];
            })
            ->all();
    }

    private function spaBookingStatusLabel(SpaBooking $booking): array
    {
        if ($booking->isCancelled()) {
            return ['label' => 'Cancelled', 'key' => 'cancelled'];
        }

        try {
            $start = Carbon::parse($booking->booking_date->format('Y-m-d').' '.(string) $booking->time_slot);
        } catch (\Throwable) {
            return ['label' => 'Upcoming', 'key' => 'upcoming'];
        }

        $duration = max((int) ($booking->duration_minutes ?? 60), 1);
        $end = $start->copy()->addMinutes($duration);
        $now = now();

        if ($now->gte($end)) {
            return ['label' => 'Completed', 'key' => 'completed'];
        }

        if ($now->gte($start) && $now->lt($end)) {
            return ['label' => 'Confirm', 'key' => 'confirm'];
        }

        return ['label' => 'Upcoming', 'key' => 'upcoming'];
    }

    public function reporting(Request $request): View
    {
        $period = $this->normalizeReportingPeriod($request->query('period', 'monthly'));
        $periodValue = $request->query('period_value');
        $payload = $this->reportingPayload($period, $periodValue);

        return view('reporting.index', array_merge($payload, [
            'reportingBootstrap' => $payload,
        ]));
    }

    public function reportingData(Request $request): JsonResponse
    {
        $period = $this->normalizeReportingPeriod($request->query('period', 'monthly'));
        $periodValue = $request->query('period_value');

        return response()->json($this->reportingPayload($period, $periodValue));
    }

    public function reportingExport(Request $request): StreamedResponse
    {
        $period = $this->normalizeReportingPeriod($request->query('period', 'monthly'));
        $periodValue = $request->query('period_value');
        $payload = $this->reportingPayload($period, $periodValue);
        $safePeriod = preg_replace('/[^a-z0-9_-]+/i', '', $period) ?: 'monthly';
        $filename = 'touchnrelief-report-'.$safePeriod.'-'.now()->format('Y-m-d-His').'.csv';

        ActivityLogger::log(
            'report.exported',
            'Exported reporting data ('.$period.')',
            ['period' => $period, 'filename' => $filename],
            request: $request,
        );

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['TOUCHnRELIEF — Reporting export']);
            fputcsv($out, ['Generated', now()->toDateTimeString()]);
            fputcsv($out, ['Period view', (string) ($payload['period'] ?? '')]);
            fputcsv($out, ['Selection', (string) ($payload['periodValueLabel'] ?? '')]);
            fputcsv($out, []);

            fputcsv($out, ['Summary']);
            fputcsv($out, ['Metric', 'Value']);
            fputcsv($out, [
                (string) ($payload['primaryLabel'] ?? ''),
                number_format((float) ($payload['primaryAmount'] ?? 0), 2, '.', ''),
            ]);
            fputcsv($out, [
                (string) ($payload['secondaryLabel'] ?? ''),
                (string) (int) ($payload['secondaryUserCount'] ?? 0),
            ]);
            fputcsv($out, [
                (string) ($payload['hoursLabel'] ?? 'Total Service Hours'),
                number_format((float) ($payload['hoursValue'] ?? 0), 1, '.', '').' hrs',
            ]);
            fputcsv($out, []);

            fputcsv($out, ['Sales trend', (string) ($payload['trendSubtitle'] ?? '')]);
            fputcsv($out, ['Period', 'Sales (PHP)']);
            $trendLabels = $payload['trendLabels'] ?? [];
            $trendData = $payload['trendData'] ?? [];
            foreach ($trendLabels as $i => $label) {
                fputcsv($out, [
                    (string) $label,
                    number_format((float) ($trendData[$i] ?? 0), 2, '.', ''),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Service sales', (string) ($payload['serviceLabel'] ?? '')]);
            fputcsv($out, ['Service', 'Amount (PHP)']);
            $serviceLabels = $payload['serviceLabels'] ?? [];
            $serviceTotals = $payload['serviceTotals'] ?? [];
            foreach ($serviceLabels as $i => $label) {
                fputcsv($out, [
                    (string) $label,
                    number_format((float) ($serviceTotals[$i] ?? 0), 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function reportingPdf(Request $request, ReportingPdfChartService $charts): Response
    {
        $period = $this->normalizeReportingPeriod($request->query('period', 'monthly'));
        $periodValue = $request->query('period_value');
        $payload = $this->reportingPayload($period, $periodValue);
        $payload['salesTrendChart'] = $charts->salesTrend($payload['trendLabels'] ?? [], $payload['trendData'] ?? []);
        $payload['serviceRevenueChart'] = $charts->serviceRevenue($payload['serviceLabels'] ?? [], $payload['serviceTotals'] ?? []);
        $safeSelection = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($payload['periodValue'] ?? 'current')) ?: 'current';
        $filename = 'touchnrelief-report-'.$period.'-'.$safeSelection.'.pdf';

        ActivityLogger::log(
            'report.pdf_generated',
            'Generated reporting PDF ('.$period.')',
            ['period' => $period, 'filename' => $filename],
            request: $request,
        );

        return Pdf::loadView('reporting.pdf', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    public function reportingBackup(): StreamedResponse|RedirectResponse
    {
        $tables = ['users', 'customers', 'receptionists', 'therapists', 'spa_services', 'time_slots', 'spa_bookings', 'transactions', 'registrations', 'site_settings'];

        try {
            $data = [];
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    $data[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
                }
            }
            $payload = json_encode([
                'application' => 'TOUCHnRELIEF',
                'generated_at' => now()->toIso8601String(),
                'format_version' => 1,
                'tables' => $data,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('reporting.index')->with('error', 'The backup could not be created. Please try again or contact the administrator.');
        }

        $downloadName = 'touchnrelief-data-backup-'.now()->format('Y-m-d-His').'.json';

        ActivityLogger::log(
            'report.backup',
            'Downloaded database backup',
            ['filename' => $downloadName],
            request: request(),
        );

        return response()->streamDownload(function () use ($payload): void {
            echo $payload;
        }, $downloadName, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    private function backupTableToCsv(string $table): string
    {
        $rows = DB::table($table)->get();
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");

        if ($rows->isEmpty()) {
            fputcsv($handle, ['_note']);
            fputcsv($handle, ['No rows in '.$table]);
            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return $content ?: '';
        }

        $first = (array) $rows->first();
        fputcsv($handle, array_keys($first));
        foreach ($rows as $row) {
            $values = [];
            foreach ((array) $row as $v) {
                $values[] = $this->backupCsvCell($v);
            }
            fputcsv($handle, $values);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content ?: '';
    }

    /**
     * @param  mixed  $value
     */
    private function backupCsvCell($value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    private function normalizeReportingPeriod(?string $period): string
    {
        if (! in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true)) {
            return 'monthly';
        }

        return $period;
    }

    /**
     * @return list<int>
     */
    private function availableReportingYears(): array
    {
        $current = (int) now()->year;
        $minYear = $current;

        if (Schema::hasTable('transactions')) {
            $min = DB::table('transactions')->min('date');
            if ($min !== null) {
                $minYear = min($minYear, (int) Carbon::parse((string) $min)->year);
            }
        }

        $minYear = max(2020, $minYear);

        return range($current, $minYear);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function availableReportingWeeks(): array
    {
        $weekStart = now()->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

        return collect(range(0, 12))
            ->map(function (int $weeksAgo) use ($weekStart): array {
                $start = $weekStart->copy()->subWeeks($weeksAgo);
                $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

                return [
                    'value' => $start->toDateString(),
                    'label' => $start->format('M j').' - '.$end->format('M j, Y'),
                ];
            })
            ->all();
    }

    /**
     * @return array{value: string, selectionLabel: string, badge: string, start: string, end: string, dateLabel: string}
     */
    private function resolveReportingContext(string $period, ?string $periodValue): array
    {
        $now = now();

        if ($period === 'daily') {
            $weekdays = [
                'monday' => Carbon::MONDAY,
                'tuesday' => Carbon::TUESDAY,
                'wednesday' => Carbon::WEDNESDAY,
                'thursday' => Carbon::THURSDAY,
                'friday' => Carbon::FRIDAY,
                'saturday' => Carbon::SATURDAY,
                'sunday' => Carbon::SUNDAY,
            ];
            $key = strtolower(trim((string) $periodValue));
            if (! isset($weekdays[$key])) {
                $key = strtolower($now->format('l'));
            }
            $date = $now->copy()->startOfDay();
            $targetDow = $weekdays[$key];
            $daysBack = ($date->dayOfWeek - $targetDow + 7) % 7;
            if ($daysBack > 0) {
                $date->subDays($daysBack);
            }
            $label = ucfirst($key);

            return [
                'value' => $key,
                'selectionLabel' => $label,
                'badge' => $label,
                'start' => $date->toDateString(),
                'end' => $date->toDateString(),
                'dateLabel' => $date->format('M j, Y'),
            ];
        }

        if ($period === 'weekly') {
            $currentWeekStart = $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();

            try {
                $start = Carbon::createFromFormat('Y-m-d', trim((string) $periodValue))->startOfWeek(Carbon::MONDAY)->startOfDay();
            } catch (\Throwable) {
                $start = $currentWeekStart->copy();
            }

            if ($start->gt($currentWeekStart)) {
                $start = $currentWeekStart->copy();
            }

            $displayEnd = $start->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();
            $rangeEnd = $displayEnd->gt($now) ? $now->copy()->startOfDay() : $displayEnd;
            $label = $start->format('M j').' - '.$displayEnd->format('M j, Y');

            return [
                'value' => $start->toDateString(),
                'selectionLabel' => $label,
                'badge' => 'Week '.$start->isoWeek(),
                'start' => $start->toDateString(),
                'end' => $rangeEnd->toDateString(),
                'dateLabel' => $label,
            ];
        }

        if ($period === 'monthly') {
            $months = [
                'january' => 1,
                'february' => 2,
                'march' => 3,
                'april' => 4,
                'may' => 5,
                'june' => 6,
                'july' => 7,
                'august' => 8,
                'september' => 9,
                'october' => 10,
                'november' => 11,
                'december' => 12,
            ];
            $key = strtolower(trim((string) $periodValue));
            if (isset($months[$key])) {
                $monthNum = $months[$key];
            } elseif (is_numeric($periodValue) && (int) $periodValue >= 1 && (int) $periodValue <= 12) {
                $monthNum = (int) $periodValue;
                $flipped = array_flip($months);
                $key = $flipped[$monthNum] ?? strtolower($now->format('F'));
            } else {
                $monthNum = (int) $now->month;
                $key = strtolower($now->format('F'));
            }
            $year = (int) $now->year;
            $start = Carbon::create($year, $monthNum, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            if ($end->gt($now)) {
                $end = $now->copy()->startOfDay();
            }
            $label = ucfirst($key);

            return [
                'value' => $key,
                'selectionLabel' => $label,
                'badge' => $label,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'dateLabel' => $start->format('F Y'),
            ];
        }

        $year = (int) preg_replace('/\D+/', '', (string) $periodValue);
        $currentYear = (int) $now->year;
        if ($year < 2000 || $year > $currentYear) {
            $year = $currentYear;
        }
        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end = $year === $currentYear
            ? $now->copy()->startOfDay()
            : Carbon::create($year, 12, 31)->startOfDay();

        return [
            'value' => (string) $year,
            'selectionLabel' => (string) $year,
            'badge' => (string) $year,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'dateLabel' => (string) $year,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reportingPayload(string $period, ?string $periodValue = null): array
    {
        $context = $this->resolveReportingContext($period, $periodValue);
        $rangeStart = $context['start'];
        $rangeEnd = $context['end'];
        $periodValue = $context['value'];
        $selectionLabel = $context['selectionLabel'];
        $selectionBadge = $context['badge'];
        $dateLabel = $context['dateLabel'];

        $primaryAmount = (float) DB::table('transactions')
            ->whereDate('date', '>=', $rangeStart)
            ->whereDate('date', '<=', $rangeEnd)
            ->sum('amount');

        $hoursValue = round(((float) DB::table('transactions')
            ->whereDate('date', '>=', $rangeStart)
            ->whereDate('date', '<=', $rangeEnd)
            ->sum('duration')) / 60, 1);

        $secondaryUserCount = $this->countUserRegistrationsBetween($rangeStart, $rangeEnd);

        if ($period === 'daily') {
            $primaryLabel = 'Daily Sales';
            $primaryBadge = $selectionBadge;
            $primarySub = 'Total sales for '.$dateLabel;
            $primaryIcon = 'receipt';
            $secondaryLabel = 'New Users';
            $secondaryBadge = $selectionBadge;
            $secondarySub = 'New users on '.$dateLabel;
            $secondaryIcon = 'people';
            $hoursLabel = 'Total Service Hours';
            $hoursBadge = $selectionBadge;
            $hoursSub = 'Service time logged on '.$dateLabel;
            $hoursIcon = 'clock';
            $pageSubtitle = $dateLabel.' — sales and new user signups';
            $serviceLabel = $dateLabel;
        } elseif ($period === 'weekly') {
            $primaryLabel = 'Weekly Sales';
            $primaryBadge = $selectionBadge;
            $primarySub = 'Total for '.$dateLabel;
            $primaryIcon = 'calendar-week';
            $secondaryLabel = 'New Users';
            $secondaryBadge = $selectionBadge;
            $secondarySub = 'New users during '.$dateLabel;
            $secondaryIcon = 'people';
            $hoursLabel = 'Total Service Hours';
            $hoursBadge = $selectionBadge;
            $hoursSub = 'Service time logged during '.$dateLabel;
            $hoursIcon = 'clock';
            $pageSubtitle = $dateLabel.' — sales and new user signups';
            $serviceLabel = $dateLabel;
        } elseif ($period === 'yearly') {
            $year = (int) $periodValue;
            $primaryLabel = 'Yearly Sales';
            $primaryBadge = $selectionBadge;
            $primarySub = $year === (int) now()->year ? 'Year-to-date' : 'Full year total';
            $primaryIcon = 'calendar2-range';
            $secondaryLabel = 'New Users';
            $secondaryBadge = $selectionBadge;
            $secondarySub = $year === (int) now()->year ? 'New users year-to-date' : 'New users in '.$selectionBadge;
            $secondaryIcon = 'people';
            $hoursLabel = 'Total Service Hours';
            $hoursBadge = $selectionBadge;
            $hoursSub = $year === (int) now()->year ? 'Service time logged year-to-date' : 'Service time logged in '.$selectionBadge;
            $hoursIcon = 'clock';
            $pageSubtitle = $selectionBadge.' — sales and new user signups';
            $serviceLabel = (string) $selectionBadge;
        } else {
            $primaryLabel = 'Monthly Sales';
            $primaryBadge = $selectionBadge;
            $primarySub = 'Total for '.$dateLabel;
            $primaryIcon = 'calendar2-week';
            $secondaryLabel = 'New Users';
            $secondaryBadge = $selectionBadge;
            $secondarySub = 'New users in '.$dateLabel;
            $secondaryIcon = 'calendar2-week';
            $hoursLabel = 'Total Service Hours';
            $hoursBadge = $selectionBadge;
            $hoursSub = 'Service time logged in '.$dateLabel;
            $hoursIcon = 'clock';
            $pageSubtitle = $dateLabel.' — sales and new user signups';
            $serviceLabel = $dateLabel;
        }

        if ($period === 'daily') {
            $trendRows = DB::table('transactions')
                ->select(['time', 'amount'])
                ->whereDate('date', '=', $rangeStart)
                ->get()
                ->groupBy(fn ($transaction) => Carbon::parse((string) $transaction->time)->format('H:00'))
                ->map(fn (Collection $transactions, string $bucket) => (object) [
                    'bucket' => $bucket,
                    'total' => $transactions->sum(fn ($transaction) => (float) $transaction->amount),
                ]);

            $map = $trendRows->keyBy('bucket');
            $trendLabelsPretty = collect(range(0, 23))
                ->map(fn (int $h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00');
            $trendData = $trendLabelsPretty->map(fn (string $b) => (float) optional($map->get($b))->total)->values();
            $trendSubtitle = $dateLabel.' (hourly sales)';
        } elseif ($period === 'yearly') {
            $year = (int) $periodValue;
            $trendRows = DB::table('transactions')
                ->select(['date', 'amount'])
                ->whereDate('date', '>=', Carbon::create($year, 1, 1)->toDateString())
                ->whereDate('date', '<=', $rangeEnd)
                ->get()
                ->groupBy(fn ($transaction) => Carbon::parse((string) $transaction->date)->format('Y-m'))
                ->map(fn (Collection $transactions, string $bucket) => (object) [
                    'bucket' => $bucket,
                    'total' => $transactions->sum(fn ($transaction) => (float) $transaction->amount),
                ]);

            $map = $trendRows->keyBy('bucket');
            $trendYm = collect(range(1, 12))
                ->map(fn (int $m) => sprintf('%04d-%02d', $year, $m))
                ->values();
            $trendData = $trendYm->map(fn (string $b) => (float) optional($map->get($b))->total)->values();
            $trendLabelsPretty = $trendYm->map(fn (string $b) => Carbon::createFromFormat('Y-m', $b)->format('M'))->values();
            $trendSubtitle = $selectionBadge.' (monthly sales)';
        } else {
            $start = Carbon::parse($rangeStart);
            $end = Carbon::parse($rangeEnd);
            $trendRows = DB::table('transactions')
                ->selectRaw('date as bucket, SUM(amount) as total')
                ->whereDate('date', '>=', $rangeStart)
                ->whereDate('date', '<=', $rangeEnd)
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();

            $map = $trendRows->keyBy('bucket');
            $trendDates = collect();
            for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
                $trendDates->push($cursor->toDateString());
            }
            $trendData = $trendDates->map(fn (string $b) => (float) optional($map->get($b))->total)->values();
            $trendLabelsPretty = $trendDates->map(fn (string $b) => Carbon::parse($b)->format('M d'))->values();
            $trendSubtitle = $dateLabel.' (daily sales)';
        }

        $minutesByTherapistId = DB::table('transactions')
            ->selectRaw('therapist_id, SUM(duration) as total_minutes')
            ->whereDate('date', '>=', $rangeStart)
            ->whereDate('date', '<=', $rangeEnd)
            ->groupBy('therapist_id')
            ->pluck('total_minutes', 'therapist_id')
            ->map(fn ($m) => (int) $m);

        if (Schema::hasTable('therapists')) {
            $therapistHoursBreakdown = DB::table('therapists')
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(function ($therapist) use ($minutesByTherapistId): array {
                    $minutes = (int) ($minutesByTherapistId->get($therapist->id, $minutesByTherapistId->get((string) $therapist->id, 0)));

                    return [
                        'name' => (string) ($therapist->name ?? 'Unknown Therapist'),
                        'hours' => round($minutes / 60, 1),
                    ];
                })
                ->sortByDesc('hours')
                ->values()
                ->all();
        } else {
            $therapistHoursBreakdown = $minutesByTherapistId
                ->map(function (int $minutes, $therapistId): array {
                    return [
                        'name' => 'Therapist #'.$therapistId,
                        'hours' => round($minutes / 60, 1),
                    ];
                })
                ->sortByDesc('hours')
                ->values()
                ->all();
        }

        $serviceBreakdown = DB::table('transactions')
            ->selectRaw('service_name as service, SUM(amount) as total')
            ->whereDate('date', '>=', $rangeStart)
            ->whereDate('date', '<=', $rangeEnd)
            ->groupBy('service')
            ->orderByDesc('total')
            ->get();

        $allServiceLabels = collect([
            'Deep Tissue',
            'Swedish Massage',
            'Aromatherapy',
            'Sports Massage',
            'Hot Stone',
            'Foot Reflexology',
            'Prenatal Massage',
            'Thai Massage',
        ]);
        $totalsByService = $serviceBreakdown->pluck('total', 'service');
        $serviceLabels = $allServiceLabels->values();
        $serviceTotals = $allServiceLabels
            ->map(fn (string $service) => (float) ($totalsByService[$service] ?? 0))
            ->values();
        $serviceTotalSum = (float) $serviceTotals->sum();
        $colors = collect(['#0c9aa6', '#04724d', '#4f6d8c', '#2f9d62', '#c95a7b', '#f0a74d', '#7f8c8d', '#8e44ad']);
        $serviceSegments = [];
        $acc = 0.0;
        foreach ($serviceTotals as $i => $val) {
            $pct = $serviceTotalSum > 0 ? ($val / $serviceTotalSum) * 100.0 : 0.0;
            $serviceSegments[] = [
                'label' => (string) ($serviceLabels[$i] ?? 'Service'),
                'value' => (float) $val,
                'color' => (string) $colors[$i % $colors->count()],
                'start' => $acc,
                'end' => $acc + $pct,
            ];
            $acc += $pct;
        }

        $insightPeakLabel = match ($period) {
            'daily' => 'Peak hour',
            'yearly' => 'Best month',
            default => 'Best day',
        };

        return [
            'period' => $period,
            'periodValue' => $periodValue,
            'periodValueLabel' => $selectionLabel,
            'availableYears' => $this->availableReportingYears(),
            'availableWeeks' => $this->availableReportingWeeks(),
            'serviceLabel' => $serviceLabel,
            'primaryAmount' => $primaryAmount,
            'primaryLabel' => $primaryLabel,
            'primaryBadge' => $primaryBadge,
            'primarySub' => $primarySub,
            'primaryIcon' => $primaryIcon,
            'secondaryUserCount' => $secondaryUserCount,
            'secondaryLabel' => $secondaryLabel,
            'secondaryBadge' => $secondaryBadge,
            'secondarySub' => $secondarySub,
            'secondaryIcon' => $secondaryIcon,
            'hoursValue' => $hoursValue,
            'hoursLabel' => $hoursLabel,
            'hoursBadge' => $hoursBadge,
            'hoursSub' => $hoursSub,
            'hoursIcon' => $hoursIcon,
            'therapistHoursBreakdown' => $therapistHoursBreakdown,
            'therapistHoursFirst' => $therapistHoursBreakdown[0] ?? null,
            'pageSubtitle' => $pageSubtitle,
            'trendLabels' => $trendLabelsPretty->values()->all(),
            'trendData' => $trendData->values()->all(),
            'serviceLabels' => $serviceLabels->values()->all(),
            'serviceTotals' => $serviceTotals->values()->all(),
            'serviceSegments' => $serviceSegments,
            'trendSubtitle' => $trendSubtitle,
            'insightPeakLabel' => $insightPeakLabel,
            'serviceTotalSum' => $serviceTotalSum,
        ];
    }

    /** New accounts on a single calendar day (customers + receptionists + admins). */
    private function countUserRegistrationsOnDate(string $date): int
    {
        $n = 0;
        foreach (['customers', 'receptionists', 'users'] as $table) {
            $n += (int) DB::table($table)->whereDate('created_at', $date)->count();
        }

        return $n;
    }

    /** New accounts from a calendar date through today (inclusive). */
    private function countUserRegistrationsFromDate(string $fromDate): int
    {
        $n = 0;
        foreach (['customers', 'receptionists', 'users'] as $table) {
            $n += (int) DB::table($table)->whereDate('created_at', '>=', $fromDate)->count();
        }

        return $n;
    }

    /** New accounts within an inclusive date range. */
    private function countUserRegistrationsBetween(string $start, string $end): int
    {
        $n = 0;
        foreach (['customers', 'receptionists', 'users'] as $table) {
            $n += (int) DB::table($table)
                ->whereDate('created_at', '>=', $start)
                ->whereDate('created_at', '<=', $end)
                ->count();
        }

        return $n;
    }

    public function storeReceptionist(Request $request): RedirectResponse
    {
        $this->ensureReceptionistsTableExists();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:receptionists,username'],
            'email' => ['required', 'email', 'max:255', 'unique:receptionists,email'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'regex:/^09\d{9}$/'],
            'birthday' => ['nullable', 'date'],
            'profile_picture' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'phone_number.regex' => 'Phone number must be 11 digits starting with 09.',
        ]);

        // Shift was removed from the UI but the DB column still exists.
        $validated['shift'] = trim((string) $request->input('shift', '—')) !== '' ? (string) $request->input('shift') : '—';

        $validated['receptionist_id'] = $this->generateReceptionistId();

        if ($request->hasFile('profile_picture')) {
            $validated['profile_picture'] = $request->file('profile_picture')->store('receptionists', 'public');
        }

        $receptionist = Receptionist::create($validated);

        // Create a login account for the receptionist (users table).
        if (Schema::hasColumn('users', 'role')) {
            $existingUser = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
                ->orWhereRaw('LOWER(username) = ?', [strtolower($validated['username'])])
                ->first();

            if ($existingUser === null) {
                User::query()->create([
                    'name' => $validated['full_name'],
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'role' => User::ROLE_RECEPTIONIST,
                ]);
            }
        }

        ActivityLogger::log(
            'receptionist.created',
            'Added receptionist '.$receptionist->full_name,
            ['email' => $receptionist->email, 'receptionist_id' => $receptionist->receptionist_id],
            subject: $receptionist,
            request: $request,
        );

        return redirect()->route('users.index', ['tab' => 'receptionists'])->with('status', 'Receptionist added successfully.');
    }

    public function updateReceptionist(Request $request, Receptionist $receptionist): RedirectResponse
    {
        $this->ensureReceptionistsTableExists();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:receptionists,username,'.$receptionist->id],
            'email' => ['required', 'email', 'max:255', 'unique:receptionists,email,'.$receptionist->id],
            'address' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'regex:/^09\d{9}$/'],
            'birthday' => ['nullable', 'date'],
            'profile_picture' => ['nullable', 'image', 'max:2048'],
        ], [
            'phone_number.regex' => 'Phone number must be 11 digits starting with 09.',
        ]);

        // Preserve existing shift unless a value is explicitly provided.
        if ($request->has('shift')) {
            $validated['shift'] = trim((string) $request->input('shift', '—')) !== '' ? (string) $request->input('shift') : '—';
        }

        if ($request->hasFile('profile_picture')) {
            if ($receptionist->profile_picture) {
                Storage::disk('public')->delete($receptionist->profile_picture);
            }
            $validated['profile_picture'] = $request->file('profile_picture')->store('receptionists', 'public');
        }

        $receptionist->update($validated);

        // Keep the receptionist login account in sync.
        if (Schema::hasColumn('users', 'role')) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($receptionist->getOriginal('email') ?: $validated['email'])])
                ->orWhereRaw('LOWER(username) = ?', [strtolower($receptionist->getOriginal('username') ?: $validated['username'])])
                ->first();

            if ($user !== null) {
                $user->name = $validated['full_name'];
                $user->username = $validated['username'];
                $user->email = $validated['email'];
                $user->role = $user->role ?: User::ROLE_RECEPTIONIST;
                $user->save();
            }
        }

        ActivityLogger::log(
            'receptionist.updated',
            'Updated receptionist '.$receptionist->full_name,
            ['email' => $receptionist->email, 'receptionist_id' => $receptionist->receptionist_id],
            subject: $receptionist,
            request: $request,
        );

        return redirect()->route('users.index', ['tab' => 'receptionists'])->with('status', 'Receptionist updated successfully.');
    }

    public function destroyReceptionist(Request $request, Receptionist $receptionist): RedirectResponse
    {
        $this->ensureReceptionistsTableExists();

        if ($receptionist->isArchived()) {
            return redirect()->route('users.index', ['tab' => 'receptionists'])
                ->with('status', 'This receptionist is already archived.');
        }

        $name = $receptionist->full_name;
        $email = $receptionist->email;
        $receptionistId = $receptionist->receptionist_id;
        $username = $receptionist->username;

        $receptionist->archive();
        $this->archiveLinkedReceptionistUser($email, $username);

        ActivityLogger::log(
            'receptionist.archived',
            'Archived receptionist '.$name,
            ['email' => $email, 'receptionist_id' => $receptionistId],
            request: $request,
        );

        return redirect()->route('users.index', ['tab' => 'receptionists'])
            ->with('status', 'Receptionist archived successfully. Their login is disabled but records are preserved.');
    }

    private function ensureReceptionistsTableExists(): void
    {
        if (Schema::hasTable('receptionists')) {
            // Ensure the newer column exists (older DBs might not be migrated).
            if (! Schema::hasColumn('receptionists', 'full_name')) {
                Schema::table('receptionists', function ($table): void {
                    /** @var Blueprint $table */
                    $table->string('full_name')->nullable()->after('receptionist_id');
                });
            }
            if (! Schema::hasColumn('receptionists', 'address')) {
                Schema::table('receptionists', function ($table): void {
                    /** @var Blueprint $table */
                    $table->string('address')->nullable()->after('email');
                });
            }
            if (! Schema::hasColumn('receptionists', 'phone_number')) {
                Schema::table('receptionists', function ($table): void {
                    /** @var Blueprint $table */
                    $table->string('phone_number', 30)->nullable()->after('address');
                });
            }
            if (! Schema::hasColumn('receptionists', 'birthday')) {
                Schema::table('receptionists', function ($table): void {
                    /** @var Blueprint $table */
                    $table->date('birthday')->nullable()->after('phone_number');
                });
            }

            return;
        }

        Schema::create('receptionists', function ($table): void {
            /** @var Blueprint $table */
            $table->id();
            $table->string('receptionist_id')->unique();
            $table->string('full_name')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('address')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->date('birthday')->nullable();
            $table->string('shift');
            $table->string('profile_picture')->nullable();
            $table->timestamps();
        });
    }

    private function generateReceptionistId(): string
    {
        $latest = Receptionist::latest('id')->first();
        $nextNumber = ($latest?->id ?? 0) + 1;

        return 'RCP-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private function paginateCollection(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = max((int) request()->query('page', 1), 1);
        $total = $items->count();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();
        $query = request()->query();
        unset($query['ajax']);

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => $query],
        );
    }

    private function clientRecordsTablesReady(): bool
    {
        return Schema::hasTable('customers')
            && Schema::hasTable('users')
            && Schema::hasColumn('customers', 'email')
            && Schema::hasColumn('customers', 'full_name');
    }

    private function removeWalkInCustomerRecords(): void
    {
        app(WalkInClientService::class)->purgeStaleWalkInCustomerRecords();
    }

    private function archiveLinkedUserByEmail(?string $email): void
    {
        if ($email === null || trim($email) === '' || ! Schema::hasColumn('users', 'archived_at')) {
            return;
        }

        User::query()
            ->active()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->get()
            ->each(fn (User $user) => $user->archive());
    }

    private function archiveLinkedReceptionistUser(?string $email, ?string $username): void
    {
        if (! Schema::hasColumn('users', 'archived_at') || ! Schema::hasColumn('users', 'role')) {
            return;
        }

        User::query()
            ->active()
            ->where('role', User::ROLE_RECEPTIONIST)
            ->where(function ($query) use ($email, $username): void {
                if ($email !== null && trim($email) !== '') {
                    $query->whereRaw('LOWER(email) = ?', [strtolower(trim($email))]);
                }

                if ($username !== null && trim($username) !== '') {
                    $method = ($email !== null && trim($email) !== '') ? 'orWhereRaw' : 'whereRaw';
                    $query->{$method}('LOWER(username) = ?', [strtolower(trim($username))]);
                }
            })
            ->get()
            ->each(fn (User $user) => $user->archive());
    }
}
