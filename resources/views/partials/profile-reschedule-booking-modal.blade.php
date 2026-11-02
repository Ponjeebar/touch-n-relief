<div class="txn-reschedule-modal txn-modal-hidden" id="tnr-reschedule-booking-modal" role="dialog" aria-modal="true" aria-labelledby="tnr-reschedule-booking-title" aria-hidden="true">
    <div class="txn-modal-backdrop" data-tnr-reschedule-close="true" tabindex="-1"></div>
    <div class="txn-reschedule-panel" role="document">
        <header class="txn-reschedule-header">
            <h2 class="txn-reschedule-title" id="tnr-reschedule-booking-title">Reschedule appointment</h2>
            <button type="button" class="txn-modal-close" data-tnr-reschedule-close="true" aria-label="Close">&times;</button>
        </header>
        <div class="txn-reschedule-body">
            <section class="txn-reschedule-current" aria-labelledby="tnr-reschedule-current-label">
                <h3 class="txn-reschedule-section-title" id="tnr-reschedule-current-label">Current appointment</h3>
                <p class="txn-reschedule-current-note">Before your changes. This appointment stays booked until you save a new schedule.</p>
                <p class="txn-reschedule-booking-ref" id="tnr-reschedule-booking-ref"></p>
                <dl class="txn-reschedule-summary">
                    <div>
                        <dt>Service</dt>
                        <dd id="tnr-reschedule-service">—</dd>
                    </div>
                    <div>
                        <dt>Current therapist</dt>
                        <dd id="tnr-reschedule-therapist">—</dd>
                    </div>
                    <div class="txn-reschedule-summary-date"><dt>Date and time</dt><dd id="tnr-reschedule-current-time"></dd></div>
                    <div class="txn-reschedule-current-payment"><dt>Already paid</dt><dd id="tnr-reschedule-paid">—</dd></div>
                    <div class="txn-reschedule-current-payment"><dt>Remaining balance</dt><dd id="tnr-reschedule-balance">—</dd></div>
                </dl>
            </section>
            <section class="txn-reschedule-editor" aria-labelledby="tnr-reschedule-new-label">
                <h3 class="txn-reschedule-section-title" id="tnr-reschedule-new-label">Choose a new schedule</h3>
                <p class="txn-reschedule-lead">Keep your therapist or choose another, then select an available time.</p>
                <div class="txn-reschedule-fields">
                    <div class="txn-reschedule-field">
                        <label class="txn-sort-label" for="tnr-reschedule-therapist-choice">Therapist</label>
                        <select id="tnr-reschedule-therapist-choice" class="txn-search-input">
                            @foreach (app(\App\Services\TherapistCatalog::class)->forBooking() as $therapistOption)
                                <option value="{{ $therapistOption['name'] }}">{{ $therapistOption['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="txn-reschedule-field">
                        <label class="txn-sort-label" for="tnr-reschedule-date">New date</label>
                        <input type="date" id="tnr-reschedule-date" class="txn-search-input" min="{{ now()->toDateString() }}">
                    </div>
                </div>
                <div class="txn-reschedule-field">
                    <span class="txn-sort-label" id="tnr-reschedule-slots-label">New time</span>
                    <p class="txn-reschedule-slots-hint" id="tnr-reschedule-slots-hint" role="status">Select a date to see available times.</p>
                    <div class="txn-reschedule-slots" id="tnr-reschedule-slots" role="listbox" aria-labelledby="tnr-reschedule-slots-label" aria-describedby="tnr-reschedule-slots-hint"></div>
                    <input type="hidden" id="tnr-reschedule-time-slot" value="">
                </div>
            </section>
            <p class="txn-reschedule-error txn-hidden" id="tnr-reschedule-error" role="alert"></p>
            <div class="txn-reschedule-review">
                <h3 class="txn-reschedule-section-title">New appointment</h3>
                <p id="tnr-reschedule-proposed" aria-live="polite"></p>
                <p class="txn-reschedule-lead">Your service, price, payment, and balance stay the same. No new payment is required.</p>
            </div>
            <p class="txn-reschedule-policy">Rescheduling does not extend an earlier cancellation deadline.</p>
        </div>
        <footer class="txn-reschedule-footer">
            <button type="button" class="txn-modal-btn txn-reschedule-back" data-tnr-reschedule-close="true">Back to transactions</button>
            <button type="button" class="txn-modal-btn txn-reschedule-submit" id="tnr-reschedule-submit" disabled>Save new schedule</button>
        </footer>
    </div>
</div>
