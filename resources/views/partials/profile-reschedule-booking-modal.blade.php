<div class="txn-reschedule-modal txn-modal-hidden" id="tnr-reschedule-booking-modal" role="dialog" aria-modal="true" aria-labelledby="tnr-reschedule-booking-title" aria-hidden="true">
    <div class="txn-modal-backdrop" data-tnr-reschedule-close="true" tabindex="-1"></div>
    <div class="txn-reschedule-panel" role="document">
        <header class="txn-reschedule-header">
            <h2 class="txn-reschedule-title" id="tnr-reschedule-booking-title">Reschedule appointment</h2>
            <button type="button" class="txn-modal-close" data-tnr-reschedule-close="true" aria-label="Close">&times;</button>
        </header>
        <div class="txn-reschedule-body">
            <p class="txn-reschedule-lead">Pick a new date and time. We check therapist availability and your other appointments so nothing overlaps.</p>
            <p class="txn-reschedule-booking-ref" id="tnr-reschedule-booking-ref"></p>

            <dl class="txn-reschedule-summary">
                <div>
                    <dt>Service</dt>
                    <dd id="tnr-reschedule-service">—</dd>
                </div>
                <div>
                    <dt>Therapist</dt>
                    <dd id="tnr-reschedule-therapist">—</dd>
                </div>
            </dl>

            <div class="txn-reschedule-field">
                <label class="txn-sort-label" for="tnr-reschedule-date">New date</label>
                <input type="date" id="tnr-reschedule-date" class="txn-search-input" min="{{ now()->toDateString() }}">
            </div>

            <div class="txn-reschedule-field">
                <label class="txn-sort-label" for="tnr-reschedule-slots">New time</label>
                <p class="txn-reschedule-slots-hint" id="tnr-reschedule-slots-hint">Select a date to see available times.</p>
                <div class="txn-reschedule-slots" id="tnr-reschedule-slots" role="listbox" aria-label="Available time slots"></div>
                <input type="hidden" id="tnr-reschedule-time-slot" value="">
            </div>

            <p class="txn-reschedule-error txn-hidden" id="tnr-reschedule-error" role="alert"></p>
        </div>
        <footer class="txn-reschedule-footer">
            <button type="button" class="txn-modal-btn txn-reschedule-back" data-tnr-reschedule-close="true">Back to transactions</button>
            <button type="button" class="txn-modal-btn txn-reschedule-submit" id="tnr-reschedule-submit" disabled>Save new schedule</button>
        </footer>
    </div>
</div>
