<?php

namespace App\Services;

use App\Models\SpaBooking;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingSlotService
{
    private ?SpaServiceCatalog $catalog = null;

    private bool $sessionsSynced = false;

    private function catalog(): SpaServiceCatalog
    {
        return $this->catalog ??= app(SpaServiceCatalog::class);
    }

    /**
     * Mark finished sessions as completed so they stop blocking availability.
     */
    public function syncExpiredSessions(): void
    {
        if ($this->sessionsSynced) {
            return;
        }

        $this->sessionsSynced = true;
        app(SpaSessionService::class)->releaseAvailabilityBlocks();
    }

    public function durationMinutesForService(string $serviceName, ?int $storedMinutes = null): int
    {
        if ($storedMinutes !== null && $storedMinutes > 0) {
            return $storedMinutes;
        }

        return $this->catalog()->durationMinutesFor($serviceName);
    }

    public function resolvedBookingDurationMinutes(SpaBooking $booking): int
    {
        return $this->durationMinutesForService(
            (string) $booking->service_name,
            $booking->duration_minutes !== null ? (int) $booking->duration_minutes : null,
        );
    }

    public function normalizeSlotLabelPublic(string $label): ?string
    {
        return $this->normalizeSlotLabel($label);
    }

    /**
     * Lock active therapist bookings for the date (call inside a DB transaction before re-checking availability).
     */
    public function lockTherapistBookingsForUpdate(string $therapistName, string $bookingDate): void
    {
        $therapistName = trim($therapistName);
        if ($therapistName === '') {
            return;
        }

        $this->syncExpiredSessions();

        // Lock a stable row even when the therapist has no bookings yet.
        \App\Models\Therapist::query()
            ->where('name', $therapistName)
            ->lockForUpdate()
            ->first();

        SpaBooking::query()
            ->blocksAvailability()
            ->where('therapist_name', $therapistName)
            ->whereDate('booking_date', $bookingDate)
            ->lockForUpdate()
            ->get(['id', 'time_slot', 'duration_minutes', 'service_name']);
    }

    /**
     * @param  array<int, string>  $therapistNames
     *
     * @throws ValidationException
     */
    public function assertBookingAvailable(
        int $userId,
        string $serviceName,
        string $therapistName,
        string $bookingDate,
        string $timeSlot,
        int $durationMinutes,
        ?int $excludeBookingId = null,
        array $therapistNames = [],
        bool $withTherapistLock = false,
    ): void {
        $this->syncExpiredSessions();

        $normalizedSlot = $this->normalizeSlotLabel($timeSlot);
        if ($normalizedSlot === null) {
            throw ValidationException::withMessages([
                'time_slot' => 'Please choose a valid time slot.',
            ]);
        }

        $durationMinutes = max($durationMinutes, 1);

        if (! $this->isSlotOfferedForService($serviceName, $normalizedSlot, $bookingDate)) {
            throw ValidationException::withMessages([
                'time_slot' => 'This time is not offered for the selected service on that date.',
            ]);
        }

        if ($this->isStoreClosedOn($bookingDate)) {
            throw ValidationException::withMessages([
                'booking_date' => 'The spa is closed on the selected date. Please choose another day.',
            ]);
        }

        app(TherapistAvailabilityService::class)->assertBookableOnDate($therapistName, $bookingDate);

        if ($withTherapistLock) {
            $this->lockTherapistBookingsForUpdate($therapistName, $bookingDate);
        }

        $userConflict = $this->userConflictAt($userId, $bookingDate, $normalizedSlot, $durationMinutes, $excludeBookingId);
        if ($userConflict !== null) {
            throw ValidationException::withMessages([
                'time_slot' => 'This time overlaps with one of your existing appointments.',
            ]);
        }

        $offeredSlots = $this->offeredSlotLabelsForServiceOnDate($serviceName, $bookingDate);
        $fullyBooked = $this->fullyBookedSlotsForDate(
            $bookingDate,
            $therapistNames,
            $offeredSlots,
            $excludeBookingId,
            $durationMinutes,
        );

        if (in_array($normalizedSlot, $fullyBooked, true)) {
            throw ValidationException::withMessages([
                'time_slot' => 'All therapists are booked at this time. Please choose another slot.',
            ]);
        }

        if ($this->therapistSlotTaken($therapistName, $bookingDate, $normalizedSlot, $durationMinutes, $excludeBookingId)) {
            throw ValidationException::withMessages([
                'time_slot' => 'That therapist is not available at this time. The selected slot overlaps with an existing appointment. Please choose another slot or therapist.',
            ]);
        }
    }
    /**
     * @return array<int, string>
     */
    public function allSlotLabels(): array
    {
        if ($this->tablesReady()) {
            $labels = TimeSlot::query()
                ->active()
                ->orderBy('sort_order')
                ->pluck('label')
                ->all();

            if ($labels !== []) {
                return $labels;
            }
        }

        return $this->fallbackAllSlotLabels();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function slotMapByService(): array
    {
        if ($this->tablesReady()) {
            $map = [];

            $rows = DB::table('service_time_slots')
                ->join('time_slots', 'time_slots.id', '=', 'service_time_slots.time_slot_id')
                ->where('time_slots.is_active', true)
                ->orderBy('time_slots.sort_order')
                ->select(['service_time_slots.service_name', 'time_slots.label'])
                ->get();

            foreach ($rows as $row) {
                $map[$row->service_name][] = $row->label;
            }

            if ($map !== []) {
                return $map;
            }
        }

        return $this->fallbackSlotMapByService();
    }

    /**
     * @return array<int, string>
     */
    public function bookableTherapistNames(): array
    {
        if (! Schema::hasTable('therapists')) {
            return [];
        }

        $query = \App\Models\Therapist::query();

        if (Schema::hasColumn('therapists', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query
            ->orderBy(Schema::hasColumn('therapists', 'sort_order') ? 'sort_order' : 'therapist_code')
            ->pluck('name')
            ->map(fn (mixed $name): string => trim((string) $name))
            ->filter(fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /**
     * Booked slots: [therapistName => [ 'Y-m-d' => ['8:30 AM', ...] ]]
     *
     * @return array<string, array<string, array<int, string>>>
     */
    public function bookedSlotsByTherapistAndDate(?int $excludeBookingId = null): array
    {
        $map = [];

        $query = SpaBooking::query()->blocksAvailability()->select(['id', 'therapist_name', 'booking_date', 'time_slot']);
        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        foreach ($query->get() as $row) {
            $therapist = trim((string) $row->therapist_name);
            if ($therapist === '') {
                continue;
            }

            $dateKey = $row->booking_date->format('Y-m-d');
            $label = $this->normalizeSlotLabel((string) $row->time_slot);
            if ($label === null) {
                continue;
            }

            $map[$therapist][$dateKey][] = $label;
        }

        return $map;
    }

    /**
     * Active bookings for a user: [ 'Y-m-d' => [ '10:00 AM' => ['service' => ..., 'therapist' => ...], ... ] ]
     *
     * @return array<string, array<string, array{service: string, therapist: string}>>
     */
    public function userBookingsByDate(int $userId): array
    {
        $map = [];

        foreach (
            SpaBooking::query()
                ->blocksAvailability()
                ->where('user_id', $userId)
                ->select(['booking_date', 'time_slot', 'service_name', 'therapist_name'])
                ->get() as $row
        ) {
            $dateKey = $row->booking_date->format('Y-m-d');
            $label = $this->normalizeSlotLabel((string) $row->time_slot);
            if ($label === null) {
                continue;
            }

            $map[$dateKey][$label] = [
                'service' => (string) $row->service_name,
                'therapist' => (string) $row->therapist_name,
            ];
        }

        return $map;
    }

    /**
     * @return array{service: string, therapist: string}|null
     */
    public function userConflictAt(int $userId, string $bookingDate, string $timeSlot, ?int $durationMinutes = null, ?int $excludeBookingId = null): ?array
    {
        $proposedWindow = $this->slotWindow($bookingDate, $timeSlot, max($durationMinutes ?? 60, 1));
        if ($proposedWindow === null) {
            return null;
        }

        foreach ($this->blockingBookingsForUserOnDate($userId, $bookingDate, $excludeBookingId) as $booking) {
            $existingWindow = $this->slotWindow(
                $bookingDate,
                (string) $booking->time_slot,
                $this->resolvedBookingDurationMinutes($booking),
            );

            if ($existingWindow !== null && $this->windowsOverlap($proposedWindow, $existingWindow)) {
                return [
                    'service' => (string) $booking->service_name,
                    'therapist' => (string) $booking->therapist_name,
                ];
            }
        }

        return null;
    }

    /**
     * Slots on a date that overlap the user's existing bookings for a proposed service duration.
     *
     * @param  array<int, string>  $offeredSlots
     * @return array<string, array{service: string, therapist: string}>
     */
    public function userConflictsForDate(
        int $userId,
        string $bookingDate,
        array $offeredSlots,
        int $proposedDurationMinutes,
        ?int $excludeBookingId = null,
    ): array {
        $conflicts = [];
        $existing = $this->blockingBookingsForUserOnDate($userId, $bookingDate, $excludeBookingId);

        if ($existing->isEmpty()) {
            return [];
        }

        foreach ($offeredSlots as $slot) {
            $slotLabel = $this->normalizeSlotLabel((string) $slot);
            if ($slotLabel === null) {
                continue;
            }

            $proposedWindow = $this->slotWindow($bookingDate, $slotLabel, $proposedDurationMinutes);
            if ($proposedWindow === null) {
                continue;
            }

            foreach ($existing as $booking) {
                $existingWindow = $this->slotWindow(
                    $bookingDate,
                    (string) $booking->time_slot,
                    $this->resolvedBookingDurationMinutes($booking),
                );

                if ($existingWindow !== null && $this->windowsOverlap($proposedWindow, $existingWindow)) {
                    $conflicts[$slotLabel] = [
                        'service' => (string) $booking->service_name,
                        'therapist' => (string) $booking->therapist_name,
                    ];
                    break;
                }
            }
        }

        return $conflicts;
    }

    /**
     * @return Collection<int, SpaBooking>
     */
    private function blockingBookingsForUserOnDate(int $userId, string $bookingDate, ?int $excludeBookingId = null): Collection
    {
        $this->syncExpiredSessions();

        return SpaBooking::query()
            ->blocksAvailability()
            ->where('user_id', $userId)
            ->whereDate('booking_date', $bookingDate)
            ->when($excludeBookingId !== null, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->get(['id', 'time_slot', 'service_name', 'therapist_name', 'duration_minutes']);
    }

    /**
     * @return Collection<int, SpaBooking>
     */
    private function blockingBookingsForTherapistOnDate(string $therapistName, string $bookingDate, ?int $excludeBookingId = null): Collection
    {
        $therapistName = trim($therapistName);
        if ($therapistName === '') {
            return collect();
        }

        $this->syncExpiredSessions();

        return SpaBooking::query()
            ->blocksAvailability()
            ->where('therapist_name', $therapistName)
            ->whereDate('booking_date', $bookingDate)
            ->when($excludeBookingId !== null, fn ($query) => $query->where('id', '!=', $excludeBookingId))
            ->get(['id', 'time_slot', 'duration_minutes', 'service_name']);
    }

    public function therapistSlotTaken(
        string $therapistName,
        string $bookingDate,
        string $timeSlot,
        ?int $durationMinutes = null,
        ?int $excludeBookingId = null,
    ): bool {
        $label = $this->normalizeSlotLabel($timeSlot);
        if ($label === null) {
            return false;
        }

        $proposedWindow = $this->slotWindow($bookingDate, $label, $durationMinutes ?? 60);
        if ($proposedWindow === null) {
            return false;
        }

        foreach ($this->blockingBookingsForTherapistOnDate($therapistName, $bookingDate, $excludeBookingId) as $booking) {
            $existingWindow = $this->slotWindow(
                $bookingDate,
                (string) $booking->time_slot,
                $this->resolvedBookingDurationMinutes($booking),
            );

            if ($existingWindow !== null && $this->windowsOverlap($proposedWindow, $existingWindow)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Offered slots that overlap an existing booking for the therapist (given proposed service duration).
     *
     * @param  array<int, string>  $offeredSlots
     * @return array<int, string>
     */
    public function therapistBusySlotsForDate(
        string $therapistName,
        string $bookingDate,
        array $offeredSlots,
        int $proposedDurationMinutes,
        ?int $excludeBookingId = null,
    ): array {
        $therapistName = trim($therapistName);
        if ($therapistName === '') {
            return [];
        }

        $existing = $this->blockingBookingsForTherapistOnDate($therapistName, $bookingDate, $excludeBookingId);
        if ($existing->isEmpty()) {
            return [];
        }

        $busy = [];

        foreach ($offeredSlots as $slot) {
            $slotLabel = $this->normalizeSlotLabel((string) $slot);
            if ($slotLabel === null) {
                continue;
            }

            $proposedWindow = $this->slotWindow($bookingDate, $slotLabel, $proposedDurationMinutes);
            if ($proposedWindow === null) {
                continue;
            }

            foreach ($existing as $booking) {
                $existingWindow = $this->slotWindow(
                    $bookingDate,
                    (string) $booking->time_slot,
                    $this->resolvedBookingDurationMinutes($booking),
                );

                if ($existingWindow !== null && $this->windowsOverlap($proposedWindow, $existingWindow)) {
                    $busy[] = $slotLabel;
                    break;
                }
            }
        }

        return array_values(array_unique($busy));
    }

    /**
     * Why each offered slot is unavailable for the therapist (duration-aware overlap).
     *
     * @param  array<int, string>  $offeredSlots
     * @return array<string, array{service: string, time_slot: string, message: string}>
     */
    public function therapistBusyDetailsForDate(
        string $therapistName,
        string $bookingDate,
        array $offeredSlots,
        int $proposedDurationMinutes,
        ?int $excludeBookingId = null,
    ): array {
        $details = [];
        $therapistName = trim($therapistName);
        if ($therapistName === '') {
            return [];
        }

        $existing = $this->blockingBookingsForTherapistOnDate($therapistName, $bookingDate, $excludeBookingId);
        if ($existing->isEmpty()) {
            return [];
        }

        foreach ($offeredSlots as $slot) {
            $slotLabel = $this->normalizeSlotLabel((string) $slot);
            if ($slotLabel === null) {
                continue;
            }

            $proposedWindow = $this->slotWindow($bookingDate, $slotLabel, $proposedDurationMinutes);
            if ($proposedWindow === null) {
                continue;
            }

            foreach ($existing as $booking) {
                $existingWindow = $this->slotWindow(
                    $bookingDate,
                    (string) $booking->time_slot,
                    $this->resolvedBookingDurationMinutes($booking),
                );

                if ($existingWindow !== null && $this->windowsOverlap($proposedWindow, $existingWindow)) {
                    $existingSlot = (string) $booking->time_slot;
                    $existingService = (string) $booking->service_name;
                    $existingDuration = $this->resolvedBookingDurationMinutes($booking);
                    $details[$slotLabel] = [
                        'service' => $existingService,
                        'time_slot' => $existingSlot,
                        'message' => sprintf(
                            'Overlaps with %s at %s (%d min). Your service needs %d min.',
                            $existingService,
                            $existingSlot,
                            $existingDuration,
                            $proposedDurationMinutes,
                        ),
                    ];
                    break;
                }
            }
        }

        return $details;
    }

    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    public function slotWindow(string $dateYmd, string $timeSlot, int $durationMinutes): ?array
    {
        $label = $this->normalizeSlotLabel($timeSlot);
        if ($label === null) {
            return null;
        }

        try {
            $start = Carbon::parse($dateYmd.' '.$label);
        } catch (\Throwable) {
            return null;
        }

        $durationMinutes = max($durationMinutes, 1);

        return [
            'start' => $start,
            'end' => $start->copy()->addMinutes($durationMinutes),
        ];
    }

    /**
     * @param  array{start: Carbon, end: Carbon}  $first
     * @param  array{start: Carbon, end: Carbon}  $second
     */
    private function windowsOverlap(array $first, array $second): bool
    {
        return $first['start']->lt($second['end']) && $second['start']->lt($first['end']);
    }

    /**
     * Slots where every therapist in the list is already booked on the given date.
     *
     * @param  array<int, string>  $therapistNames
     * @param  array<int, string>|null  $offeredSlots
     * @return array<int, string>
     */
    public function fullyBookedSlotsForDate(
        string $bookingDate,
        array $therapistNames,
        ?array $offeredSlots = null,
        ?int $excludeBookingId = null,
        int $proposedDurationMinutes = 60,
    ): array {
        $therapistNames = array_values(array_filter(array_map(
            fn (mixed $name): string => trim((string) $name),
            $therapistNames !== [] ? $therapistNames : $this->bookableTherapistNames(),
        ), fn (string $name): bool => $name !== ''));

        if ($therapistNames === []) {
            return [];
        }

        $slots = $offeredSlots ?? $this->allSlotLabels();
        $fullyBooked = [];

        foreach ($slots as $slot) {
            $slotLabel = $this->normalizeSlotLabel((string) $slot);
            if ($slotLabel === null) {
                continue;
            }

            $allBusy = true;

            foreach ($therapistNames as $therapist) {
                if (! $this->therapistSlotTaken($therapist, $bookingDate, $slotLabel, $proposedDurationMinutes, $excludeBookingId)) {
                    $allBusy = false;
                    break;
                }
            }

            if ($allBusy) {
                $fullyBooked[] = $slotLabel;
            }
        }

        return $fullyBooked;
    }

    /**
     * @return array{
     *     offered_slots: array<int, string>,
     *     fully_booked_slots: array<int, string>,
     *     user_conflicts: array<string, array{service: string, therapist: string}>,
     *     store_closed: bool,
     *     server_now: string
     * }
     */
    public function landingAvailability(
        string $serviceName,
        string $bookingDate,
        ?int $userId = null,
        array $therapistNames = [],
        int $proposedDurationMinutes = 60,
        ?int $excludeBookingId = null,
    ): array {
        $this->syncExpiredSessions();

        $offered = $this->offeredSlotLabelsForServiceOnDate($serviceName, $bookingDate);
        $userConflicts = $userId !== null
            ? $this->userConflictsForDate($userId, $bookingDate, $offered, $proposedDurationMinutes, $excludeBookingId)
            : [];
        $fullyBooked = $this->fullyBookedSlotsForDate($bookingDate, $therapistNames, $offered, $excludeBookingId, $proposedDurationMinutes);

        return [
            'offered_slots' => array_values($offered),
            'fully_booked_slots' => array_values($fullyBooked),
            'user_conflicts' => $userConflicts,
            'store_closed' => $this->isStoreClosedOn($bookingDate),
            'server_now' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     all_slots: array<int, string>,
     *     offered_slots: array<int, string>,
     *     booked_slots: array<int, string>,
     *     user_conflicts: array<string, array{service: string, therapist: string}>,
     *     fully_booked_slots: array<int, string>,
     *     store_closed: bool,
     *     server_now: string
     * }
     */
    public function availability(
        string $serviceName,
        string $therapistName,
        string $bookingDate,
        ?int $userId = null,
        array $therapistNames = [],
        int $proposedDurationMinutes = 60,
        ?int $excludeBookingId = null,
    ): array {
        $this->syncExpiredSessions();

        $offered = $this->offeredSlotLabelsForServiceOnDate($serviceName, $bookingDate);
        $therapistName = trim($therapistName);
        $booked = $this->therapistBusySlotsForDate(
            $therapistName,
            $bookingDate,
            $offered,
            $proposedDurationMinutes,
            $excludeBookingId,
        );
        $therapistBusyDetails = $this->therapistBusyDetailsForDate(
            $therapistName,
            $bookingDate,
            $offered,
            $proposedDurationMinutes,
            $excludeBookingId,
        );
        $userConflicts = $userId !== null
            ? $this->userConflictsForDate($userId, $bookingDate, $offered, $proposedDurationMinutes, $excludeBookingId)
            : [];
        $fullyBooked = $this->fullyBookedSlotsForDate($bookingDate, $therapistNames, $offered, $excludeBookingId, $proposedDurationMinutes);

        return [
            'all_slots' => $this->allSlotLabels(),
            'offered_slots' => array_values($offered),
            'booked_slots' => array_values($booked),
            'therapist_busy_details' => $therapistBusyDetails,
            'user_conflicts' => $userConflicts,
            'fully_booked_slots' => array_values($fullyBooked),
            'store_closed' => $this->isStoreClosedOn($bookingDate),
            'server_now' => now()->toIso8601String(),
        ];
    }

    public function isSlotOfferedForService(string $serviceName, string $timeSlot, ?string $dateYmd = null): bool
    {
        if ($dateYmd !== null) {
            return in_array($timeSlot, $this->offeredSlotLabelsForServiceOnDate($serviceName, $dateYmd), true);
        }

        $offered = $this->slotMapByService()[$serviceName] ?? [];

        return in_array($timeSlot, $offered, true);
    }

    public function isStoreClosedOn(string $dateYmd): bool
    {
        if (! Schema::hasTable('store_closures')) {
            return false;
        }

        return DB::table('store_closures')->whereDate('closure_date', $dateYmd)->exists();
    }

    /**
     * @return array<int, string>
     */
    public function offeredSlotLabelsForServiceOnDate(string $serviceName, string $dateYmd): array
    {
        if ($this->isStoreClosedOn($dateYmd)) {
            return [];
        }

        if (! $this->tablesReady()) {
            return $this->slotMapByService()[$serviceName] ?? [];
        }

        $allSlots = TimeSlot::query()
            ->active()
            ->orderBy('sort_order')
            ->get();

        $weeklyIds = array_flip($this->weeklyEnabledSlotIdsForService($serviceName));
        $overrides = Schema::hasTable('service_slot_date_overrides')
            ? DB::table('service_slot_date_overrides')
                ->where('service_name', $serviceName)
                ->whereDate('slot_date', $dateYmd)
                ->pluck('is_enabled', 'time_slot_id')
            : collect();

        $enabled = [];

        foreach ($allSlots as $slot) {
            $weeklyOn = isset($weeklyIds[$slot->id]);
            $effective = $overrides->has($slot->id)
                ? (bool) $overrides->get($slot->id)
                : $weeklyOn;

            if ($effective) {
                $enabled[] = $slot->label;
            }
        }

        return $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSlotEditorPayload(string $serviceName, string $dateYmd, string $mode): array
    {
        if ($this->tablesReady() && TimeSlot::query()->count() === 0) {
            $this->seedDefaults();
        }

        $windowLabels = array_flip($this->bookableWindowSlotLabels());
        $visibleCustomIds = $this->visibleCustomSlotIdsForService($serviceName, $dateYmd, $mode);

        $allSlots = TimeSlot::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(function (TimeSlot $slot) use ($windowLabels, $visibleCustomIds): bool {
                if ($slot->is_custom) {
                    return isset($visibleCustomIds[$slot->id]);
                }

                return isset($windowLabels[$slot->label]);
            });

        $weeklyIds = array_flip($this->weeklyEnabledSlotIdsForService($serviceName));
        $overrides = Schema::hasTable('service_slot_date_overrides')
            ? DB::table('service_slot_date_overrides')
                ->where('service_name', $serviceName)
                ->whereDate('slot_date', $dateYmd)
                ->pluck('is_enabled', 'time_slot_id')
            : collect();

        $storeClosed = $this->isStoreClosedOn($dateYmd);

        $slots = $allSlots->map(function (TimeSlot $slot) use ($mode, $weeklyIds, $overrides, $storeClosed): array {
            $weeklyOn = isset($weeklyIds[$slot->id]);

            if ($mode === 'weekly') {
                $enabled = $weeklyOn;
            } else {
                $enabled = $storeClosed
                    ? false
                    : ($overrides->has($slot->id) ? (bool) $overrides->get($slot->id) : $weeklyOn);
            }

            return [
                'id' => (int) $slot->id,
                'label' => (string) $slot->label,
                'enabled' => $enabled,
                'weekly_enabled' => $weeklyOn,
                'is_custom' => (bool) $slot->is_custom,
            ];
        })->values()->all();

        return [
            'service_name' => $serviceName,
            'selected_date' => $dateYmd,
            'mode' => $mode,
            'store_closed' => $storeClosed,
            'slots' => $slots,
            'enabled_count' => count(array_filter($slots, fn (array $slot): bool => $slot['enabled'])),
        ];
    }

    /**
     * @param  array<int, int>  $enabledSlotIds
     */
    public function syncWeeklySlotsForService(string $serviceName, array $enabledSlotIds): void
    {
        if (! $this->tablesReady() || trim($serviceName) === '') {
            return;
        }

        DB::table('service_time_slots')->where('service_name', $serviceName)->delete();

        foreach (array_unique($enabledSlotIds) as $slotId) {
            DB::table('service_time_slots')->insert([
                'service_name' => $serviceName,
                'time_slot_id' => (int) $slotId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $enabledSlotIds
     */
    public function syncDateSlotOverrides(string $serviceName, string $dateYmd, array $enabledSlotIds): void
    {
        if (! Schema::hasTable('service_slot_date_overrides') || trim($serviceName) === '') {
            return;
        }

        $weeklyIds = array_flip($this->weeklyEnabledSlotIdsForService($serviceName));
        $enabledSet = array_flip(array_unique($enabledSlotIds));

        DB::table('service_slot_date_overrides')
            ->where('service_name', $serviceName)
            ->whereDate('slot_date', $dateYmd)
            ->delete();

        $slotIds = TimeSlot::query()->where('is_active', true)->pluck('id');

        foreach ($slotIds as $slotId) {
            $slotId = (int) $slotId;
            $weeklyOn = isset($weeklyIds[$slotId]);
            $dateOn = isset($enabledSet[$slotId]);

            if ($dateOn !== $weeklyOn) {
                DB::table('service_slot_date_overrides')->insert([
                    'service_name' => $serviceName,
                    'slot_date' => $dateYmd,
                    'time_slot_id' => $slotId,
                    'is_enabled' => $dateOn,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function setStoreClosed(string $dateYmd, bool $closed, ?string $note = null): void
    {
        if (! Schema::hasTable('store_closures')) {
            return;
        }

        if ($closed) {
            DB::table('store_closures')->updateOrInsert(
                ['closure_date' => $dateYmd],
                [
                    'note' => $note,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            return;
        }

        DB::table('store_closures')->whereDate('closure_date', $dateYmd)->delete();
    }

    /**
     * @return array<int, int>
     */
    public function weeklyEnabledSlotIdsForService(string $serviceName): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        return DB::table('service_time_slots')
            ->where('service_name', $serviceName)
            ->pluck('time_slot_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function bookableWindowSlotLabels(): array
    {
        return $this->serviceWindowSlotLabels();
    }

    public function tablesReady(): bool
    {
        return Schema::hasTable('time_slots') && Schema::hasTable('service_time_slots');
    }

    /**
     * @return array<int, string>
     */
    private function fallbackAllSlotLabels(): array
    {
        return [
            '8:00 AM', '8:30 AM', '9:00 AM', '9:30 AM',
            '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM',
            '12:00 PM', '12:30 PM', '1:00 PM', '1:30 PM',
            '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM',
            '4:00 PM', '4:30 PM', '5:00 PM', '5:30 PM',
            '6:00 PM', '6:30 PM', '7:00 PM', '7:30 PM',
            '8:00 PM', '8:30 PM', '9:00 PM', '9:30 PM',
            '10:00 PM',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function fallbackSlotMapByService(): array
    {
        $map = [];

        foreach ($this->fallbackServiceCatalog() as $service) {
            $map[$service['name']] = $service['times'];
        }

        return $map;
    }

    /**
     * @return array<int, array{name: string, times: array<int, string>}>
     */
    public function fallbackServiceCatalog(): array
    {
        $serviceWindowSlots = $this->serviceWindowSlotLabels();

        return [
            ['name' => 'Swedish Massage', 'times' => $serviceWindowSlots],
            ['name' => 'Deep Tissue', 'times' => $serviceWindowSlots],
            ['name' => 'Hot Stone', 'times' => $serviceWindowSlots],
            ['name' => 'Aromatherapy', 'times' => $serviceWindowSlots],
            ['name' => 'Prenatal Massage', 'times' => $serviceWindowSlots],
            ['name' => 'Thai Massage', 'times' => $serviceWindowSlots],
            ['name' => 'Sports Massage', 'times' => $serviceWindowSlots],
            ['name' => 'Foot Reflexology', 'times' => $serviceWindowSlots],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function serviceWindowSlotLabels(): array
    {
        return array_values(array_filter(
            $this->fallbackAllSlotLabels(),
            static fn (string $label): bool => strtotime($label) <= strtotime('10:00 PM')
        ));
    }

    public function normalizeSlotLabel(string $label): ?string
    {
        $trimmed = trim($label);
        if ($trimmed === '') {
            return null;
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return null;
        }

        return date('g:i A', $timestamp);
    }

    private function nextSortOrderForLabel(string $label): int
    {
        $target = strtotime($label);
        if ($target === false) {
            return (int) TimeSlot::query()->max('sort_order') + 1;
        }

        $insertAfter = 0;
        foreach (TimeSlot::query()->orderBy('sort_order')->get(['id', 'label', 'sort_order']) as $slot) {
            $slotTime = strtotime((string) $slot->label);
            if ($slotTime !== false && $slotTime <= $target) {
                $insertAfter = (int) $slot->sort_order;
            }
        }

        return $insertAfter + 1;
    }

    public function seedDefaults(): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $labels = $this->fallbackAllSlotLabels();
        $labelToId = [];

        foreach ($labels as $index => $label) {
            $slot = TimeSlot::query()->updateOrCreate(
                ['label' => $label],
                ['sort_order' => $index + 1, 'is_active' => true],
            );
            $labelToId[$label] = $slot->id;
        }

        foreach ($this->fallbackServiceCatalog() as $service) {
            foreach ($service['times'] as $timeLabel) {
                $slotId = $labelToId[$timeLabel] ?? null;
                if ($slotId === null) {
                    continue;
                }

                DB::table('service_time_slots')->updateOrInsert(
                    [
                        'service_name' => $service['name'],
                        'time_slot_id' => $slotId,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    public function addTimeSlotForService(
        string $serviceName,
        string $label,
        string $mode = 'weekly',
        ?string $dateYmd = null,
    ): ?TimeSlot {
        if (! $this->tablesReady() || trim($serviceName) === '') {
            return null;
        }

        return DB::transaction(function () use ($serviceName, $label, $mode, $dateYmd): ?TimeSlot {
            $slot = $this->findOrCreateTimeSlot($label);
            if ($slot === null) {
                return null;
            }

            if ($mode === 'date' && $dateYmd !== null && $dateYmd !== '') {
                $this->enableSlotForServiceOnDate($serviceName, (int) $slot->id, $dateYmd);
            } else {
                $this->enableSlotForServiceWeekly($serviceName, (int) $slot->id);
            }

            return $slot->fresh();
        });
    }

    public function findOrCreateTimeSlot(string $label, bool $markCustom = true): ?TimeSlot
    {
        if (! $this->tablesReady()) {
            return null;
        }

        $normalized = $this->normalizeSlotLabel($label);
        if ($normalized === null) {
            return null;
        }

        $existing = TimeSlot::query()->where('label', $normalized)->first();
        if ($existing !== null) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return $existing->fresh();
        }

        return TimeSlot::query()->create([
            'label' => $normalized,
            'sort_order' => $this->nextSortOrderForLabel($normalized),
            'is_active' => true,
            'is_custom' => $markCustom,
        ]);
    }

    public function enableSlotForServiceWeekly(string $serviceName, int $slotId): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        DB::table('service_time_slots')->updateOrInsert(
            [
                'service_name' => $serviceName,
                'time_slot_id' => $slotId,
            ],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function enableSlotForServiceOnDate(string $serviceName, int $slotId, string $dateYmd): void
    {
        if (! Schema::hasTable('service_slot_date_overrides')) {
            $this->enableSlotForServiceWeekly($serviceName, $slotId);

            return;
        }

        $weeklyIds = array_flip($this->weeklyEnabledSlotIdsForService($serviceName));
        $weeklyOn = isset($weeklyIds[$slotId]);

        if ($weeklyOn) {
            DB::table('service_slot_date_overrides')
                ->where('service_name', $serviceName)
                ->whereDate('slot_date', $dateYmd)
                ->where('time_slot_id', $slotId)
                ->delete();

            return;
        }

        DB::table('service_slot_date_overrides')->updateOrInsert(
            [
                'service_name' => $serviceName,
                'slot_date' => $dateYmd,
                'time_slot_id' => $slotId,
            ],
            [
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    /**
     * @return array<int, int>
     */
    private function visibleCustomSlotIdsForService(string $serviceName, string $dateYmd, string $mode): array
    {
        $ids = DB::table('service_time_slots')
            ->join('time_slots', 'time_slots.id', '=', 'service_time_slots.time_slot_id')
            ->where('service_time_slots.service_name', $serviceName)
            ->where('time_slots.is_custom', true)
            ->pluck('time_slots.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($mode === 'date' && Schema::hasTable('service_slot_date_overrides')) {
            $overrideIds = DB::table('service_slot_date_overrides')
                ->join('time_slots', 'time_slots.id', '=', 'service_slot_date_overrides.time_slot_id')
                ->where('service_slot_date_overrides.service_name', $serviceName)
                ->whereDate('service_slot_date_overrides.slot_date', $dateYmd)
                ->where('time_slots.is_custom', true)
                ->pluck('time_slots.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $ids = array_values(array_unique(array_merge($ids, $overrideIds)));
        }

        return array_flip($ids);
    }

    public function attachDefaultSlotsForService(string $serviceName): void
    {
        if (! $this->tablesReady() || trim($serviceName) === '') {
            return;
        }

        $labelToId = TimeSlot::query()
            ->where('is_active', true)
            ->pluck('id', 'label')
            ->all();

        foreach ($this->serviceWindowSlotLabels() as $timeLabel) {
            $slotId = $labelToId[$timeLabel] ?? null;
            if ($slotId === null) {
                continue;
            }

            DB::table('service_time_slots')->updateOrInsert(
                [
                    'service_name' => $serviceName,
                    'time_slot_id' => $slotId,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
