<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SpaBooking;
use App\Models\SpaService;
use App\Models\Therapist;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BookingCancellationService;
use App\Services\BookingRefundService;
use App\Services\BookingRescheduleService;
use App\Services\BookingSlotService;
use App\Services\BookingSuggestionService;
use App\Services\SpaServiceCatalog;
use App\Services\WalkInClientService;
use App\Services\TherapistAvailabilityService;
use App\Services\TherapistCatalog;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StaffAppointmentController extends Controller
{
    public function __construct(
        private readonly BookingSlotService $slots,
        private readonly TherapistAvailabilityService $therapistAvailability,
        private readonly WalkInClientService $walkInClients,
        private readonly BookingRescheduleService $reschedules,
        private readonly BookingCancellationService $cancellations,
        private readonly BookingRefundService $refunds,
        private readonly BookingSuggestionService $suggestions,
    ) {}

    public function availability(Request $request): JsonResponse
    {
        $this->ensureStaff($request);
        $this->ensureCatalogSeeded();

        $serviceNames = $this->activeServiceNames();
        $therapistNames = $this->therapistNames();

        $validated = $request->validate([
            'service' => ['required', 'string', 'in:'.implode(',', $serviceNames)],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'therapist' => ['nullable', 'string', Rule::in($therapistNames)],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:20'],
            'exclude_booking_id' => ['nullable', 'integer', 'exists:spa_bookings,id'],
        ]);

        $therapist = trim((string) ($validated['therapist'] ?? ''));
        $excludeBookingId = isset($validated['exclude_booking_id']) ? (int) $validated['exclude_booking_id'] : null;
        $clientUserId = isset($validated['client_user_id']) ? (int) $validated['client_user_id'] : null;
        if ($clientUserId === null) {
            $clientUserId = $this->resolveClientUserIdForAvailability(
                $validated['client_email'] ?? null,
                $this->normalizeAvailabilityPhone($validated['client_phone'] ?? null),
                $validated['client_name'] ?? null,
            );
        }
        $bookingDate = $validated['booking_date'];
        $duration = app(SpaServiceCatalog::class)->durationMinutesFor($validated['service']);
        $bookableTherapists = $this->therapistAvailability->bookableTherapistNamesForDate(Carbon::parse($bookingDate));

        if ($therapist !== '') {
            $payload = $this->slots->availability(
                $validated['service'],
                $therapist,
                $bookingDate,
                $clientUserId,
                $bookableTherapists,
                $duration,
                $excludeBookingId,
            );

            $selectedMeta = $this->therapistAvailability
                ->bookingAvailabilityMapForDate(Carbon::parse($bookingDate))[$therapist] ?? null;

            $payload['therapist_schedule'] = [
                'bookable' => (bool) ($selectedMeta['bookable'] ?? true),
                'status' => (string) ($selectedMeta['status'] ?? 'available'),
                'label' => $selectedMeta['label'] ?? null,
            ];
            $payload = $this->applyOffDutyAvailability($payload);
        } else {
            $payload = $this->slots->landingAvailability(
                $validated['service'],
                $bookingDate,
                $clientUserId,
                $bookableTherapists,
                $duration,
            );
            $payload['all_slots'] = $this->slots->allSlotLabels();
            $payload['booked_slots'] = [];
            $payload['therapist_busy_details'] = [];
            $payload['therapist_schedule'] = [
                'bookable' => true,
                'status' => 'available',
                'label' => null,
            ];
        }

        $payload['therapists'] = $this->therapistAvailability->bookingAvailabilityMapForDate(Carbon::parse($bookingDate));

        return response()->json($payload);
    }

    public function searchClients(Request $request): JsonResponse
    {
        $this->ensureStaff($request);

        if (! Schema::hasTable('users')) {
            return response()->json(['clients' => []]);
        }

        $query = trim($request->string('q')->toString());
        if (mb_strlen($query) < 2) {
            return response()->json(['clients' => []]);
        }

        try {
            return response()->json([
                'clients' => $this->registeredClientsMatching($query),
            ]);
        } catch (\Throwable) {
            return response()->json(['clients' => []], 500);
        }
    }

    public function clientSuggestions(Request $request): JsonResponse
    {
        $this->ensureStaff($request);

        if (! Schema::hasTable('users') || ! Schema::hasTable('spa_bookings')) {
            return response()->json(['history' => $this->emptyClientSuggestionHistory()]);
        }

        $validated = $request->validate([
            'client_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        try {
            $client = User::query()
                ->when(Schema::hasColumn('users', 'role'), fn ($builder) => $builder->where('role', User::ROLE_USER))
                ->registeredClient()
                ->find((int) $validated['client_user_id']);

            if (! $client instanceof User) {
                return response()->json(['history' => $this->emptyClientSuggestionHistory()]);
            }

            $this->ensureCatalogSeeded();

            return response()->json([
                'history' => $this->suggestions->historyFor(
                    $client,
                    $this->catalogServicesForSuggestions(),
                    $this->catalogTherapistsForSuggestions(),
                ),
            ]);
        } catch (\Throwable) {
            return response()->json(['history' => $this->emptyClientSuggestionHistory()], 500);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $staff = $this->ensureStaff($request);

        if (! Schema::hasTable('users') || ! Schema::hasTable('spa_bookings')) {
            return back()
                ->withErrors(['client_name' => 'Appointments are unavailable until the database is set up.'], 'appointment')
                ->withInput()
                ->with('open_add_appointment', true);
        }

        $this->ensureCatalogSeeded();

        $serviceNames = $this->activeServiceNames();
        $therapistNames = $this->therapistNames();
        $allSlots = $this->slots->allSlotLabels();

        if ($serviceNames === []) {
            return back()
                ->withErrors(['service' => 'Add at least one active service before creating appointments.'], 'appointment')
                ->withInput()
                ->with('open_add_appointment', true);
        }

        if ($allSlots === []) {
            return back()
                ->withErrors(['time_slot' => 'No booking time slots are configured yet.'], 'appointment')
                ->withInput()
                ->with('open_add_appointment', true);
        }

        $hasPaymentFields = Schema::hasColumn('spa_bookings', 'payment_method');

        $rules = [
            'client_type' => ['required', 'in:walk_in,registered'],
            'client_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'regex:/^09\d{9}$/'],
            'client_password' => ['nullable', 'required_if:client_type,walk_in', 'confirmed', Password::defaults()],
            'client_birthday' => ['nullable', 'date'],
            'client_sex' => ['nullable', Rule::in(User::sexOptions())],
            'service' => ['required', 'string', 'in:'.implode(',', $serviceNames)],
            'therapist' => ['nullable', 'string', Rule::in($therapistNames)],
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string', 'max:30', 'in:'.implode(',', $allSlots)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        $messages = [
            'client_phone.regex' => 'Phone number must be 11 digits starting with 09.',
            'client_password.required_if' => 'Password is required for new clients.',
        ];

        if ($hasPaymentFields) {
            $hasTransactionIdColumn = Schema::hasColumn('spa_bookings', 'payment_transaction_id');
            $isWalkIn = ($request->input('client_type') === 'walk_in');

            $rules['payment_method'] = ['required', 'string', 'in:'.implode(',', PaymentMethodCatalog::staffMethodKeys())];
            $rules['payment_type'] = ['required', 'string', 'in:'.implode(',', PaymentMethodCatalog::typeKeys())];

            if ($hasTransactionIdColumn) {
                $isCashCounter = PaymentMethodCatalog::isCashCounter($request->input('payment_method'));

                if ($isCashCounter && ! $isWalkIn) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Cash over the counter is only available for walk-in clients.',
                    ]);
                }

                $rules['payment_transaction_id'] = [
                    Rule::requiredIf(fn (): bool => ! PaymentMethodCatalog::isCashCounter($request->input('payment_method'))),
                    'nullable',
                    'string',
                    'max:100',
                ];
                $rules['payment_proof'] = ['nullable', 'image', 'max:5120'];
                $messages['payment_transaction_id.required'] = 'Enter the payment transaction number.';
            } else {
                $rules['payment_proof'] = ['required', 'image', 'max:5120'];
                $messages['payment_proof.required'] = 'Upload proof of payment.';
                $messages['payment_proof.image'] = 'Payment proof must be an image file.';
            }

            $messages['payment_method.required'] = 'Select a payment method.';
            $messages['payment_type.required'] = 'Select down payment or full payment.';
        }

        $validated = $request->validate($rules, $messages);

        if ($validated['client_type'] === 'walk_in' && trim((string) ($validated['client_phone'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'client_phone' => 'Phone number is required for new walk-in clients.',
            ]);
        }

        if ($validated['client_type'] === 'walk_in' && trim((string) ($validated['client_email'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'client_email' => 'Email is required so the client can sign in.',
            ]);
        }

        if ($validated['client_type'] === 'registered' && empty($validated['client_user_id'])) {
            throw ValidationException::withMessages([
                'client_name' => 'Select an existing client from the suggestions list.',
            ]);
        }

        $serviceRow = SpaService::query()->where('name', $validated['service'])->first();
        $createdBooking = null;

        try {
            DB::transaction(function () use ($request, $validated, $therapistNames, $staff, $serviceRow, $hasPaymentFields, &$createdBooking): void {
                if ($validated['client_type'] === 'registered') {
                    $client = User::query()
                        ->when(Schema::hasColumn('users', 'role'), fn ($builder) => $builder->where('role', User::ROLE_USER))
                        ->registeredClient()
                        ->find((int) $validated['client_user_id']);

                    if (! $client instanceof User && Schema::hasTable('customers')) {
                        $email = strtolower(trim((string) ($validated['client_email'] ?? '')));
                        if ($email !== '') {
                            $customer = Customer::query()
                                ->registered()
                                ->whereRaw('LOWER(email) = ?', [$email])
                                ->first();

                            if ($customer instanceof Customer) {
                                $client = $this->walkInClients->ensureRegisteredUserForCustomer($customer);
                            }
                        }
                    }

                    if (! $client instanceof User) {
                        throw ValidationException::withMessages([
                            'client_user_id' => 'The selected client is not a registered account.',
                        ]);
                    }
                } else {
                    $client = $this->walkInClients->resolveStaffCreatedUser(
                        $validated['client_name'],
                        (string) $validated['client_email'],
                        (string) $validated['client_phone'],
                        (string) $validated['client_password'],
                        $validated['client_birthday'] ?? null,
                        $validated['client_sex'] ?? null,
                    );
                }

                $durationMinutes = max(
                    (int) ($serviceRow?->duration_minutes ?? app(SpaServiceCatalog::class)->durationMinutesFor($validated['service'])),
                    1,
                );

                $therapist = trim((string) ($validated['therapist'] ?? ''));
                if ($therapist === '') {
                    $therapist = $this->resolveAutoTherapist(
                        $client->id,
                        $validated['service'],
                        $validated['booking_date'],
                        $validated['time_slot'],
                        $durationMinutes,
                        $therapistNames,
                    );
                }

                $this->therapistAvailability->assertBookableOnDate($therapist, $validated['booking_date']);

                $this->slots->assertBookingAvailable(
                    $client->id,
                    $validated['service'],
                    $therapist,
                    $validated['booking_date'],
                    $validated['time_slot'],
                    $durationMinutes,
                    null,
                    $therapistNames,
                    withTherapistLock: true,
                );

                $serviceAmount = $serviceRow?->price_amount !== null ? (float) $serviceRow->price_amount : 0.0;
                $paymentAmount = $hasPaymentFields
                    ? PaymentMethodCatalog::calculateAmount($serviceAmount, (string) ($validated['payment_type'] ?? PaymentMethodCatalog::TYPE_DOWNPAYMENT))
                    : 0.0;
                $paymentProofPath = $hasPaymentFields && $request->hasFile('payment_proof')
                    ? $request->file('payment_proof')?->store('payment-proofs', 'public')
                    : null;

                $bookingAttributes = [
                    'user_id' => $client->id,
                    'client_name' => trim($validated['client_name']),
                    'service_name' => $validated['service'],
                    'therapist_name' => $therapist,
                    'booking_date' => $validated['booking_date'],
                    'time_slot' => $validated['time_slot'],
                    'duration_minutes' => $durationMinutes,
                    'amount' => $serviceRow?->price_amount !== null ? (float) $serviceRow->price_amount : null,
                    'notes' => $validated['notes'] ?? null,
                    'session_status' => SpaBooking::STATUS_CONFIRMED,
                ];

                if (Schema::hasColumn('spa_bookings', 'payment_method')) {
                    $paymentMethod = (string) $validated['payment_method'];
                    $isCashCounter = PaymentMethodCatalog::isCashCounter($paymentMethod);
                    $transactionId = trim((string) ($validated['payment_transaction_id'] ?? ''));

                    if ($isCashCounter && $transactionId === '') {
                        $transactionId = 'COT-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
                    }

                    $bookingAttributes['payment_method'] = $paymentMethod;
                    $bookingAttributes['payment_type'] = $validated['payment_type'];
                    $bookingAttributes['payment_amount'] = $paymentAmount;
                    $bookingAttributes['payment_proof_path'] = $paymentProofPath;
                    $bookingAttributes['payment_status'] = $isCashCounter
                        ? PaymentMethodCatalog::STATUS_PAID
                        : PaymentMethodCatalog::STATUS_PENDING;

                    if (Schema::hasColumn('spa_bookings', 'payment_transaction_id')) {
                        $bookingAttributes['payment_transaction_id'] = $transactionId;
                    }
                }

                if (Schema::hasColumn('spa_bookings', 'booking_source')) {
                    $bookingAttributes['booking_source'] = SpaBooking::SOURCE_WALK_IN;
                }

                $booking = SpaBooking::query()->create($bookingAttributes);
                $createdBooking = $booking;

                ActivityLogger::log(
                    'appointment.created',
                    sprintf(
                        'Created appointment for %s (%s with %s on %s at %s).',
                        trim($validated['client_name']),
                        $validated['service'],
                        $therapist,
                        Carbon::parse($validated['booking_date'])->format('M j, Y'),
                        $validated['time_slot'],
                    ),
                    [
                        'booking_id' => $booking->id,
                        'client_name' => trim($validated['client_name']),
                        'service_name' => $validated['service'],
                        'therapist_name' => $therapist,
                        'user_id' => $client->id,
                    ],
                    user: $staff,
                    subject: $booking,
                    request: $request,
                );
            });
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors(), 'appointment')
                ->withInput()
                ->with('open_add_appointment', true);
        } catch (QueryException) {
            return back()
                ->withErrors(['time_slot' => 'Unable to save this appointment. Please try again.'], 'appointment')
                ->withInput()
                ->with('open_add_appointment', true);
        }

        $dateFormatted = Carbon::parse($validated['booking_date'])->format('M j, Y');
        $clientLabel = trim($validated['client_name']);

        $redirect = redirect()
            ->route('appointments.index', ['date' => $validated['booking_date']])
            ->with('status', 'Appointment created for '.$clientLabel.' — '.$validated['service'].' on '.$dateFormatted.' at '.$validated['time_slot'].'.');

        if ($createdBooking instanceof SpaBooking && $hasPaymentFields) {
            $redirect->with('payment_receipt', $this->receiptPayloadFor($createdBooking->fresh(), $clientLabel));
        }

        return $redirect;
    }

    /**
     * @return array<string, string>
     */
    private function receiptPayloadFor(SpaBooking $booking, string $clientName): array
    {
        $paymentAmount = (float) ($booking->payment_amount ?? 0);

        return [
            'receipt_no' => 'RCP-'.str_pad((string) $booking->id, 5, '0', STR_PAD_LEFT),
            'client_name' => $clientName,
            'therapist' => (string) ($booking->therapist_name ?? '—'),
            'service' => (string) ($booking->service_name ?? '—'),
            'date' => $booking->booking_date?->format('M j, Y') ?? '—',
            'time' => (string) ($booking->time_slot ?? '—'),
            'payment_method' => PaymentMethodCatalog::labelFor($booking->payment_method),
            'payment_type' => PaymentMethodCatalog::typeLabelFor($booking->payment_type),
            'payment_amount' => number_format($paymentAmount, 2),
            'payment_status' => PaymentMethodCatalog::statusLabelFor($booking->payment_status),
            'reference' => (string) ($booking->payment_transaction_id ?? '—'),
            'issued_at' => now()->format('M j, Y g:i A'),
        ];
    }

    public function rescheduleAvailability(Request $request, SpaBooking $spaBooking): JsonResponse
    {
        $this->ensureStaff($request);
        $this->ensureCatalogSeeded();

        $this->walkInClients->assertValidBookingClient($spaBooking);

        $validated = $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        return response()->json(
            $this->reschedules->staffRescheduleAvailability($spaBooking, $validated['booking_date']),
        );
    }

    public function reschedule(Request $request, SpaBooking $spaBooking): JsonResponse|RedirectResponse
    {
        $staff = $this->ensureStaff($request);
        $this->ensureCatalogSeeded();

        $spaBooking->loadMissing('user');
        $this->walkInClients->assertValidBookingClient($spaBooking);

        $allSlots = $this->slots->allSlotLabels();

        $validated = $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string', 'max:30', 'in:'.implode(',', $allSlots)],
        ]);

        $result = null;

        try {
            DB::transaction(function () use ($spaBooking, $validated, &$result): void {
                $spaBooking->refresh();

                if (trim((string) $spaBooking->therapist_name) === '') {
                    $durationMinutes = max(
                        (int) ($spaBooking->duration_minutes ?? app(SpaServiceCatalog::class)->durationMinutesFor((string) $spaBooking->service_name)),
                        1,
                    );

                    $spaBooking->therapist_name = $this->resolveAutoTherapist(
                        (int) $spaBooking->user_id,
                        (string) $spaBooking->service_name,
                        $validated['booking_date'],
                        $validated['time_slot'],
                        $durationMinutes,
                        $this->therapistNames(),
                    );
                    $spaBooking->save();
                }

                $result = $this->reschedules->staffReschedule(
                    $spaBooking,
                    $validated['booking_date'],
                    $validated['time_slot'],
                );
            });
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()
                ->withErrors($e->errors(), 'reschedule')
                ->with('open_reschedule_booking_id', $spaBooking->id);
        } catch (QueryException) {
            $message = 'Unable to reschedule this appointment. Please try again.';

            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['time_slot' => $message]);
            }

            return back()
                ->withErrors(['time_slot' => $message], 'reschedule')
                ->with('open_reschedule_booking_id', $spaBooking->id);
        }

        $spaBooking->refresh();

        $result ??= [
            'booking_date' => $spaBooking->booking_date?->format('Y-m-d') ?? $validated['booking_date'],
            'time_slot' => (string) $spaBooking->time_slot,
            'date_display' => $spaBooking->booking_date?->format('M d, Y') ?? '',
        ];

        $clientName = (string) ($spaBooking->client_name ?: $spaBooking->user?->name ?: 'Client');
        $when = trim(
            ($spaBooking->booking_date?->format('M d, Y') ?? '').' '.(string) $spaBooking->time_slot
        );

        ActivityLogger::log(
            'appointment.rescheduled',
            sprintf(
                'Rescheduled %s for %s to %s.',
                (string) $spaBooking->service_name,
                $clientName,
                $when,
            ),
            [
                'booking_id' => $spaBooking->id,
                'client_name' => $clientName,
                'service_name' => $spaBooking->service_name,
                'therapist_name' => $spaBooking->therapist_name,
                'booking_date' => optional($spaBooking->booking_date)->toDateString(),
                'time_slot' => $spaBooking->time_slot,
            ],
            subject: $spaBooking,
            user: $staff,
            request: $request,
        );

        $message = 'Appointment for '.$clientName.' rescheduled to '
            .$result['date_display'].' at '.$result['time_slot'].'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'booking_id' => $spaBooking->id,
                'booking' => $result,
            ]);
        }

        return redirect()
            ->route('appointments.index', ['date' => $validated['booking_date']])
            ->with('status', $message);
    }

    public function cancel(Request $request, SpaBooking $spaBooking): JsonResponse|RedirectResponse
    {
        $staff = $this->ensureStaff($request);

        $spaBooking->loadMissing('user');
        $this->walkInClients->assertValidBookingClient($spaBooking);

        $validated = $request->validate([
            'cancellation_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->cancellations->staffCancel(
                $spaBooking,
                $validated['cancellation_note'] ?? null,
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()
                ->withErrors($e->errors(), 'cancel')
                ->with('open_cancel_booking_id', $spaBooking->id);
        } catch (QueryException) {
            $message = 'Unable to cancel this appointment. Please try again.';

            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['booking' => $message]);
            }

            return back()
                ->withErrors(['booking' => $message], 'cancel')
                ->with('open_cancel_booking_id', $spaBooking->id);
        }

        $spaBooking->refresh();

        $clientName = (string) ($spaBooking->client_name ?: $spaBooking->user?->name ?: 'Client');
        $when = trim(
            ($spaBooking->booking_date?->format('M d, Y') ?? '').' '.(string) $spaBooking->time_slot
        );

        ActivityLogger::log(
            'appointment.cancelled',
            sprintf(
                'Cancelled %s for %s (%s).',
                (string) $spaBooking->service_name,
                $clientName,
                $when,
            ),
            [
                'booking_id' => $spaBooking->id,
                'client_name' => $clientName,
                'service_name' => $spaBooking->service_name,
                'therapist_name' => $spaBooking->therapist_name,
                'booking_date' => optional($spaBooking->booking_date)->toDateString(),
                'time_slot' => $spaBooking->time_slot,
                'cancellation_reason' => $spaBooking->cancellation_reason,
            ],
            subject: $spaBooking,
            user: $staff,
            request: $request,
        );

        $message = 'Appointment for '.$clientName.' has been cancelled.';
        $refundNote = $this->refunds->customerMessage($spaBooking);
        if ($refundNote !== '') {
            $message .= ' '.$refundNote;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'booking_id' => $spaBooking->id,
                'refund_status' => $spaBooking->refund_status,
                'refund_status_label' => $this->refunds->labelFor($spaBooking->refund_status),
                'refund_amount' => (float) ($spaBooking->refund_amount ?? 0) > 0
                    ? '₱'.number_format((float) $spaBooking->refund_amount, 2)
                    : '',
                'refund_note' => (string) ($spaBooking->refund_note ?? ''),
                'can_complete_refund' => $this->refunds->canCompleteManualRefund($spaBooking),
            ]);
        }

        return redirect()
            ->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
            ->with('status', $message);
    }

    public function completeRefund(Request $request, SpaBooking $spaBooking): JsonResponse|RedirectResponse
    {
        $staff = $this->ensureStaff($request);

        $spaBooking->loadMissing('user');
        $this->walkInClients->assertValidBookingClient($spaBooking);

        $validated = $request->validate([
            'refund_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $spaBooking = $this->refunds->completeManualRefund(
                $spaBooking,
                $validated['refund_note'] ?? null,
            );
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            return back()->withErrors($e->errors(), 'refund');
        }

        $clientName = (string) ($spaBooking->client_name ?: $spaBooking->user?->name ?: 'Client');

        ActivityLogger::log(
            'refund.completed',
            sprintf(
                'Marked refund complete for %s (₱%s).',
                $clientName,
                number_format((float) $spaBooking->refund_amount, 2),
            ),
            [
                'booking_id' => $spaBooking->id,
                'client_name' => $clientName,
                'refund_amount' => (float) $spaBooking->refund_amount,
                'refund_reference' => $spaBooking->refund_reference,
            ],
            subject: $spaBooking,
            user: $staff,
            request: $request,
        );

        $message = 'Refund of ₱'.number_format((float) $spaBooking->refund_amount, 2).' marked complete for '.$clientName.'.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'booking_id' => $spaBooking->id,
                'refund_status' => $spaBooking->refund_status,
                'refund_status_label' => $this->refunds->labelFor($spaBooking->refund_status),
                'refund_amount' => '₱'.number_format((float) $spaBooking->refund_amount, 2),
                'refund_reference' => (string) ($spaBooking->refund_reference ?? ''),
                'refund_note' => (string) ($spaBooking->refund_note ?? ''),
            ]);
        }

        return redirect()
            ->route('appointments.index', ['date' => $spaBooking->booking_date?->format('Y-m-d')])
            ->with('status', $message);
    }

    /**
     * @param  array<int, string>  $therapistNames
     *
     * @throws ValidationException
     */
    private function resolveAutoTherapist(
        int $userId,
        string $serviceName,
        string $bookingDate,
        string $timeSlot,
        int $durationMinutes,
        array $therapistNames,
    ): string {
        $bookingWhen = Carbon::parse($bookingDate)->startOfDay();
        $bookableOnDate = $this->therapistAvailability->bookableTherapistNamesForDate($bookingWhen);
        $candidates = array_values(array_intersect($therapistNames, $bookableOnDate));

        foreach ($candidates as $name) {
            try {
                $this->slots->assertBookingAvailable(
                    $userId,
                    $serviceName,
                    $name,
                    $bookingDate,
                    $timeSlot,
                    $durationMinutes,
                    null,
                    $therapistNames,
                );

                return $name;
            } catch (ValidationException) {
                continue;
            }
        }

        throw ValidationException::withMessages([
            'time_slot' => 'No therapist is available at this time. Please choose another slot.',
        ]);
    }

    private function ensureStaff(Request $request): User
    {
        $user = $request->user();

        if ($user === null || (! $user->isAdmin() && ! $user->isReceptionist())) {
            abort(403);
        }

        return $user;
    }

    private function ensureCatalogSeeded(): void
    {
        app(TherapistCatalog::class)->ensureSeeded();
        app(SpaServiceCatalog::class)->ensureSeeded();

        if ($this->slots->tablesReady() && TimeSlot::query()->count() === 0) {
            $this->slots->seedDefaults();
        }
    }

    /**
     * @return array<int, string>
     */
    private function activeServiceNames(): array
    {
        return SpaService::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function therapistNames(): array
    {
        return Therapist::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    private function resolveClientUserIdForAvailability(?string $email, ?string $phone, ?string $clientName = null): ?int
    {
        $user = $this->walkInClients->findExisting($email, $phone, $clientName);

        return $user instanceof User ? (int) $user->id : null;
    }

    private function normalizeAvailabilityPhone(?string $phone): ?string
    {
        $phone = trim((string) ($phone ?? ''));

        return preg_match('/^09\d{9}$/', $phone) === 1 ? $phone : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyOffDutyAvailability(array $payload): array
    {
        if (($payload['therapist_schedule']['bookable'] ?? true) !== false) {
            return $payload;
        }

        $offered = array_values($payload['offered_slots'] ?? []);
        if ($offered === []) {
            return $payload;
        }

        $payload['booked_slots'] = array_values(array_unique(array_merge(
            (array) ($payload['booked_slots'] ?? []),
            $offered,
        )));

        return $payload;
    }

    /**
     * @return array<int, array{user_id: int|null, name: string, email: string, phone: string, birthday: string, sex: string}>
     */
    private function registeredClientsMatching(string $query): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        $needle = '%'.mb_strtolower(trim($query)).'%';
        $results = [];
        $seenEmails = [];

        $users = User::query()
            ->when(Schema::hasColumn('users', 'role'), fn ($builder) => $builder->where('role', User::ROLE_USER))
            ->registeredClient()
            ->whereRaw('LOWER(name) LIKE ?', [$needle])
            ->orderBy('name')
            ->limit(12)
            ->get();

        foreach ($users as $user) {
            $emailKey = strtolower(trim((string) $user->email));
            if ($emailKey === '' || isset($seenEmails[$emailKey])) {
                continue;
            }

            $seenEmails[$emailKey] = true;
            $results[] = [
                'user_id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => (string) ($user->contact_number ?? ''),
                'birthday' => $user->birthday?->format('Y-m-d') ?? '',
                'sex' => (string) ($user->sex ?? ''),
            ];
        }

        if (Schema::hasTable('customers')) {
            $customers = Customer::query()
                ->registered()
                ->whereRaw('LOWER(full_name) LIKE ?', [$needle])
                ->orderBy('full_name')
                ->limit(12)
                ->get();

            foreach ($customers as $customer) {
                $emailKey = strtolower(trim((string) $customer->email));
                if ($emailKey === '' || isset($seenEmails[$emailKey])) {
                    continue;
                }

                if ($customer->isWalkIn()) {
                    continue;
                }

                try {
                    $linkedUser = User::query()
                        ->whereRaw('LOWER(email) = ?', [$emailKey])
                        ->first();

                    if ($linkedUser instanceof User && $linkedUser->isWalkIn()) {
                        continue;
                    }

                    if (! ($linkedUser instanceof User)) {
                        $linkedUser = $this->walkInClients->ensureRegisteredUserForCustomer($customer);
                    }

                    $seenEmails[$emailKey] = true;
                    $results[] = [
                        'user_id' => (int) $linkedUser->id,
                        'name' => (string) $customer->full_name,
                        'email' => (string) $customer->email,
                        'phone' => (string) ($customer->number ?: ($linkedUser->contact_number ?? '')),
                        'birthday' => $customer->birthday?->format('Y-m-d') ?? ($linkedUser->birthday?->format('Y-m-d') ?? ''),
                        'sex' => (string) ($linkedUser->sex ?? ''),
                    ];
                } catch (ValidationException) {
                    continue;
                }
            }
        }

        usort($results, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return array_values(array_slice($results, 0, 10));
    }

    /**
     * @return array{
     *     has_completed_transaction: bool,
     *     total_visits: int,
     *     last_visit: ?string,
     *     last_visit_ago: ?string,
     *     top_service: ?string,
     *     top_therapist: ?string,
     *     most_frequent: array<int, array{name: string, count: int}>,
     *     recommended: array<int, string>
     * }
     */
    private function emptyClientSuggestionHistory(): array
    {
        return [
            'has_completed_transaction' => false,
            'total_visits' => 0,
            'last_visit' => null,
            'last_visit_ago' => null,
            'top_service' => null,
            'top_therapist' => null,
            'most_frequent' => [],
            'recommended' => [],
        ];
    }

    /**
     * @return array<int, array{name: string}>
     */
    private function catalogServicesForSuggestions(): array
    {
        if (! Schema::hasTable('spa_services')) {
            return [];
        }

        return SpaService::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (SpaService $service): array => ['name' => (string) $service->name])
            ->all();
    }

    /**
     * @return array<int, array{name: string}>
     */
    private function catalogTherapistsForSuggestions(): array
    {
        if (! Schema::hasTable('therapists')) {
            return [];
        }

        return Therapist::query()
            ->orderBy('name')
            ->get(['name'])
            ->map(fn (Therapist $therapist): array => ['name' => (string) $therapist->name])
            ->all();
    }
}
