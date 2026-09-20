@php
    $ttDayOptions = [
        0 => 'Sun',
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
    ];
    $ttScheduleDefaults = $scheduleSettings ?? ['automation_enabled' => true, 'default_working_days' => [1, 2, 3, 4, 5, 6]];
@endphp

<div class="tt-modal hidden" id="ttScheduleModal" role="dialog" aria-modal="true" aria-labelledby="ttScheduleTitle">
    <div class="tt-modal-backdrop" data-close-tt-schedule></div>
    <div class="tt-modal-content tt-modal-content-compact" role="document">
        <button class="tt-modal-close" type="button" aria-label="Close" data-close-tt-schedule>&times;</button>
        <div class="tt-modal-head">
            <div class="tt-modal-icon" aria-hidden="true"><i class="bi bi-calendar-week"></i></div>
            <div>
                <h2 id="ttScheduleTitle" class="tt-modal-title">Therapist availability</h2>
                <p class="tt-modal-sub" id="ttScheduleSubtitle">Set day off, off duty, or allow work on scheduled off days.</p>
            </div>
        </div>

        <form id="ttScheduleForm" class="tt-form">
            <input type="hidden" id="ttScheduleTherapistId" name="therapist_id" value="">
            <input type="hidden" id="ttScheduleUpdateUrl" name="update_url" value="">

            <div class="tt-field">
                <span class="tt-field-label">Availability</span>
                <div class="tt-schedule-options" role="radiogroup" aria-label="Availability type">
                    <label class="tt-schedule-option">
                        <input type="radio" name="availability_type" value="available" checked>
                        <span>Available</span>
                    </label>
                    <label class="tt-schedule-option">
                        <input type="radio" name="availability_type" value="off-duty">
                        <span>Off duty</span>
                    </label>
                    <label class="tt-schedule-option">
                        <input type="radio" name="availability_type" value="day-off">
                        <span>Day off</span>
                    </label>
                </div>
            </div>

            <div class="tt-field tt-schedule-dayoff-field hidden" id="ttScheduleDayOffField">
                <label for="ttScheduleDayOffUntil">Day off until</label>
                <input id="ttScheduleDayOffUntil" name="day_off_until" type="date" min="{{ now()->toDateString() }}">
                <small>Off duty through this date, then schedule rules apply again.</small>
            </div>

            <div class="tt-field">
                <label class="tt-check-row" for="ttScheduleWorkOnOffDay">
                    <input id="ttScheduleWorkOnOffDay" name="work_on_off_day" type="checkbox" value="1">
                    <span>Allow bookings on off duty / day off (therapist wants to work)</span>
                </label>
            </div>

            <div class="tt-field tt-field-full">
                <label class="tt-check-row" for="ttScheduleUseClinicDays">
                    <input id="ttScheduleUseClinicDays" name="use_clinic_working_days" type="checkbox" value="1" checked>
                    <span>Use clinic default working days</span>
                </label>
            </div>

            <div class="tt-field tt-field-full tt-schedule-days-field hidden" id="ttScheduleDaysField">
                <span class="tt-field-label">Working days for this therapist</span>
                <div class="tt-schedule-days" role="group" aria-label="Working days">
                    @foreach ($ttDayOptions as $dayValue => $dayLabel)
                        <label class="tt-schedule-day">
                            <input type="checkbox" name="working_days[]" value="{{ $dayValue }}" data-tt-working-day="{{ $dayValue }}">
                            <span>{{ $dayLabel }}</span>
                        </label>
                    @endforeach
                </div>
                <small>Used when clinic automation is enabled.</small>
            </div>

            <div class="tt-form-actions">
                <button class="tt-btn secondary" type="button" data-close-tt-schedule>Cancel</button>
                <button class="tt-btn primary" type="submit" id="ttScheduleSaveBtn">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    <span>Save availability</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="tt-modal hidden" id="ttScheduleSettingsModal" role="dialog" aria-modal="true" aria-labelledby="ttScheduleSettingsTitle">
    <div class="tt-modal-backdrop" data-close-tt-schedule-settings></div>
    <div class="tt-modal-content tt-modal-content-compact" role="document">
        <button class="tt-modal-close" type="button" aria-label="Close" data-close-tt-schedule-settings>&times;</button>
        <div class="tt-modal-head">
            <div class="tt-modal-icon" aria-hidden="true"><i class="bi bi-calendar2-range"></i></div>
            <div>
                <h2 id="ttScheduleSettingsTitle" class="tt-modal-title">Clinic schedule settings</h2>
                <p class="tt-modal-sub">Automate off duty outside working days for all therapists.</p>
            </div>
        </div>

        <form id="ttScheduleSettingsForm" class="tt-form">
            <div class="tt-field tt-field-full">
                <label class="tt-check-row" for="ttScheduleAutomation">
                    <input
                        id="ttScheduleAutomation"
                        name="automation_enabled"
                        type="checkbox"
                        value="1"
                        @checked($ttScheduleDefaults['automation_enabled'] ?? true)
                    >
                    <span>Automatically mark therapists off duty outside working days</span>
                </label>
            </div>

            <div class="tt-field tt-field-full">
                <span class="tt-field-label">Default working days (all therapists)</span>
                <div class="tt-schedule-days" role="group" aria-label="Default working days">
                    @foreach ($ttDayOptions as $dayValue => $dayLabel)
                        <label class="tt-schedule-day">
                            <input
                                type="checkbox"
                                name="default_working_days[]"
                                value="{{ $dayValue }}"
                                data-tt-default-working-day="{{ $dayValue }}"
                                @checked(in_array($dayValue, $ttScheduleDefaults['default_working_days'] ?? [], true))
                            >
                            <span>{{ $dayLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="tt-field tt-field-full">
                <label class="tt-check-row" for="ttScheduleApplyAll">
                    <input id="ttScheduleApplyAll" name="apply_working_days_to_all" type="checkbox" value="1">
                    <span>Apply default working days to every therapist profile</span>
                </label>
            </div>

            <div class="tt-form-actions">
                <button class="tt-btn secondary" type="button" data-close-tt-schedule-settings>Cancel</button>
                <button class="tt-btn primary" type="submit">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                    <span>Save settings</span>
                </button>
            </div>
        </form>
    </div>
</div>
