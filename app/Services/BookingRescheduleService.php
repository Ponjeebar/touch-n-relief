<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\Therapist;
use App\Models\User;
use App\Support\PaymentMethodCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingRescheduleService
{
    public function __construct(
        private readonly BookingCancellationService $cancellations,
        private readonly BookingSlotService $slots,
        private readonly SpaServiceCatalog $catalog,
    ) {}

    public function canReschedule(SpaBooking $booking): bool
    {
        if ($booking->payment_status !== PaymentMethodCatalog::STATUS_PAID || $booking->initialPaidAmount() <= 0) {
            return false;
        }

        return $this->cancellations->canCancel($booking);
    }

    /**
     * Staff may reschedule any confirmed booking that has not started, including walk-ins.
     */
    public function canStaffReschedule(SpaBooking $booking): bool
    {
        if ($booking->cancelled_at !== null || in_array($booking->session_status, [SpaBooking::STATUS_CANCELLED, SpaBooking::STATUS_NO_SHOW], true)) {
            return false;
        }

        if ($booking->completed_at !== null || $booking->session_status === SpaBooking::STATUS_COMPLETED) {
            return false;
        }

        if ($booking->session_started_at !== null || $booking->session_status === SpaBooking::STATUS_IN_SESSION) {
            return false;
        }

        return (int) $booking->user_id > 0
            && User::query()->whereKey($booking->user_id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function staffReschedule(SpaBooking $booking, string $bookingDate, string $timeSlot): array
    {
        if (! $this->canStaffReschedule($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'This booking can no longer be rescheduled.',
            ]);
        }

        return $this->performReschedule($booking, $bookingDate, $timeSlot, forStaff: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function reschedule(SpaBooking $booking, string $bookingDate, string $timeSlot, ?string $therapistName = null): array
    {
        if (! $this->canReschedule($booking)) {
            throw ValidationException::withMessages([
                'booking' => 'This booking can no longer be rescheduled.',
            ]);
        }

        return $this->performReschedule($booking, $bookingDate, $timeSlot, forStaff: false, therapistName: $therapistName);
    }

    /**
     * @return array<string, mixed>
     */
    private function performReschedule(SpaBooking $booking, string $bookingDate, string $timeSlot, bool $forStaff, ?string $therapistName = null): array
    {
        $retainTherapist = $therapistName === null;
        $therapistName = trim($therapistName ?? (string) $booking->therapist_name);
        $normalizedSlot = $this->slots->normalizeSlotLabel($timeSlot);
        if ($normalizedSlot === null) {
            throw ValidationException::withMessages([
                'time_slot' => 'Please choose a valid time slot.',
            ]);
        }

        $currentDate = $booking->booking_date->format('Y-m-d');
        $currentSlot = $this->slots->normalizeSlotLabel((string) $booking->time_slot)
            ?? trim((string) $booking->time_slot);

        if ($bookingDate === $currentDate && $normalizedSlot === $currentSlot && $therapistName === $booking->therapist_name) {
            throw ValidationException::withMessages([
                'time_slot' => 'Choose a different therapist, date, or time than your current appointment.',
            ]);
        }

        $this->assertSlotAvailable($booking, $bookingDate, $normalizedSlot, therapistName: $therapistName, forStaff: $forStaff);

        DB::transaction(function () use ($booking, $bookingDate, $normalizedSlot, $forStaff, $therapistName, $retainTherapist): void {
            User::query()->whereKey($booking->user_id)->lockForUpdate()->firstOrFail();
            $current = SpaBooking::query()->findOrFail($booking->id);
            if ($retainTherapist) {
                $therapistName = trim((string) $current->therapist_name);
            }
            // A stable order also serializes customers swapping therapists.
            Therapist::query()->whereIn('name', [(string) $current->therapist_name, $therapistName])
                ->orderBy('id')->lockForUpdate()->get();
            $this->slots->lockTherapistBookingsForUpdate($therapistName, $bookingDate);
            $locked = SpaBooking::query()->lockForUpdate()->findOrFail($booking->id);

            $canProceed = $forStaff ? $this->canStaffReschedule($locked) : $this->canReschedule($locked);
            if (! $canProceed) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking can no longer be rescheduled.',
                ]);
            }

            $lockedDate = $locked->booking_date->format('Y-m-d');
            $lockedSlot = $this->slots->normalizeSlotLabel((string) $locked->time_slot) ?? trim((string) $locked->time_slot);
            if ($bookingDate === $lockedDate && $normalizedSlot === $lockedSlot && $therapistName === $locked->therapist_name) {
                throw ValidationException::withMessages(['time_slot' => 'Choose a different therapist, date, or time than your current appointment.']);
            }
            $this->assertSlotAvailable($locked, $bookingDate, $normalizedSlot, withTherapistLock: true, therapistName: $therapistName, forStaff: $forStaff);

            $locked->forceFill(array_merge(
                $this->rescheduleAttributes($locked, $bookingDate, $normalizedSlot),
                ['session_status' => SpaBooking::STATUS_CONFIRMED, 'therapist_name' => $therapistName],
            ))->save();
        });

        $booking->refresh();

        if ($forStaff) {
            app(CustomerNotificationService::class)->notifyStaffRescheduled($booking);
        }

        return $this->resultPayload($booking);
    }

    /**
     * @return array<string, mixed>
     */
    private function rescheduleAttributes(SpaBooking $booking, string $bookingDate, string $timeSlot): array
    {
        return [
            'cancellation_deadline_at' => $this->cancellations->cancellationDeadline($booking),
            'rescheduled_from_therapist_name' => $booking->therapist_name,
            'rescheduled_from_date' => $booking->booking_date->format('Y-m-d'),
            'rescheduled_from_time_slot' => (string) $booking->time_slot,
            'rescheduled_at' => now(),
            'booking_date' => $bookingDate,
            'time_slot' => $timeSlot,
            'no_show_reversed_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultPayload(SpaBooking $booking): array
    {
        $date = $booking->booking_date->format('Y-m-d');
        $sessions = app(SpaSessionService::class);

        return [
            'therapist_name' => (string) $booking->therapist_name,
            'previous_therapist' => $booking->rescheduled_from_therapist_name,
            'previous_date' => $booking->rescheduled_from_date?->format('M d, Y'),
            'previous_time' => $booking->rescheduled_from_time_slot,
            'can_cancel' => $this->cancellations->canCancel($booking),
            'can_reschedule' => $this->canReschedule($booking),
            'booking_date' => $date,
            'time_slot' => (string) $booking->time_slot,
            'date_display' => $booking->booking_date->format('M d, Y'),
            'sort_ts' => $sessions->sortTimestamp($date, (string) $booking->time_slot),
            'activity_ts' => $sessions->activityTimestamp($booking),
        ];
    }

    public function assertSlotAvailable(
        SpaBooking $booking,
        string $bookingDate,
        string $timeSlot,
        bool $withTherapistLock = false,
        ?string $serviceName = null,
        ?string $therapistName = null,
        bool $forStaff = false,
    ): void {
        $serviceName = $serviceName ?? (string) $booking->service_name;
        $therapistName = $therapistName ?? trim((string) $booking->therapist_name);
        $durationMinutes = $this->slots->durationMinutesForService(
            $serviceName,
            $booking->duration_minutes !== null ? (int) $booking->duration_minutes : null,
        );
        $excludeId = (int) $booking->id;
        $normalizedSlot = $this->slots->normalizeSlotLabelPublic($timeSlot) ?? $timeSlot;

        if ($bookingDate < now()->toDateString()) {
            throw ValidationException::withMessages([
                'booking_date' => 'Please choose today or a future date.',
            ]);
        }

        try {
            $appointment = Carbon::parse($bookingDate.' '.$normalizedSlot);
            if ($appointment->lte(now())) {
                throw ValidationException::withMessages([
                    'time_slot' => 'Please choose a future time slot.',
                ]);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'time_slot' => 'Please choose a valid time slot.',
            ]);
        }

        if ($withTherapistLock && $therapistName !== '') {
            $this->slots->lockTherapistBookingsForUpdate($therapistName, $bookingDate);
        }

        if ((int) $booking->user_id <= 0) {
            throw ValidationException::withMessages([
                'time_slot' => 'This appointment is missing a linked client account.',
            ]);
        }

        try {
            $this->slots->assertBookingAvailable(
                (int) $booking->user_id,
                $serviceName,
                $therapistName,
                $bookingDate,
                $normalizedSlot,
                $durationMinutes,
                $excludeId,
                app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(Carbon::parse($bookingDate)),
                minimumLeadMinutes: $forStaff ? 0 : app(CustomerBookingPolicy::class)->minimumLeadMinutes(),
            );
        } catch (ValidationException $e) {
            $messages = $e->errors();
            if (isset($messages['time_slot'][0]) && str_contains((string) $messages['time_slot'][0], 'your existing')) {
                throw ValidationException::withMessages([
                    'time_slot' => $forStaff
                        ? 'This time overlaps with another appointment this client already has.'
                        : 'This time overlaps with another appointment you already have.',
                ]);
            }

            if (isset($messages['time_slot'][0]) && str_contains((string) $messages['time_slot'][0], 'That therapist')) {
                throw ValidationException::withMessages([
                    'time_slot' => $forStaff
                        ? 'The assigned therapist is not available at this time. The selected slot overlaps with an existing appointment.'
                        : 'Your therapist is not available at this time. The selected slot overlaps with an existing appointment.',
                ]);
            }

            throw $e;
        }
    }

    /**
     * Live availability payload for staff rescheduling a specific booking.
     *
     * @return array<string, mixed>
     */
    public function staffRescheduleAvailability(SpaBooking $booking, string $bookingDate, ?string $selectedTherapist = null, bool $forStaff = true): array
    {
        $booking->loadMissing('user');

        if ((int) $booking->user_id <= 0 || ! User::query()->whereKey($booking->user_id)->exists()) {
            throw ValidationException::withMessages([
                'booking' => 'The client account for this appointment could not be found.',
            ]);
        }

        $serviceName = (string) $booking->service_name;
        $therapistName = trim($selectedTherapist ?? (string) $booking->therapist_name);
        $durationMinutes = $this->slots->durationMinutesForService(
            $serviceName,
            $booking->duration_minutes !== null ? (int) $booking->duration_minutes : null,
        );
        $bookableTherapists = $this->slots->bookableTherapistNames();

        if ($therapistName === '' || ! in_array($therapistName, $bookableTherapists, true)) {
            if (! $forStaff) {
                throw ValidationException::withMessages(['therapist' => 'Choose an active therapist for this appointment.']);
            }
            $payload = $this->slots->landingAvailability(
                $serviceName,
                $bookingDate,
                (int) $booking->user_id,
                app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(Carbon::parse($bookingDate)),
                $durationMinutes,
                (int) $booking->id,
            );
            $payload['all_slots'] = $this->slots->allSlotLabels();
            $payload['booked_slots'] = [];
            $payload['therapist_busy_details'] = [];
            $payload['therapist_schedule'] = [
                'bookable' => true,
                'status' => 'available',
                'label' => 'Auto assign therapist',
            ];
            $payload['therapists'] = app(TherapistAvailabilityService::class)
                ->bookingAvailabilityMapForDate(Carbon::parse($bookingDate));

            return $payload;
        }

        $payload = $this->slots->availability(
            $serviceName,
            $therapistName,
            $bookingDate,
            (int) $booking->user_id,
            app(TherapistAvailabilityService::class)->bookableTherapistNamesForDate(Carbon::parse($bookingDate)),
            $durationMinutes,
            (int) $booking->id,
            minimumLeadMinutes: $forStaff ? 0 : app(CustomerBookingPolicy::class)->minimumLeadMinutes(),
        );

        $selectedMeta = app(TherapistAvailabilityService::class)
            ->bookingAvailabilityMapForDate(Carbon::parse($bookingDate))[$therapistName] ?? null;

        $payload['therapist_schedule'] = [
            'bookable' => (bool) ($selectedMeta['bookable'] ?? true),
            'status' => (string) ($selectedMeta['status'] ?? 'available'),
            'label' => $selectedMeta['label'] ?? null,
        ];
        $payload = $this->applyOffDutyAvailability($payload);
        $payload['therapists'] = app(TherapistAvailabilityService::class)
            ->bookingAvailabilityMapForDate(Carbon::parse($bookingDate));

        return $payload;
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
}
