<aside class="appointments-side">
    @php
        $monthForHeader = \Carbon\Carbon::createFromDate(
            (int) ($calendarYear ?? now()->year),
            (int) ($calendarMonth ?? now()->month),
            1
        );
        $selectedForCalendar = isset($selectedDateIso)
            ? \Carbon\Carbon::createFromFormat('Y-m-d', (string) $selectedDateIso)
            : now()->startOfDay();
        $hasExplicitSelected = isset($selectedDateIso) && request()->has('date');
        $calendarStart = $monthForHeader->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $calendarEnd = $monthForHeader->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
    @endphp
    <article class="data-card side-card side-card-calendar">
        <div class="side-card-head">
            <h3>Calendar</h3>
            <div class="month-switch" aria-label="Month navigation">
                <button class="month-btn" type="button" aria-label="Previous month" data-prev-month="true"><i class="bi bi-chevron-left"></i></button>
                <span class="month-label" data-calendar-month-label="true">{{ $monthForHeader->format('F Y') }}</span>
                <button class="month-btn" type="button" aria-label="Next month" data-next-month="true"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>

        <div
            class="calendar"
            role="grid"
            aria-label="Calendar"
            data-month="{{ $calendarMonth ?? 6 }}"
            data-year="{{ $calendarYear ?? 2026 }}"
            data-selected-date-iso="{{ $selectedDateIso ?? '2026-06-10' }}"
        >
            <div class="cal-row cal-head" role="row">
                <span role="columnheader">Sun</span>
                <span role="columnheader">Mon</span>
                <span role="columnheader">Tue</span>
                <span role="columnheader">Wed</span>
                <span role="columnheader">Thu</span>
                <span role="columnheader">Fri</span>
                <span role="columnheader">Sat</span>
            </div>
            @for ($weekStart = $calendarStart->copy(); $weekStart->lte($calendarEnd); $weekStart->addWeek())
                <div class="cal-row" role="row">
                    @for ($i = 0; $i < 7; $i++)
                        @php
                            $cellDate = $weekStart->copy()->addDays($i);
                            $classes = [];
                            if (! $cellDate->isSameMonth($monthForHeader)) $classes[] = 'muted';
                            if (! $hasExplicitSelected && $cellDate->isToday()) $classes[] = 'today';
                            if ($cellDate->isSameDay($selectedForCalendar)) $classes[] = 'selected';
                        @endphp
                        <span
                            class="{{ implode(' ', $classes) }}"
                            role="gridcell"
                            data-date-iso="{{ $cellDate->format('Y-m-d') }}"
                        >{{ $cellDate->day }}</span>
                    @endfor
                </div>
            @endfor
        </div>
    </article>

    <article class="data-card side-card side-card-notifications" id="appointments-notifications">
        <div class="side-card-head">
            <h3>Notifications</h3>
            <button class="icon-btn slim" type="button" aria-label="Mark all as read" data-mark-all-read="true" title="Mark all as read">
                <i class="bi bi-check2-all"></i>
            </button>
        </div>

        <div class="notifications" aria-label="Notifications list">
            @forelse (($notifications ?? []) as $note)
                <a
                    class="note note-{{ $note['type'] ?? 'system' }} note-unread"
                    href="{{ $note['url'] ?? route('appointments.index') }}"
                    data-notif-at="{{ $note['notification_at'] ?? '' }}"
                    data-notif-key="{{ $note['notification_key'] ?? '' }}"
                    aria-label="{{ $note['title'] ?? 'Notification' }}"
                >
                    <div class="note-icon" aria-hidden="true">
                        @php($type = $note['type'] ?? 'system')
                        @if ($type === 'confirmed')
                            <i class="bi bi-check-circle"></i>
                        @elseif ($type === 'cancelled')
                            <i class="bi bi-x-circle"></i>
                        @elseif ($type === 'rescheduled')
                            <i class="bi bi-calendar2-event"></i>
                        @else
                            <i class="bi bi-bell"></i>
                        @endif
                    </div>
                    <div class="note-body">
                        <div class="note-top">
                            <div class="note-title">{{ $note['title'] ?? 'Notification' }}</div>
                            <div class="note-time">{{ $note['time'] ?? '' }}</div>
                        </div>
                        <div class="note-message">{{ $note['message'] ?? '' }}</div>
                    </div>
                </a>
            @empty
                <div class="empty-users">No notifications.</div>
            @endforelse
        </div>
    </article>
</aside>

