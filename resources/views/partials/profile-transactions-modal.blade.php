@once
    <link rel="stylesheet" href="{{ asset('css/transactions-modal.css') }}?v={{ filemtime(public_path('css/transactions-modal.css')) }}">
@endonce

<div class="txn-modal txn-modal-hidden" id="tnr-transactions-modal" role="dialog" aria-modal="true" aria-labelledby="tnr-transactions-modal-title" aria-hidden="true">
    <div class="txn-modal-backdrop" data-tnr-txn-close="true" tabindex="-1"></div>
    <div class="txn-modal-panel" role="document">
        <header class="txn-modal-header">
            <div>
                <p class="txn-modal-label">Account</p>
                <h2 class="txn-modal-title" id="tnr-transactions-modal-title">My Transactions</h2>
                @if (!empty($customerBirthday) || !empty($customerAge))
                    <p class="txn-modal-client-meta">
                        @if (!empty($customerBirthday))
                            <span>DOB: {{ $customerBirthday }}</span>
                        @endif
                        @if (!empty($customerAge))
                            <span>Age: {{ $customerAge }}</span>
                        @endif
                    </p>
                @endif
            </div>
            <button type="button" class="txn-modal-close" data-tnr-txn-close="true" aria-label="Close">&times;</button>
        </header>
        <div class="txn-modal-body">
            <p class="txn-modal-sub">Your bookings, payment status, and completed spa sessions — most recent activity shown first.</p>
            <p class="txn-modal-note">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                <span class="txn-modal-note-text"><strong>Late note:</strong> your session will be automatically cancelled if you are <strong>10 minutes late</strong>.</span>
            </p>
            @include('partials.profile-transactions-list')
        </div>
        <footer class="txn-modal-footer">
            <button type="button" class="txn-modal-btn" data-tnr-txn-close="true">Close</button>
        </footer>
    </div>
</div>

@include('partials.profile-cancel-booking-modal')
@include('partials.profile-reschedule-booking-modal')

@php
    $txnAvailabilityUrl = route('booking.availability');
@endphp
<script>window.__tnrTxnAvailabilityUrl = @json($txnAvailabilityUrl);</script>

<div class="txn-status-toast hidden" id="txn-status-toast" role="status" aria-live="polite">
    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
    <span id="txn-status-toast-msg"></span>
    <button type="button" class="txn-status-toast-close" aria-label="Close">&times;</button>
</div>
