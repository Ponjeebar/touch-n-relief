@php
    $showCustomerNotifications = auth()->check()
        && auth()->user()->isUser()
        && ! auth()->user()->isWalkIn();

    $customerUnreadCount = 0;
    $customerNotifications = [];

    if ($showCustomerNotifications) {
        $feed = app(\App\Services\CustomerNotificationFeedService::class);
        $customerNotifications = $feed->recentForUser(auth()->user(), 10);
        $customerUnreadCount = $feed->unreadCountForUser(auth()->user());
    }
@endphp

@if ($showCustomerNotifications)
    <div
        class="customer-notifications-wrap"
        data-customer-notifications
        data-poll-url="{{ route('customer-notifications.poll') }}"
        data-mark-all-url="{{ route('customer-notifications.mark-all-read') }}"
        data-read-url-template="{{ route('customer-notifications.read', ['customerNotification' => '__ID__']) }}"
    >
        <button
            class="customer-notif-btn"
            type="button"
            aria-label="Notifications"
            aria-haspopup="true"
            aria-expanded="false"
            data-customer-notif-toggle
        >
            <i class="bi bi-bell"></i>
            <span class="customer-notif-badge" data-customer-notif-badge @if ($customerUnreadCount === 0) hidden @endif>{{ $customerUnreadCount }}</span>
        </button>

        <aside class="customer-notif-panel hidden-section" aria-label="Notifications panel" data-customer-notif-panel>
            <div class="customer-notif-panel-head">
                <h3>Notifications</h3>
                <button
                    class="customer-notif-mark-read"
                    type="button"
                    data-customer-notif-mark-all
                    @if ($customerUnreadCount === 0) disabled @endif
                >
                    Mark all read
                </button>
            </div>
            <div class="customer-notif-list" data-customer-notif-list>
                @forelse ($customerNotifications as $note)
                    <button
                        type="button"
                        class="customer-notif-item {{ ($note['is_read'] ?? false) ? '' : 'is-unread' }}"
                        data-customer-notif-item
                        data-notification-id="{{ $note['id'] ?? '' }}"
                    >
                        @php($type = $note['type'] ?? 'system')
                        <span class="customer-notif-item-icon {{ $type }}">
                            @if ($type === 'cancelled')
                                <i class="bi bi-x-circle"></i>
                            @elseif ($type === 'rescheduled')
                                <i class="bi bi-calendar2-event"></i>
                            @elseif ($type === 'reminder')
                                <i class="bi bi-alarm"></i>
                            @else
                                <i class="bi bi-bell"></i>
                            @endif
                        </span>
                        <span class="customer-notif-item-body">
                            <span class="customer-notif-item-title">{{ $note['title'] ?? 'Notification' }}</span>
                            <span class="customer-notif-item-message">{{ $note['message'] ?? '' }}</span>
                            <span class="customer-notif-item-time">{{ $note['time'] ?? '' }}</span>
                        </span>
                    </button>
                @empty
                    <div class="customer-notif-empty">No notifications yet.</div>
                @endforelse
            </div>
        </aside>
    </div>

    @once
        <div class="cn-modal" data-customer-notif-modal hidden aria-hidden="true">
            <div class="cn-modal__backdrop" data-customer-notif-modal-backdrop></div>
            <div class="cn-modal__panel" role="dialog" aria-modal="true" aria-labelledby="customer-notif-modal-title">
                <header class="cn-modal__header">
                    <div class="cn-modal__header-text">
                        <p class="cn-modal__eyebrow">Notification</p>
                        <div class="cn-modal__title-row">
                            <h3 class="cn-modal__title" id="customer-notif-modal-title" data-customer-notif-modal-title>Notification</h3>
                            <span class="cn-modal__status" data-customer-notif-modal-status>Update</span>
                        </div>
                    </div>
                    <button type="button" class="cn-modal__close" aria-label="Close" data-customer-notif-modal-close>&times;</button>
                </header>

                <div class="cn-modal__body">
                    <div class="cn-modal__note" data-customer-notif-modal-note hidden>
                        <i class="bi bi-info-circle"></i>
                        <p class="cn-modal__note-text" data-customer-notif-modal-message></p>
                    </div>

                    <div class="cn-modal__schedule" data-customer-notif-schedule-change hidden>
                        <div class="cn-modal__schedule-block">
                            <span class="cn-modal__schedule-label">Was</span>
                            <strong data-customer-notif-schedule-from></strong>
                        </div>
                        <span class="cn-modal__schedule-arrow" aria-hidden="true">→</span>
                        <div class="cn-modal__schedule-block cn-modal__schedule-block--new">
                            <span class="cn-modal__schedule-label">Now</span>
                            <strong data-customer-notif-schedule-to></strong>
                        </div>
                    </div>

                    <dl class="cn-modal__summary" data-customer-notif-modal-details></dl>
                </div>

                <footer class="cn-modal__footer">
                    <button type="button" class="cn-modal__btn" data-customer-notif-modal-close>Close</button>
                </footer>
            </div>
        </div>

        <link rel="stylesheet" href="{{ asset('css/customer-notifications.css') }}?v={{ filemtime(public_path('css/customer-notifications.css')) }}">
        <script>
            window.__tnrCustomerNotifications = @json($customerNotifications);
        </script>
        <script src="{{ asset('js/customer-notifications.js') }}" defer></script>
    @endonce
@endif
