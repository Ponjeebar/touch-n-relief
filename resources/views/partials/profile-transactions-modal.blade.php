@once
    <link rel="stylesheet" href="{{ asset('css/transactions-modal.css') }}?v={{ filemtime(public_path('css/transactions-modal.css')) }}">
@endonce

<div class="txn-modal txn-modal-hidden" id="tnr-transactions-modal" role="dialog" aria-modal="true" aria-labelledby="tnr-transactions-modal-title" aria-hidden="true">
    <div class="txn-modal-backdrop" data-tnr-txn-close="true" tabindex="-1"></div>
    <div class="txn-modal-panel" role="document">
        <header class="txn-modal-header">
            <div>
                <p class="txn-modal-label">Account</p>
                <h2 class="txn-modal-title" id="tnr-transactions-modal-title">My Appointments</h2>
            </div>
            <button type="button" class="txn-modal-close" data-tnr-txn-close="true" aria-label="Close">&times;</button>
        </header>
        <div class="txn-modal-body">
            <p class="txn-modal-sub">Your appointments, payment status, and completed spa sessions — most recent activity shown first.</p>
            <details class="txn-arrival-policy">
                <summary>Arrival and no-show policy</summary>
            <p class="txn-modal-note">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                @php
                    $attendanceRules = app(\App\Services\SiteSettingsService::class);
                @endphp
                <span class="txn-modal-note-text">Please arrive on time. A {{ $attendanceRules->lateGraceMinutes() }}-minute grace period applies. Staff then have {{ $attendanceRules->noShowReviewMinutes() }} minutes to confirm attendance. If attendance is not updated, your appointment is automatically marked as a no-show {{ $attendanceRules->lateGraceMinutes() + $attendanceRules->noShowReviewMinutes() }} minutes after the scheduled start.</span>
            </p>
            </details>
            @include('partials.profile-transactions-list')
        </div>
        <footer class="txn-modal-footer">
            <button type="button" class="txn-modal-btn txn-modal-btn-secondary" data-start-customer-tour data-tour-scope="appointments">
                Appointments guide
            </button>
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
