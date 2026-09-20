@if (session('payment_receipt') || session('booking_confirmed'))
    @php
        $receipt = session('payment_receipt', session('booking_summary', []));
        $receiptNo = $receipt['receipt_no'] ?? ('RCP-'.str_pad((string) ($receipt['booking_id'] ?? '0'), 5, '0', STR_PAD_LEFT));
        $issuedAt = $receipt['issued_at'] ?? now()->format('M j, Y g:i A');
    @endphp
    <div class="payment-receipt-modal" id="paymentReceiptModal" role="dialog" aria-modal="true" aria-labelledby="paymentReceiptTitle" aria-hidden="false">
        <div class="payment-receipt-backdrop" data-receipt-close="true"></div>
        <div class="payment-receipt-dialog" role="document">
            <div class="payment-receipt-screen" id="paymentReceiptPrintArea">
                <header class="payment-receipt-head">
                    <div class="payment-receipt-brand">
                        <img src="{{ asset('images/dashboard/logo.png') }}" alt="TOUCHnRELIEF" class="payment-receipt-logo">
                        <div>
                            <h3 class="payment-receipt-title" id="paymentReceiptTitle">Payment Receipt</h3>
                            <p class="payment-receipt-subtitle">TOUCHnRELIEF Spa</p>
                        </div>
                    </div>
                    <button type="button" class="payment-receipt-close" data-receipt-close="true" aria-label="Close">&times;</button>
                </header>

                <div class="payment-receipt-meta">
                    <div>
                        <span class="payment-receipt-meta-label">Receipt No.</span>
                        <strong>{{ $receiptNo }}</strong>
                    </div>
                    <div>
                        <span class="payment-receipt-meta-label">Issued</span>
                        <strong>{{ $issuedAt }}</strong>
                    </div>
                </div>

                @if (! empty($receipt))
                    <dl class="payment-receipt-body">
                        @if (! empty($receipt['client_name']))
                            <div class="payment-receipt-row">
                                <dt>Client</dt>
                                <dd>{{ $receipt['client_name'] }}</dd>
                            </div>
                        @endif
                        <div class="payment-receipt-row">
                            <dt>Therapist</dt>
                            <dd>{{ $receipt['therapist'] ?? '—' }}</dd>
                        </div>
                        <div class="payment-receipt-row">
                            <dt>Service</dt>
                            <dd>{{ $receipt['service'] ?? '—' }}</dd>
                        </div>
                        <div class="payment-receipt-row">
                            <dt>Date</dt>
                            <dd>{{ $receipt['date'] ?? '—' }}</dd>
                        </div>
                        <div class="payment-receipt-row">
                            <dt>Time</dt>
                            <dd>{{ $receipt['time'] ?? '—' }}</dd>
                        </div>
                        <div class="payment-receipt-divider" aria-hidden="true"></div>
                        <div class="payment-receipt-row">
                            <dt>Payment method</dt>
                            <dd>{{ $receipt['payment_method'] ?? 'PayMongo' }}</dd>
                        </div>
                        <div class="payment-receipt-row">
                            <dt>Payment type</dt>
                            <dd>{{ $receipt['payment_type'] ?? '—' }}</dd>
                        </div>
                        <div class="payment-receipt-row payment-receipt-row--amount">
                            <dt>Amount paid</dt>
                            <dd>₱{{ $receipt['payment_amount'] ?? '0.00' }}</dd>
                        </div>
                        @if (! empty($receipt['reference']) && $receipt['reference'] !== '—')
                            <div class="payment-receipt-row">
                                <dt>Reference</dt>
                                <dd>{{ $receipt['reference'] }}</dd>
                            </div>
                        @endif
                        @if (! empty($receipt['payment_status']))
                            <div class="payment-receipt-row">
                                <dt>Status</dt>
                                <dd>{{ $receipt['payment_status'] }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif

                <p class="payment-receipt-note">
                    <i class="bi bi-camera" aria-hidden="true"></i>
                    You may screenshot this receipt for your records, or use Print below.
                </p>

                <footer class="payment-receipt-foot">
                    <p>Thank you for choosing TOUCHnRELIEF.</p>
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
