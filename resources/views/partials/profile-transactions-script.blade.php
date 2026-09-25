<script>
(function () {
    var transactionsModal = document.getElementById('tnr-transactions-modal');
    var cancelModal = document.getElementById('tnr-cancel-booking-modal');
    var rescheduleModal = document.getElementById('tnr-reschedule-booking-modal');
    var availabilityUrl = window.__tnrTxnAvailabilityUrl || '';

    if (transactionsModal && transactionsModal.parentElement !== document.body) {
        document.body.appendChild(transactionsModal);
    }

    if (cancelModal && cancelModal.parentElement !== document.body) {
        document.body.appendChild(cancelModal);
    }

    if (rescheduleModal && rescheduleModal.parentElement !== document.body) {
        document.body.appendChild(rescheduleModal);
    }

    function closeTransactionsModal() {
        if (!transactionsModal) return;
        transactionsModal.classList.add('txn-modal-hidden');
        transactionsModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('txn-modal-open');
        document.body.classList.remove('modal-open');
    }

    function openTransactionsModal() {
        if (!transactionsModal) return;
        transactionsModal.classList.remove('txn-modal-hidden');
        transactionsModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('txn-modal-open');
        transactionsModal.querySelector('.txn-modal-close')?.focus();
    }

    document.querySelectorAll('[data-tnr-open-transactions]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var profileDropdown = document.getElementById('tnr-profile-dropdown');
            var trigger = document.getElementById('tnr-profile-menu-trigger');
            var profileWrap = document.querySelector('[data-tnr-profile-wrap]');
            if (profileDropdown) {
                profileDropdown.classList.add('profile-dropdown-hidden');
                trigger?.setAttribute('aria-expanded', 'false');
                profileWrap?.classList.remove('is-menu-open');
            }

            var userMenuWrap = document.querySelector('[data-user-menu]');
            if (userMenuWrap) {
                userMenuWrap.classList.remove('open');
                userMenuWrap.querySelector('.nav-user')?.setAttribute('aria-expanded', 'false');
            }

            openTransactionsModal();
        });
    });

    document.querySelectorAll('[data-tnr-txn-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            closeTransactionsModal();
        });
    });

    document.addEventListener('click', function (e) {
        if (!transactionsModal || transactionsModal.classList.contains('txn-modal-hidden')) return;
        if (e.target.closest('.txn-modal-panel')) return;
        if (e.target.closest('.txn-cancel-panel')) return;
        if (e.target.closest('.txn-reschedule-panel')) return;
        if (cancelModal && !cancelModal.classList.contains('txn-modal-hidden')) return;
        if (rescheduleModal && !rescheduleModal.classList.contains('txn-modal-hidden')) return;
        if (e.target.closest('[data-tnr-open-transactions]')) return;
        closeTransactionsModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (cancelModal && !cancelModal.classList.contains('txn-modal-hidden')) {
            closeCancelModal();
            return;
        }
        if (rescheduleModal && !rescheduleModal.classList.contains('txn-modal-hidden')) {
            closeRescheduleModal();
            return;
        }
        if (transactionsModal && !transactionsModal.classList.contains('txn-modal-hidden')) {
            closeTransactionsModal();
        }
    });

    var dateInput = document.getElementById('txn-date-filter');
    var searchInput = document.getElementById('txn-search');
    var filterSelect = document.getElementById('txn-filter-select');
    var sortSelect = document.getElementById('txn-sort-select');
    var filterEmpty = document.getElementById('txn-filter-empty');
    var cardsList = document.getElementById('txn-cards-list');

    function compareByRecent(a, b, ascending) {
        var actA = Number(a.getAttribute('data-activity-ts')) || Number(a.getAttribute('data-sort-ts')) || 0;
        var actB = Number(b.getAttribute('data-activity-ts')) || Number(b.getAttribute('data-sort-ts')) || 0;
        if (actA !== actB) {
            return ascending ? actA - actB : actB - actA;
        }
        var tsA = Number(a.getAttribute('data-sort-ts')) || 0;
        var tsB = Number(b.getAttribute('data-sort-ts')) || 0;
        if (tsA !== tsB) {
            return ascending ? tsA - tsB : tsB - tsA;
        }
        var dateA = a.getAttribute('data-sort-date') || '';
        var dateB = b.getAttribute('data-sort-date') || '';
        if (dateA !== dateB) {
            return ascending ? dateA.localeCompare(dateB) : dateB.localeCompare(dateA);
        }
        return 0;
    }

    function sortTransactions(mode) {
        if (!cardsList) return;
        var cards = Array.from(cardsList.querySelectorAll('.txn-card'));
        cards.sort(function (a, b) {
            var tsA = Number(a.getAttribute('data-sort-ts')) || 0;
            var tsB = Number(b.getAttribute('data-sort-ts')) || 0;
            var actA = Number(a.getAttribute('data-activity-ts')) || tsA;
            var actB = Number(b.getAttribute('data-activity-ts')) || tsB;
            var amtA = Number(a.getAttribute('data-amount-raw')) || 0;
            var amtB = Number(b.getAttribute('data-amount-raw')) || 0;

            if (mode === 'date-asc') {
                return tsA - tsB;
            }
            if (mode === 'amount-desc') {
                return (amtB - amtA) || (actB - actA);
            }
            if (mode === 'amount-asc') {
                return (amtA - amtB) || (actB - actA);
            }
            if (mode === 'service-asc') {
                var serviceA = a.getAttribute('data-service') || '';
                var serviceB = b.getAttribute('data-service') || '';
                return serviceA.localeCompare(serviceB) || compareByRecent(a, b, false);
            }
            return compareByRecent(a, b, false);
        });
        cards.forEach(function (card) {
            cardsList.appendChild(card);
        });
    }

    function applySearchAndFilter() {
        if (!cardsList) return;
        var query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
        var chosenDate = (dateInput && dateInput.value ? dateInput.value : '').trim();
        var filter = filterSelect ? filterSelect.value : 'all';
        var visible = 0;

        cardsList.querySelectorAll('.txn-card').forEach(function (card) {
            var haystack = [
                card.getAttribute('data-service') || '',
                card.getAttribute('data-therapist') || '',
                card.getAttribute('data-sort-date') || '',
                card.getAttribute('data-time') || '',
                card.getAttribute('data-duration') || ''
            ].join(' ');

            var matchesQuery = !query || haystack.indexOf(query) !== -1;
            var matchesDate = !chosenDate || (card.getAttribute('data-sort-date') || '') === chosenDate;
            var hasAmount = (card.getAttribute('data-has-amount') || '0') === '1';
            var matchesFilter = filter === 'all'
                || (filter === 'with-amount' && hasAmount)
                || (filter === 'without-amount' && !hasAmount);

            var show = matchesQuery && matchesDate && matchesFilter;
            card.hidden = !show;
            if (show) visible += 1;
        });

        if (filterEmpty) {
            filterEmpty.classList.toggle('txn-hidden', visible > 0);
        }

        if (sortSelect) {
            sortTransactions(sortSelect.value);
        }
    }

    sortSelect?.addEventListener('change', function () {
        sortTransactions(sortSelect.value);
    });

    searchInput?.addEventListener('input', function () {
        applySearchAndFilter();
    });

    dateInput?.addEventListener('change', function () {
        applySearchAndFilter();
    });

    filterSelect?.addEventListener('change', function () {
        applySearchAndFilter();
    });

    applySearchAndFilter();

    var cancelForm = document.getElementById('tnr-cancel-booking-form');
    var cancelError = document.getElementById('tnr-cancel-error');
    var cancelRef = document.getElementById('tnr-cancel-booking-ref');
    var cancelRefundNotice = document.getElementById('tnr-cancel-refund-notice');
    var cancelOther = document.getElementById('tnr-cancel-other-text');
    var cancelOtherRadio = document.getElementById('tnr-cancel-reason-other');
    var cancelSubmit = document.getElementById('tnr-cancel-submit');
    var activeCancelUrl = '';
    var activeCancelCard = null;
    var txnStatusToast = document.getElementById('txn-status-toast');
    var txnStatusToastMsg = document.getElementById('txn-status-toast-msg');
    var txnStatusToastTimer = null;

    if (txnStatusToast && txnStatusToast.parentElement !== document.body) {
        document.body.appendChild(txnStatusToast);
    }

    function hideTxnStatusToast() {
        if (!txnStatusToast) return;
        txnStatusToast.classList.remove('is-visible');
        txnStatusToast.classList.add('hidden');
    }

    function showTxnStatusToast(message) {
        if (!txnStatusToast || !txnStatusToastMsg) return;
        txnStatusToastMsg.textContent = message;
        txnStatusToast.classList.remove('hidden');
        txnStatusToast.classList.add('is-visible');
        if (txnStatusToastTimer) clearTimeout(txnStatusToastTimer);
        txnStatusToastTimer = window.setTimeout(hideTxnStatusToast, 4500);
    }

    txnStatusToast?.querySelector('.txn-status-toast-close')?.addEventListener('click', function () {
        if (txnStatusToastTimer) clearTimeout(txnStatusToastTimer);
        hideTxnStatusToast();
    });

    function closeCancelModal() {
        if (!cancelModal) return;
        cancelModal.classList.add('txn-modal-hidden');
        cancelModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('txn-cancel-open');
        activeCancelUrl = '';
        activeCancelCard = null;
        if (cancelForm) cancelForm.reset();
        if (cancelOther) {
            cancelOther.hidden = true;
            cancelOther.value = '';
        }
        if (cancelRefundNotice) {
            cancelRefundNotice.textContent = '';
            cancelRefundNotice.classList.add('txn-hidden');
        }
        if (cancelError) {
            cancelError.textContent = '';
            cancelError.classList.add('txn-hidden');
        }
    }

    function openCancelModal(url, label, card, btn) {
        if (!cancelModal || !url) return;
        activeCancelUrl = url;
        activeCancelCard = card || null;
        if (cancelRef) cancelRef.textContent = label || '';
        if (cancelRefundNotice) {
            var isRefundable = btn && btn.getAttribute('data-is-refundable') === '1';
            var paymentAmount = btn ? (btn.getAttribute('data-payment-amount') || '') : '';
            var refundNote = btn ? (btn.getAttribute('data-refund-note') || '') : '';
            if (isRefundable && paymentAmount) {
                cancelRefundNotice.textContent = refundNote !== ''
                    ? refundNote
                    : 'This booking was paid (' + paymentAmount + '). If you cancel, a refund will be processed to your original payment method.';
                cancelRefundNotice.classList.remove('txn-hidden');
            } else {
                cancelRefundNotice.textContent = '';
                cancelRefundNotice.classList.add('txn-hidden');
            }
        }
        cancelModal.classList.remove('txn-modal-hidden');
        cancelModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('txn-cancel-open');
        cancelModal.querySelector('.txn-cancel-back')?.focus();
    }

    document.querySelectorAll('[data-tnr-cancel-booking]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var card = btn.closest('.txn-card');
            openCancelModal(
                btn.getAttribute('data-cancel-url') || '',
                btn.getAttribute('data-booking-label') || '',
                card,
                btn
            );
        });
    });

    document.querySelectorAll('[data-tnr-cancel-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeCancelModal();
        });
    });

    function syncCancelOtherField() {
        if (!cancelOther || !cancelOtherRadio) return;
        var show = cancelOtherRadio.checked;
        cancelOther.hidden = !show;
        if (!show) cancelOther.value = '';
    }

    cancelForm?.querySelectorAll('input[name="cancellation_reason"]').forEach(function (input) {
        input.addEventListener('change', syncCancelOtherField);
    });

    function showCancelError(message) {
        if (!cancelError) return;
        cancelError.textContent = message;
        cancelError.classList.remove('txn-hidden');
    }

    function markCardCancelled(card, reason, refundData) {
        if (!card) return;
        card.classList.remove('txn-card-cancellable');
        var badge = card.querySelector('.txn-badge');
        if (badge) {
            badge.textContent = 'Cancelled booking';
            badge.className = 'txn-badge txn-badge-cancelled';
        }
        var actions = card.querySelector('.txn-card-actions');
        if (actions) actions.remove();
        if (reason) {
            var existing = card.querySelector('.txn-cancel-reason');
            if (!existing) {
                var p = document.createElement('p');
                p.className = 'txn-cancel-reason';
                var label = document.createElement('span');
                label.className = 'txn-cancel-reason-label';
                label.textContent = 'Cancellation reason:';
                p.appendChild(label);
                p.appendChild(document.createTextNode(' ' + reason));
                card.appendChild(p);
            }
        }
        if (refundData && refundData.refund_status && refundData.refund_status !== 'not_applicable') {
            var paymentBlock = card.querySelector('.txn-payment-grid');
            if (paymentBlock && !paymentBlock.querySelector('.txn-payment-item-refund')) {
                var refundItem = document.createElement('div');
                refundItem.className = 'txn-payment-item txn-payment-item-refund';
                var refundLabel = document.createElement('dt');
                refundLabel.textContent = 'Refund';
                var refundValue = document.createElement('dd');
                var refundStatus = document.createElement('span');
                refundStatus.className = 'txn-refund-status txn-refund-status--' + (refundData.refund_status || '');
                refundStatus.textContent = (refundData.refund_status_label || 'Refund')
                    + (refundData.refund_amount ? ' · ' + refundData.refund_amount : '');
                refundValue.appendChild(refundStatus);
                if (refundData.refund_reference) {
                    var refundRef = document.createElement('span');
                    refundRef.className = 'txn-refund-ref';
                    refundRef.textContent = 'Ref: ' + refundData.refund_reference;
                    refundValue.appendChild(refundRef);
                }
                if (refundData.refund_note) {
                    var refundNote = document.createElement('span');
                    refundNote.className = 'txn-refund-note';
                    refundNote.textContent = refundData.refund_note;
                    refundValue.appendChild(refundNote);
                }
                refundItem.appendChild(refundLabel);
                refundItem.appendChild(refundValue);
                paymentBlock.appendChild(refundItem);
            }
            var balanceItem = card.querySelector('.txn-payment-item-balance');
            if (balanceItem) {
                balanceItem.remove();
            }
            var statusPill = card.querySelector('.txn-payment-status');
            if (statusPill) {
                var displayedRefundStatus = refundData.refund_status === 'processed'
                    ? 'refunded'
                    : refundData.refund_status;
                statusPill.textContent = refundData.refund_status_label || 'Refund pending';
                statusPill.className = 'txn-payment-status txn-payment-status--' + displayedRefundStatus;
            }
        }
    }

    cancelForm?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!activeCancelUrl) return;

        var reasonInput = cancelForm.querySelector('input[name="cancellation_reason"]:checked');
        if (!reasonInput) {
            showCancelError('Please choose a reason for cancelling.');
            return;
        }

        if (cancelError) {
            cancelError.textContent = '';
            cancelError.classList.add('txn-hidden');
        }

        var tokenInput = cancelForm.querySelector('input[name="_token"]');
        var formData = new FormData();
        formData.append('cancellation_reason', reasonInput.value);
        if (reasonInput.value === 'other' && cancelOther) {
            formData.append('cancellation_other', cancelOther.value.trim());
        }
        if (tokenInput) formData.append('_token', tokenInput.value);

        if (cancelSubmit) cancelSubmit.disabled = true;

        fetch(activeCancelUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                }).catch(function () {
                    return { ok: res.ok, data: {} };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var errors = result.data && result.data.errors;
                    var msg = 'Unable to cancel this booking. Please try again.';
                    if (errors && typeof errors === 'object') {
                        msg = Object.keys(errors).map(function (key) {
                            var val = errors[key];
                            return Array.isArray(val) ? val.join(' ') : String(val);
                        }).join(' ');
                    } else if (result.data && result.data.message) {
                        msg = result.data.message;
                    }
                    showCancelError(msg);
                    return;
                }
                markCardCancelled(activeCancelCard, result.data.cancellation_reason || '', result.data);
                closeCancelModal();
                showTxnStatusToast(
                    (result.data && result.data.message)
                        ? result.data.message
                        : 'Your schedule has been cancelled.'
                );
            })
            .catch(function () {
                showCancelError('Unable to cancel this booking. Please check your connection and try again.');
            })
            .finally(function () {
                if (cancelSubmit) cancelSubmit.disabled = false;
            });
    });

    @if (session('txn_status'))
        showTxnStatusToast(@json(session('txn_status')));
    @endif

    var rescheduleDate = document.getElementById('tnr-reschedule-date');
    var rescheduleSlots = document.getElementById('tnr-reschedule-slots');
    var rescheduleSlotsHint = document.getElementById('tnr-reschedule-slots-hint');
    var rescheduleTimeInput = document.getElementById('tnr-reschedule-time-slot');
    var rescheduleError = document.getElementById('tnr-reschedule-error');
    var rescheduleSubmit = document.getElementById('tnr-reschedule-submit');
    var rescheduleRef = document.getElementById('tnr-reschedule-booking-ref');
    var rescheduleService = document.getElementById('tnr-reschedule-service');
    var rescheduleTherapist = document.getElementById('tnr-reschedule-therapist');
    var activeRescheduleUrl = '';
    var activeRescheduleCard = null;
    var activeRescheduleBookingId = '';
    var activeRescheduleService = '';
    var activeRescheduleTherapist = '';
    var activeRescheduleOriginalDate = '';
    var activeRescheduleOriginalTime = '';
    var rescheduleSlotsLoading = false;

    function normalizeRescheduleSlot(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function isCurrentAppointmentSlot(slot) {
        if (!rescheduleDate || !activeRescheduleOriginalDate || !activeRescheduleOriginalTime) {
            return false;
        }
        if (rescheduleDate.value !== activeRescheduleOriginalDate) {
            return false;
        }

        return normalizeRescheduleSlot(slot) === normalizeRescheduleSlot(activeRescheduleOriginalTime);
    }

    function hasScheduleChanged() {
        if (!rescheduleDate || !rescheduleTimeInput) {
            return false;
        }
        if (rescheduleDate.value !== activeRescheduleOriginalDate) {
            return true;
        }

        return normalizeRescheduleSlot(rescheduleTimeInput.value) !== normalizeRescheduleSlot(activeRescheduleOriginalTime);
    }

    function showRescheduleError(message) {
        if (!rescheduleError) return;
        rescheduleError.textContent = message;
        rescheduleError.classList.remove('txn-hidden');
    }

    function clearRescheduleError() {
        if (!rescheduleError) return;
        rescheduleError.textContent = '';
        rescheduleError.classList.add('txn-hidden');
    }

    function closeRescheduleModal() {
        if (!rescheduleModal) return;
        rescheduleModal.classList.add('txn-modal-hidden');
        rescheduleModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('txn-reschedule-open');
        activeRescheduleUrl = '';
        activeRescheduleCard = null;
        activeRescheduleBookingId = '';
        activeRescheduleOriginalDate = '';
        activeRescheduleOriginalTime = '';
        if (rescheduleTimeInput) rescheduleTimeInput.value = '';
        if (rescheduleSlots) rescheduleSlots.innerHTML = '';
        clearRescheduleError();
    }

    function updateRescheduleSubmitState() {
        if (!rescheduleSubmit) return;
        rescheduleSubmit.disabled = !(
            rescheduleDate && rescheduleDate.value
            && rescheduleTimeInput && rescheduleTimeInput.value
            && activeRescheduleUrl
        );
    }

    function paintRescheduleSlots(offered, booked, userConflicts) {
        if (!rescheduleSlots || !rescheduleTimeInput) return;
        rescheduleSlots.innerHTML = '';
        var selected = rescheduleTimeInput.value;

        if (!offered.length) {
            if (rescheduleSlotsHint) {
                rescheduleSlotsHint.hidden = false;
                rescheduleSlotsHint.textContent = 'No time slots are available for this date.';
            }
            updateRescheduleSubmitState();
            return;
        }

        if (rescheduleSlotsHint) rescheduleSlotsHint.hidden = true;

        offered.forEach(function (slot) {
            var userConflict = userConflicts && userConflicts[slot];
            var therapistBooked = booked.indexOf(slot) !== -1;
            var isCurrentSlot = isCurrentAppointmentSlot(slot);
            var unavailable = userConflict || therapistBooked || isCurrentSlot;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'txn-reschedule-slot' + (unavailable ? ' is-unavailable' : '') + (selected === slot ? ' is-active' : '');
            btn.textContent = slot;
            btn.disabled = unavailable;
            if (isCurrentSlot) {
                btn.title = 'This is your current appointment time. Choose a different slot.';
            } else if (userConflict) {
                btn.title = 'Overlaps with another appointment you have';
            } else if (therapistBooked) {
                btn.title = 'Therapist is not available at this time (overlaps with an existing appointment)';
            }
            if (!unavailable) {
                btn.addEventListener('click', function () {
                    rescheduleTimeInput.value = rescheduleTimeInput.value === slot ? '' : slot;
                    paintRescheduleSlots(offered, booked, userConflicts);
                    updateRescheduleSubmitState();
                });
            }
            rescheduleSlots.appendChild(btn);
        });

        updateRescheduleSubmitState();
    }

    function loadRescheduleSlots() {
        if (!availabilityUrl || !rescheduleDate || !activeRescheduleService || !activeRescheduleTherapist) return;
        if (!rescheduleDate.value) {
            if (rescheduleSlots) rescheduleSlots.innerHTML = '';
            if (rescheduleSlotsHint) {
                rescheduleSlotsHint.hidden = false;
                rescheduleSlotsHint.textContent = 'Select a date to see available times.';
            }
            updateRescheduleSubmitState();
            return;
        }
        if (rescheduleSlotsLoading) return;

        rescheduleSlotsLoading = true;
        if (rescheduleSlotsHint) {
            rescheduleSlotsHint.hidden = false;
            rescheduleSlotsHint.textContent = 'Loading available times…';
        }
        if (rescheduleSlots) rescheduleSlots.innerHTML = '';
        if (rescheduleTimeInput) rescheduleTimeInput.value = '';

        var params = new URLSearchParams({
            booking_date: rescheduleDate.value,
            service: activeRescheduleService,
            therapist: activeRescheduleTherapist,
        });
        if (activeRescheduleBookingId) {
            params.set('exclude_booking_id', activeRescheduleBookingId);
        }

        fetch(availabilityUrl + '?' + params.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) throw new Error('availability');
                return res.json();
            })
            .then(function (data) {
                if (data.store_closed) {
                    if (rescheduleSlotsHint) {
                        rescheduleSlotsHint.hidden = false;
                        rescheduleSlotsHint.textContent = 'The spa is closed on this date.';
                    }
                    updateRescheduleSubmitState();
                    return;
                }
                var offered = Array.isArray(data.offered_slots) ? data.offered_slots : [];
                var booked = Array.isArray(data.booked_slots) ? data.booked_slots : [];
                var userConflicts = data.user_conflicts && typeof data.user_conflicts === 'object' ? data.user_conflicts : {};
                paintRescheduleSlots(offered, booked, userConflicts);
            })
            .catch(function () {
                showRescheduleError('Could not load time slots. Please try again.');
            })
            .finally(function () {
                rescheduleSlotsLoading = false;
            });
    }

    function openRescheduleModal(btn) {
        if (!rescheduleModal || !btn) return;
        activeRescheduleUrl = btn.getAttribute('data-reschedule-url') || '';
        activeRescheduleCard = btn.closest('.txn-card');
        activeRescheduleBookingId = btn.getAttribute('data-booking-id') || '';
        activeRescheduleService = btn.getAttribute('data-service') || '';
        activeRescheduleTherapist = btn.getAttribute('data-therapist') || '';
        activeRescheduleOriginalDate = btn.getAttribute('data-booking-date') || '';
        activeRescheduleOriginalTime = btn.getAttribute('data-time-slot') || '';
        var currentDate = activeRescheduleOriginalDate;

        if (rescheduleRef) rescheduleRef.textContent = btn.getAttribute('data-booking-label') || '';
        if (rescheduleService) rescheduleService.textContent = activeRescheduleService || '—';
        if (rescheduleTherapist) rescheduleTherapist.textContent = activeRescheduleTherapist || '—';
        if (rescheduleDate) rescheduleDate.value = currentDate;
        if (rescheduleTimeInput) rescheduleTimeInput.value = '';

        clearRescheduleError();
        rescheduleModal.classList.remove('txn-modal-hidden');
        rescheduleModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('txn-reschedule-open');
        loadRescheduleSlots();
        rescheduleModal.querySelector('.txn-reschedule-back')?.focus();
    }

    function updateCardAfterReschedule(card, booking) {
        if (!card || !booking) return;
        card.setAttribute('data-sort-date', booking.booking_date || '');
        card.setAttribute('data-sort-ts', String(booking.sort_ts || 0));
        card.setAttribute('data-activity-ts', String(booking.activity_ts || booking.sort_ts || 0));
        card.setAttribute('data-time', String(booking.time_slot || '').toLowerCase());

        var metaItems = card.querySelectorAll('.txn-meta > div');
        metaItems.forEach(function (item) {
            var label = item.querySelector('dt');
            var value = item.querySelector('dd');
            if (!label || !value) return;
            if (label.textContent.trim() === 'Date') {
                value.textContent = booking.date_display || booking.booking_date;
            }
            if (label.textContent.trim() === 'Time') {
                value.textContent = booking.time_slot || '—';
            }
        });

        var rescheduleBtn = card.querySelector('[data-tnr-reschedule-booking]');
        if (rescheduleBtn) {
            rescheduleBtn.setAttribute('data-booking-date', booking.booking_date || '');
            rescheduleBtn.setAttribute('data-time-slot', booking.time_slot || '');
        }

        if (sortSelect) {
            sortTransactions(sortSelect.value);
        }
    }

    document.querySelectorAll('[data-tnr-reschedule-booking]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openRescheduleModal(btn);
        });
    });

    document.querySelectorAll('[data-tnr-reschedule-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            closeRescheduleModal();
        });
    });

    rescheduleDate?.addEventListener('change', function () {
        if (rescheduleTimeInput) rescheduleTimeInput.value = '';
        clearRescheduleError();
        loadRescheduleSlots();
    });

    rescheduleSubmit?.addEventListener('click', function () {
        if (!activeRescheduleUrl || !rescheduleDate || !rescheduleTimeInput) return;
        if (!rescheduleDate.value || !rescheduleTimeInput.value) {
            showRescheduleError('Please choose a date and time.');
            return;
        }
        if (!hasScheduleChanged()) {
            showRescheduleError('Choose a different date or time than your current appointment.');
            return;
        }

        clearRescheduleError();
        rescheduleSubmit.disabled = true;

        var token = document.querySelector('#tnr-cancel-booking-form input[name="_token"]')
            || document.querySelector('meta[name="csrf-token"]');
        var csrf = token ? (token.value || token.getAttribute('content')) : '';

        fetch(activeRescheduleUrl, {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                booking_date: rescheduleDate.value,
                time_slot: rescheduleTimeInput.value,
            }),
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                }).catch(function () {
                    return { ok: res.ok, data: {} };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    var errors = result.data && result.data.errors;
                    var msg = 'Unable to reschedule this booking. Please try again.';
                    if (errors && typeof errors === 'object') {
                        msg = Object.keys(errors).map(function (key) {
                            var val = errors[key];
                            return Array.isArray(val) ? val.join(' ') : String(val);
                        }).join(' ');
                    } else if (result.data && result.data.message) {
                        msg = result.data.message;
                    }
                    showRescheduleError(msg);
                    return;
                }
                updateCardAfterReschedule(activeRescheduleCard, result.data.booking || null);
                closeRescheduleModal();
                showTxnStatusToast(result.data.message || 'Your appointment has been rescheduled.');
            })
            .catch(function () {
                showRescheduleError('Unable to reschedule. Please check your connection and try again.');
            })
            .finally(function () {
                updateRescheduleSubmitState();
            });
    });
})();
</script>
