@once
    <link rel="stylesheet" href="{{ asset('css/transactions-modal.css') }}">
@endonce

<div class="txn-cancel-modal txn-modal-hidden" id="tnr-cancel-booking-modal" role="dialog" aria-modal="true" aria-labelledby="tnr-cancel-booking-title" aria-hidden="true">
    <div class="txn-modal-backdrop" data-tnr-cancel-close="true" tabindex="-1"></div>
    <div class="txn-cancel-panel" role="document">
        <header class="txn-cancel-header">
            <h2 class="txn-cancel-title" id="tnr-cancel-booking-title">Cancel booking</h2>
            <button type="button" class="txn-modal-close" data-tnr-cancel-close="true" aria-label="Close">&times;</button>
        </header>
        <form id="tnr-cancel-booking-form" class="txn-cancel-body" novalidate>
            @csrf
            <p class="txn-cancel-lead">Please tell us why you are cancelling. This helps us improve your experience.</p>
            <p class="txn-cancel-booking-ref" id="tnr-cancel-booking-ref"></p>
            <p class="txn-cancel-refund-notice txn-hidden" id="tnr-cancel-refund-notice" role="status"></p>

            <fieldset class="txn-cancel-group">
                <legend class="txn-cancel-group-title">Change of plans / schedule conflict</legend>
                <label class="txn-cancel-option">
                    <input type="radio" name="cancellation_reason" value="schedule_conflict" required>
                    <span>My schedule changed / I'm no longer available.</span>
                </label>
            </fieldset>

            <fieldset class="txn-cancel-group">
                <legend class="txn-cancel-group-title">Price / cost</legend>
                <label class="txn-cancel-option">
                    <input type="radio" name="cancellation_reason" value="price_cheaper">
                    <span>Found a cheaper alternative.</span>
                </label>
                <label class="txn-cancel-option">
                    <input type="radio" name="cancellation_reason" value="price_expensive">
                    <span>Too expensive.</span>
                </label>
            </fieldset>

            <fieldset class="txn-cancel-group">
                <legend class="txn-cancel-group-title">Service / platform issues</legend>
                <label class="txn-cancel-option">
                    <input type="radio" name="cancellation_reason" value="service_different">
                    <span>Decided to use a different service.</span>
                </label>
                <label class="txn-cancel-option">
                    <input type="radio" name="cancellation_reason" value="platform_confusing">
                    <span>The booking process was confusing.</span>
                </label>
                <label class="txn-cancel-option">
                    <input type="radio" name="cancellation_reason" value="other_therapist">
                    <span>I decided to choose other therapist.</span>
                </label>
            </fieldset>

            <fieldset class="txn-cancel-group">
                <legend class="txn-cancel-group-title">Other</legend>
                <label class="txn-cancel-option">
                    <input type="radio" name="cancellation_reason" value="other" id="tnr-cancel-reason-other">
                    <span>Other (please specify)</span>
                </label>
                <textarea
                    id="tnr-cancel-other-text"
                    name="cancellation_other"
                    class="txn-cancel-other"
                    rows="3"
                    maxlength="500"
                    placeholder="Tell us more…"
                    hidden
                ></textarea>
            </fieldset>

            <p class="txn-cancel-error txn-hidden" id="tnr-cancel-error" role="alert"></p>
        </form>
        <footer class="txn-cancel-footer">
            <button type="button" class="txn-modal-btn txn-cancel-back" data-tnr-cancel-close="true">Back to transaction</button>
            <button type="submit" form="tnr-cancel-booking-form" class="txn-modal-btn txn-cancel-submit" id="tnr-cancel-submit">Confirm cancellation</button>
        </footer>
    </div>
</div>
