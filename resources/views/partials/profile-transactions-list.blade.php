@php
    $userTransactions = $userTransactions ?? [];
@endphp
@if (count($userTransactions) > 0)
    <div class="txn-toolbar">
        <div class="txn-toolbar-field">
            <label class="txn-sort-label" for="txn-date-filter">Date</label>
            <input type="date" id="txn-date-filter" class="txn-search-input" aria-label="Filter transactions by date">
        </div>
        <div class="txn-toolbar-field">
            <label class="txn-sort-label" for="txn-search">Search</label>
            <input type="search" id="txn-search" class="txn-search-input" aria-label="Search transactions" placeholder="Search service, therapist, date">
        </div>
        <div class="txn-toolbar-field">
            <label class="txn-sort-label" for="txn-filter-select">Filter</label>
            <select id="txn-filter-select" class="txn-sort-select" aria-label="Filter transactions">
                <option value="all">All</option>
                <option value="with-amount">With amount</option>
                <option value="without-amount">Without amount</option>
            </select>
        </div>
        <div class="txn-toolbar-field">
            <label class="txn-sort-label" for="txn-sort-select">Sort</label>
            <select id="txn-sort-select" class="txn-sort-select" aria-label="Sort transactions">
                <option value="date-desc" selected>Most recent first</option>
                <option value="date-asc">Appointment oldest first</option>
                <option value="amount-desc">Amount high to low</option>
                <option value="amount-asc">Amount low to high</option>
                <option value="service-asc">Service A to Z</option>
            </select>
        </div>
    </div>
    <p class="txn-filter-empty txn-hidden" id="txn-filter-empty" role="status">No transactions match your search/filter.</p>
    <ul class="txn-cards" id="txn-cards-list" aria-label="Your transactions">
        @foreach ($userTransactions as $txn)
            @php
                $statusLower = strtolower($txn['status'] ?? '');
                $badgeClass = str_contains($statusLower, 'cancelled')
                    ? 'cancelled'
                    : (str_contains($statusLower, 'payment pending')
                        ? 'pending'
                        : (str_contains($statusLower, 'confirmed')
                        ? 'booked'
                        : (str_contains($statusLower, 'in session')
                            ? 'active'
                            : (str_contains($statusLower, 'completed session') ? 'paid' : 'done'))));
                $statusAccent = match ($badgeClass) {
                    'cancelled' => 'is-cancelled',
                    'pending' => 'is-pending',
                    'booked' => 'is-booked',
                    'active' => 'is-active',
                    'paid', 'done' => 'is-completed',
                    default => '',
                };
            @endphp
            <li
                class="txn-card txn-card-{{ $statusAccent }} {{ (! empty($txn['can_cancel']) || ! empty($txn['can_reschedule'])) ? 'txn-card-cancellable' : '' }}"
                data-sort-ts="{{ $txn['sort_ts'] ?? 0 }}"
                data-activity-ts="{{ $txn['activity_ts'] ?? ($txn['sort_ts'] ?? 0) }}"
                data-sort-date="{{ $txn['date'] ?? '' }}"
                data-service="{{ strtolower((string) ($txn['service'] ?? '')) }}"
                data-therapist="{{ strtolower((string) ($txn['therapist'] ?? '')) }}"
                data-time="{{ strtolower((string) ($txn['time'] ?? '')) }}"
                data-duration="{{ strtolower((string) ($txn['duration'] ?? '')) }}"
                data-amount-raw="{{ $txn['amount_raw'] ?? 0 }}"
                data-has-amount="{{ (float) ($txn['amount_raw'] ?? 0) > 0 ? '1' : '0' }}"
                @if (! empty($txn['booking_id'])) data-booking-id="{{ $txn['booking_id'] }}" @endif
            >
                <div class="txn-card-top">
                    <span class="txn-ref">{{ $txn['transaction_id'] }}</span>
                    <span class="txn-amount-pill">{{ $txn['amount'] }}</span>
                </div>
                <div class="txn-card-mid">
                    <p class="txn-service">{{ $txn['service'] }}</p>
                    <span class="txn-badge txn-badge-{{ $badgeClass }}">
                        {{ $txn['status'] ?? '—' }}
                    </span>
                </div>
                <dl class="txn-meta">
                    <div>
                        <dt>Therapist</dt>
                        <dd>{{ $txn['therapist'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Date</dt>
                        <dd>{{ \Illuminate\Support\Carbon::parse($txn['date'])->format('M d, Y') }}</dd>
                    </div>
                    <div>
                        <dt>Time</dt>
                        <dd>{{ $txn['time'] }}</dd>
                    </div>
                    <div>
                        <dt>Duration</dt>
                        <dd>{{ $txn['duration'] ?? '—' }}</dd>
                    </div>
                    @if (! empty($txn['payment_method_label']) && $txn['payment_method_label'] !== '—')
                        <div class="txn-meta-full txn-payment-block">
                            <dt>Payment</dt>
                            <dd>
                                <dl class="txn-payment-grid">
                                    <div class="txn-payment-item">
                                        <dt>Method</dt>
                                        <dd>{{ $txn['payment_method_label'] }}</dd>
                                    </div>
                                    <div class="txn-payment-item">
                                        <dt>Type</dt>
                                        <dd>{{ $txn['payment_type_label'] ?? '—' }}</dd>
                                    </div>
                                    <div class="txn-payment-item">
                                        <dt>Initial payment</dt>
                                        <dd>{{ $txn['payment_amount'] ?? '—' }}</dd>
                                    </div>
                                    @if (! empty($txn['full_payment_status']))
                                        <div class="txn-payment-item">
                                            <dt>Status</dt>
                                            <dd>
                                                @php
                                                    $showRefundStatus = $badgeClass === 'cancelled'
                                                        && ! empty($txn['refund_status'])
                                                        && $txn['refund_status'] !== 'not_applicable';
                                                    $displayPaymentStatus = $showRefundStatus
                                                        ? ($txn['refund_status'] === 'processed' ? 'refunded' : $txn['refund_status'])
                                                        : $txn['full_payment_status'];
                                                    $displayPaymentLabel = $showRefundStatus
                                                        ? ($txn['refund_status_label'] ?? 'Refund pending')
                                                        : ($txn['full_payment_status_label'] ?? '—');
                                                @endphp
                                                <span class="txn-payment-status txn-payment-status--{{ $displayPaymentStatus }}">
                                                    {{ $displayPaymentLabel }}
                                                </span>
                                            </dd>
                                        </div>
                                    @endif
                                    <div class="txn-payment-item">
                                        <dt>Total paid</dt>
                                        <dd>{{ $txn['paid_amount'] ?? '₱0.00' }}</dd>
                                    </div>
                                    @if (! ($txn['is_fully_paid'] ?? false) && $badgeClass !== 'cancelled')
                                        <div class="txn-payment-item txn-payment-item-balance">
                                            <dt>Balance due</dt>
                                            <dd>{{ $txn['remaining_balance'] ?? '—' }}</dd>
                                        </div>
                                    @endif
                                    @if (! empty($txn['payment_transaction_id']))
                                        <div class="txn-payment-item txn-payment-item-ref">
                                            <dt>Reference</dt>
                                            <dd>{{ $txn['payment_transaction_id'] }}</dd>
                                        </div>
                                    @endif
                                    @if (! empty($txn['refund_status']) && $txn['refund_status'] !== 'not_applicable')
                                        <div class="txn-payment-item txn-payment-item-refund">
                                            <dt>Refund</dt>
                                            <dd>
                                                <span class="txn-refund-status txn-refund-status--{{ $txn['refund_status'] }}">
                                                    {{ $txn['refund_status_label'] ?? '—' }}
                                                    @if (! empty($txn['refund_amount']))
                                                        · {{ $txn['refund_amount'] }}
                                                    @endif
                                                </span>
                                                @if (! empty($txn['refund_reference']))
                                                    <span class="txn-refund-ref">Ref: {{ $txn['refund_reference'] }}</span>
                                                @endif
                                                @if (! empty($txn['refund_note']))
                                                    <span class="txn-refund-note">{{ $txn['refund_note'] }}</span>
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            </dd>
                        </div>
                    @endif
                </dl>
                @if (! empty($txn['cancellation_reason']))
                    <p class="txn-cancel-reason">
                        <span class="txn-cancel-reason-label">Cancellation reason:</span>
                        {{ $txn['cancellation_reason'] }}
                    </p>
                @endif
                @if ((! empty($txn['can_cancel']) || ! empty($txn['can_reschedule']) || ! empty($txn['can_resume_payment'])) && ! empty($txn['booking_id']))
                    <div class="txn-card-actions">
                        @if (! empty($txn['can_resume_payment']))
                            <form method="POST" action="{{ route('booking.payment.retry', ['spaBooking' => $txn['booking_id']]) }}">
                                @csrf
                                <button type="submit" class="txn-payment-btn">
                                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                                    Continue payment
                                </button>
                            </form>
                        @endif
                        @if (! empty($txn['can_reschedule']))
                            <button
                                type="button"
                                class="txn-reschedule-btn"
                                data-tnr-reschedule-booking
                                data-reschedule-url="{{ route('booking.reschedule', ['spaBooking' => $txn['booking_id']]) }}"
                                data-booking-id="{{ $txn['booking_id'] }}"
                                data-service="{{ $txn['service'] }}"
                                data-therapist="{{ $txn['therapist'] }}"
                                data-booking-date="{{ $txn['date'] }}"
                                data-time-slot="{{ $txn['time'] }}"
                                data-booking-label="{{ $txn['transaction_id'] }} · {{ $txn['service'] }} · {{ \Illuminate\Support\Carbon::parse($txn['date'])->format('M d, Y') }} at {{ $txn['time'] }}"
                            >
                                Reschedule
                            </button>
                        @endif
                        @if (! empty($txn['can_cancel']))
                            <button
                                type="button"
                                class="txn-cancel-btn"
                                data-tnr-cancel-booking
                                data-cancel-url="{{ route('booking.cancel', ['spaBooking' => $txn['booking_id']]) }}"
                                data-booking-label="{{ $txn['transaction_id'] }} · {{ $txn['service'] }} · {{ \Illuminate\Support\Carbon::parse($txn['date'])->format('M d, Y') }} at {{ $txn['time'] }}"
                                data-is-refundable="{{ ! empty($txn['is_refundable']) ? '1' : '0' }}"
                                data-payment-amount="{{ $txn['payment_amount'] ?? '' }}"
                                data-refund-note="{{ $txn['refund_note'] ?? '' }}"
                            >
                                Cancel booking
                            </button>
                        @endif
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@else
    <p class="txn-empty">You have no bookings or completed sessions yet. Confirm an appointment on the booking page and it will appear here.</p>
@endif
