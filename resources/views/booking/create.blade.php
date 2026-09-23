<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Book Appointment - TouchNRelief</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/payment-receipt.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('partials.chatbot-assets')
</head>
<body class="booking-page-body">
    @include('partials.landing-nav', ['navMode' => 'booking', 'solidNav' => true])

    <main class="booking-page">
        <div class="container booking-wrap">
            <section class="booking-head-shell">
                <div class="booking-head">
                    <p class="booking-eyebrow">Appointment Booking</p>
                    <h1>Secure Your Wellness Session</h1>
                    <p>Pick your service, therapist, date, and time slot. We keep this simple so booking takes less than a minute.</p>
                </div>
            </section>

            @if (session('status') && ! session('booking_confirmed'))
                <div class="booking-success">{{ session('status') }}</div>
            @endif

            @if (!empty($isPregnantCustomer))
                <div class="booking-prenatal-notice" role="status">
                    <i class="bi bi-heart-pulse" aria-hidden="true"></i>
                    <span>Because you are pregnant, only prenatal-safe services are available for booking.</span>
                </div>
            @endif

            @if ($errors->booking->any())
                <div class="booking-errors">
                    <strong>Please check these fields:</strong>
                    <ul>
                        @foreach ($errors->booking->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="booking-layout">
            <div class="booking-grid" id="booking-grid">
                <section class="booking-panel booking-panel-schedule" id="booking-panel-schedule">
                    <h2>1. Your Schedule</h2>
                    <form id="booking-form" method="POST" action="{{ route('booking.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="payment_method" id="payment_method" value="paymongo">
                        <input type="hidden" name="payment_type" id="payment_type" value="{{ old('payment_type', 'downpayment') }}">
                        <div class="booking-field">
                            <label for="booking_date">Date</label>
                            <input id="booking_date" name="booking_date" type="date" min="{{ $today }}" value="{{ old('booking_date', request('date')) }}" required>
                        </div>

                        <div class="booking-field">
                            <label>Available Time Slots</label>
                            <p class="time-slots-hint" id="time-slots-hint">Select a date, service, and therapist to view available time slots.</p>
                            <div class="time-slots" id="time-slots"></div>
                            <div class="slot-legend">
                                <span class="slot-key"><i class="dot dot-available"></i> Available</span>
                                <span class="slot-key"><i class="dot dot-selected"></i> Selected</span>
                                <span class="slot-key"><i class="dot dot-unavailable"></i> Therapist busy</span>
                                <span class="slot-key"><i class="dot dot-fully-booked"></i> All therapists booked</span>
                                <span class="slot-key"><i class="dot dot-user-conflict"></i> Your appointment</span>
                            </div>
                            <input type="hidden" name="time_slot" id="time_slot" value="{{ old('time_slot') }}">
                        </div>

                        <div class="booking-field">
                            <label for="notes">Notes (optional)</label>
                            <textarea id="notes" name="notes" rows="3" placeholder="Any preference or request...">{{ old('notes') }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-light booking-submit">Proceed to payment</button>
                    </form>
                </section>

                <section class="booking-panel booking-panel-service is-locked" id="booking-panel-service" aria-disabled="true">
                    <h2>2. Choose a Service</h2>
                    <p class="booking-panel-lock-hint" id="service-lock-hint">Select a date first to choose a service.</p>
                    <div class="service-list" id="service-list">
                        @foreach ($services as $service)
                            @php
                                $active = (old('service', $selectedServiceName) ?? '') === $service['name'];
                            @endphp
                            <label class="service-item {{ $active ? 'active' : '' }}">
                                <input
                                    type="radio"
                                    name="service"
                                    value="{{ $service['name'] }}"
                                    form="booking-form"
                                    {{ $active ? 'checked' : '' }}
                                    disabled
                                >
                                <span class="service-main">
                                    <span class="service-name">{{ $service['name'] }}</span>
                                    <span class="service-meta">{{ $service['duration'] }} · {{ $service['best_for'] }}</span>
                                </span>
                                <span class="service-price">{{ $service['price'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="booking-panel booking-panel-therapist is-locked {{ !empty($fromLandingTherapist) ? 'therapist-from-landing' : '' }}" id="booking-panel-therapist" aria-disabled="true">
                    <h2>3. Choose Your Therapist</h2>
                    <p class="booking-panel-lock-hint" id="therapist-lock-hint">Select a service first to choose a therapist.</p>
                    <p class="booking-therapist-hint" id="booking-therapist-hint" hidden></p>
                    <div class="therapist-list" id="therapist-list">
                        @foreach ($therapists as $therapist)
                            @php
                                $therapistActive = (old('therapist', $selectedTherapistName) ?? '') === $therapist['name'];
                                $therapistPhoto = $therapist['photo_url'] ?? asset('images/landing/therapist/' . ($therapist['photo'] ?? ''));
                                $isBookable = ! empty($therapist['is_bookable']);
                                $unavailableLabel = $therapist['unavailable_label'] ?? null;
                                $availabilityStatus = $therapist['availability_status'] ?? 'available';
                            @endphp
                            <label
                                class="therapist-item {{ $therapistActive ? 'active' : '' }} {{ $isBookable ? '' : 'is-unavailable' }}"
                                data-therapist-specialties='@json($therapist['specialties'])'
                                data-therapist-bookable="{{ $isBookable ? '1' : '0' }}"
                                data-availability-status="{{ $availabilityStatus }}"
                                data-unavailable-label="{{ $unavailableLabel ?? '' }}"
                            >
                                <input
                                    type="radio"
                                    name="therapist"
                                    value="{{ $therapist['name'] }}"
                                    form="booking-form"
                                    {{ $therapistActive && $isBookable ? 'checked' : '' }}
                                    disabled
                                >
                                <img
                                    class="therapist-item-photo"
                                    src="{{ $therapistPhoto }}"
                                    alt="{{ $therapist['name'] }}"
                                    width="52"
                                    height="52"
                                    loading="lazy"
                                >
                                <span class="therapist-item-main">
                                    <span class="therapist-item-name-row">
                                        <span class="therapist-item-name">{{ $therapist['name'] }}</span>
                                        <span class="therapist-recommend-badge">Best recommendation</span>
                                        <span class="therapist-unavailable-medal {{ $isBookable ? 'is-hidden' : 'is-visible' }}" data-therapist-unavailable-medal aria-hidden="{{ $isBookable ? 'true' : 'false' }}">
                                            <i class="bi bi-award-fill" aria-hidden="true"></i>
                                            <span data-therapist-unavailable-label>{{ $unavailableLabel ?? 'Unavailable' }}</span>
                                        </span>
                                    </span>
                                    <span class="therapist-item-role">{{ $therapist['role'] }}</span>
                                    <span class="therapist-item-tags">{{ \App\Support\TherapistSpecialtyDisplay::summaryLine($therapist['specialties'] ?? []) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>
            </div>
            </div>
        </div>
    </main>

    @include('partials.profile-transactions-modal')

    <div class="bk-modal bk-hidden" id="bkConfirmModal" role="dialog" aria-modal="true" aria-labelledby="bkConfirmTitle" aria-hidden="true">
        <div class="bk-backdrop" data-bk-close="true"></div>
        <div class="bk-dialog bk-dialog-payment" role="document">
            <div class="bk-head bk-head-payment">
                <div class="bk-head-payment-lead">
                    <span class="bk-payment-badge" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
                    <div>
                        <h3 class="bk-title" id="bkConfirmTitle">Complete your payment</h3>
                        <p class="bk-payment-tagline">Secure checkout powered by PayMongo</p>
                    </div>
                </div>
                <button type="button" class="bk-x" data-bk-close="true" aria-label="Close">&times;</button>
            </div>

            <div class="bk-body bk-payment-body">
                <div class="bk-payment-hero">
                    <div class="bk-payment-hero-main">
                        <span class="bk-payment-hero-label">Amount due</span>
                        <span class="bk-payment-hero-amount" id="bkSumAmountDue">—</span>
                        <span class="bk-payment-hero-meta">Service price <strong id="bkSumServicePrice">—</strong></span>
                    </div>
                    <div class="bk-payment-hero-type">
                        <p class="bk-payment-label">Payment type</p>
                        <div class="bk-payment-type-group" role="radiogroup" aria-label="Payment type">
                            <button type="button" class="bk-payment-type is-active" data-payment-type="downpayment">
                                <span class="bk-payment-type-title">Downpayment</span>
                                <span class="bk-payment-type-sub">50% to confirm</span>
                            </button>
                            <button type="button" class="bk-payment-type" data-payment-type="full">
                                <span class="bk-payment-type-title">Full payment</span>
                                <span class="bk-payment-type-sub">Pay in full now</span>
                            </button>
                        </div>
                        <p class="time-slots-hint" id="bkFullPaymentNotice" hidden>Appointments starting in less than 1 hour require full payment.</p>
                    </div>
                </div>

                <div class="bk-payment-layout">
                    <div class="bk-payment-left">
                        <details class="bk-payment-summary-toggle">
                            <summary class="bk-payment-summary-head">
                                <span>Booking details</span>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </summary>
                            <div class="bk-summary bk-summary-compact">
                                <div class="bk-row">
                                    <span class="bk-k">Service</span>
                                    <span class="bk-v" id="bkSumService">—</span>
                                </div>
                                <div class="bk-row">
                                    <span class="bk-k">Therapist</span>
                                    <span class="bk-v" id="bkSumTherapist">—</span>
                                </div>
                                <div class="bk-row">
                                    <span class="bk-k">Date</span>
                                    <span class="bk-v" id="bkSumDate">—</span>
                                </div>
                                <div class="bk-row">
                                    <span class="bk-k">Time</span>
                                    <span class="bk-v" id="bkSumTime">—</span>
                                </div>
                                <div class="bk-row bk-row-notes">
                                    <span class="bk-k">Notes</span>
                                    <span class="bk-v" id="bkSumNotes">—</span>
                                </div>
                            </div>
                        </details>

                        <div class="bk-payment-section">
                            <div class="bk-payment-section-head">
                                <p class="bk-payment-label">Secure online checkout</p>
                                <span class="bk-payment-step">Step 1</span>
                            </div>
                            @if (empty($paymongoEnabled))
                                <div class="bk-payment-paymongo-alert">
                                    Online payment is not configured yet. Please contact the spa to complete your booking.
                                </div>
                            @else
                                <div class="bk-payment-paymongo-panel">
                                    <div class="bk-payment-paymongo-brand">
                                        <span class="bk-payment-method-mark" aria-hidden="true" style="--bk-method-brand: #00a3e0">PM</span>
                                        <div>
                                            <p class="bk-payment-paymongo-title">Pay with PayMongo</p>
                                            <p class="bk-payment-paymongo-sub">You will be redirected to a secure PayMongo page to complete payment.</p>
                                        </div>
                                    </div>
                                    <div class="bk-payment-paymongo-channels" aria-label="Accepted payment channels">
                                        @foreach ($paymongoChannels as $channel)
                                            <article class="bk-payment-paymongo-channel-card">
                                                <div class="bk-payment-paymongo-channel-head">
                                                    <span class="bk-payment-method-mark" aria-hidden="true" style="--bk-method-brand: {{ $channel['brand'] }}">{{ $channel['initials'] }}</span>
                                                    <div>
                                                        <p class="bk-payment-paymongo-channel-title">{{ $channel['label'] }}</p>
                                                        <p class="bk-payment-paymongo-channel-note">{{ $channel['refund_note'] }}</p>
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bk-payment-right">
                        <div class="bk-qr-panel bk-paymongo-panel" id="bkQrPanel">
                            <div class="bk-qr-panel-head">
                                <p class="bk-payment-label">How it works</p>
                                <span class="bk-payment-step">Step 2</span>
                            </div>
                            <div class="bk-paymongo-steps-card">
                                <span class="bk-qr-placeholder-icon" aria-hidden="true"><i class="bi bi-credit-card-2-front"></i></span>
                                <p class="bk-qr-placeholder-title">Secure hosted checkout</p>
                                <p class="bk-qr-placeholder-sub">On PayMongo, pick <strong>GCash</strong> for wallet checkout or <strong>QR Ph</strong> to scan with any supported app.</p>
                            </div>
                            <ol class="bk-qr-steps">
                                <li>Review your booking and payment type</li>
                                <li>Continue to PayMongo to pay the exact amount</li>
                                <li>Return here automatically after payment</li>
                            </ol>
                        </div>
                        <div class="bk-paymongo-browser-notice" id="bkPaymongoBrowserNotice" hidden role="note">
                            <i class="bi bi-browser-chrome" aria-hidden="true"></i>
                            <p><strong>Open this page in Chrome or Safari before continuing.</strong> Messenger and other in-app browsers can block PayMongo’s Download QR Code button. Tap the browser menu, then choose <em>Open in browser</em>.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bk-foot bk-foot-payment">
                <p class="bk-foot-note"><i class="bi bi-lock-fill" aria-hidden="true"></i> You will be redirected to PayMongo to complete payment securely.</p>
                <div class="bk-foot-actions">
                    <button type="button" class="bk-btn bk-cancel" data-bk-close="true">Cancel</button>
                    <button type="button" class="bk-btn bk-confirm" id="bkConfirmSubmit" @if (empty($paymongoEnabled)) disabled @endif>
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                        Continue to PayMongo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="bk-modal bk-hidden" id="bkUserConflictModal" role="dialog" aria-modal="true" aria-labelledby="bkUserConflictTitle" aria-hidden="true">
        <div class="bk-backdrop" data-bk-user-conflict-close="true"></div>
        <div class="bk-dialog bk-dialog-warning" role="document">
            <div class="bk-head bk-head-warning">
                <h3 class="bk-title" id="bkUserConflictTitle">Overlapping appointment</h3>
                <button type="button" class="bk-x" data-bk-user-conflict-close="true" aria-label="Close">&times;</button>
            </div>
            <div class="bk-body">
                <p class="bk-sub">This time overlaps with an appointment you already have, so another booking is not possible until that session ends.</p>
                <div class="bk-summary bk-conflict-summary">
                    <div class="bk-row">
                        <span class="bk-k">Your appointment</span>
                        <span class="bk-v" id="bkConflictService">—</span>
                    </div>
                    <div class="bk-row">
                        <span class="bk-k">Therapist</span>
                        <span class="bk-v" id="bkConflictTherapist">—</span>
                    </div>
                    <div class="bk-row">
                        <span class="bk-k">Date</span>
                        <span class="bk-v" id="bkConflictDate">—</span>
                    </div>
                    <div class="bk-row">
                        <span class="bk-k">Time</span>
                        <span class="bk-v" id="bkConflictTime">—</span>
                    </div>
                </div>
                <p class="bk-conflict-note">Please choose a different time slot, or manage your existing appointment from your profile.</p>
            </div>
            <div class="bk-foot">
                <button type="button" class="bk-btn bk-confirm" data-bk-user-conflict-close="true">Got it</button>
            </div>
        </div>
    </div>

    @include('partials.payment-receipt-modal')
    @include('partials.payment-receipt-modal-script')

    <div class="booking-toast hidden" id="booking-toast" role="alert" aria-live="assertive">
        <i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>
        <span id="booking-toast-msg"></span>
        <button type="button" class="booking-toast-close" aria-label="Close">&times;</button>
    </div>

    <script>
        (function () {
            var slotMap = @json($slotMap);
            var allSlots = @json($allSlots);
            var therapistNames = @json($therapistNames ?? []);
            var availabilityUrl = @json($availabilityUrl ?? '');
            var therapistAvailabilityUrl = @json($therapistAvailabilityUrl ?? '');
            var serverUserConflict = @json(session('booking_user_conflict'));
            var servicePriceMap = @json($servicePriceMap ?? []);
            var slotsLoading = false;
            var availabilityController = null;
            var availabilityRequestId = 0;
            var lastAvailabilityKey = '';
            var therapistScheduleMap = {};
            var therapistScheduleDateKey = '';
            var serviceInputs = Array.from(document.querySelectorAll('input[name="service"]'));
            var therapistInputs = Array.from(document.querySelectorAll('input[name="therapist"]'));
            var hiddenSlot = document.getElementById('time_slot');
            var slotsWrap = document.getElementById('time-slots');
            var oldSlot = hiddenSlot ? hiddenSlot.value : '';
            var form = document.getElementById('booking-form');
            var dateInput = document.getElementById('booking_date');
            var notesInput = document.getElementById('notes');
            var panelService = document.getElementById('booking-panel-service');
            var panelTherapist = document.getElementById('booking-panel-therapist');

            var modal = document.getElementById('bkConfirmModal');
            var sumService = document.getElementById('bkSumService');
            var sumTherapist = document.getElementById('bkSumTherapist');
            var sumDate = document.getElementById('bkSumDate');
            var sumTime = document.getElementById('bkSumTime');
            var sumNotes = document.getElementById('bkSumNotes');
            var sumServicePrice = document.getElementById('bkSumServicePrice');
            var sumAmountDue = document.getElementById('bkSumAmountDue');
            var paymongoEnabled = @json(!empty($paymongoEnabled));
            var paymongoBrowserNotice = document.getElementById('bkPaymongoBrowserNotice');
            var paymentMethodInput = document.getElementById('payment_method');
            var paymentTypeInput = document.getElementById('payment_type');
            var paymentTypeButtons = Array.from(document.querySelectorAll('[data-payment-type]'));
            var selectedPaymentType = paymentTypeInput ? paymentTypeInput.value || 'downpayment' : 'downpayment';
            var fullPaymentRequiredSlots = [];
            var fullPaymentNotice = document.getElementById('bkFullPaymentNotice');
            var confirmBtn = document.getElementById('bkConfirmSubmit');
            var allowSubmit = false;
            var bookingToast = document.getElementById('booking-toast');
            var bookingToastMsg = document.getElementById('booking-toast-msg');
            var bookingToastTimer = null;
            var userConflictModal = document.getElementById('bkUserConflictModal');
            var conflictService = document.getElementById('bkConflictService');
            var conflictTherapist = document.getElementById('bkConflictTherapist');
            var conflictDate = document.getElementById('bkConflictDate');
            var conflictTime = document.getElementById('bkConflictTime');
            var currentUserConflicts = {};

            if (paymongoBrowserNotice && /FBAN|FBAV|FB_IAB|Messenger|Instagram/i.test(navigator.userAgent || '')) {
                paymongoBrowserNotice.hidden = false;
            }

            function hideBookingToast() {
                if (!bookingToast) return;
                bookingToast.classList.add('hidden');
                bookingToast.classList.remove('is-visible');
            }

            function showBookingToast(message) {
                if (!bookingToast || !bookingToastMsg) return;
                bookingToastMsg.textContent = message;
                bookingToast.classList.remove('hidden');
                bookingToast.classList.add('is-visible');
                if (bookingToastTimer) clearTimeout(bookingToastTimer);
                bookingToastTimer = window.setTimeout(hideBookingToast, 4200);
            }

            bookingToast?.querySelector('.booking-toast-close')?.addEventListener('click', function () {
                if (bookingToastTimer) clearTimeout(bookingToastTimer);
                hideBookingToast();
            });

            function selectedService() {
                var checked = serviceInputs.find(function (i) { return i.checked; });
                return checked ? checked.value : '';
            }

            function selectedTherapist() {
                var checked = therapistInputs.find(function (i) { return i.checked; });
                return checked ? checked.value : '';
            }

            function clearServiceSelection() {
                serviceInputs.forEach(function (input) {
                    input.checked = false;
                    delete input.dataset.uncheck;
                });
                paintServiceCards();
            }

            function clearTherapistSelection() {
                therapistInputs.forEach(function (input) {
                    input.checked = false;
                    delete input.dataset.uncheck;
                });
                applyTherapistCardStates();
            }

            function setPanelInteractive(panel, enabled, inputs) {
                if (!panel) return;
                panel.classList.toggle('is-locked', !enabled);
                panel.setAttribute('aria-disabled', enabled ? 'false' : 'true');
                inputs.forEach(function (input) {
                    var item = input.closest('.therapist-item');
                    var bookable = !item || item.dataset.therapistBookable !== '0';
                    input.disabled = !enabled || !bookable;
                });
            }

            function applyTherapistScheduleEntry(item, meta) {
                if (!item || !meta) return;
                var bookable = !!meta.bookable;
                var label = meta.label || (bookable ? '' : 'Unavailable');
                item.dataset.therapistBookable = bookable ? '1' : '0';
                item.dataset.availabilityStatus = meta.status || (bookable ? 'available' : 'off-duty');
                item.dataset.unavailableLabel = label;
                item.classList.toggle('is-unavailable', !bookable);
                var medal = item.querySelector('[data-therapist-unavailable-medal]');
                var medalLabel = item.querySelector('[data-therapist-unavailable-label]');
                if (medal) {
                    medal.classList.toggle('is-visible', !bookable);
                    medal.classList.toggle('is-hidden', bookable);
                    medal.setAttribute('aria-hidden', bookable ? 'true' : 'false');
                }
                if (medalLabel) medalLabel.textContent = label;
                var input = item.querySelector('input[name="therapist"]');
                if (input) {
                    if (!bookable) {
                        input.checked = false;
                        input.disabled = true;
                    } else {
                        input.disabled = !(dateInput && dateInput.value) || !selectedService();
                    }
                }
            }

            function applyTherapistScheduleMap(map) {
                therapistScheduleMap = map || {};
                document.querySelectorAll('.therapist-item').forEach(function (item) {
                    var input = item.querySelector('input[name="therapist"]');
                    if (!input) return;
                    var meta = therapistScheduleMap[input.value];
                    if (meta) applyTherapistScheduleEntry(item, meta);
                });
                applyTherapistCardStates();
                syncBookingSteps();
            }

            function fetchTherapistSchedule(dateKey) {
                if (!therapistAvailabilityUrl || !dateKey) {
                    return Promise.resolve(null);
                }
                if (therapistScheduleDateKey === dateKey && Object.keys(therapistScheduleMap).length) {
                    return Promise.resolve(therapistScheduleMap);
                }
                var url = new URL(therapistAvailabilityUrl, window.location.origin);
                url.searchParams.set('booking_date', dateKey);
                return fetch(url.toString(), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.ok ? res.json() : null; })
                    .then(function (data) {
                        if (!data || !data.therapists) return null;
                        therapistScheduleDateKey = dateKey;
                        applyTherapistScheduleMap(data.therapists);
                        return data.therapists;
                    })
                    .catch(function () { return null; });
            }

            function syncBookingSteps() {
                var hasDate = !!(dateInput && dateInput.value);
                var hasService = !!selectedService();

                setPanelInteractive(panelService, hasDate, serviceInputs);
                setPanelInteractive(panelTherapist, hasDate && hasService, therapistInputs);

                var serviceLockHint = document.getElementById('service-lock-hint');
                var therapistLockHint = document.getElementById('therapist-lock-hint');
                if (serviceLockHint) serviceLockHint.hidden = hasDate;
                if (therapistLockHint) therapistLockHint.hidden = hasDate && hasService;

                if (!hasDate) {
                    if (hiddenSlot) hiddenSlot.value = '';
                } else if (!hasService) {
                    if (hiddenSlot) hiddenSlot.value = '';
                }
            }

            function openModal() {
                if (!modal) return;
                modal.classList.remove('bk-hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('bk-modal-open');
            }

            function closeUserConflictModal() {
                if (!userConflictModal) return;
                userConflictModal.classList.add('bk-hidden');
                userConflictModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('bk-modal-open');
            }

            function openUserConflictModal(slot, details, dateKey) {
                if (!userConflictModal) return;
                if (conflictService) conflictService.textContent = details && details.service ? details.service : '—';
                if (conflictTherapist) conflictTherapist.textContent = details && details.therapist ? details.therapist : '—';
                if (conflictDate) conflictDate.textContent = formatDate(dateKey || (dateInput ? dateInput.value : ''));
                if (conflictTime) conflictTime.textContent = slot || '—';
                userConflictModal.classList.remove('bk-hidden');
                userConflictModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('bk-modal-open');
                userConflictModal.querySelector('.bk-confirm')?.focus();
            }

            function userConflictsForDate(dateKey) {
                if (!dateKey) return {};
                return currentUserConflicts || {};
            }

            function findUserConflict(slot, dateKey) {
                var conflicts = userConflictsForDate(dateKey);
                return conflicts[slot] || null;
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.add('bk-hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('bk-modal-open');
            }

            function formatCurrency(amount) {
                return '₱' + Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function servicePriceFor(name) {
                if (!name || !servicePriceMap) return 0;
                return Number(servicePriceMap[name] || 0);
            }

            function paymentAmountFor(serviceName, type) {
                var price = servicePriceFor(serviceName);
                if (type === 'full') return price;
                return Math.round(price * 0.5 * 100) / 100;
            }

            function updatePaymentSummary(serviceName) {
                var price = servicePriceFor(serviceName);
                var due = paymentAmountFor(serviceName, selectedPaymentType);
                if (sumServicePrice) sumServicePrice.textContent = formatCurrency(price);
                if (sumAmountDue) sumAmountDue.textContent = formatCurrency(due);
            }

            function paintPaymentTypeButtons() {
                paymentTypeButtons.forEach(function (btn) {
                    var type = btn.getAttribute('data-payment-type') || '';
                    btn.classList.toggle('is-active', type === selectedPaymentType);
                    var disabled = type === 'downpayment' && fullPaymentRequiredSlots.indexOf(hiddenSlot?.value || '') !== -1;
                    btn.disabled = disabled;
                    btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
                });
                if (paymentTypeInput) paymentTypeInput.value = selectedPaymentType;
            }

            function resetPaymentModal() {
                selectedPaymentType = paymentTypeInput?.value || 'downpayment';
                paintPaymentTypeButtons();
            }

            paymentTypeButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    selectedPaymentType = btn.getAttribute('data-payment-type') || 'downpayment';
                    paintPaymentTypeButtons();
                    updatePaymentSummary(selectedService());
                });
            });

            function formatDate(iso) {
                if (!iso) return '—';
                try {
                    var parts = iso.split('-');
                    if (parts.length !== 3) return iso;
                    var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
                } catch (e) {
                    return iso;
                }
            }

            function showConfirmModal() {
                var svc = selectedService();
                var therapist = selectedTherapist();
                var date = dateInput ? dateInput.value : '';
                var time = hiddenSlot ? hiddenSlot.value : '';
                var requiresFullPayment = fullPaymentRequiredSlots.indexOf(time) !== -1;
                if (requiresFullPayment) selectedPaymentType = 'full';
                if (fullPaymentNotice) fullPaymentNotice.hidden = !requiresFullPayment;
                paintPaymentTypeButtons();

                if (!svc) {
                    showBookingToast('Please select a service.');
                    return;
                }
                if (!therapist) {
                    showBookingToast('Please select a therapist.');
                    return;
                }

                var therapistItem = therapistInputs.find(function (i) { return i.checked; })?.closest('.therapist-item');
                if (therapistItem && (therapistItem.classList.contains('is-unavailable') || therapistItem.dataset.therapistBookable === '0')) {
                    var unavailableText = therapistItem.dataset.unavailableLabel || 'Unavailable';
                    showBookingToast(therapist + ' is ' + unavailableText.toLowerCase() + ' on the selected date.');
                    return;
                }
                if (!date) {
                    showBookingToast('Please select a date.');
                    return;
                }
                if (!time) {
                    showBookingToast('Please select a time slot.');
                    return;
                }

                var conflict = findUserConflict(time, date);
                if (conflict) {
                    openUserConflictModal(time, conflict, date);
                    return;
                }

                if (sumService) sumService.textContent = svc;
                if (sumTherapist) sumTherapist.textContent = therapist;
                if (sumDate) sumDate.textContent = formatDate(date);
                if (sumTime) sumTime.textContent = time;
                if (sumNotes) {
                    var notes = (notesInput && notesInput.value || '').trim();
                    sumNotes.textContent = notes ? notes : '—';
                }

                resetPaymentModal();
                updatePaymentSummary(svc);
                openModal();
            }

            function paintServiceCards() {
                serviceInputs.forEach(function (input) {
                    var parent = input.closest('.service-item');
                    if (!parent) return;
                    parent.classList.toggle('active', input.checked);
                });
            }

            function therapistSpecialties(item) {
                if (!item || !item.dataset.therapistSpecialties) return [];
                try {
                    var parsed = JSON.parse(item.dataset.therapistSpecialties);
                    return Array.isArray(parsed) ? parsed : [];
                } catch (e) {
                    return [];
                }
            }

            function therapistMatchesService(item, serviceName) {
                if (!serviceName) return false;
                var service = String(serviceName).trim();
                return therapistSpecialties(item).some(function (specialty) {
                    return String(specialty).trim() === service;
                });
            }

            function applyTherapistCardStates() {
                var service = selectedService();
                var hint = document.getElementById('booking-therapist-hint');
                var selected = therapistInputs.find(function (i) { return i.checked; }) || null;
                var anyMatch = false;
                var rows = [];

                therapistInputs.forEach(function (input) {
                    var item = input.closest('.therapist-item');
                    if (!item) return;
                    var unavailable = item.classList.contains('is-unavailable') || item.dataset.therapistBookable === '0';
                    var match = !!service && !unavailable && therapistMatchesService(item, service);
                    rows.push({ input: input, item: item, match: match, unavailable: unavailable });
                    if (match) anyMatch = true;
                });

                rows.forEach(function (row) {
                    var isSelected = selected === row.input;
                    var badge = row.item.querySelector('.therapist-recommend-badge');
                    var showBadge = anyMatch && row.match && !row.unavailable;

                    row.item.classList.toggle('active', isSelected);
                    row.input.disabled = row.unavailable || !(dateInput && dateInput.value) || !selectedService();

                    if (selected) {
                        row.item.classList.toggle('is-dimmed', !isSelected);
                        row.item.classList.toggle('is-recommended', false);
                    } else {
                        row.item.classList.toggle('is-dimmed', row.unavailable);
                        row.item.classList.toggle('is-recommended', anyMatch && row.match && !row.unavailable);
                    }

                    if (badge) badge.classList.toggle('is-visible', showBadge);
                });

                if (hint) {
                    if (!service) {
                        hint.hidden = true;
                        hint.textContent = '';
                    } else if (anyMatch) {
                        hint.hidden = false;
                        hint.textContent = 'Highlighted therapists specialize in ' + service + '. We recommend booking with one of them.';
                    } else {
                        hint.hidden = false;
                        hint.textContent = 'No therapist is listed as a specialist for ' + service + '. You may still choose any available therapist.';
                    }
                }
            }

            function syncTherapistRecommendations() {
                var service = selectedService();
                var anyMatch = false;

                therapistInputs.forEach(function (input) {
                    var item = input.closest('.therapist-item');
                    if (!item) return;
                    var unavailable = item.classList.contains('is-unavailable') || item.dataset.therapistBookable === '0';
                    if (!!service && !unavailable && therapistMatchesService(item, service)) {
                        anyMatch = true;
                    }
                });

                if (service && !anyMatch) {
                    therapistInputs.forEach(function (input) {
                        var item = input.closest('.therapist-item');
                        var unavailable = item && (item.classList.contains('is-unavailable') || item.dataset.therapistBookable === '0');
                        if (!unavailable) {
                            input.checked = false;
                        }
                    });
                }

                applyTherapistCardStates();
            }

            function fullyBookedSlotsForDate(dateKey, offeredSlots, fullyBookedFromApi) {
                return Array.isArray(fullyBookedFromApi) ? fullyBookedFromApi : [];
            }

            function availabilitySnapshot(data, dateKey, therapist, service) {
                return JSON.stringify({
                    dateKey: dateKey,
                    service: service,
                    therapist: therapist,
                    offered: data.offered_slots || [],
                    booked: data.booked_slots || [],
                    past: data.past_slots || [],
                    userConflicts: data.user_conflicts || {},
                    fullyBooked: data.fully_booked_slots || [],
                    therapistBusyDetails: data.therapist_busy_details || {},
                });
            }

            function resetAvailabilityCache() {
                lastAvailabilityKey = '';
            }

            function paintSlots(offeredSlots, bookedForDay, therapistName, userConflicts, dateKey, fullyBookedFromApi, therapistBusyDetails, pastSlots) {
                if (!slotsWrap || !hiddenSlot) return;

                var hint = document.getElementById('time-slots-hint');
                var selected = hiddenSlot.value;
                slotsWrap.innerHTML = '';
                currentUserConflicts = userConflicts || {};
                dateKey = dateKey || (dateInput ? dateInput.value : '');
                userConflicts = userConflicts || {};
                currentUserConflicts = userConflicts;
                therapistBusyDetails = therapistBusyDetails || {};
                pastSlots = Array.isArray(pastSlots) ? pastSlots : [];
                var fullyBooked = fullyBookedSlotsForDate(dateKey, offeredSlots, fullyBookedFromApi);

                if (!offeredSlots.length) {
                    if (hint) {
                        hint.hidden = false;
                        hint.textContent = 'No time slots are configured for this service.';
                    }
                    hiddenSlot.value = '';
                    return;
                }

                if (hint) hint.hidden = true;

                offeredSlots.forEach(function (slot) {
                    var userConflict = userConflicts && userConflicts[slot] ? userConflicts[slot] : null;
                    var isFullyBooked = fullyBooked.indexOf(slot) !== -1;
                    var therapistBooked = !isFullyBooked && bookedForDay.indexOf(slot) !== -1;
                    var isPast = pastSlots.indexOf(slot) !== -1;
                    var available = !isPast && !userConflict && !isFullyBooked && !therapistBooked;
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'time-slot';
                    if (isPast) {
                        btn.classList.add('unavailable', 'past-slot');
                        btn.disabled = true;
                        btn.setAttribute('aria-disabled', 'true');
                        btn.title = 'This time has already passed';
                    } else if (userConflict) {
                        btn.classList.add('user-conflict');
                        btn.title = 'This time overlaps with one of your existing appointments';
                        btn.addEventListener('click', function () {
                            openUserConflictModal(slot, userConflict, dateKey);
                        });
                    } else if (isFullyBooked) {
                        btn.classList.add('unavailable', 'fully-booked');
                        btn.disabled = true;
                        btn.setAttribute('aria-disabled', 'true');
                        btn.title = 'All therapists are booked at this time';
                    } else if (therapistBooked) {
                        btn.classList.add('therapist-busy');
                        btn.disabled = true;
                        btn.setAttribute('aria-disabled', 'true');
                        var busyDetail = therapistBusyDetails[slot];
                        btn.title = busyDetail && busyDetail.message
                            ? busyDetail.message
                            : (therapistName ? (therapistName + ' is not available at this time (overlaps with an existing appointment). Choose another therapist or time.') : 'This therapist is not available at this time');
                    } else if (selected === slot) {
                        btn.classList.add('active');
                    }
                    btn.textContent = slot;
                    if (available) {
                        btn.addEventListener('click', function () {
                            hiddenSlot.value = hiddenSlot.value === slot ? '' : slot;
                            paintSlots(offeredSlots, bookedForDay, therapistName, userConflicts, dateKey, fullyBooked, therapistBusyDetails, pastSlots);
                        });
                    }
                    slotsWrap.appendChild(btn);
                });

                var selectable = offeredSlots.filter(function (s) {
                    if (fullyBooked.indexOf(s) !== -1) return false;
                    if (bookedForDay.indexOf(s) !== -1) return false;
                    if (pastSlots.indexOf(s) !== -1) return false;
                    if (userConflicts && userConflicts[s]) return false;
                    return true;
                });
                if (!selectable.length) {
                    if (hint) {
                        hint.hidden = false;
                        hint.textContent = 'No open slots on this date. Try another date, therapist, or service.';
                    }
                    hiddenSlot.value = '';
                    return;
                }
                if (selectable.indexOf(hiddenSlot.value) === -1) {
                    var restore = oldSlot && selectable.indexOf(oldSlot) !== -1 ? oldSlot : '';
                    hiddenSlot.value = restore;
                    oldSlot = '';
                    if (restore) {
                        paintSlots(offeredSlots, bookedForDay, therapistName, userConflicts, dateKey, fullyBooked, therapistBusyDetails, pastSlots);
                    }
                }
            }

            function renderSlotsLocal(service, therapist, dateKey) {
                if (!slotsWrap || !hiddenSlot) return;
                var hint = document.getElementById('time-slots-hint');
                slotsWrap.innerHTML = '';
                hiddenSlot.value = '';
                if (hint) {
                    hint.hidden = false;
                    hint.textContent = 'Could not load live availability. Please check your connection and try again.';
                }
            }

            function renderSlots(options) {
                options = options || {};
                var silent = !!options.silent;

                if (!slotsWrap || !hiddenSlot) return;
                var service = selectedService();
                var therapist = selectedTherapist();
                var dateKey = dateInput ? dateInput.value : '';
                var hint = document.getElementById('time-slots-hint');

                if (!silent && availabilityController) {
                    availabilityController.abort();
                    availabilityController = null;
                    availabilityRequestId += 1;
                    slotsLoading = false;
                }

                if (!dateKey) {
                    resetAvailabilityCache();
                    if (hint) {
                        hint.hidden = false;
                        hint.textContent = 'Select a date to continue.';
                    }
                    slotsWrap.innerHTML = '';
                    hiddenSlot.value = '';
                    return;
                }
                if (!service) {
                    resetAvailabilityCache();
                    if (hint) {
                        hint.hidden = false;
                        hint.textContent = 'Select a service to view available time slots.';
                    }
                    slotsWrap.innerHTML = '';
                    hiddenSlot.value = '';
                    return;
                }
                if (!therapist) {
                    resetAvailabilityCache();
                    if (hint) {
                        hint.hidden = false;
                        hint.textContent = 'Select a therapist to view available time slots.';
                    }
                    slotsWrap.innerHTML = '';
                    hiddenSlot.value = '';
                    return;
                }

                if (!availabilityUrl) {
                    renderSlotsLocal(service, therapist, dateKey);
                    return;
                }

                if (silent && slotsLoading) return;

                availabilityController = typeof AbortController === 'function' ? new AbortController() : null;
                var requestId = ++availabilityRequestId;
                slotsLoading = true;

                if (!silent) {
                    resetAvailabilityCache();
                    if (hint) {
                        hint.hidden = false;
                        hint.textContent = 'Loading available time slots…';
                    }
                    slotsWrap.innerHTML = '';
                }

                var params = new URLSearchParams({
                    booking_date: dateKey,
                    service: service,
                    therapist: therapist,
                });

                fetch(availabilityUrl + '?' + params.toString(), {
                    signal: availabilityController ? availabilityController.signal : undefined,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(function (res) {
                        if (!res.ok) throw new Error('availability');
                        return res.json();
                    })
                    .then(function (data) {
                        if (requestId !== availabilityRequestId) return;

                        if (data.therapists && typeof data.therapists === 'object') {
                            applyTherapistScheduleMap(data.therapists);
                        } else if (data.therapist_schedule && typeof data.therapist_schedule === 'object') {
                            var selectedItem = therapistInputs.find(function (i) { return i.checked; })?.closest('.therapist-item');
                            if (selectedItem && data.therapist_schedule.bookable === false) {
                                var meta = data.therapist_schedule;
                                applyTherapistScheduleEntry(selectedItem, {
                                    bookable: false,
                                    status: meta.status || 'off-duty',
                                    label: meta.label || 'Unavailable',
                                });
                            }
                        }

                        var snapshotKey = availabilitySnapshot(data, dateKey, therapist, service);
                        if (silent && snapshotKey === lastAvailabilityKey) {
                            return;
                        }
                        lastAvailabilityKey = snapshotKey;

                        var offered = Array.isArray(data.offered_slots) ? data.offered_slots : (slotMap[service] || []);
                        var booked = Array.isArray(data.booked_slots) ? data.booked_slots : [];
                        var userConflicts = data.user_conflicts && typeof data.user_conflicts === 'object'
                            ? data.user_conflicts
                            : {};
                        var fullyBooked = Array.isArray(data.fully_booked_slots) ? data.fully_booked_slots : [];
                        var therapistBusyDetails = data.therapist_busy_details && typeof data.therapist_busy_details === 'object'
                            ? data.therapist_busy_details
                            : {};
                        var pastSlots = Array.isArray(data.past_slots) ? data.past_slots : [];
                        fullPaymentRequiredSlots = Array.isArray(data.full_payment_required_slots) ? data.full_payment_required_slots : [];
                        paintSlots(offered, booked, therapist, userConflicts, dateKey, fullyBooked, therapistBusyDetails, pastSlots);
                    })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') return;
                        if (requestId !== availabilityRequestId) return;
                        if (!silent) {
                            renderSlotsLocal(service, therapist, dateKey);
                        }
                    })
                    .finally(function () {
                        if (requestId === availabilityRequestId) {
                            slotsLoading = false;
                            availabilityController = null;
                        }
                    });
            }

            function onServiceSelectionChange() {
                hiddenSlot.value = '';
                paintServiceCards();
                syncBookingSteps();
                syncTherapistRecommendations();
                renderSlots();
            }

            document.querySelectorAll('.service-item').forEach(function (label) {
                var input = label.querySelector('input[name="service"]');
                if (!input) return;

                label.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (input.disabled) {
                        return;
                    }
                    input.checked = !input.checked;
                    onServiceSelectionChange();
                });
            });

            serviceInputs.forEach(function (input) {
                input.addEventListener('change', onServiceSelectionChange);
            });

            document.querySelectorAll('.therapist-item').forEach(function (label) {
                var input = label.querySelector('input[name="therapist"]');
                if (!input) return;

                label.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (input.disabled || label.classList.contains('is-unavailable') || label.dataset.therapistBookable === '0') {
                        var labelText = label.dataset.unavailableLabel || 'Unavailable';
                        var nameEl = label.querySelector('.therapist-item-name');
                        showBookingToast((nameEl ? nameEl.textContent : 'This therapist') + ' is ' + labelText.toLowerCase() + ' on the selected date.');
                        return;
                    }
                    input.checked = !input.checked;
                    applyTherapistCardStates();
                    renderSlots();
                });
            });

            therapistInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    applyTherapistCardStates();
                    renderSlots();
                });
            });

            dateInput?.addEventListener('change', function () {
                therapistScheduleDateKey = '';
                syncBookingSteps();
                fetchTherapistSchedule(dateInput.value).finally(function () {
                    syncTherapistRecommendations();
                    renderSlots();
                });
            });

            form?.addEventListener('submit', function (e) {
                if (allowSubmit) return;
                e.preventDefault();
                showConfirmModal();
            });

            confirmBtn?.addEventListener('click', function () {
                if (!form) return;
                if (!paymongoEnabled) {
                    showBookingToast('Online payment is not available right now.');
                    return;
                }
                if (paymentTypeInput) paymentTypeInput.value = selectedPaymentType;
                allowSubmit = true;
                closeModal();
                form.submit();
            });

            document.querySelectorAll('[data-bk-close]').forEach(function (el) {
                el.addEventListener('click', function () {
                    closeModal();
                });
            });

            document.querySelectorAll('[data-bk-user-conflict-close]').forEach(function (el) {
                el.addEventListener('click', function () {
                    closeUserConflictModal();
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (userConflictModal && !userConflictModal.classList.contains('bk-hidden')) {
                    closeUserConflictModal();
                    return;
                }
                if (!modal || modal.classList.contains('bk-hidden')) return;
                closeModal();
            });

            var successModal = document.getElementById('bkSuccessModal');

            function closeSuccessModal() {
                if (!successModal) return;
                successModal.classList.add('bk-hidden');
                successModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('bk-modal-open');
                window.location.assign(@json(route('landing')));
            }

            function openSuccessModal() {
                if (!successModal) return;
                successModal.classList.remove('bk-hidden');
                successModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('bk-modal-open');
                successModal.querySelector('.bk-confirm')?.focus();
            }

            document.querySelectorAll('[data-bk-success-close]').forEach(function (el) {
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSuccessModal();
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (successModal && !successModal.classList.contains('bk-hidden')) {
                    closeSuccessModal();
                }
            });

            if (successModal) {
                openSuccessModal();
            }

            if (serverUserConflict && serverUserConflict.time_slot) {
                openUserConflictModal(
                    serverUserConflict.time_slot,
                    {
                        service: serverUserConflict.service || '',
                        therapist: serverUserConflict.therapist || '',
                    },
                    serverUserConflict.booking_date || ''
                );
            }

            syncBookingSteps();
            paintServiceCards();

            if (dateInput && dateInput.value) {
                fetchTherapistSchedule(dateInput.value).finally(function () {
                    syncTherapistRecommendations();
                    renderSlots();
                });
            } else {
                syncTherapistRecommendations();
                renderSlots();
            }

            if (paymentTypeInput && paymentTypeInput.value) {
                selectedPaymentType = paymentTypeInput.value;
            }
            paintPaymentTypeButtons();

            setInterval(function () {
                if (selectedService() && selectedTherapist() && dateInput && dateInput.value) {
                    renderSlots({ silent: true });
                }
            }, 15000);

            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') {
                    renderSlots({ silent: true });
                }
            });
        })();
    </script>
    @include('partials.registration-onboarding-modal')
    @include('partials.profile-transactions-modal')
    @include('partials.profile-transactions-script')
    @include('partials.logout-confirm-modal')
</body>
</html>
