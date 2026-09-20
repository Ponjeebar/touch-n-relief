<div class="appointments-list" role="list" aria-label="Appointments list">
    <div
        data-appointments-meta="true"
        data-confirmed="{{ $stats['confirmed'] ?? 0 }}"
        data-pending="{{ $stats['pending'] ?? 0 }}"
        data-rescheduled="{{ $stats['rescheduled'] ?? 0 }}"
        data-completed="{{ $stats['completed'] ?? 0 }}"
        data-cancelled="{{ $stats['cancelled'] ?? 0 }}"
        data-in-session="{{ $stats['in_session'] ?? 0 }}"
        data-is-past-day="{{ ($isPastDay ?? false) ? 1 : 0 }}"
        hidden
    ></div>
    @forelse ($appointments as $appointment)
        @php
            $isCancelled = ($appointment['status'] ?? '') === 'Cancelled';
            $isCompleted = ($appointment['status'] ?? '') === 'Completed';
            $isConfirmed = ($appointment['status'] ?? '') === 'Confirmed';
            $isPending = ($appointment['status'] ?? '') === 'Pending';
            $isRescheduled = ($appointment['status'] ?? '') === 'Rescheduled';
            $isInSession = ($appointment['status'] ?? '') === 'In Session';
            $isActiveBooking = $isConfirmed || $isPending || $isRescheduled;
            $bookingId = $appointment['booking_id'] ?? null;
        @endphp
        <div class="appt-row" role="listitem" data-booking-id="{{ $bookingId ?? '' }}">
            <div class="appt-client">
                <div class="appt-avatar" aria-hidden="true">
                    {{ strtoupper(substr($appointment['client'] ?? 'CL', 0, 2)) }}
                </div>
                <div class="appt-client-main">
                    <div class="appt-client-name-row">
                        <strong>{{ $appointment['client'] }}</strong>
                        @include('partials.client-source-pill', [
                            'clientSource' => $appointment['client_source'] ?? 'online',
                            'clientSourceLabel' => $appointment['client_source_label'] ?? 'Online Appointment',
                        ])
                    </div>
                    <span>{{ $appointment['service'] }} · {{ $appointment['therapist'] }}</span>
                    @if (!empty($appointment['payment_summary']) && $appointment['payment_summary'] !== '—')
                        <span class="appt-payment-line">{{ $appointment['payment_summary'] }}</span>
                    @endif
                </div>
            </div>

            <div class="appt-meta">
                <div class="appt-meta-col">
                    <span class="appt-meta-label">Date</span>
                    <span class="appt-meta-value">{{ $appointment['date'] }}</span>
                </div>
                <div class="appt-meta-col">
                    <span class="appt-meta-label">Time</span>
                    <span class="appt-meta-value">{{ $appointment['time'] }}</span>
                </div>
                <div class="appt-meta-col appt-meta-col-status">
                    <span class="appt-meta-label">Status</span>
                    @if ($isCompleted)
                        <a href="{{ route('completed-sessions.index') }}" class="appt-status completed appt-status-link">
                            {{ $appointment['status'] }}
                        </a>
                    @elseif ($isInSession)
                        <a href="{{ route('ongoing-sessions.index') }}" class="appt-status in-session appt-status-link">
                            {{ $appointment['status'] }}
                        </a>
                    @else
                        <span class="appt-status {{ \Illuminate\Support\Str::slug($appointment['status']) }}">
                            {{ $appointment['status'] }}
                        </span>
                    @endif
                </div>
            </div>

            @if ($isActiveBooking || $isCompleted || $isCancelled || $isInSession)
                <div class="appt-actions" role="group" aria-label="Actions for {{ $appointment['client'] }}">
                    @if ($isActiveBooking && $bookingId)
                        <form method="POST" action="{{ route('appointments.start', $bookingId) }}" class="appt-start-form">
                            @csrf
                            @method('PATCH')
                            <button class="appt-icon-btn start" type="submit" title="Start" aria-label="Start appointment for {{ $appointment['client'] }}">
                                <i class="bi bi-play-fill" aria-hidden="true"></i>
                            </button>
                        </form>
                    @endif
                    @if (($isStaff ?? false) && ! empty($appointment['can_reschedule']) && $bookingId)
                        <button
                            class="appt-icon-btn reschedule"
                            type="button"
                            title="Reschedule"
                            aria-label="Reschedule appointment for {{ $appointment['client'] }}"
                            data-open-reschedule="true"
                            data-reschedule-url="{{ route('appointments.reschedule', $bookingId) }}"
                            data-reschedule-availability-url="{{ route('appointments.reschedule.availability', $bookingId) }}"
                            data-client="{{ $appointment['client'] }}"
                            data-service="{{ $appointment['service'] }}"
                            data-therapist="{{ $appointment['therapist_name'] ?? $appointment['therapist'] }}"
                            data-date="{{ $appointment['date'] }}"
                            data-time="{{ $appointment['time'] }}"
                            data-booking-date-iso="{{ $appointment['booking_date_iso'] ?? '' }}"
                            data-time-slot="{{ $appointment['time_slot'] ?? '' }}"
                            data-client-user-id="{{ $appointment['client_user_id'] ?? '' }}"
                        >
                            <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                        </button>
                    @endif
                    <button
                        class="appt-icon-btn view"
                        type="button"
                        title="View"
                        aria-label="View appointment for {{ $appointment['client'] }}"
                        data-open-view="true"
                        data-client="{{ $appointment['client'] }}"
                        data-service="{{ $appointment['service'] }}"
                        data-therapist="{{ $appointment['therapist'] }}"
                        data-status="{{ $appointment['status'] }}"
                        data-date="{{ $appointment['date'] }}"
                        data-time="{{ $appointment['time'] }}"
                        data-notes="{{ $appointment['notes'] ?? '' }}"
                        data-payment-summary="{{ $appointment['payment_summary'] ?? '' }}"
                        data-payment-method="{{ $appointment['payment_method_label'] ?? '' }}"
                        data-payment-type="{{ $appointment['payment_type_label'] ?? '' }}"
                        data-payment-amount="{{ $appointment['payment_amount'] ?? '' }}"
                        data-service-amount="{{ $appointment['service_amount'] ?? '' }}"
                        data-payment-proof="{{ $appointment['payment_proof_url'] ?? '' }}"
                        data-payment-transaction="{{ $appointment['payment_transaction_id'] ?? '' }}"
                        data-booking-id="{{ $bookingId ?? '' }}"
                        data-refund-status="{{ $appointment['refund_status'] ?? '' }}"
                        data-refund-status-label="{{ $appointment['refund_status_label'] ?? '' }}"
                        data-refund-amount="{{ $appointment['refund_amount'] ?? '' }}"
                        data-refund-reference="{{ $appointment['refund_reference'] ?? '' }}"
                        data-refund-note="{{ $appointment['refund_note'] ?? '' }}"
                        data-can-complete-refund="{{ ! empty($appointment['can_complete_refund']) ? '1' : '0' }}"
                        data-refund-url="{{ ! empty($appointment['can_complete_refund']) && $bookingId ? route('appointments.refund.complete', $bookingId) : '' }}"
                    >
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                    <button
                        class="appt-icon-btn cancel"
                        type="button"
                        title="Cancel"
                        aria-label="Cancel appointment for {{ $appointment['client'] }}"
                        @if (($isStaff ?? false) && ! empty($appointment['can_cancel']) && $bookingId)
                            data-open-cancel="true"
                            data-cancel-url="{{ route('appointments.cancel', $bookingId) }}"
                            data-client="{{ $appointment['client'] }}"
                            data-service="{{ $appointment['service'] }}"
                            data-therapist="{{ $appointment['therapist'] }}"
                            data-date="{{ $appointment['date'] }}"
                            data-time="{{ $appointment['time'] }}"
                        @else
                            disabled
                        @endif
                    >
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </div>
            @endif
        </div>
    @empty
        <div class="empty-users">No appointments yet.</div>
    @endforelse
</div>

@if ($appointments instanceof \Illuminate\Pagination\LengthAwarePaginator && $appointments->hasPages())
    <div class="appointments-pagination">
        {{ $appointments->withQueryString()->links() }}
    </div>
@endif
