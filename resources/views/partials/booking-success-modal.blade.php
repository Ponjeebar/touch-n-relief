@if (session('booking_confirmed'))
    @php
        $summary = session('booking_summary', []);
    @endphp
    <div class="bk-modal bk-success-modal" id="bkSuccessModal" role="dialog" aria-modal="true" aria-labelledby="bkSuccessTitle" aria-hidden="false">
        <div class="bk-backdrop" data-bk-success-close="true"></div>
        <div class="bk-dialog" role="document">
            <div class="bk-head bk-head-success">
                <h3 class="bk-title" id="bkSuccessTitle">Booking confirmed</h3>
                <button type="button" class="bk-x" data-bk-success-close="true" aria-label="Close">&times;</button>
            </div>
            <div class="bk-body">
                <p class="bk-success-lead">Your appointment has been confirmed.</p>

                @if (! empty($summary))
                    <dl class="bk-success-summary">
                        <div class="bk-success-row">
                            <dt>Therapist</dt>
                            <dd>{{ $summary['therapist'] ?? '—' }}</dd>
                        </div>
                        <div class="bk-success-row">
                            <dt>Service</dt>
                            <dd>{{ $summary['service'] ?? '—' }}</dd>
                        </div>
                        <div class="bk-success-row">
                            <dt>Date</dt>
                            <dd>{{ $summary['date'] ?? '—' }}</dd>
                        </div>
                        <div class="bk-success-row">
                            <dt>Time</dt>
                            <dd>{{ $summary['time'] ?? '—' }}</dd>
                        </div>
                        <div class="bk-success-row">
                            <dt>Payment</dt>
                            <dd>{{ $summary['payment_method'] ?? 'PayMongo' }}</dd>
                        </div>
                        <div class="bk-success-row">
                            <dt>Type</dt>
                            <dd>{{ $summary['payment_type'] ?? '—' }}</dd>
                        </div>
                        <div class="bk-success-row">
                            <dt>Amount</dt>
                            <dd>₱{{ $summary['payment_amount'] ?? '0.00' }}</dd>
                        </div>
                    </dl>
                @elseif (session('status'))
                    <p class="bk-success-fallback">{{ session('status') }}</p>
                @endif

                <div class="bk-reminder">
                    <p class="bk-reminder-item">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <span>Please arrive at the spa <strong>10 minutes before</strong> your scheduled session.</span>
                    </p>
                    <p class="bk-reminder-item bk-reminder-muted">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span><strong>Late note:</strong> your session will be automatically cancelled if you are <strong>10 minutes late</strong>.</span>
                    </p>
                </div>
            </div>
            <div class="bk-foot">
                <button type="button" class="bk-btn bk-confirm" data-bk-success-close="true">Got it</button>
            </div>
        </div>
    </div>
@endif
