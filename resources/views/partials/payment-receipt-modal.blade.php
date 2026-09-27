@if (session('payment_receipt') || session('booking_confirmed'))
    @php
        $receipt = session('payment_receipt', session('booking_summary', []));
        $receiptNo = $receipt['receipt_no'] ?? ('RCP-'.str_pad((string) ($receipt['booking_id'] ?? '0'), 5, '0', STR_PAD_LEFT));
        $issuedAt = $receipt['issued_at'] ?? now()->format('M j, Y g:i A');
        $paymentStatus = $receipt['payment_status'] ?? 'Paid';
        $normalizedStatus = strtolower((string) $paymentStatus);
        $isPaidReceipt = in_array($normalizedStatus, ['paid', 'completed', 'fully paid'], true);
    @endphp
    <div class="payment-receipt-modal" id="paymentReceiptModal" role="dialog" aria-modal="true" aria-labelledby="paymentReceiptTitle" aria-hidden="false">
        <div class="payment-receipt-backdrop" data-receipt-close="true"></div>
        <div class="payment-receipt-dialog" role="document">
            <div class="payment-receipt-screen" id="paymentReceiptPrintArea">
                <header class="payment-receipt-head">
                    <div class="payment-receipt-brand">
                        <span class="payment-receipt-logo-wrap">
                            <img src="{{ asset('images/dashboard/logo.png') }}" alt="" class="payment-receipt-logo">
                        </span>
                        <div>
                            <p class="payment-receipt-kicker">Buenos Touché Spa</p>
                            <h3 class="payment-receipt-title" id="paymentReceiptTitle">Payment receipt</h3>
                            <p class="payment-receipt-subtitle">Issued through TouchNRelief</p>
                        </div>
                    </div>
                    <button type="button" class="payment-receipt-close" data-receipt-close="true" aria-label="Close receipt">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </header>

                <div class="payment-receipt-meta">
                    <div>
                        <span class="payment-receipt-meta-label">Receipt number</span>
                        <strong>{{ $receiptNo }}</strong>
                    </div>
                    <div>
                        <span class="payment-receipt-meta-label">Issued</span>
                        <strong>{{ $issuedAt }}</strong>
                    </div>
                </div>

                @if (! empty($receipt))
                    <section class="payment-receipt-total" aria-label="Payment summary">
                        <div>
                            <span class="payment-receipt-total-label">Amount paid</span>
                            <strong class="payment-receipt-total-amount">₱{{ $receipt['payment_amount'] ?? '0.00' }}</strong>
                            <span class="payment-receipt-total-service">{{ $receipt['service'] ?? 'Spa service' }}</span>
                        </div>
                        <span class="payment-receipt-status {{ $isPaidReceipt ? 'is-paid' : '' }}">
                            <i class="bi {{ $isPaidReceipt ? 'bi-check-circle-fill' : 'bi-clock-fill' }}" aria-hidden="true"></i>
                            {{ $paymentStatus }}
                        </span>
                    </section>

                    <section class="payment-receipt-section">
                        <h4><i class="bi bi-calendar2-check" aria-hidden="true"></i> Appointment details</h4>
                        <dl class="payment-receipt-body">
                            @if (! empty($receipt['client_name']))
                                <div class="payment-receipt-row">
                                    <dt>Client</dt>
                                    <dd>{{ $receipt['client_name'] }}</dd>
                                </div>
                            @endif
                            <div class="payment-receipt-row">
                                <dt>Service</dt>
                                <dd>{{ $receipt['service'] ?? '—' }}</dd>
                            </div>
                            <div class="payment-receipt-row">
                                <dt>Therapist</dt>
                                <dd>{{ $receipt['therapist'] ?? '—' }}</dd>
                            </div>
                            <div class="payment-receipt-row">
                                <dt>Schedule</dt>
                                <dd>{{ $receipt['date'] ?? '—' }}<span class="payment-receipt-schedule-separator"> at </span>{{ $receipt['time'] ?? '—' }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="payment-receipt-section payment-receipt-section--payment">
                        <h4><i class="bi bi-credit-card" aria-hidden="true"></i> Payment details</h4>
                        <dl class="payment-receipt-body">
                            <div class="payment-receipt-row">
                                <dt>Method</dt>
                                <dd>{{ $receipt['payment_method'] ?? 'PayMongo' }}</dd>
                            </div>
                            <div class="payment-receipt-row">
                                <dt>Payment</dt>
                                <dd>{{ $receipt['payment_type'] ?? '—' }}</dd>
                            </div>
                            @if (! empty($receipt['reference']) && $receipt['reference'] !== '—')
                                <div class="payment-receipt-row payment-receipt-row--reference">
                                    <dt>Transaction reference</dt>
                                    <dd><code>{{ $receipt['reference'] }}</code></dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                @endif

                <p class="payment-receipt-note">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Keep this for your records. Save a screenshot or use the print option below.</span>
                </p>

                <footer class="payment-receipt-foot">
                    <span class="payment-receipt-foot-mark" aria-hidden="true"></span>
                    <p>Thank you for choosing Buenos Touché.</p>
                    <small>Relaxation, care, and relief—reserved for you.</small>
                </footer>
            </div>

            <div class="payment-receipt-actions">
                <button type="button" class="payment-receipt-btn payment-receipt-btn--print" id="paymentReceiptPrintBtn">
                    <i class="bi bi-printer" aria-hidden="true"></i>
                    Print receipt
                </button>
                <button type="button" class="payment-receipt-btn payment-receipt-btn--done" data-receipt-close="true">Done</button>
            </div>
        </div>
    </div>
@endif
