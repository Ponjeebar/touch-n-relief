<?php

namespace App\Http\Controllers;

use App\Models\SpaBooking;
use App\Models\User;
use App\Models\TimeSlot;
use App\Support\PaymentMethodCatalog;
use App\Services\ActivityLogger;
use App\Services\BookingCancellationService;
use App\Services\BookingRescheduleService;
use App\Services\BookingSlotService;
use App\Services\PaymongoService;
use App\Services\TherapistAvailabilityService;
use App\Services\SpaServiceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly BookingRescheduleService $reschedules,
        private readonly BookingSlotService $slots,
        private readonly TherapistAvailabilityService $therapistAvailability,
        private readonly PaymongoService $paymongo,
    ) {}

    /**
     * Show landing page.
     */
    public function landing(): View
    {
        $this->ensureSlotsSeeded();

        $user = auth()->user();
        $services = $this->servicesWithSlotTimes($user instanceof User ? $user : null);
        $slotMap = $this->slots->slotMapByService();
        $hidePrenatalRefs = $user instanceof User && $user->isMale();
        $therapists = app(\App\Services\TherapistCatalog::class)->forLanding($hidePrenatalRefs);
        $footer = app(\App\Services\SiteSettingsService::class)->footer();

        return view('welcome', [
            'services' => $services,
            'therapists' => $therapists,
            'therapistGridColumns' => \App\Support\TherapistGridLayout::responsiveColumns(count($therapists)),
            'slotMap' => $slotMap,
            'bookableTherapistNames' => collect($this->therapistCatalog())->pluck('name')->values()->all(),
            'landingAvailabilityUrl' => route('landing.availability'),
            'footer' => $footer,
            'today' => now()->toDateString(),
            'isPregnantCustomer' => $user instanceof User && $user->isPregnant(),
            'isMaleCustomer' => $user instanceof User && $user->isMale(),
        ]);
    }

    /**
     * Show booking page.
     */
    public function create(Request $request): View
    {
        $this->ensureSlotsSeeded();

        $user = $request->user();
        $services = $this->servicesWithSlotTimes($user instanceof User ? $user : null);
        $bookingDateInput = (string) old('booking_date', $request->query('date', ''));
        $bookingWhen = $bookingDateInput !== ''
            ? \Carbon\Carbon::parse($bookingDateInput)->startOfDay()
            : now()->startOfDay();
        $therapists = app(\App\Services\TherapistCatalog::class)->forBooking($bookingWhen);
        $requestedService = (string) $request->query('service', '');
        $requestedTherapist = (string) $request->query('therapist', '');

        $selectedService = collect($services)->first(function (array $service) use ($requestedService): bool {
            return $requestedService !== '' && strcasecmp($service['name'], $requestedService) === 0;
        });

        $selectedTherapist = collect($therapists)->first(function (array $therapist) use ($requestedTherapist): bool {
            return strcasecmp($therapist['name'], $requestedTherapist) === 0;
        });

        // Only pre-select when the landing page passes ?service= or ?therapist= (not generic "Book Now").
        $selectedServiceName = $selectedService['name'] ?? null;
        $selectedTherapistName = $selectedTherapist['name'] ?? null;

        $slotMap = $this->slots->slotMapByService();
        $fromLandingTherapist = $requestedTherapist !== '' && $selectedTherapist !== null;

        return view('booking.create', [
            'services' => $services,
            'therapists' => $therapists,
            'selectedServiceName' => $selectedServiceName,
            'selectedTherapistName' => $selectedTherapistName,
            'fromLandingTherapist' => $fromLandingTherapist,
            'slotMap' => $slotMap,
            'allSlots' => $this->slots->allSlotLabels(),
            'therapistNames' => collect($therapists)->pluck('name')->values()->all(),
            'availabilityUrl' => route('booking.availability'),
            'therapistAvailabilityUrl' => route('booking.therapist-availability'),
            'today' => now()->toDateString(),
            'isPregnantCustomer' => $user instanceof User && $user->isPregnant(),
            'isMaleCustomer' => $user instanceof User && $user->isMale(),
            'servicePriceMap' => collect($services)->mapWithKeys(function (array $service): array {
                return [$service['name'] => (float) ($service['price_amount'] ?? 0)];
            })->all(),
            'paymentMethods' => PaymentMethodCatalog::methods(),
            'paymongoEnabled' => $this->paymongo->isConfigured(),
            'paymongoMethods' => $this->paymongo->paymentMethodTypes(),
            'paymongoChannels' => PaymentMethodCatalog::paymongoChannelOptions($this->paymongo->paymentMethodTypes()),
        ]);
    }

    /**
     * Handle booking submission.
     */
    public function store(Request $request): RedirectResponse
    {
        if (! $this->paymongo->isConfigured()) {
            return back()
                ->withErrors(['payment' => 'Online payment is not configured yet. Please contact the spa.'], 'booking')
                ->withInput();
        }

        $user = $request->user();
        $catalog = $this->servicesFor($user instanceof User ? $user : null);
        $therapists = $this->therapistCatalog();
        $serviceNames = collect($catalog)->pluck('name')->all();
        $therapistNames = collect($therapists)->pluck('name')->all();
        $allSlots = $this->slots->allSlotLabels();

        $validated = $request->validateWithBag('booking', [
            'service' => ['required', 'string', 'in:'.implode(',', $serviceNames)],
            'therapist' => ['required', 'string', 'in:'.implode(',', $therapistNames)],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string', 'max:30', 'in:'.implode(',', $allSlots)],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_type' => ['required', 'string', 'in:'.implode(',', PaymentMethodCatalog::typeKeys())],
        ]);

        $validated['payment_method'] = PaymentMethodCatalog::METHOD_PAYMONGO;

        $serviceRow = collect($catalog)->firstWhere('name', $validated['service']);
        $serviceAmount = $this->serviceAmount($serviceRow) ?? 0.0;
        $paymentAmount = PaymentMethodCatalog::calculateAmount($serviceAmount, $validated['payment_type']);
        $durationMinutes = $this->serviceDurationMinutes($serviceRow) ?? app(SpaServiceCatalog::class)->durationMinutesFor($validated['service']);
        $durationMinutes = max($durationMinutes, 1);

        if ($paymentAmount <= 0) {
            return back()
                ->withErrors(['payment' => 'Unable to calculate payment amount for this service.'], 'booking')
                ->withInput();
        }

        try {
            $this->therapistAvailability->assertBookableOnDate(
                $validated['therapist'],
                $validated['booking_date'],
            );

            $this->slots->assertBookingAvailable(
                $user->id,
                $validated['service'],
                $validated['therapist'],
                $validated['booking_date'],
                $validated['time_slot'],
                $durationMinutes,
                null,
                $therapistNames,
            );
        } catch (ValidationException $e) {
            return $this->bookingValidationResponse($e, $user, $validated, $durationMinutes);
        }

        $booking = null;

        try {
            DB::transaction(function () use ($validated, $serviceRow, $user, $durationMinutes, $therapistNames, $paymentAmount, &$booking): void {
                $this->slots->assertBookingAvailable(
                    $user->id,
                    $validated['service'],
                    $validated['therapist'],
                    $validated['booking_date'],
                    $validated['time_slot'],
                    $durationMinutes,
                    null,
                    $therapistNames,
                    withTherapistLock: true,
                );

                $bookingAttributes = [
                    'user_id' => $user->id,
                    'client_name' => (string) ($user->name ?: $user->email),
                    'service_name' => $validated['service'],
                    'therapist_name' => $validated['therapist'],
                    'booking_date' => $validated['booking_date'],
                    'time_slot' => $validated['time_slot'],
                    'duration_minutes' => $durationMinutes,
                    'amount' => $this->serviceAmount($serviceRow),
                    'payment_method' => $validated['payment_method'],
                    'payment_type' => $validated['payment_type'],
                    'payment_amount' => $paymentAmount,
                    'payment_proof_path' => null,
                    'payment_status' => PaymentMethodCatalog::STATUS_PENDING,
                    'notes' => $validated['notes'] ?? null,
                    'session_status' => SpaBooking::STATUS_CONFIRMED,
                ];

                if (Schema::hasColumn('spa_bookings', 'booking_source')) {
                    $bookingAttributes['booking_source'] = SpaBooking::SOURCE_ONLINE;
                }

                $booking = SpaBooking::query()->create($bookingAttributes);
            });
        } catch (ValidationException $e) {
            return $this->bookingValidationResponse($e, $user, $validated, $durationMinutes);
        } catch (\Illuminate\Database\QueryException) {
            return back()
                ->withErrors(['time_slot' => 'Unable to save this booking. Please try again.'], 'booking')
                ->withInput();
        }

        if (! $booking instanceof SpaBooking) {
            return back()
                ->withErrors(['payment' => 'Unable to create your booking. Please try again.'], 'booking')
                ->withInput();
        }

        try {
            $typeLabel = PaymentMethodCatalog::typeLabelFor($validated['payment_type']);
            $checkoutSession = $this->paymongo->createCheckoutSession([
                'billing' => [
                    'name' => (string) ($user->name ?: $user->email),
                    'email' => (string) $user->email,
                ],
                'line_items' => [[
                    'name' => $validated['service'].' — '.$typeLabel,
                    'amount' => (int) round($paymentAmount * 100),
                    'currency' => 'PHP',
                    'quantity' => 1,
                ]],
                'payment_method_types' => $this->paymongo->paymentMethodTypes(),
                'success_url' => route('paymongo.success', ['spaBooking' => $booking->id]),
                'cancel_url' => route('paymongo.cancel', ['spaBooking' => $booking->id]),
                'reference_number' => (string) $booking->id,
                'metadata' => [
                    'booking_id' => (string) $booking->id,
                    'user_id' => (string) $user->id,
                    'service_name' => $validated['service'],
                    'payment_type' => $validated['payment_type'],
                ],
                'description' => 'Touch N Relief booking #'.$booking->id,
            ]);

            $checkoutUrl = (string) ($checkoutSession['attributes']['checkout_url'] ?? '');
            $sessionId = (string) ($checkoutSession['id'] ?? '');

            if ($checkoutUrl === '') {
                throw new \RuntimeException('PayMongo did not return a checkout URL.');
            }

            $booking->forceFill([
                'paymongo_checkout_session_id' => $sessionId !== '' ? $sessionId : null,
            ])->save();

            return redirect()->away($checkoutUrl);
        } catch (\Throwable $exception) {
            Log::warning('PayMongo checkout session failed.', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);

            $booking->delete();

            return back()
                ->withErrors(['payment' => 'Unable to start PayMongo checkout. Please try again.'], 'booking')
                ->withInput();
        }
    }

    public function landingAvailability(Request $request): JsonResponse
    {
        $this->ensureSlotsSeeded();

        $user = $request->user();
        $catalog = $this->allServices();
        $serviceNames = collect($catalog)->pluck('name')->all();
        $therapistNames = collect($this->therapistCatalog())->pluck('name')->all();

        $validated = $request->validate([
            'service' => ['required', 'string', 'in:'.implode(',', $serviceNames)],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $bookingDate = \Carbon\Carbon::parse($validated['booking_date']);
        $bookableTherapistNames = $this->therapistAvailability->bookableTherapistNamesForDate($bookingDate);

        return response()->json(
            $this->slots->landingAvailability(
                $validated['service'],
                $validated['booking_date'],
                $user instanceof User ? $user->id : null,
                $bookableTherapistNames,
                app(SpaServiceCatalog::class)->durationMinutesFor($validated['service']),
            ),
        );
    }

    public function therapistAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $date = \Carbon\Carbon::parse($validated['booking_date'])->startOfDay();

        return response()->json([
            'booking_date' => $date->toDateString(),
            'therapists' => $this->therapistAvailability->bookingAvailabilityMapForDate($date),
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $this->ensureSlotsSeeded();

        $user = $request->user();
        $catalog = $this->servicesFor($user instanceof User ? $user : null);
        $therapists = $this->therapistCatalog();
        $serviceNames = collect($catalog)->pluck('name')->all();
        $therapistNames = collect($therapists)->pluck('name')->all();

        $validated = $request->validate([
            'service' => ['required', 'string', 'in:'.implode(',', $serviceNames)],
            'therapist' => ['required', 'string', 'in:'.implode(',', $therapistNames)],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'exclude_booking_id' => ['nullable', 'integer', 'exists:spa_bookings,id'],
        ]);

        $excludeBookingId = isset($validated['exclude_booking_id']) ? (int) $validated['exclude_booking_id'] : null;
        if ($excludeBookingId !== null) {
            $owned = SpaBooking::query()
                ->where('id', $excludeBookingId)
                ->where('user_id', $user->id)
                ->exists();
            if (! $owned) {
                abort(403);
            }
        }

        $bookingDate = \Carbon\Carbon::parse($validated['booking_date']);
        $bookableTherapistNames = $this->therapistAvailability->bookableTherapistNamesForDate($bookingDate);

        $payload = $this->slots->availability(
            $validated['service'],
            $validated['therapist'],
            $validated['booking_date'],
            $user instanceof User ? $user->id : null,
            $bookableTherapistNames,
            app(SpaServiceCatalog::class)->durationMinutesFor($validated['service']),
            $excludeBookingId,
        );

        $therapistStatus = $this->therapistAvailability->bookingAvailabilityMapForDate($bookingDate);
        $selected = trim($validated['therapist']);
        $selectedMeta = $therapistStatus[$selected] ?? null;

        $payload['therapist_schedule'] = [
            'bookable' => (bool) ($selectedMeta['bookable'] ?? true),
            'status' => (string) ($selectedMeta['status'] ?? 'available'),
            'label' => $selectedMeta['label'] ?? null,
        ];
        $payload['therapists'] = $therapistStatus;

        return response()->json($payload);
    }

    public function reschedule(Request $request, SpaBooking $spaBooking): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user || $spaBooking->user_id !== $user->id) {
            abort(403);
        }

        $allSlots = $this->slots->allSlotLabels();

        $validated = $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string', 'max:30', 'in:'.implode(',', $allSlots)],
        ]);

        try {
            $result = $this->reschedules->reschedule(
                $spaBooking,
                $validated['booking_date'],
                $validated['time_slot'],
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->withErrors($e->errors())->withInput();
        }

        $spaBooking->refresh();

        $when = trim(
            ($spaBooking->booking_date?->format('M d, Y') ?? '').' '.(string) $spaBooking->time_slot
        );

        ActivityLogger::logCustomerAction(
            'schedule.rescheduled',
            sprintf(
                'Rescheduled %s to %s.',
                (string) $spaBooking->service_name,
                $when,
            ),
            $user,
            [
                'booking_id' => $spaBooking->id,
                'service_name' => $spaBooking->service_name,
                'therapist_name' => $spaBooking->therapist_name,
                'booking_date' => optional($spaBooking->booking_date)->toDateString(),
                'time_slot' => $spaBooking->time_slot,
            ],
            subject: $spaBooking,
            request: $request,
        );

        $message = 'Your appointment has been rescheduled to '
            .$result['date_display'].' at '.$result['time_slot'].'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'booking_id' => $spaBooking->id,
                'booking' => $result,
            ]);
        }

        return back()->with('txn_status', $message);
    }

    public function cancel(Request $request, SpaBooking $spaBooking): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user || $spaBooking->user_id !== $user->id) {
            abort(403);
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', Rule::in(array_keys(BookingCancellationService::REASON_OPTIONS))],
            'cancellation_other' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $spaBooking = $this->cancellations->cancel(
                $spaBooking,
                $validated['cancellation_reason'],
                $validated['cancellation_other'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->withErrors($e->errors())->withInput();
        }

        $spaBooking->refresh();

        $refundService = app(\App\Services\BookingRefundService::class);
        $refundMessage = $refundService->customerMessage($spaBooking);

        ActivityLogger::logCustomerAction(
            'schedule.cancelled',
            sprintf(
                'Cancelled %s (%s).',
                (string) $spaBooking->service_name,
                trim(
                    ($spaBooking->booking_date?->format('M d, Y') ?? '').' '.(string) $spaBooking->time_slot
                ),
            ),
            $user,
            [
                'booking_id' => $spaBooking->id,
                'service_name' => $spaBooking->service_name,
                'therapist_name' => $spaBooking->therapist_name,
                'booking_date' => optional($spaBooking->booking_date)->toDateString(),
                'time_slot' => $spaBooking->time_slot,
                'cancellation_reason' => $spaBooking->cancellation_reason,
            ],
            subject: $spaBooking,
            request: $request,
        );

        $message = 'Your schedule has been cancelled.';
        if ($refundMessage !== '') {
            $message .= ' '.$refundMessage;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'booking_id' => $spaBooking->id,
                'cancellation_reason' => $spaBooking->cancellation_reason,
                'refund_status' => $spaBooking->refund_status,
                'refund_status_label' => $refundService->labelFor($spaBooking->refund_status),
                'refund_amount' => (float) ($spaBooking->refund_amount ?? 0) > 0
                    ? '₱'.number_format((float) $spaBooking->refund_amount, 2)
                    : '',
                'refund_reference' => (string) ($spaBooking->refund_reference ?? ''),
                'refund_note' => (string) ($spaBooking->refund_note ?? ''),
            ]);
        }

        return back()->with('txn_status', $message);
    }

    /**
     * Booked slots: [serviceName => [ 'Y-m-d' => ['8:30 AM', ...] ]]
     *
     * @return array<string, array<string, array<int, string>>>
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    private function servicesWithSlotTimes(?User $user): array
    {
        $slotMap = $this->slots->slotMapByService();

        return array_map(function (array $service) use ($slotMap): array {
            $service['times'] = $slotMap[$service['name']] ?? $service['times'] ?? [];

            return $service;
        }, $this->servicesFor($user));
    }

    private function ensureSlotsSeeded(): void
    {
        app(\App\Services\TherapistCatalog::class)->ensureSeeded();
        app(SpaServiceCatalog::class)->ensureSeeded();
        app(\App\Services\SiteSettingsService::class)->ensureSeeded();

        if (! $this->slots->tablesReady()) {
            return;
        }

        if (TimeSlot::query()->count() === 0) {
            $this->slots->seedDefaults();
        }
    }

    /**
     * @return array<int, array{name: string, photo: string, role: string, specialties: array<int, string>}>
     */
    private function therapistCatalog(): array
    {
        return app(\App\Services\TherapistCatalog::class)->forBooking();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allServices(): array
    {
        return app(SpaServiceCatalog::class)->all();
    }

    /**
     * Services available to the current customer.
     * Prenatal Massage is only for pregnant female customers; hidden from male and all other customers.
     *
     * @return array<int, array<string, mixed>>
     */
    private function servicesFor(?User $user): array
    {
        return array_values(array_filter(
            $this->allServices(),
            fn (array $service): bool => $this->serviceIsAvailableTo($user, $service),
        ));
    }

    /**
     * @param  array<string, mixed>  $service
     */
    private function serviceIsAvailableTo(?User $user, array $service): bool
    {
        $isPrenatal = ! empty($service['prenatal_only']);

        // Guests do not have profile data yet; show full catalog on landing.
        if (! $user instanceof User) {
            return true;
        }

        if ($isPrenatal) {
            return $user->canAccessPrenatalServices();
        }

        return ! $user->canAccessPrenatalServices();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function bookingValidationResponse(ValidationException $e, User $user, array $validated, int $durationMinutes): RedirectResponse
    {
        $userConflict = $this->slots->userConflictAt(
            $user->id,
            $validated['booking_date'],
            $validated['time_slot'],
            $durationMinutes,
        );

        if ($userConflict !== null) {
            return back()
                ->with('booking_user_conflict', [
                    'booking_date' => $validated['booking_date'],
                    'time_slot' => $validated['time_slot'],
                    'service' => $userConflict['service'],
                    'therapist' => $userConflict['therapist'],
                ])
                ->withInput();
        }

        return back()
            ->withErrors($e->errors(), 'booking')
            ->withInput();
    }

    /**
     * @param  array<string, mixed>|null  $serviceRow
     */
    private function serviceDurationMinutes(?array $serviceRow): ?int
    {
        if (! is_array($serviceRow)) {
            return null;
        }

        $duration = (string) ($serviceRow['duration'] ?? '');
        if (preg_match('/(\d+)/', $duration, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $serviceRow
     */
    private function serviceAmount(?array $serviceRow): ?float
    {
        if (! is_array($serviceRow)) {
            return null;
        }

        $amount = $serviceRow['price_amount'] ?? null;
        if ($amount === null || $amount === '') {
            return null;
        }

        return (float) $amount;
    }
}
