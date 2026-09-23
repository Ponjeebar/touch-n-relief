<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Therapist monitoring">
    <title>Therapist Monitoring</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/therapist-tracking.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    @include('partials.staff-mobile-style')
</head>
<body>
    <div class="app-shell">
        <div class="dashboard">
            <aside class="sidebar">
                <div class="brand">
                    <span class="brand-logo" aria-hidden="false">
                        <img src="{{ asset('images/dashboard/logo.png') }}" alt="TOUCHnRELIEF logo" class="brand-logo-img">
                    </span>
                    <span class="brand-copy">
                        <span class="brand-text">TOUCHnRELIEF</span>
                        <span class="brand-subtext">Appointment and Record Management System</span>
                    </span>
                </div>
                @include('partials.sidebar-nav', ['active' => (auth()->user()->isAdmin() && request()->boolean('manage')) ? 'therapist-manage' : 'therapist-monitoring'])
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
                    </form>
                </div>
            </aside>

            <main class="main tt-main">
                <header class="tt-hero tt-hero-with-profile" aria-label="Therapist monitoring header">
                    <div class="tt-hero-text">
                        @include('partials.topbar-panel-badge')
                        <h1 id="tt-page-title">Therapist Monitoring</h1>
                        <div class="tt-subtitle" id="tt-page-subtitle">Monitor masseuse availability and service hours</div>
                    </div>
                    <div class="right tt-hero-actions">
                        @include('partials.topbar-notifications')
                        @include('partials.topbar-settings')
                        @include('partials.topbar-profile')
                    </div>
                </header>

                @include('partials.status-toast')

                <section class="tt-grid" id="tt-tracking-panel" aria-label="Therapist monitoring content">
                    <section class="tt-metrics" aria-label="Therapist monitoring metrics">
                        <article class="tt-metric">
                            <div class="top">
                                <div>
                                    <div class="label">Total Therapists</div>
                                    <div class="value">{{ $stats['total_therapists'] ?? 0 }}</div>
                                </div>
                                <div class="icon" aria-hidden="true"><i class="bi bi-people"></i></div>
                            </div>
                            <div class="sub">Active staff members</div>
                        </article>
                        <article class="tt-metric">
                            <div class="top">
                                <div>
                                    <div class="label">Available Now</div>
                                    <div class="value">{{ $stats['available_now'] ?? 0 }}</div>
                                </div>
                                <div class="icon" aria-hidden="true"><i class="bi bi-check-circle"></i></div>
                            </div>
                            <div class="sub">Ready for appointments</div>
                        </article>
                        <article class="tt-metric">
                            <div class="top">
                                <div>
                                    <div class="label">Currently Busy</div>
                                    <div class="value">{{ $stats['currently_busy'] ?? 0 }}</div>
                                </div>
                                <div class="icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></div>
                            </div>
                            <div class="sub">In active sessions</div>
                        </article>
                        <article class="tt-metric">
                            <div class="top">
                                <div>
                                    <div class="label">Avg. Service Hours</div>
                                    <div class="value">{{ $stats['avg_service_hours'] ?? 0 }}</div>
                                </div>
                                <div class="icon" aria-hidden="true"><i class="bi bi-clock"></i></div>
                            </div>
                            <div class="sub">Hours per therapist</div>
                        </article>
                    </section>

                    <article class="tt-card">
                        <div class="tt-card-head">
                            <h3>Therapist Directory</h3>
                            @if (auth()->user()->isAdmin())
                                <div class="tt-card-head-actions">
                                    <button class="tt-manage-btn" type="button" data-open-tt-schedule-settings>
                                        <i class="bi bi-calendar2-range" aria-hidden="true"></i>
                                        <span>Schedule settings</span>
                                    </button>
                                    <button class="tt-add-btn" type="button" data-open-tt-add>
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                        <span>Add Therapist</span>
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="tt-table-wrap" role="region" aria-label="Therapist directory table">
                            <table class="tt-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Specialization</th>
                                        <th>Status</th>
                                        <th>Total Hours</th>
                                        @if (auth()->user()->isAdmin())
                                            <th class="tt-th-actions">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($therapists as $t)
                                        <tr data-tt-id="{{ $t['id'] }}">
                                            <td>
                                                <button
                                                    type="button"
                                                    class="tt-view-link"
                                                    data-open-tt-view
                                                    data-therapist-id="{{ $t['id'] }}"
                                                    data-update-url="{{ route('therapists.update', ['therapistCode' => $t['id']]) }}"
                                                    data-name="{{ $t['name'] }}"
                                                    data-initials="{{ $t['avatar_initials'] ?? '' }}"
                                                    data-photo="{{ $t['photo_url'] ?? '' }}"
                                                    data-contact-number="{{ $t['contact_number'] ?? '' }}"
                                                    data-address="{{ $t['address'] ?? '' }}"
                                                    data-email="{{ $t['email'] ?? '' }}"
                                                    data-birthday="{{ $t['birthday'] ?? '' }}"
                                                    data-status="{{ $t['status'] }}"
                                                    data-total-hours="{{ (int) ($t['total_hours'] ?? 0) }}"
                                                    data-service-hours-pct="{{ (int) ($t['service_hours_pct'] ?? 0) }}"
                                                    data-specializations="{{ implode(', ', array_map('strval', $t['specializations'] ?? [])) }}"
                                                >
                                                    <span class="tt-row-avatar" aria-hidden="true">
                                                        @if (!empty($t['photo_url']))
                                                            <img src="{{ $t['photo_url'] }}" alt="">
                                                        @else
                                                            <span class="tt-row-avatar-initials">{{ $t['avatar_initials'] ?? 'TT' }}</span>
                                                        @endif
                                                    </span>
                                                    <span class="tt-view-main">
                                                        <strong>{{ $t['name'] }}</strong>
                                                        <span class="tt-view-sub">View therapist</span>
                                                    </span>
                                                </button>
                                            </td>
                                            <td>
                                                <span class="tt-specs">
                                                    @include('partials.therapist-card-tags', [
                                                        'specialties' => $t['specializations'] ?? [],
                                                        'tagClass' => 'tt-chip',
                                                        'overflowClass' => 'more',
                                                    ])
                                                </span>
                                            </td>
                                            <td>
                                                <span class="tt-status {{ $t['status'] }}">{{ $t['status'] }}</span>
                                            </td>
                                            <td>
                                                <span class="tt-meta"><i class="bi bi-clock"></i> {{ (int) ($t['total_hours'] ?? 0) }} hrs</span>
                                            </td>
                                            @if (auth()->user()->isAdmin())
                                                <td class="tt-td-actions">
                                                    <div class="tt-row-actions">
                                                        <button
                                                            type="button"
                                                            class="tt-icon-btn schedule"
                                                            data-open-tt-schedule
                                                            data-therapist-id="{{ $t['id'] }}"
                                                            data-name="{{ $t['name'] }}"
                                                            data-update-url="{{ route('therapists.availability.update', ['therapistCode' => $t['id']]) }}"
                                                            data-stored-status="{{ $t['stored_status'] ?? $t['status'] }}"
                                                            data-status="{{ $t['status'] }}"
                                                            data-day-off-until="{{ $t['day_off_until'] ?? '' }}"
                                                            data-work-on-off-day="{{ !empty($t['work_on_off_day']) ? '1' : '0' }}"
                                                            data-working-days="{{ json_encode($t['working_days'] ?? []) }}"
                                                            aria-label="Schedule availability for {{ $t['name'] }}"
                                                        >
                                                            <i class="bi bi-calendar-week" aria-hidden="true"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="tt-icon-btn"
                                                            data-open-tt-edit
                                                            data-therapist-id="{{ $t['id'] }}"
                                                            data-update-url="{{ route('therapists.update', ['therapistCode' => $t['id']]) }}"
                                                            data-name="{{ $t['name'] }}"
                                                            data-role="{{ $t['role'] ?? '' }}"
                                                            data-bio="{{ $t['bio'] ?? '' }}"
                                                            data-initials="{{ $t['avatar_initials'] ?? '' }}"
                                                            data-photo="{{ $t['landing_photo_url'] ?? $t['photo_url'] ?? '' }}"
                                                            data-contact-number="{{ $t['contact_number'] ?? '' }}"
                                                            data-address="{{ $t['address'] ?? '' }}"
                                                            data-email="{{ $t['email'] ?? '' }}"
                                                            data-birthday="{{ $t['birthday'] ?? '' }}"
                                                            data-status="{{ $t['status'] }}"
                                                            data-total-hours="{{ (int) ($t['total_hours'] ?? 0) }}"
                                                            data-service-hours-pct="{{ (int) ($t['service_hours_pct'] ?? 0) }}"
                                                            data-specializations="{{ implode(', ', array_map('strval', $t['specializations'] ?? [])) }}"
                                                            data-certifications="{{ implode(', ', array_map('strval', $t['certifications'] ?? [])) }}"
                                                            data-sessions-label="{{ $t['sessions_label'] ?? '' }}"
                                                            data-accent-color="{{ $t['accent_color'] ?? '#8fa89a' }}"
                                                            data-is-active="{{ !empty($t['is_active']) ? '1' : '0' }}"
                                                            aria-label="Edit {{ $t['name'] }}"
                                                        >
                                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="tt-icon-btn danger"
                                                            data-tt-delete
                                                            data-therapist-id="{{ $t['id'] }}"
                                                            data-name="{{ $t['name'] }}"
                                                            data-delete-url="{{ route('therapists.destroy', ['therapistCode' => $t['id']]) }}"
                                                            aria-label="Delete {{ $t['name'] }}"
                                                        >
                                                            <i class="bi bi-trash3" aria-hidden="true"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </article>

                    <section class="tt-cards" aria-label="Therapist cards">
                        @foreach ($therapists as $t)
                            <article
                                class="tt-card tt-person tt-person-click"
                                role="button"
                                tabindex="0"
                                data-tt-id="{{ $t['id'] }}"
                                data-open-tt-view
                                data-therapist-id="{{ $t['id'] }}"
                                data-update-url="{{ route('therapists.update', ['therapistCode' => $t['id']]) }}"
                                data-name="{{ $t['name'] }}"
                                data-initials="{{ $t['avatar_initials'] ?? '' }}"
                                data-photo="{{ $t['photo_url'] ?? '' }}"
                                data-contact-number="{{ $t['contact_number'] ?? '' }}"
                                data-address="{{ $t['address'] ?? '' }}"
                                data-email="{{ $t['email'] ?? '' }}"
                                data-birthday="{{ $t['birthday'] ?? '' }}"
                                data-status="{{ $t['status'] }}"
                                data-total-hours="{{ (int) ($t['total_hours'] ?? 0) }}"
                                data-service-hours-pct="{{ (int) ($t['service_hours_pct'] ?? 0) }}"
                                data-specializations="{{ implode(', ', array_map('strval', $t['specializations'] ?? [])) }}"
                            >
                                <div class="tt-person-top">
                                    <div>
                                        <h3 class="tt-person-name">{{ $t['name'] }}</h3>
                                    </div>
                                    <div class="tt-person-status">
                                        <span class="tt-status {{ $t['status'] }}">{{ $t['status'] }}</span>
                                    </div>
                                </div>

                                <div class="tt-person-section">
                                    <div class="k">Specializations</div>
                                    <div class="tt-specs">
                                        @include('partials.therapist-card-tags', [
                                            'specialties' => $t['specializations'] ?? [],
                                            'tagClass' => 'tt-chip',
                                            'overflowClass' => 'more',
                                        ])
                                    </div>
                                </div>

                                <div class="tt-person-section">
                                    <div class="k">Service Hours</div>
                                    <div class="tt-bar" aria-hidden="true">
                                        <span style="width: {{ min(max((int) ($t['service_hours_pct'] ?? 0), 0), 100) }}%"></span>
                                    </div>
                                    <div class="tt-bottom">
                                        <span>{{ (int) ($t['total_hours'] ?? 0) }} hrs</span>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </section>
                </section>

                @if (auth()->user()->isAdmin())
                    <section class="tt-manage-panel hidden" id="tt-manage-panel" aria-label="Manage landing page therapists">
                        <article class="tt-card">
                            <div class="tt-card-head tt-manage-head">
                                <div>
                                    <h3>Manage landing page therapists</h3>
                                    <p class="tt-manage-help">Edit the therapist profiles shown on the landing page and booking flow. Add new hires or remove therapists who leave.</p>
                                </div>
                                <button class="tt-add-btn" type="button" data-open-tt-add>
                                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                    <span>Add Therapist</span>
                                </button>
                            </div>

                            <div class="tt-manage-grid">
                                @foreach ($therapists as $t)
                                    @php
                                        $displayPhoto = $t['landing_photo_url'] ?? $t['photo_url'] ?? '';
                                        $certs = implode(', ', array_map('strval', $t['certifications'] ?? []));
                                    @endphp
                                    <article
                                        class="tt-manage-card"
                                        style="--card-accent: {{ $t['accent_color'] ?? '#8fa89a' }}"
                                        data-tt-id="{{ $t['id'] }}"
                                    >
                                        <div class="tt-manage-card-top">
                                            <div class="tt-manage-avatar">
                                                @if (!empty($displayPhoto))
                                                    <img src="{{ $displayPhoto }}" alt="">
                                                @else
                                                    <span>{{ $t['avatar_initials'] ?? 'TT' }}</span>
                                                @endif
                                            </div>
                                            <div class="tt-manage-copy">
                                                <h4>{{ $t['name'] }}</h4>
                                                <p>{{ $t['role'] ?: 'Therapist' }}</p>
                                                @if (empty($t['is_active']))
                                                    <span class="tt-manage-hidden-badge">Hidden from landing</span>
                                                @endif
                                            </div>
                                        </div>
                                        <p class="tt-manage-bio">{{ \Illuminate\Support\Str::limit($t['bio'] ?? 'No bio yet.', 140) }}</p>
                                        <div class="tt-specs">
                                            @include('partials.therapist-card-tags', [
                                                'specialties' => $t['specializations'] ?? [],
                                                'tagClass' => 'tt-chip',
                                                'overflowClass' => 'more',
                                            ])
                                        </div>
                                        <p class="tt-manage-meta">{{ $t['sessions_label'] ?: '0' }} sessions</p>
                                        <div class="tt-manage-actions">
                                            <button
                                                type="button"
                                                class="tt-btn secondary"
                                                data-open-tt-edit
                                                data-therapist-id="{{ $t['id'] }}"
                                                data-update-url="{{ route('therapists.update', ['therapistCode' => $t['id']]) }}"
                                                data-name="{{ $t['name'] }}"
                                                data-role="{{ $t['role'] ?? '' }}"
                                                data-bio="{{ $t['bio'] ?? '' }}"
                                                data-initials="{{ $t['avatar_initials'] ?? '' }}"
                                                data-photo="{{ $displayPhoto }}"
                                                data-contact-number="{{ $t['contact_number'] ?? '' }}"
                                                data-address="{{ $t['address'] ?? '' }}"
                                                data-email="{{ $t['email'] ?? '' }}"
                                                data-birthday="{{ $t['birthday'] ?? '' }}"
                                                data-status="{{ $t['status'] }}"
                                                data-total-hours="{{ (int) ($t['total_hours'] ?? 0) }}"
                                                data-service-hours-pct="{{ (int) ($t['service_hours_pct'] ?? 0) }}"
                                                data-specializations="{{ implode(', ', array_map('strval', $t['specializations'] ?? [])) }}"
                                                data-certifications="{{ $certs }}"
                                                data-sessions-label="{{ $t['sessions_label'] ?? '' }}"
                                                data-accent-color="{{ $t['accent_color'] ?? '#8fa89a' }}"
                                                data-is-active="{{ !empty($t['is_active']) ? '1' : '0' }}"
                                            >Edit profile</button>
                                            <button
                                                type="button"
                                                class="tt-btn danger"
                                                data-tt-delete
                                                data-therapist-id="{{ $t['id'] }}"
                                                data-name="{{ $t['name'] }}"
                                                data-delete-url="{{ route('therapists.destroy', ['therapistCode' => $t['id']]) }}"
                                            >Remove</button>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </article>
                    </section>
                @endif

                @if (auth()->user()->isAdmin())
                    @include('partials.therapist-schedule-modals', ['scheduleSettings' => $scheduleSettings ?? []])

                    <div class="tt-modal hidden" id="ttAddModal" role="dialog" aria-modal="true" aria-labelledby="ttAddTitle">
                        <div class="tt-modal-backdrop" data-close-tt-modal></div>
                        <div class="tt-modal-content" role="document">
                            <button class="tt-modal-close" type="button" aria-label="Close" data-close-tt-modal>&times;</button>
                            <div class="tt-modal-head">
                                <div class="tt-modal-icon" aria-hidden="true"><i class="bi bi-person-plus"></i></div>
                                <div>
                                    <h2 id="ttAddTitle" class="tt-modal-title">Add Therapist</h2>
                                    <p class="tt-modal-sub" id="ttAddSubtitle">Create a therapist profile for the landing page, booking flow, and internal tracking.</p>
                                </div>
                            </div>

                            <form class="tt-form" id="ttAddForm" autocomplete="off">
                                <div class="tt-form-grid">
                                    <input id="ttTherapistId" name="therapist_id" type="hidden">
                                    <div class="tt-field">
                                        <label for="ttName">Name</label>
                                        <input id="ttName" name="name" type="text" placeholder="e.g., Anna Reyes" required>
                                    </div>
                                    <div class="tt-field">
                                        <label for="ttRole">Role / title</label>
                                        <input id="ttRole" name="role" type="text" placeholder="e.g., Senior Massage Therapist">
                                    </div>
                                    <div class="tt-field tt-field-full">
                                        <label class="tt-check-row" for="ttIsActive">
                                            <input id="ttIsActive" name="is_active" type="checkbox" value="1" checked>
                                            <span>Show on landing page and booking</span>
                                        </label>
                                        <small>Uncheck only if this therapist should be hidden from customers.</small>
                                    </div>
                                    <div class="tt-field tt-field-full">
                                        <label for="ttBio">Bio</label>
                                        <textarea id="ttBio" name="bio" rows="4" placeholder="Short profile shown on the landing page."></textarea>
                                    </div>
                                    <div class="tt-field tt-field-full">
                                        <label for="ttPhoto">Profile Picture</label>
                                        <input id="ttPhoto" class="tt-file-input" name="photo_file" type="file" accept="image/*">
                                        <label for="ttPhoto" class="tt-file-picker">
                                            <span class="tt-file-btn">Choose image</span>
                                            <span class="tt-file-name" id="ttPhotoName">No file chosen</span>
                                        </label>
                                        <small>Optional. Upload a therapist photo.</small>
                                    </div>
                                    <div class="tt-field">
                                        <label for="ttContactNumber">Contact Number</label>
                                        <input id="ttContactNumber" name="contact_number" type="text" maxlength="11" pattern="^09\d{9}$" title="Use 09XXXXXXXXX (11 digits)." inputmode="numeric" placeholder="09171234567">
                                    </div>
                                    <div class="tt-field">
                                        <label for="ttEmail">Email</label>
                                        <input id="ttEmail" name="email" type="email" placeholder="anna@example.com">
                                    </div>
                                    <div class="tt-field">
                                        <label for="ttBirthday">Birthday</label>
                                        <input id="ttBirthday" name="birthday" type="date">
                                    </div>
                                    <div class="tt-field tt-field-full">
                                        <label for="ttAddress">Address</label>
                                        <input id="ttAddress" name="address" type="text" placeholder="Street, Barangay, City">
                                    </div>
                                    <div class="tt-field tt-field-full">
                                        <label for="ttSpecs">Specialization</label>
                                        <input id="ttSpecs" name="specializations" type="hidden" required>
                                        <div class="tt-spec-picks" id="ttSpecPicks" role="group" aria-label="Select specializations">
                                            @foreach ($specializationOptions as $specOption)
                                                <button type="button" class="tt-spec-pick" data-spec-option="{{ $specOption }}">{{ $specOption }}</button>
                                            @endforeach
                                        </div>
                                        <small>Click one or more badges to select specializations.</small>
                                    </div>
                                    <div class="tt-field tt-field-full">
                                        <label for="ttCertifications">Certifications</label>
                                        <textarea id="ttCertifications" name="certifications" rows="3" placeholder="Licensed Massage Therapist (LMT), CPR & First Aid Certified"></textarea>
                                        <small>Separate each certification with a comma.</small>
                                    </div>
                                    <div class="tt-field">
                                        <label for="ttSessionsLabel">Sessions completed label</label>
                                        <input id="ttSessionsLabel" name="sessions_label" type="text" placeholder="e.g., 1,200+">
                                    </div>
                                    <div class="tt-field">
                                        <label for="ttAccentColor">Card accent color</label>
                                        <input id="ttAccentColor" name="accent_color" type="color" value="#8fa89a">
                                    </div>
                                    <div class="tt-field" data-tt-status-field>
                                        <label for="ttStatus">Status</label>
                                        <input id="ttStatus" type="text" value="available" readonly class="tt-input-readonly">
                                        <small>Auto-updated based on therapist activity.</small>
                                    </div>
                                    <div class="tt-field" data-tt-hours-field>
                                        <label for="ttTotalHours">Total Hours</label>
                                        <input id="ttTotalHours" name="total_hours" type="number" min="0" step="1" value="0" readonly class="tt-input-readonly">
                                        <small>Auto-generated from recorded transactions.</small>
                                    </div>
                                </div>

                                <div class="tt-form-actions">
                                    <button class="tt-btn secondary" type="button" data-close-tt-modal>Cancel</button>
                                    <button class="tt-btn primary" type="submit" id="ttAddSubmit">
                                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                        <span id="ttAddSubmitLabel">Add Therapist</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="tt-modal hidden" id="ttDeleteModal" role="dialog" aria-modal="true" aria-labelledby="ttDeleteTitle">
                    <div class="tt-modal-backdrop" data-close-tt-delete></div>
                    <div class="tt-modal-content tt-modal-content-compact" role="document">
                        <button class="tt-modal-close" type="button" aria-label="Close" data-close-tt-delete>&times;</button>
                        <div class="tt-modal-head">
                            <div class="tt-modal-icon tt-modal-icon-danger" aria-hidden="true"><i class="bi bi-trash3"></i></div>
                            <div>
                                <h2 id="ttDeleteTitle" class="tt-modal-title">Delete Therapist</h2>
                                <p class="tt-modal-sub">This action will permanently remove this therapist from the directory.</p>
                            </div>
                        </div>
                        <p class="tt-delete-copy">Are you sure you want to delete <strong id="ttDeleteName">this therapist</strong>?</p>
                        <div class="tt-form-actions">
                            <button class="tt-btn secondary" type="button" data-close-tt-delete>Cancel</button>
                            <button class="tt-btn danger" type="button" id="ttDeleteConfirmBtn">
                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                <span>Delete Therapist</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tt-modal hidden" id="ttViewModal" role="dialog" aria-modal="true" aria-labelledby="ttViewTitle">
                    <div class="tt-modal-backdrop" data-close-tt-view></div>
                    <div class="tt-modal-content tt-view-content" role="document">
                        <button class="tt-modal-close" type="button" aria-label="Close" data-close-tt-view>&times;</button>
                        <div class="tt-view-head">
                            <div class="tt-view-avatar" aria-hidden="true">
                                <img class="tt-view-img hidden" id="ttViewImg" alt="">
                                <span class="tt-view-initials" id="ttViewInitials">TT</span>
                            </div>
                            <div class="tt-view-title-block">
                                <h2 id="ttViewTitle" class="tt-modal-title">Therapist Profile</h2>
                                <p class="tt-modal-sub" id="ttViewSubtitle">Details and current availability.</p>
                            </div>
                            <span class="tt-status tt-view-status" id="ttViewStatus">available</span>
                        </div>

                        <div class="tt-view-grid">
                            <div class="tt-view-card">
                                <div class="tt-view-k">Name</div>
                                <div class="tt-view-v" id="ttViewName">—</div>
                            </div>
                            <div class="tt-view-card">
                                <div class="tt-view-k">Contact Number</div>
                                <div class="tt-view-v" id="ttViewContactNumber">—</div>
                            </div>
                            <div class="tt-view-card">
                                <div class="tt-view-k">Email</div>
                                <div class="tt-view-v" id="ttViewEmail">—</div>
                            </div>
                            <div class="tt-view-card tt-view-card-full">
                                <div class="tt-view-k">Address</div>
                                <div class="tt-view-v" id="ttViewAddress">—</div>
                            </div>
                            <div class="tt-view-card">
                                <div class="tt-view-k">Birthday</div>
                                <div class="tt-view-v" id="ttViewBirthday">—</div>
                            </div>
                            <div class="tt-view-card">
                                <div class="tt-view-k">Age</div>
                                <div class="tt-view-v" id="ttViewAge">—</div>
                            </div>
                            <div class="tt-view-card tt-view-card-full">
                                <div class="tt-view-k">Specialization</div>
                                <div class="tt-view-v" id="ttViewSpecs">—</div>
                            </div>
                            <div class="tt-view-card">
                                <div class="tt-view-k">Total Hours</div>
                                <div class="tt-view-v" id="ttViewHours">—</div>
                            </div>
                            <div class="tt-view-card">
                                <div class="tt-view-k">Service Hours %</div>
                                <div class="tt-view-v">
                                    <div class="tt-bar" aria-hidden="true"><span id="ttViewBar" style="width: 0%"></span></div>
                                    <div class="tt-view-mini" id="ttViewPct">0%</div>
                                </div>
                            </div>
                        </div>

                        <div class="tt-form-actions">
                            <button class="tt-btn secondary" type="button" data-close-tt-view>Close</button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    @php
        $ttDefaultWorkingDays = $scheduleSettings['default_working_days'] ?? [1, 2, 3, 4, 5, 6];
    @endphp
    <script>
        (function () {
            const ttStoreUrl = @json(route('therapists.store'));
            const ttScheduleSettingsUrl = @json(auth()->user()->isAdmin() ? route('therapist-schedule.settings.update') : '');
            const ttCsrfToken = @json(csrf_token());
            const ttDefaultWorkingDays = @json($ttDefaultWorkingDays);
            const trackingPanel = document.getElementById('tt-tracking-panel');
            const managePanel = document.getElementById('tt-manage-panel');
            const pageTitle = document.getElementById('tt-page-title');
            const pageSubtitle = document.getElementById('tt-page-subtitle');
            const modal = document.getElementById('ttAddModal');
            const openBtn = document.querySelector('[data-open-tt-add]');
            const openBtns = document.querySelectorAll('[data-open-tt-add]');
            const closeEls = modal ? modal.querySelectorAll('[data-close-tt-modal]') : [];
            const form = document.getElementById('ttAddForm');
            const ttTherapistId = document.getElementById('ttTherapistId');
            const ttAddTitle = document.getElementById('ttAddTitle');
            const ttAddSubtitle = document.getElementById('ttAddSubtitle');
            const ttAddSubmitLabel = document.getElementById('ttAddSubmitLabel');
            const ttPhotoInput = document.getElementById('ttPhoto');
            const ttPhotoName = document.getElementById('ttPhotoName');
            const ttSpecsHidden = document.getElementById('ttSpecs');
            const ttSpecPickButtons = Array.from(document.querySelectorAll('.tt-spec-pick'));
            const ttStatusField = form?.querySelector('[data-tt-status-field]');
            const ttHoursField = form?.querySelector('[data-tt-hours-field]');
            let ttCurrentPhoto = '';
            let ttCurrentServicePct = 0;
            let ttCurrentStatus = 'available';
            let ttUpdateUrl = '';
            const ttDeleteModal = document.getElementById('ttDeleteModal');
            const ttDeleteName = document.getElementById('ttDeleteName');
            const ttDeleteConfirmBtn = document.getElementById('ttDeleteConfirmBtn');
            const ttDeleteCloseEls = ttDeleteModal ? ttDeleteModal.querySelectorAll('[data-close-tt-delete]') : [];
            let ttPendingDelete = null;

            let ttEditId = null;
            let ttManageView = false;

            function syncManageUrl(isManageView) {
                const url = new URL(window.location.href);
                if (isManageView) {
                    url.searchParams.set('manage', '1');
                } else {
                    url.searchParams.delete('manage');
                }
                window.history.replaceState({}, '', url);
            }

            function showManageView() {
                ttManageView = true;
                trackingPanel?.classList.add('hidden');
                managePanel?.classList.remove('hidden');
                if (pageTitle) pageTitle.textContent = 'Manage Therapists';
                if (pageSubtitle) {
                    pageSubtitle.textContent = 'Edit landing page profiles, add new hires, or remove therapists.';
                }
                syncManageUrl(true);
            }

            function showTrackingView() {
                ttManageView = false;
                managePanel?.classList.add('hidden');
                trackingPanel?.classList.remove('hidden');
                if (pageTitle) pageTitle.textContent = 'Therapist Monitoring';
                if (pageSubtitle) pageSubtitle.textContent = 'Monitor masseuse availability and service hours';
                syncManageUrl(false);
            }

            if (managePanel && new URLSearchParams(window.location.search).get('manage') === '1') {
                showManageView();
            }

            function appendLandingFields(formData, formEl) {
                formData.append('role', formEl.querySelector('[name="role"]')?.value?.trim() || '');
                formData.append('bio', formEl.querySelector('[name="bio"]')?.value?.trim() || '');
                formData.append('certifications', formEl.querySelector('[name="certifications"]')?.value?.trim() || '');
                formData.append('sessions_label', formEl.querySelector('[name="sessions_label"]')?.value?.trim() || '');
                formData.append('accent_color', formEl.querySelector('[name="accent_color"]')?.value?.trim() || '#8fa89a');
                const isActive = formEl.querySelector('[name="is_active"]');
                formData.append('is_active', (isActive?.checked ?? true) ? '1' : '0');
            }

            function initialsFromName(name) {
                const parts = String(name || '')
                    .trim()
                    .split(/\s+/)
                    .filter(Boolean);
                if (!parts.length) return 'TT';
                if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
                return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
            }

            function parseSpecs(raw) {
                return String(raw || '')
                    .split(',')
                    .map((s) => s.trim())
                    .filter(Boolean);
            }

            function selectedSpecValues() {
                return ttSpecPickButtons
                    .filter((btn) => btn.classList.contains('is-active'))
                    .map((btn) => btn.dataset.specOption || '')
                    .filter(Boolean);
            }

            function syncSpecHiddenInput() {
                if (!ttSpecsHidden) return;
                ttSpecsHidden.value = selectedSpecValues().join(', ');
            }

            function setSelectedSpecs(specs) {
                const wanted = new Set((specs || []).map((s) => String(s).trim()).filter(Boolean));
                ttSpecPickButtons.forEach((btn) => {
                    const spec = String(btn.dataset.specOption || '').trim();
                    btn.classList.toggle('is-active', wanted.has(spec));
                    btn.setAttribute('aria-pressed', btn.classList.contains('is-active') ? 'true' : 'false');
                });
                syncSpecHiddenInput();
            }

            function buildSpecChipsHtml(specs) {
                const tags = (specs || [])
                    .map((sp) => String(sp).trim())
                    .filter((sp) => sp !== '' && !sp.startsWith('+'));
                const visible = tags.slice(0, 2);
                const overflow = Math.max(tags.length - visible.length, 0);

                return visible
                    .map((sp) => {
                        const esc = sp.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                        return `<span class="tt-chip">${esc}</span>`;
                    })
                    .concat(overflow > 0 ? [`<span class="tt-chip more">+${overflow}</span>`] : [])
                    .join('');
            }

            function therapistPayloadFromForm() {
                const id = (ttTherapistId && ttTherapistId.value) || '';
                const name = form.querySelector('[name="name"]')?.value?.trim() || '';
                const totalHours = parseInt(form.querySelector('[name="total_hours"]')?.value || '0', 10) || 0;
                const specs = parseSpecs(form.querySelector('[name="specializations"]')?.value);
                const specStr = specs.join(', ');
                const initials = initialsFromName(name);
                return {
                    therapistId: id,
                    updateUrl: ttUpdateUrl || '',
                    name,
                    role: form.querySelector('[name="role"]')?.value?.trim() || '',
                    bio: form.querySelector('[name="bio"]')?.value?.trim() || '',
                    contactNumber: form.querySelector('[name="contact_number"]')?.value?.trim() || '',
                    address: form.querySelector('[name="address"]')?.value?.trim() || '',
                    email: form.querySelector('[name="email"]')?.value?.trim() || '',
                    birthday: form.querySelector('[name="birthday"]')?.value || '',
                    status: ttCurrentStatus,
                    totalHours,
                    serviceHoursPct: Math.max(0, Math.min(parseInt(String(ttCurrentServicePct), 10) || 0, 100)),
                    specializations: specStr,
                    certifications: form.querySelector('[name="certifications"]')?.value?.trim() || '',
                    sessionsLabel: form.querySelector('[name="sessions_label"]')?.value?.trim() || '',
                    accentColor: form.querySelector('[name="accent_color"]')?.value?.trim() || '#8fa89a',
                    isActive: form.querySelector('[name="is_active"]')?.checked === true,
                    initials,
                    photo: ttCurrentPhoto || '',
                };
            }

            function fileToDataUrl(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
                    reader.onerror = () => reject(new Error('Failed to read image.'));
                    reader.readAsDataURL(file);
                });
            }

            function applyDatasetToTherapistEls(id, p) {
                const sel = `[data-therapist-id="${CSS.escape(id)}"]`;
                document.querySelectorAll(sel).forEach((node) => {
                    if (node.dataset) {
                        node.dataset.therapistId = p.therapistId;
                        node.dataset.updateUrl = p.updateUrl || node.dataset.updateUrl || '';
                        node.dataset.name = p.name;
                        node.dataset.initials = p.initials;
                        node.dataset.photo = p.photo || '';
                        node.dataset.contactNumber = p.contactNumber || '';
                        node.dataset.address = p.address || '';
                        node.dataset.email = p.email || '';
                        node.dataset.birthday = p.birthday || '';
                        node.dataset.status = p.status;
                        node.dataset.storedStatus = p.storedStatus || p.status;
                        node.dataset.dayOffUntil = p.dayOffUntil || '';
                        node.dataset.workOnOffDay = p.workOnOffDay ? '1' : '0';
                        node.dataset.workingDays = JSON.stringify(p.workingDays || []);
                        node.dataset.totalHours = String(p.totalHours);
                        node.dataset.serviceHoursPct = String(p.serviceHoursPct);
                        node.dataset.specializations = p.specializations;
                    }
                });
            }

            function updateTherapistDom(prevId, p) {
                const row = document.querySelector(`tr[data-tt-id="${CSS.escape(prevId)}"]`);
                const card = document.querySelector(`article.tt-person[data-tt-id="${CSS.escape(prevId)}"]`);
                if (row) {
                    row.dataset.ttId = p.therapistId;
                    const viewBtn = row.querySelector('[data-open-tt-view]');
                    if (viewBtn) {
                        const strong = viewBtn.querySelector('strong');
                        if (strong) strong.textContent = p.name;
                    }
                    const specsWrap = row.querySelector('.tt-specs');
                    if (specsWrap) specsWrap.innerHTML = buildSpecChipsHtml(parseSpecs(p.specializations));
                    const st = row.querySelector('.tt-status');
                    if (st) {
                        st.textContent = p.status;
                        st.classList.remove('available', 'busy', 'resting', 'off-duty');
                        st.classList.add(p.status);
                    }
                    const meta = row.querySelector('.tt-meta');
                    if (meta) meta.innerHTML = `<i class="bi bi-clock"></i> ${p.totalHours} hrs`;
                }
                if (card) {
                    card.dataset.ttId = p.therapistId;
                    const nm = card.querySelector('.tt-person-name');
                    if (nm) nm.textContent = p.name;
                    const st = card.querySelector('.tt-person-status .tt-status');
                    if (st) {
                        st.textContent = p.status;
                        st.classList.remove('available', 'busy', 'resting', 'off-duty');
                        st.classList.add(p.status);
                    }
                    const specsWrap = card.querySelector('.tt-specs');
                    if (specsWrap) specsWrap.innerHTML = buildSpecChipsHtml(parseSpecs(p.specializations));
                    const bar = card.querySelector('.tt-bar span');
                    if (bar) bar.style.width = `${p.serviceHoursPct}%`;
                    const hrs = card.querySelector('.tt-bottom span');
                    if (hrs) hrs.textContent = `${p.totalHours} hrs`;
                }
                applyDatasetToTherapistEls(prevId, p);
            }

            function recalcStats() {
                const rows = document.querySelectorAll('tbody tr[data-tt-id]');
                const total = rows.length;
                let available = 0;
                let busy = 0;
                let hoursSum = 0;
                rows.forEach((tr) => {
                    const st = tr.querySelector('.tt-status')?.textContent?.trim().toLowerCase();
                    if (st === 'available') available += 1;
                    if (st === 'busy') busy += 1;
                    const meta = tr.querySelector('.tt-meta')?.textContent || '';
                    const m = meta.match(/(\d+)/);
                    if (m) hoursSum += parseInt(m[1], 10);
                });
                const values = document.querySelectorAll('.tt-metric .value');
                if (values[0]) values[0].textContent = String(total);
                if (values[1]) values[1].textContent = String(available);
                if (values[2]) values[2].textContent = String(busy);
                if (values[3]) values[3].textContent = String(total ? Math.round(hoursSum / total) : 0);
            }

            function setAddMode() {
                ttEditId = null;
                ttCurrentPhoto = '';
                ttCurrentServicePct = 0;
                ttCurrentStatus = 'available';
                ttUpdateUrl = '';
                if (ttPhotoName) ttPhotoName.textContent = 'No file chosen';
                if (ttTherapistId) {
                    ttTherapistId.readOnly = false;
                    ttTherapistId.classList.remove('tt-input-readonly');
                }
                if (ttAddTitle) ttAddTitle.textContent = 'Add Therapist';
                if (ttAddSubtitle) {
                    ttAddSubtitle.textContent =
                        'Create a therapist profile for the landing page, booking flow, and internal tracking.';
                }
                if (ttAddSubmitLabel) ttAddSubmitLabel.textContent = 'Add Therapist';
                const roleEl = form?.querySelector('[name="role"]');
                if (roleEl) roleEl.value = '';
                const bioEl = form?.querySelector('[name="bio"]');
                if (bioEl) bioEl.value = '';
                const certsEl = form?.querySelector('[name="certifications"]');
                if (certsEl) certsEl.value = '';
                const sessionsEl = form?.querySelector('[name="sessions_label"]');
                if (sessionsEl) sessionsEl.value = '';
                const accentEl = form?.querySelector('[name="accent_color"]');
                if (accentEl) accentEl.value = '#8fa89a';
                const activeEl = form?.querySelector('[name="is_active"]');
                if (activeEl) activeEl.checked = true;
                const stReadonly = form?.querySelector('#ttStatus');
                if (stReadonly instanceof HTMLInputElement) stReadonly.value = ttCurrentStatus;
                if (ttStatusField) ttStatusField.style.display = '';
                if (ttHoursField) ttHoursField.style.display = '';
            }

            function setEditMode(ds) {
                ttEditId = ds.therapistId || null;
                ttUpdateUrl = ds.updateUrl || '';
                ttCurrentStatus = (ds.status || 'available').toLowerCase();
                if (ttAddTitle) ttAddTitle.textContent = 'Edit Therapist';
                if (ttAddSubtitle) ttAddSubtitle.textContent = 'Update landing page profile, contact details, and availability.';
                if (ttAddSubmitLabel) ttAddSubmitLabel.textContent = 'Save changes';
                if (ttTherapistId) {
                    ttTherapistId.readOnly = true;
                    ttTherapistId.classList.add('tt-input-readonly');
                }
                if (ttStatusField) ttStatusField.style.display = 'none';
                if (ttHoursField) ttHoursField.style.display = 'none';
            }

            function fillFormFromDataset(ds) {
                if (ttTherapistId) ttTherapistId.value = ds.therapistId || '';
                ttCurrentPhoto = ds.photo || '';
                if (ttPhotoInput) ttPhotoInput.value = '';
                if (ttPhotoName) ttPhotoName.textContent = ttCurrentPhoto ? 'Current photo attached' : 'No file chosen';
                const nameEl = form?.querySelector('[name="name"]');
                if (nameEl) nameEl.value = ds.name || '';
                const roleEl = form?.querySelector('[name="role"]');
                if (roleEl) roleEl.value = ds.role || '';
                const bioEl = form?.querySelector('[name="bio"]');
                if (bioEl) bioEl.value = ds.bio || '';
                setSelectedSpecs(parseSpecs(ds.specializations || ''));
                const certsEl = form?.querySelector('[name="certifications"]');
                if (certsEl) certsEl.value = ds.certifications || '';
                const sessionsEl = form?.querySelector('[name="sessions_label"]');
                if (sessionsEl) sessionsEl.value = ds.sessionsLabel || ds.sessionslabel || '';
                const accentEl = form?.querySelector('[name="accent_color"]');
                if (accentEl) accentEl.value = ds.accentColor || ds.accentcolor || '#8fa89a';
                const activeEl = form?.querySelector('[name="is_active"]');
                if (activeEl) activeEl.checked = ds.isActive === '1' || ds.isactive === '1' || ds.isActive === true;
                const contactEl = form?.querySelector('[name="contact_number"]');
                if (contactEl) contactEl.value = ds.contactNumber || '';
                const addressEl = form?.querySelector('[name="address"]');
                if (addressEl) addressEl.value = ds.address || '';
                const emailEl = form?.querySelector('[name="email"]');
                if (emailEl) emailEl.value = ds.email || '';
                const birthdayEl = form?.querySelector('[name="birthday"]');
                if (birthdayEl) birthdayEl.value = ds.birthday || '';
                const stReadonly = form?.querySelector('#ttStatus');
                if (stReadonly instanceof HTMLInputElement) {
                    stReadonly.value = ttCurrentStatus;
                }
                const hrsEl = form?.querySelector('[name="total_hours"]');
                if (hrsEl) hrsEl.value = ds.totalHours || '0';
                ttCurrentServicePct = Math.max(0, Math.min(parseInt(ds.serviceHoursPct || '0', 10) || 0, 100));
            }

            function openModal() {
                if (!modal) return;
                modal.classList.remove('hidden');
                document.body.classList.add('modal-open');
                const first = modal.querySelector('input, select, button');
                if (first) first.focus();
            }

            function openAddFresh() {
                setAddMode();
                form?.reset();
                if (ttPhotoInput) ttPhotoInput.value = '';
                if (ttPhotoName) ttPhotoName.textContent = 'No file chosen';
                setSelectedSpecs([]);
                const accentEl = form?.querySelector('[name="accent_color"]');
                if (accentEl) accentEl.value = '#8fa89a';
                const activeEl = form?.querySelector('[name="is_active"]');
                if (activeEl) activeEl.checked = true;
                openModal();
            }

            function openEditFromEl(el) {
                if (!form || !modal) return;
                const ds = el.dataset || {};
                setEditMode(ds);
                fillFormFromDataset(ds);
                openModal();
            }

            function closeModal() {
                if (!modal) return;
                modal.classList.add('hidden');
                document.body.classList.remove('modal-open');
                setAddMode();
                if (ttPhotoInput) ttPhotoInput.value = '';
                if (ttPhotoName) ttPhotoName.textContent = 'No file chosen';
                setSelectedSpecs([]);
            }

            openBtns.forEach((btn) => {
                btn.addEventListener('click', () => openAddFresh());
            });
            closeEls.forEach((el) => el.addEventListener('click', closeModal));
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
            });

            document.querySelectorAll('[data-open-tt-edit]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    openEditFromEl(btn);
                });
            });

            ttSpecPickButtons.forEach((btn) => {
                btn.setAttribute('aria-pressed', 'false');
                btn.addEventListener('click', () => {
                    btn.classList.toggle('is-active');
                    btn.setAttribute('aria-pressed', btn.classList.contains('is-active') ? 'true' : 'false');
                    syncSpecHiddenInput();
                });
            });

            ttPhotoInput?.addEventListener('change', () => {
                const file = ttPhotoInput.files && ttPhotoInput.files.length > 0 ? ttPhotoInput.files[0] : null;
                if (ttPhotoName) {
                    ttPhotoName.textContent = file ? file.name : (ttCurrentPhoto ? 'Current photo attached' : 'No file chosen');
                }
            });

            if (form) {
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    syncSpecHiddenInput();
                    if (!ttSpecsHidden || ttSpecsHidden.value.trim() === '') {
                        window.alert('Please select at least one specialization.');
                        return;
                    }
                    const pickedFile = ttPhotoInput?.files && ttPhotoInput.files.length > 0 ? ttPhotoInput.files[0] : null;
                    if (pickedFile) {
                        try {
                            ttCurrentPhoto = await fileToDataUrl(pickedFile);
                        } catch (_err) {
                            ttCurrentPhoto = '';
                        }
                    }
                    if (ttEditId) {
                        const p = therapistPayloadFromForm();
                        if (!p.therapistId || !p.name) return;
                        if (p.updateUrl) {
                            const formData = new FormData();
                            formData.append('_token', ttCsrfToken);
                            formData.append('_method', 'PUT');
                            formData.append('name', p.name);
                            formData.append('contact_number', p.contactNumber || '');
                            formData.append('address', p.address || '');
                            formData.append('email', p.email || '');
                            formData.append('birthday', p.birthday || '');
                            formData.append('specializations', p.specializations || '');
                            appendLandingFields(formData, form);
                            if (pickedFile) {
                                formData.append('photo_file', pickedFile);
                            }

                            try {
                                const res = await fetch(p.updateUrl, {
                                    method: 'POST',
                                    body: formData,
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin',
                                });
                                if (res.ok) {
                                    if (ttManageView) {
                                        window.location.reload();
                                        return;
                                    }
                                    const json = await res.json();
                                    if (json?.therapist?.landing_photo_url || json?.therapist?.photo_url) {
                                        p.photo = String(json.therapist.landing_photo_url || json.therapist.photo_url);
                                    }
                                    if (json?.therapist?.avatar_initials) {
                                        p.initials = String(json.therapist.avatar_initials);
                                    }
                                }
                            } catch (_err) {
                                // Keep local update even if save request fails.
                            }
                        } else if (ttStoreUrl) {
                            // Reserved for future full server-side create flow.
                        }
                        if (!ttManageView) {
                            updateTherapistDom(ttEditId, p);
                            recalcStats();
                        }
                    } else if (ttStoreUrl) {
                        const p = therapistPayloadFromForm();
                        const formData = new FormData();
                        formData.append('_token', ttCsrfToken);
                        formData.append('therapist_code', p.therapistId || '');
                        formData.append('name', p.name);
                        formData.append('contact_number', p.contactNumber || '');
                        formData.append('address', p.address || '');
                        formData.append('email', p.email || '');
                        formData.append('birthday', p.birthday || '');
                        formData.append('specializations', p.specializations || '');
                        appendLandingFields(formData, form);
                        if (pickedFile) {
                            formData.append('photo_file', pickedFile);
                        }
                        try {
                            const res = await fetch(ttStoreUrl, {
                                method: 'POST',
                                body: formData,
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin',
                            });
                            if (res.ok) {
                                window.location.reload();
                                return;
                            }
                        } catch (_err) {
                            // Fallback to current local behavior if request fails.
                        }
                    }
                    closeModal();
                    form.reset();
                    ttCurrentPhoto = '';
                    if (ttPhotoName) ttPhotoName.textContent = 'No file chosen';
                    setSelectedSpecs([]);
                });
            }

            document.querySelectorAll('[data-tt-delete]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const id = btn.dataset.therapistId;
                    const name = btn.dataset.name || 'this therapist';
                    const deleteUrl = btn.dataset.deleteUrl || '';
                    if (!id) return;
                    ttPendingDelete = { id, name, deleteUrl };
                    if (ttDeleteName) ttDeleteName.textContent = name;
                    ttDeleteModal?.classList.remove('hidden');
                    document.body.classList.add('modal-open');
                });
            });

            function closeDeleteModal() {
                ttDeleteModal?.classList.add('hidden');
                document.body.classList.remove('modal-open');
                ttPendingDelete = null;
            }

            ttDeleteCloseEls.forEach((el) => el.addEventListener('click', closeDeleteModal));

            ttDeleteConfirmBtn?.addEventListener('click', async () => {
                if (!ttPendingDelete) return;
                const { id, deleteUrl } = ttPendingDelete;
                if (deleteUrl) {
                    try {
                        const res = await fetch(deleteUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': ttCsrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ _method: 'DELETE' }),
                        });
                        if (!res.ok) {
                            return;
                        }
                    } catch (_err) {
                        return;
                    }
                }
                const row = document.querySelector(`tr[data-tt-id="${CSS.escape(id)}"]`);
                const card = document.querySelector(`article.tt-person[data-tt-id="${CSS.escape(id)}"]`);
                const manageCard = document.querySelector(`.tt-manage-card[data-tt-id="${CSS.escape(id)}"]`);
                row?.remove();
                card?.remove();
                manageCard?.remove();
                if (ttManageView && !document.querySelector('.tt-manage-card')) {
                    showTrackingView();
                }
                recalcStats();
                closeDeleteModal();
            });

            const viewModal = document.getElementById('ttViewModal');
            const viewOpenEls = document.querySelectorAll('[data-open-tt-view]');
            const viewCloseEls = viewModal ? viewModal.querySelectorAll('[data-close-tt-view]') : [];

            const viewImg = document.getElementById('ttViewImg');
            const viewInitials = document.getElementById('ttViewInitials');
            const viewStatus = document.getElementById('ttViewStatus');
            const viewId = document.getElementById('ttViewId');
            const viewName = document.getElementById('ttViewName');
            const viewContactNumber = document.getElementById('ttViewContactNumber');
            const viewAddress = document.getElementById('ttViewAddress');
            const viewEmail = document.getElementById('ttViewEmail');
            const viewBirthday = document.getElementById('ttViewBirthday');
            const viewAge = document.getElementById('ttViewAge');
            const viewSpecs = document.getElementById('ttViewSpecs');
            const viewHours = document.getElementById('ttViewHours');
            const viewBar = document.getElementById('ttViewBar');
            const viewPct = document.getElementById('ttViewPct');

            function ageFromBirthday(dateIso) {
                if (!dateIso) return '—';
                const birth = new Date(dateIso);
                if (Number.isNaN(birth.getTime())) return '—';
                const now = new Date();
                let age = now.getFullYear() - birth.getFullYear();
                const monthDelta = now.getMonth() - birth.getMonth();
                if (monthDelta < 0 || (monthDelta === 0 && now.getDate() < birth.getDate())) {
                    age -= 1;
                }
                return age >= 0 ? String(age) : '—';
            }

            function openView(el) {
                if (!viewModal) return;
                const ds = el.dataset || {};
                const photo = (ds.photo || '').trim();
                const initials = (ds.initials || '').trim() || 'TT';

                if (viewImg) {
                    if (photo) {
                        viewImg.src = photo;
                        viewImg.alt = `${ds.name || 'Therapist'} photo`;
                        viewImg.classList.remove('hidden');
                    } else {
                        viewImg.removeAttribute('src');
                        viewImg.classList.add('hidden');
                    }
                }

                if (viewInitials) {
                    viewInitials.textContent = initials;
                    viewInitials.classList.toggle('hidden', !!photo);
                }

                const status = (ds.status || 'available').toLowerCase();
                if (viewStatus) {
                    viewStatus.textContent = status;
                    viewStatus.classList.remove('available', 'busy', 'off-duty');
                    viewStatus.classList.add(status);
                }

                if (viewId) viewId.textContent = ds.therapistId || '—';
                if (viewName) viewName.textContent = ds.name || '—';
                if (viewContactNumber) viewContactNumber.textContent = ds.contactNumber || '—';
                if (viewAddress) viewAddress.textContent = ds.address || '—';
                if (viewEmail) viewEmail.textContent = ds.email || '—';
                if (viewBirthday) viewBirthday.textContent = ds.birthday || '—';
                if (viewAge) viewAge.textContent = ageFromBirthday(ds.birthday || '');
                if (viewSpecs) viewSpecs.textContent = ds.specializations || '—';
                if (viewHours) viewHours.textContent = `${ds.totalHours || 0} hrs`;

                const pctVal = Math.max(0, Math.min(parseInt(ds.serviceHoursPct || '0', 10) || 0, 100));
                if (viewBar) viewBar.style.width = `${pctVal}%`;
                if (viewPct) viewPct.textContent = `${pctVal}%`;

                viewModal.classList.remove('hidden');
                document.body.classList.add('modal-open');
                const first = viewModal.querySelector('button');
                if (first) first.focus();
            }

            function closeView() {
                if (!viewModal) return;
                viewModal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }

            function refreshTherapistFromApi(therapist) {
                const id = String(therapist.id || '');
                const status = therapist.status || 'available';

                const row = document.querySelector(`tr[data-tt-id="${CSS.escape(id)}"]`);
                if (row) {
                    const st = row.querySelector('.tt-status');
                    if (st) {
                        st.textContent = status;
                        st.classList.remove('available', 'busy', 'resting', 'off-duty');
                        st.classList.add(status);
                    }
                }

                const card = document.querySelector(`article.tt-person[data-tt-id="${CSS.escape(id)}"]`);
                if (card) {
                    const st = card.querySelector('.tt-person-status .tt-status');
                    if (st) {
                        st.textContent = status;
                        st.classList.remove('available', 'busy', 'resting', 'off-duty');
                        st.classList.add(status);
                    }
                }

                document.querySelectorAll(`[data-therapist-id="${CSS.escape(id)}"]`).forEach((node) => {
                    if (!node.dataset) return;
                    node.dataset.status = status;
                    node.dataset.storedStatus = therapist.stored_status || status;
                    node.dataset.dayOffUntil = therapist.day_off_until || '';
                    node.dataset.workOnOffDay = therapist.work_on_off_day ? '1' : '0';
                    node.dataset.workingDays = JSON.stringify(therapist.working_days || []);
                });

                recalcStats();
            }

            const ttScheduleModal = document.getElementById('ttScheduleModal');
            const ttScheduleSettingsModal = document.getElementById('ttScheduleSettingsModal');
            const ttScheduleForm = document.getElementById('ttScheduleForm');
            const ttScheduleSettingsForm = document.getElementById('ttScheduleSettingsForm');
            const ttScheduleTitle = document.getElementById('ttScheduleTitle');
            const ttScheduleDayOffField = document.getElementById('ttScheduleDayOffField');
            const ttScheduleDaysField = document.getElementById('ttScheduleDaysField');
            const ttScheduleUseClinicDays = document.getElementById('ttScheduleUseClinicDays');
            const ttScheduleDayOffUntil = document.getElementById('ttScheduleDayOffUntil');
            const ttScheduleWorkOnOffDay = document.getElementById('ttScheduleWorkOnOffDay');
            const ttScheduleUpdateUrl = document.getElementById('ttScheduleUpdateUrl');

            function parseWorkingDays(raw) {
                if (Array.isArray(raw)) return raw.map((d) => parseInt(d, 10)).filter((d) => !Number.isNaN(d));
                try {
                    const parsed = JSON.parse(String(raw || '[]'));
                    return Array.isArray(parsed) ? parsed.map((d) => parseInt(d, 10)).filter((d) => !Number.isNaN(d)) : [];
                } catch (e) {
                    return [];
                }
            }

            function syncScheduleFieldVisibility() {
                const type = ttScheduleForm?.querySelector('input[name="availability_type"]:checked')?.value || 'available';
                ttScheduleDayOffField?.classList.toggle('hidden', type !== 'day-off');
                ttScheduleDaysField?.classList.toggle('hidden', ttScheduleUseClinicDays?.checked !== false);
            }

            function setWorkingDayChecks(container, days) {
                if (!container) return;
                const wanted = new Set((days || []).map((d) => parseInt(d, 10)));
                container.querySelectorAll('[data-tt-working-day]').forEach((input) => {
                    if (!(input instanceof HTMLInputElement)) return;
                    input.checked = wanted.has(parseInt(input.value, 10));
                });
            }

            function collectWorkingDayChecks(container, selector) {
                if (!container) return [];
                const match = selector || '[data-tt-working-day]:checked';
                return Array.from(container.querySelectorAll(match))
                    .map((input) => parseInt(input.value, 10))
                    .filter((d) => !Number.isNaN(d));
            }

            function openScheduleModal(el) {
                if (!ttScheduleModal || !ttScheduleForm) return;
                const ds = el.dataset || {};
                const name = ds.name || 'Therapist';
                if (ttScheduleTitle) ttScheduleTitle.textContent = `Availability — ${name}`;
                if (ttScheduleUpdateUrl) ttScheduleUpdateUrl.value = ds.updateUrl || '';

                const stored = (ds.storedStatus || ds.status || 'available').toLowerCase();
                const dayOffUntil = ds.dayOffUntil || '';
                let type = 'available';
                if (dayOffUntil) {
                    type = 'day-off';
                } else if (stored === 'off-duty') {
                    type = 'off-duty';
                }

                ttScheduleForm.querySelectorAll('input[name="availability_type"]').forEach((input) => {
                    if (input instanceof HTMLInputElement) {
                        input.checked = input.value === type;
                    }
                });

                if (ttScheduleDayOffUntil) ttScheduleDayOffUntil.value = dayOffUntil;
                if (ttScheduleWorkOnOffDay) ttScheduleWorkOnOffDay.checked = ds.workOnOffDay === '1';

                const customDays = parseWorkingDays(ds.workingDays || '[]');
                if (ttScheduleUseClinicDays) {
                    ttScheduleUseClinicDays.checked = customDays.length === 0;
                }
                setWorkingDayChecks(ttScheduleDaysField, customDays.length ? customDays : ttDefaultWorkingDays);

                syncScheduleFieldVisibility();
                ttScheduleModal.classList.remove('hidden');
                document.body.classList.add('modal-open');
            }

            function closeScheduleModal() {
                ttScheduleModal?.classList.add('hidden');
                if (!modal || modal.classList.contains('hidden')) {
                    if (!ttScheduleSettingsModal || ttScheduleSettingsModal.classList.contains('hidden')) {
                        if (!viewModal || viewModal.classList.contains('hidden')) {
                            document.body.classList.remove('modal-open');
                        }
                    }
                }
            }

            function openScheduleSettingsModal() {
                ttScheduleSettingsModal?.classList.remove('hidden');
                document.body.classList.add('modal-open');
            }

            function closeScheduleSettingsModal() {
                ttScheduleSettingsModal?.classList.add('hidden');
                if (!ttScheduleModal || ttScheduleModal.classList.contains('hidden')) {
                    if (!modal || modal.classList.contains('hidden')) {
                        document.body.classList.remove('modal-open');
                    }
                }
            }

            document.querySelectorAll('[data-open-tt-schedule]').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    openScheduleModal(btn);
                });
            });

            document.querySelector('[data-open-tt-schedule-settings]')?.addEventListener('click', openScheduleSettingsModal);

            ttScheduleModal?.querySelectorAll('[data-close-tt-schedule]').forEach((el) => {
                el.addEventListener('click', closeScheduleModal);
            });
            ttScheduleSettingsModal?.querySelectorAll('[data-close-tt-schedule-settings]').forEach((el) => {
                el.addEventListener('click', closeScheduleSettingsModal);
            });

            ttScheduleForm?.querySelectorAll('input[name="availability_type"]').forEach((input) => {
                input.addEventListener('change', syncScheduleFieldVisibility);
            });
            ttScheduleUseClinicDays?.addEventListener('change', syncScheduleFieldVisibility);

            ttScheduleForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const url = ttScheduleUpdateUrl?.value || '';
                if (!url) return;

                const formData = new FormData(ttScheduleForm);
                const payload = {
                    availability_type: formData.get('availability_type'),
                    day_off_until: formData.get('day_off_until') || null,
                    work_on_off_day: formData.get('work_on_off_day') ? 1 : 0,
                    use_clinic_working_days: formData.get('use_clinic_working_days') ? 1 : 0,
                    working_days: collectWorkingDayChecks(ttScheduleDaysField),
                };

                try {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': ttCsrfToken,
                        },
                        body: JSON.stringify(payload),
                    });
                    const json = await res.json();
                    if (!res.ok || !json?.ok) {
                        window.alert(json?.message || 'Could not save availability.');
                        return;
                    }
                    refreshTherapistFromApi(json.therapist);
                    closeScheduleModal();
                } catch (err) {
                    window.alert('Could not save availability.');
                }
            });

            ttScheduleSettingsForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!ttScheduleSettingsUrl) return;

                const formData = new FormData(ttScheduleSettingsForm);
                const payload = {
                    automation_enabled: formData.get('automation_enabled') ? 1 : 0,
                    default_working_days: collectWorkingDayChecks(ttScheduleSettingsForm, '[data-tt-default-working-day]:checked'),
                    apply_working_days_to_all: formData.get('apply_working_days_to_all') ? 1 : 0,
                };

                try {
                    const res = await fetch(ttScheduleSettingsUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': ttCsrfToken,
                        },
                        body: JSON.stringify(payload),
                    });
                    const json = await res.json();
                    if (!res.ok || !json?.ok) {
                        window.alert(json?.message || 'Could not save schedule settings.');
                        return;
                    }
                    (json.therapists || []).forEach((therapist) => refreshTherapistFromApi(therapist));
                    closeScheduleSettingsModal();
                } catch (err) {
                    window.alert('Could not save schedule settings.');
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && ttScheduleModal && !ttScheduleModal.classList.contains('hidden')) closeScheduleModal();
                if (e.key === 'Escape' && ttScheduleSettingsModal && !ttScheduleSettingsModal.classList.contains('hidden')) closeScheduleSettingsModal();
            });

            viewOpenEls.forEach((el) => {
                el.addEventListener('click', (e) => {
                    if (e.target.closest('[data-open-tt-edit], [data-tt-delete], [data-open-tt-schedule]')) return;
                    e.preventDefault();
                    openView(el);
                });
                el.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openView(el);
                    }
                });
            });

            viewCloseEls.forEach((el) => el.addEventListener('click', closeView));
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && viewModal && !viewModal.classList.contains('hidden')) closeView();
                if (e.key === 'Escape' && ttDeleteModal && !ttDeleteModal.classList.contains('hidden')) closeDeleteModal();
            });
        })();
    </script>
</body>
</html>
