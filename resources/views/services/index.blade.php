<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Spa services catalog">
    <title>Services</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/services.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body>
    @php
        $defaultServiceImage = asset('images/login/background.jpg');
        $landingImageBase = asset('images/landing');
    @endphp

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
                @include('partials.sidebar-nav', ['active' => 'services'])
                <div class="sidebar-footer">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="logout" type="submit"><span class="nav-icon"><i class="bi bi-box-arrow-right"></i></span><span class="nav-text">Logout</span></button>
                    </form>
                </div>
            </aside>

            <main class="main">
                <div class="topbar">
                    <div class="welcome">
                        @include('partials.topbar-panel-badge')
                        <h1>Services</h1>
                        <div class="subtitle">View massage offerings, pricing, and availability</div>
                    </div>
                    <div class="right">
                        <div class="actions" aria-label="Services actions">
                            <form class="search" method="get" action="{{ route('services.index') }}" role="search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                @if (($availability ?? 'available') !== 'available')
                                    <input type="hidden" name="availability" value="{{ $availability }}">
                                @endif
                                <input
                                    type="search"
                                    name="search"
                                    placeholder="Search services..."
                                    aria-label="Search services"
                                    value="{{ $search ?? '' }}"
                                    autocomplete="off"
                                >
                            </form>
                            @include('partials.topbar-notifications')
                            @include('partials.topbar-settings')
                        </div>
                        @include('partials.topbar-profile')
                    </div>
                </div>

                @if (session('status'))
                    <div class="toast-success" id="status-toast" role="status" aria-live="polite">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>{{ session('status') }}</span>
                        <button type="button" class="toast-close" aria-label="Close">&times;</button>
                    </div>
                @endif

                <section class="services-wrap" aria-label="Services catalog">
                    <div class="services-summary">
                        <div class="services-summary-left">
                            <span class="services-count">{{ $totalServices ?? 0 }} service{{ ($totalServices ?? 0) === 1 ? '' : 's' }}</span>
                            <div class="services-filters" aria-label="Filter by availability">
                                <a
                                    class="services-filter {{ ($availability ?? 'available') === 'available' ? 'is-active' : '' }}"
                                    href="{{ route('services.index', array_filter(['search' => $search ?: null, 'availability' => 'available'])) }}"
                                >Available ({{ $availableCount ?? 0 }})</a>
                                <a
                                    class="services-filter {{ ($availability ?? 'available') === 'unavailable' ? 'is-active' : '' }}"
                                    href="{{ route('services.index', array_filter(['search' => $search ?: null, 'availability' => 'unavailable'])) }}"
                                >Unavailable ({{ $unavailableCount ?? 0 }})</a>
                                <a
                                    class="services-filter {{ ($availability ?? 'available') === 'all' ? 'is-active' : '' }}"
                                    href="{{ route('services.index', array_filter(['search' => $search ?: null, 'availability' => 'all'])) }}"
                                >All</a>
                            </div>
                            @if (($search ?? '') !== '')
                                <a class="services-clear-search" href="{{ route('services.index', ['availability' => $availability ?? 'available']) }}">Clear search</a>
                            @endif
                        </div>
                        <button class="add-user-btn" type="button" id="toggle-add-service">
                            <i class="bi bi-plus-circle"></i> Add Service
                        </button>
                    </div>

                    <div class="services-grid">
                        @forelse ($services as $service)
                            <button
                                type="button"
                                class="service-card {{ empty($service['is_active']) ? 'is-unavailable' : '' }}"
                                data-service-search-item="true"
                                data-open-service-edit="true"
                                data-service-id="{{ $service['id'] }}"
                                data-service-name="{{ $service['name'] }}"
                                data-service-price="{{ $service['price_amount'] }}"
                                data-service-duration="{{ $service['duration_minutes'] }}"
                                data-service-best-for="{{ $service['best_for'] }}"
                                data-service-description="{{ $service['description'] ?? $service['desc'] }}"
                                data-service-image="{{ $service['image'] ?? '' }}"
                                data-service-image-url="{{ $service['image_url'] }}"
                                data-service-prenatal="{{ ! empty($service['prenatal_only']) ? '1' : '0' }}"
                                data-service-active="{{ ! empty($service['is_active']) ? '1' : '0' }}"
                                data-service-slots="{{ $service['time_slots_count'] ?? 0 }}"
                                data-service-bookings="{{ $service['total_bookings'] ?? 0 }}"
                                aria-label="Edit {{ $service['name'] }}"
                            >
                                <div class="service-card-media">
                                    <img src="{{ $service['image_url'] }}" alt="">
                                    @if (empty($service['is_active']))
                                        <span class="service-badge service-badge-unavailable">Unavailable</span>
                                    @elseif (! empty($service['prenatal_only']))
                                        <span class="service-badge">Prenatal</span>
                                    @endif
                                    <span class="service-card-overlay">
                                        <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                        <span>Edit service</span>
                                    </span>
                                </div>
                                <div class="service-card-body">
                                    <h3>{{ $service['name'] }}</h3>
                                    <p class="service-desc">{{ $service['desc'] }}</p>
                                    <div class="service-meta">
                                        <span><i class="bi bi-clock"></i> {{ $service['duration'] }}</span>
                                        <span><i class="bi bi-tag"></i> {{ $service['price'] }}</span>
                                    </div>
                                    <div class="service-meta service-meta-sub">
                                        <span><i class="bi bi-heart"></i> {{ $service['best_for'] }}</span>
                                    </div>
                                    <div class="service-stats">
                                        <span>{{ $service['time_slots_count'] ?? 0 }} time slots</span>
                                        <span>{{ $service['total_bookings'] ?? 0 }} bookings</span>
                                    </div>
                                </div>
                            </button>
                        @empty
                            <div class="services-empty">
                                @if (($search ?? '') !== '')
                                    No services match your search.
                                @else
                                    No services available.
                                @endif
                            </div>
                        @endforelse
                    </div>
                </section>
            </main>
        </div>
    </div>

    <div class="profile-modal hidden-section" id="service-modal" role="dialog" aria-modal="true" aria-labelledby="service-modal-title">
        <div class="profile-modal-backdrop" data-close-service-modal="true"></div>
        <div class="profile-modal-content service-modal-panel">
            <button class="profile-modal-close service-modal-close" type="button" id="close-service-modal" aria-label="Close">&times;</button>

            <div class="service-modal-layout">
                <aside class="service-modal-preview" aria-hidden="false">
                    <div class="service-preview-card">
                        <div class="service-preview-media">
                            <img id="service-preview-image" src="{{ $defaultServiceImage }}" alt="">
                            <span class="service-preview-badge hidden-section" id="service-preview-badge">Prenatal</span>
                            <span class="service-preview-badge service-preview-badge-unavailable hidden-section" id="service-preview-unavailable-badge">Unavailable</span>
                        </div>
                        <div class="service-preview-body">
                            <p class="service-preview-label">Live preview</p>
                            <h4 id="service-preview-name">Service name</h4>
                            <p id="service-preview-desc" class="service-preview-desc">Description appears here.</p>
                            <div class="service-preview-chips">
                                <span id="service-preview-duration"><i class="bi bi-clock"></i> 60 min</span>
                                <span id="service-preview-price"><i class="bi bi-tag"></i> PHP 0.00</span>
                            </div>
                            <p id="service-preview-best-for" class="service-preview-best"><i class="bi bi-heart"></i> Best for</p>
                            <div class="service-preview-stats" id="service-preview-stats"></div>
                        </div>
                    </div>
                </aside>

                <div class="service-modal-form-wrap">
                    <div id="service-details-panel" class="service-details-panel">
                        @if ($errors->any())
                            <div class="services-form-errors" role="alert">
                                @foreach ($errors->all() as $err)
                                    <div>{{ $err }}</div>
                                @endforeach
                            </div>
                        @endif

                        <form class="service-modal-form" id="service-form" method="POST" action="{{ route('services.store') }}">
                            @csrf
                            <input type="hidden" name="_modal" id="service-modal-mode" value="{{ old('_modal', 'add') }}">
                            <input type="hidden" name="_service_id" id="service-record-id" value="{{ old('_service_id') }}">

                            <header class="service-modal-header">
                                <div class="service-modal-icon" aria-hidden="true">
                                    <i class="bi bi-grid" id="service-modal-header-icon"></i>
                                </div>
                                <div class="service-modal-header-copy">
                                    <h3 class="profile-modal-title" id="service-modal-title">Add Service</h3>
                                    <p class="service-modal-subtitle" id="service-modal-subtitle">Create a new massage offering for booking.</p>
                                </div>
                            </header>

                            <div class="service-modal-tabs hidden-section" id="service-modal-tabs" role="tablist" aria-label="Service editor sections">
                                <button type="button" class="service-modal-tab is-active" data-service-tab="details" role="tab" aria-selected="true">
                                    <i class="bi bi-sliders" aria-hidden="true"></i> Details
                                </button>
                                <button type="button" class="service-modal-tab" data-service-tab="timeslots" role="tab" aria-selected="false">
                                    <i class="bi bi-clock-history" aria-hidden="true"></i> Time slots
                                </button>
                            </div>

                            <div class="service-form-flow">
                                <section class="service-form-section">
                                    <h4 class="service-form-section-title">
                                        <i class="bi bi-card-text" aria-hidden="true"></i>
                                        Basic information
                                    </h4>
                                    <div class="profile-field">
                                        <label for="service-name">Service name</label>
                                        <input id="service-name" type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Swedish Massage">
                                    </div>
                                    <div class="profile-field">
                                        <label for="service-description">Description</label>
                                        <textarea id="service-description" name="description" rows="3" required placeholder="Describe the treatment experience...">{{ old('description') }}</textarea>
                                    </div>
                                </section>

                                <section class="service-form-section">
                                    <h4 class="service-form-section-title">
                                        <i class="bi bi-currency-exchange" aria-hidden="true"></i>
                                        Pricing &amp; duration
                                    </h4>
                                    <div class="profile-field two-col">
                                        <div>
                                            <label for="service-price">Price (PHP)</label>
                                            <input id="service-price" type="number" name="price_amount" value="{{ old('price_amount') }}" min="0" step="0.01" required placeholder="0.00">
                                        </div>
                                        <div>
                                            <label for="service-duration">Duration (minutes)</label>
                                            <input id="service-duration" type="number" name="duration_minutes" value="{{ old('duration_minutes', 60) }}" min="15" max="240" required>
                                        </div>
                                    </div>
                                    <div class="profile-field">
                                        <label for="service-best-for">Best for</label>
                                        <input id="service-best-for" type="text" name="best_for" value="{{ old('best_for') }}" placeholder="e.g. Stress relief" required>
                                    </div>
                                </section>

                                <section class="service-form-section">
                                    <h4 class="service-form-section-title">
                                        <i class="bi bi-image" aria-hidden="true"></i>
                                        Media &amp; options
                                    </h4>
                                    <div class="profile-field">
                                        <label for="service-image">Cover image filename</label>
                                        <input id="service-image" type="text" name="image" value="{{ old('image') }}" placeholder="swedish-massage.webp">
                                        <span class="services-field-hint">Upload to <code>public/images/landing/</code> — preview updates as you type.</span>
                                    </div>
                                    <div class="services-checkbox-card">
                                        <label class="services-checkbox" for="service-prenatal">
                                            <input type="checkbox" name="prenatal_only" id="service-prenatal" value="1" @checked(old('prenatal_only'))>
                                            <span class="services-checkbox-copy">
                                                <strong>Prenatal-only service</strong>
                                                <small>Only shown to eligible expecting clients</small>
                                            </span>
                                        </label>
                                    </div>
                                </section>

                                <section class="service-form-section service-availability-section hidden-section" id="service-availability-section">
                                    <h4 class="service-form-section-title">
                                        <i class="bi bi-toggle2-on" aria-hidden="true"></i>
                                        Booking visibility
                                    </h4>
                                    <p class="service-availability-copy" id="service-availability-copy">This service is available for new bookings on the landing page and booking flow.</p>
                                </section>
                            </div>

                            <div class="service-modal-actions">
                                <div class="service-modal-actions-left hidden-section" id="service-footer-availability">
                                    <button type="submit" class="service-toggle-btn" id="service-toggle-btn" form="service-toggle-form">
                                        <i class="bi bi-slash-circle" id="service-toggle-icon" aria-hidden="true"></i>
                                        <span id="service-toggle-label">Make unavailable</span>
                                    </button>
                                </div>
                                <div class="service-modal-actions-right">
                                    <button type="button" class="user-action" id="service-form-cancel">Cancel</button>
                                    <button type="submit" class="user-action add" id="service-form-submit">
                                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                                        <span id="service-form-submit-label">Save Service</span>
                                    </button>
                                </div>
                            </div>
                        </form>

                        <form id="service-toggle-form" method="POST" action="" class="service-toggle-form-inline hidden-section" aria-hidden="true">
                            @csrf
                            @if (($search ?? '') !== '')
                                <input type="hidden" name="search" value="{{ $search }}">
                            @endif
                        </form>
                    </div>

                    <div id="service-timeslots-panel" class="service-timeslots-panel hidden-section" aria-label="Service time slots editor">
                        <div class="timeslots-panel-head">
                            <div>
                                <h4 class="timeslots-panel-title">Manage time slots</h4>
                                <p class="timeslots-help" id="timeslots-help">
                                    Click time slots to turn availability on or off for the default weekly schedule.
                                </p>
                            </div>
                        </div>

                        <div class="timeslots-toolbar">
                            <div class="timeslots-mode-toggle" role="group" aria-label="Schedule mode">
                                <button type="button" class="timeslots-mode-btn is-active" data-slot-mode="weekly">Weekly schedule</button>
                                <button type="button" class="timeslots-mode-btn" data-slot-mode="date">Specific date</button>
                            </div>
                            <div class="timeslots-toolbar-row">
                                <label class="timeslots-date-field hidden-section" id="timeslots-date-wrap" for="timeslots-date">
                                    <span>Date</span>
                                    <input type="date" id="timeslots-date">
                                </label>
                                <label class="timeslots-store-closed hidden-section" id="timeslots-store-closed-wrap">
                                    <input type="checkbox" id="timeslots-store-closed">
                                    <span>Store closed this day (holiday / closure)</span>
                                </label>
                            </div>
                        </div>

                        <div class="timeslots-summary">
                            <span id="timeslots-enabled-count">0 available</span>
                            <button type="button" class="timeslots-quick-btn" id="timeslots-select-all">Select all</button>
                            <button type="button" class="timeslots-quick-btn" id="timeslots-clear-all">Clear all</button>
                            <button type="button" class="timeslots-quick-btn timeslots-add-btn" id="timeslots-add-btn">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                Add time slot
                            </button>
                        </div>

                        <div class="timeslots-add-form hidden-section" id="timeslots-add-form">
                            <label class="timeslots-add-field" for="timeslots-add-time">
                                <span>New time</span>
                                <input type="time" id="timeslots-add-time" step="1800" value="08:00">
                            </label>
                            <div class="timeslots-add-actions">
                                <button type="button" class="timeslots-quick-btn" id="timeslots-add-cancel">Cancel</button>
                                <button type="button" class="timeslots-quick-btn timeslots-add-confirm" id="timeslots-add-confirm">Add slot</button>
                            </div>
                        </div>

                        <div class="timeslots-grid" id="timeslots-grid" role="listbox" aria-label="Time slots"></div>

                        <footer class="service-modal-actions timeslots-actions">
                            <button type="button" class="user-action" id="timeslots-back-btn">Back to details</button>
                            <button type="button" class="user-action add" id="timeslots-save-btn">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                Save time slots
                            </button>
                        </footer>
                        <p class="timeslots-status" id="timeslots-status" role="status" aria-live="polite"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const serviceModal = document.getElementById('service-modal');
            const serviceForm = document.getElementById('service-form');
            const toggleAddService = document.getElementById('toggle-add-service');
            const closeServiceModal = document.getElementById('close-service-modal');
            const serviceFormCancel = document.getElementById('service-form-cancel');
            const modalTitle = document.getElementById('service-modal-title');
            const modalSubtitle = document.getElementById('service-modal-subtitle');
            const modalHeaderIcon = document.getElementById('service-modal-header-icon');
            const modalModeField = document.getElementById('service-modal-mode');
            const serviceRecordIdField = document.getElementById('service-record-id');
            const submitLabel = document.getElementById('service-form-submit-label');
            const previewStats = document.getElementById('service-preview-stats');

            const defaultImage = @json($defaultServiceImage);
            const landingImageBase = @json($landingImageBase);
            const storeUrl = @json(route('services.store'));
            const updateUrlTemplate = @json(url('/services/__ID__'));
            const toggleUrlTemplate = @json(url('/services/__ID__/toggle-availability'));

            const availabilitySection = document.getElementById('service-availability-section');
            const availabilityCopy = document.getElementById('service-availability-copy');
            const serviceToggleForm = document.getElementById('service-toggle-form');
            const serviceToggleBtn = document.getElementById('service-toggle-btn');
            const serviceToggleLabel = document.getElementById('service-toggle-label');
            const serviceToggleIcon = document.getElementById('service-toggle-icon');
            const previewUnavailableBadge = document.getElementById('service-preview-unavailable-badge');
            const serviceFooterAvailability = document.getElementById('service-footer-availability');
            const serviceModalTabs = document.getElementById('service-modal-tabs');
            const serviceDetailsPanel = document.getElementById('service-details-panel');
            const serviceTimeslotsPanel = document.getElementById('service-timeslots-panel');
            const serviceModalLayout = document.querySelector('.service-modal-layout');
            const timeslotsGrid = document.getElementById('timeslots-grid');
            const timeslotsHelp = document.getElementById('timeslots-help');
            const timeslotsDate = document.getElementById('timeslots-date');
            const timeslotsDateWrap = document.getElementById('timeslots-date-wrap');
            const timeslotsStoreClosed = document.getElementById('timeslots-store-closed');
            const timeslotsStoreClosedWrap = document.getElementById('timeslots-store-closed-wrap');
            const timeslotsEnabledCount = document.getElementById('timeslots-enabled-count');
            const timeslotsStatus = document.getElementById('timeslots-status');
            const timeslotsSaveBtn = document.getElementById('timeslots-save-btn');
            const timeslotsBackBtn = document.getElementById('timeslots-back-btn');
            const timeslotsAddBtn = document.getElementById('timeslots-add-btn');
            const timeslotsAddForm = document.getElementById('timeslots-add-form');
            const timeslotsAddTime = document.getElementById('timeslots-add-time');
            const timeslotsAddConfirm = document.getElementById('timeslots-add-confirm');
            const timeslotsAddCancel = document.getElementById('timeslots-add-cancel');
            const timeslotsDataTemplate = @json(url('/services/__ID__/timeslots'));
            const timeslotsUpdateTemplate = @json(url('/services/__ID__/timeslots'));
            const timeslotsStoreTemplate = @json(url('/services/__ID__/timeslots'));
            const csrfToken = document.querySelector('#service-form input[name="_token"]')?.value ?? '';

            let currentServiceId = null;
            let slotEditorMode = 'weekly';
            let slotEditorSlots = [];
            let slotEditorLoading = false;

            const fields = {
                name: document.getElementById('service-name'),
                description: document.getElementById('service-description'),
                price: document.getElementById('service-price'),
                duration: document.getElementById('service-duration'),
                bestFor: document.getElementById('service-best-for'),
                image: document.getElementById('service-image'),
                prenatal: document.getElementById('service-prenatal'),
            };

            const preview = {
                image: document.getElementById('service-preview-image'),
                badge: document.getElementById('service-preview-badge'),
                name: document.getElementById('service-preview-name'),
                desc: document.getElementById('service-preview-desc'),
                duration: document.getElementById('service-preview-duration'),
                price: document.getElementById('service-preview-price'),
                bestFor: document.getElementById('service-preview-best-for'),
            };

            let editStatsHtml = '';

            function formatPrice(amount) {
                const value = parseFloat(String(amount ?? '0'));
                if (!Number.isFinite(value)) return 'PHP 0.00';
                return 'PHP ' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function resolveImageUrl(filename, fallbackUrl) {
                if (fallbackUrl) return fallbackUrl;
                const trimmed = String(filename ?? '').trim();
                if (!trimmed) return defaultImage;
                return landingImageBase.replace(/\/$/, '') + '/' + trimmed.replace(/^\//, '');
            }

            function syncPreview() {
                const name = fields.name?.value.trim() || 'Service name';
                const desc = fields.description?.value.trim() || 'Description appears here.';
                const minutes = parseInt(String(fields.duration?.value ?? '60'), 10) || 60;
                const price = fields.price?.value ?? '0';
                const bestFor = fields.bestFor?.value.trim() || 'Best for';
                const isPrenatal = fields.prenatal?.checked === true;

                preview.name.textContent = name;
                preview.desc.textContent = desc;
                preview.duration.innerHTML = '<i class="bi bi-clock"></i> ' + minutes + ' min';
                preview.price.innerHTML = '<i class="bi bi-tag"></i> ' + formatPrice(price);
                preview.bestFor.innerHTML = '<i class="bi bi-heart"></i> ' + bestFor;
                preview.image.src = resolveImageUrl(fields.image?.value, '');
                preview.image.alt = name;
                preview.badge.classList.toggle('hidden-section', !isPrenatal);

                if (previewStats) {
                    previewStats.innerHTML = editStatsHtml;
                }
            }

            function setAvailabilityControls(isActive, serviceId) {
                if (!availabilitySection || !serviceToggleForm || !serviceToggleBtn) return;

                if (!serviceId) {
                    availabilitySection.classList.add('hidden-section');
                    serviceFooterAvailability?.classList.add('hidden-section');
                    serviceToggleForm.classList.add('hidden-section');
                    serviceToggleBtn.setAttribute('disabled', 'disabled');
                    return;
                }

                availabilitySection.classList.remove('hidden-section');
                serviceFooterAvailability?.classList.remove('hidden-section');
                serviceToggleForm.classList.remove('hidden-section');
                serviceToggleBtn.removeAttribute('disabled');
                serviceToggleForm.action = toggleUrlTemplate.replace('__ID__', String(serviceId));

                if (isActive) {
                    availabilitySection.classList.remove('is-unavailable');
                    availabilityCopy.textContent = 'This service is available for new bookings on the landing page and booking flow.';
                    serviceToggleLabel.textContent = 'Make unavailable';
                    serviceToggleIcon.className = 'bi bi-slash-circle';
                    serviceToggleBtn.classList.remove('is-available-action');
                    serviceToggleBtn.classList.add('is-unavailable-action');
                    previewUnavailableBadge?.classList.add('hidden-section');
                } else {
                    availabilitySection.classList.add('is-unavailable');
                    availabilityCopy.textContent = 'This service is hidden from booking. Customers cannot select it until you make it available again.';
                    serviceToggleLabel.textContent = 'Make available';
                    serviceToggleIcon.className = 'bi bi-check-circle';
                    serviceToggleBtn.classList.remove('is-unavailable-action');
                    serviceToggleBtn.classList.add('is-available-action');
                    previewUnavailableBadge?.classList.remove('hidden-section');
                    preview.badge.classList.add('hidden-section');
                }
            }

            function switchServiceTab(tab) {
                const isTimeslots = tab === 'timeslots';
                serviceDetailsPanel?.classList.toggle('hidden-section', isTimeslots);
                serviceTimeslotsPanel?.classList.toggle('hidden-section', !isTimeslots);
                serviceModalLayout?.classList.toggle('is-timeslots-view', isTimeslots);

                document.querySelectorAll('[data-service-tab]').forEach((btn) => {
                    if (!(btn instanceof HTMLElement)) return;
                    const active = btn.dataset.serviceTab === tab;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                if (isTimeslots && currentServiceId) {
                    loadTimeSlotsEditor().catch(() => {});
                }
            }

            function isTimeslotsStoreClosed() {
                return slotEditorMode === 'date' && timeslotsStoreClosed?.checked === true;
            }

            function updateTimeslotsAddControls() {
                const storeClosed = isTimeslotsStoreClosed();
                if (timeslotsAddBtn) {
                    timeslotsAddBtn.disabled = storeClosed || !currentServiceId;
                }
                if (storeClosed) {
                    timeslotsAddForm?.classList.add('hidden-section');
                }
            }

            function updateTimeslotsToolbar() {
                const isDateMode = slotEditorMode === 'date';
                timeslotsDateWrap?.classList.toggle('hidden-section', !isDateMode);
                timeslotsStoreClosedWrap?.classList.toggle('hidden-section', !isDateMode);

                document.querySelectorAll('[data-slot-mode]').forEach((btn) => {
                    if (!(btn instanceof HTMLElement)) return;
                    btn.classList.toggle('is-active', btn.dataset.slotMode === slotEditorMode);
                });

                if (timeslotsHelp) {
                    timeslotsHelp.textContent = isDateMode
                        ? 'Pick a date to override the weekly schedule — useful for holidays or early closures. Toggle individual times or mark the whole store closed.'
                        : 'Click time slots to set the default weekly availability used for all booking dates.';
                }

                updateTimeslotsAddControls();
            }

            function renderTimeslotsGrid() {
                if (!timeslotsGrid) return;
                timeslotsGrid.innerHTML = '';

                const storeClosed = slotEditorMode === 'date' && timeslotsStoreClosed?.checked === true;

                slotEditorSlots.forEach((slot) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'timeslot-chip' + (slot.enabled ? ' is-enabled' : '');
                    btn.dataset.slotId = String(slot.id);
                    btn.textContent = slot.label;
                    btn.disabled = storeClosed;
                    btn.setAttribute('aria-pressed', slot.enabled ? 'true' : 'false');
                    btn.addEventListener('click', () => {
                        if (storeClosed) return;
                        slot.enabled = !slot.enabled;
                        renderTimeslotsGrid();
                        updateTimeslotsSummary();
                    });
                    timeslotsGrid.appendChild(btn);
                });

                updateTimeslotsSummary();
                updateTimeslotsAddControls();
            }

            function sortSlotEditorSlots() {
                slotEditorSlots.sort((a, b) => {
                    const aTime = Date.parse('1/1/2000 ' + a.label);
                    const bTime = Date.parse('1/1/2000 ' + b.label);
                    if (Number.isFinite(aTime) && Number.isFinite(bTime) && aTime !== bTime) {
                        return aTime - bTime;
                    }
                    return String(a.label).localeCompare(String(b.label));
                });
            }

            async function addTimeSlotFromForm() {
                if (!currentServiceId || slotEditorLoading || isTimeslotsStoreClosed()) return;

                const timeValue = timeslotsAddTime?.value?.trim();
                if (!timeValue) {
                    if (timeslotsStatus) timeslotsStatus.textContent = 'Choose a time for the new slot.';
                    return;
                }

                slotEditorLoading = true;
                if (timeslotsAddConfirm) timeslotsAddConfirm.disabled = true;
                if (timeslotsStatus) timeslotsStatus.textContent = 'Adding time slot...';

                try {
                    const res = await fetch(timeslotsStoreTemplate.replace('__ID__', String(currentServiceId)), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            time: timeValue,
                            mode: slotEditorMode,
                            date: slotEditorMode === 'date' ? timeslotsDate?.value : null,
                        }),
                    });

                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message ?? 'Add failed');

                    if (data.payload) {
                        slotEditorSlots = Array.isArray(data.payload.slots)
                            ? data.payload.slots.map((slot) => ({ ...slot }))
                            : slotEditorSlots;
                        if (timeslotsDate && data.payload.selected_date) {
                            timeslotsDate.value = data.payload.selected_date;
                        }
                        if (timeslotsStoreClosed) {
                            timeslotsStoreClosed.checked = data.payload.store_closed === true;
                        }
                        renderTimeslotsGrid();
                        editStatsHtml = '<span>' + (data.payload.enabled_count ?? slotEditorSlots.filter((s) => s.enabled).length) + ' time slots</span>';
                        syncPreview();
                    }

                    timeslotsAddForm?.classList.add('hidden-section');
                    if (timeslotsStatus) timeslotsStatus.textContent = data.message ?? 'Time slot saved.';
                } catch (error) {
                    if (timeslotsStatus) {
                        timeslotsStatus.textContent = error?.message ?? 'Could not add time slot. Please try again.';
                    }
                } finally {
                    slotEditorLoading = false;
                    if (timeslotsAddConfirm) timeslotsAddConfirm.disabled = false;
                }
            }

            function updateTimeslotsSummary() {
                const enabledCount = slotEditorSlots.filter((slot) => slot.enabled).length;
                if (timeslotsEnabledCount) {
                    timeslotsEnabledCount.textContent = enabledCount + ' available';
                }
            }

            async function loadTimeSlotsEditor() {
                if (!currentServiceId || slotEditorLoading) return;
                slotEditorLoading = true;
                if (timeslotsStatus) timeslotsStatus.textContent = 'Loading time slots...';

                const url = new URL(timeslotsDataTemplate.replace('__ID__', String(currentServiceId)), window.location.origin);
                url.searchParams.set('mode', slotEditorMode);
                if (slotEditorMode === 'date' && timeslotsDate?.value) {
                    url.searchParams.set('date', timeslotsDate.value);
                }

                try {
                    const res = await fetch(url.toString(), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    slotEditorSlots = Array.isArray(data.slots) ? data.slots.map((slot) => ({ ...slot })) : [];
                    if (timeslotsDate && data.selected_date) {
                        timeslotsDate.value = data.selected_date;
                    }
                    if (timeslotsStoreClosed) {
                        timeslotsStoreClosed.checked = data.store_closed === true;
                    }
                    renderTimeslotsGrid();
                    if (timeslotsStatus) timeslotsStatus.textContent = '';
                } catch (error) {
                    if (timeslotsStatus) timeslotsStatus.textContent = 'Could not load time slots. Please try again.';
                } finally {
                    slotEditorLoading = false;
                }
            }

            async function saveTimeSlotsEditor() {
                if (!currentServiceId || slotEditorLoading) return;
                slotEditorLoading = true;
                if (timeslotsSaveBtn) timeslotsSaveBtn.disabled = true;
                if (timeslotsStatus) timeslotsStatus.textContent = 'Saving...';

                const payload = {
                    mode: slotEditorMode,
                    enabled_slot_ids: slotEditorSlots.filter((slot) => slot.enabled).map((slot) => slot.id),
                    store_closed: slotEditorMode === 'date' ? timeslotsStoreClosed?.checked === true : false,
                };

                if (slotEditorMode === 'date' && timeslotsDate?.value) {
                    payload.date = timeslotsDate.value;
                }

                try {
                    const res = await fetch(timeslotsUpdateTemplate.replace('__ID__', String(currentServiceId)), {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(payload),
                    });

                    const data = await res.json();
                    if (!res.ok) throw new Error(data.message ?? 'Save failed');

                    if (data.payload) {
                        slotEditorSlots = Array.isArray(data.payload.slots) ? data.payload.slots.map((slot) => ({ ...slot })) : slotEditorSlots;
                        if (timeslotsStoreClosed) {
                            timeslotsStoreClosed.checked = data.payload.store_closed === true;
                        }
                        renderTimeslotsGrid();
                    }

                    if (timeslotsStatus) timeslotsStatus.textContent = data.message ?? 'Time slots saved.';
                    editStatsHtml = '<span>' + (data.payload?.enabled_count ?? slotEditorSlots.filter((s) => s.enabled).length) + ' time slots</span>';
                    syncPreview();
                } catch (error) {
                    if (timeslotsStatus) timeslotsStatus.textContent = 'Could not save time slots. Please try again.';
                } finally {
                    slotEditorLoading = false;
                    if (timeslotsSaveBtn) timeslotsSaveBtn.disabled = false;
                }
            }

            function bindPreviewListeners() {
                Object.values(fields).forEach((field) => {
                    if (!field) return;
                    field.addEventListener('input', syncPreview);
                    field.addEventListener('change', syncPreview);
                });
            }

            function openServiceModal(mode, data, options) {
                const opts = options || {};
                const preserveFields = opts.preserveFields === true;
                const isEdit = mode === 'edit';
                editStatsHtml = '';

                if (isEdit && data) {
                    currentServiceId = String(data.id ?? '');
                    serviceModalTabs?.classList.remove('hidden-section');
                    switchServiceTab('details');
                    fields.name.value = data.name ?? '';
                    fields.description.value = data.description ?? '';
                    fields.price.value = data.price ?? '';
                    fields.duration.value = data.duration ?? '60';
                    fields.bestFor.value = data.bestFor ?? '';
                    fields.image.value = data.image ?? '';
                    fields.prenatal.checked = data.prenatal === '1';

                    serviceForm.action = updateUrlTemplate.replace('__ID__', String(data.id));
                    let methodInput = serviceForm.querySelector('input[name="_method"]');
                    if (!methodInput) {
                        methodInput = document.createElement('input');
                        methodInput.type = 'hidden';
                        methodInput.name = '_method';
                        serviceForm.appendChild(methodInput);
                    }
                    methodInput.value = 'PUT';

                    modalTitle.textContent = 'Edit Service';
                    modalSubtitle.textContent = 'Update pricing, description, and availability details.';
                    modalHeaderIcon.className = 'bi bi-pencil-square';
                    submitLabel.textContent = 'Update Service';
                    modalModeField.value = 'edit';
                    serviceRecordIdField.value = String(data.id ?? '');

                    const slots = parseInt(String(data.slots ?? '0'), 10) || 0;
                    const bookings = parseInt(String(data.bookings ?? '0'), 10) || 0;
                    editStatsHtml = '<span>' + slots + ' time slots</span><span>' + bookings + ' bookings</span>';

                    preview.image.src = resolveImageUrl(data.image, data.imageUrl);
                    setAvailabilityControls(data.active === '1', data.id);
                } else {
                    currentServiceId = null;
                    serviceModalTabs?.classList.add('hidden-section');
                    switchServiceTab('details');
                    if (!preserveFields) {
                        serviceForm.reset();
                        fields.duration.value = '60';
                        fields.prenatal.checked = false;
                    }

                    serviceForm.action = storeUrl;
                    const methodInput = serviceForm.querySelector('input[name="_method"]');
                    if (methodInput) methodInput.remove();

                    modalTitle.textContent = 'Add Service';
                    modalSubtitle.textContent = 'Create a new massage offering for booking.';
                    modalHeaderIcon.className = 'bi bi-plus-circle';
                    submitLabel.textContent = 'Save Service';
                    modalModeField.value = 'add';
                    serviceRecordIdField.value = '';
                    preview.image.src = defaultImage;
                    setAvailabilityControls(true, null);
                }

                syncPreview();
                serviceModal?.classList.remove('hidden-section');
                document.body.classList.add('modal-open');
                fields.name?.focus();
            }

            function closeServiceModalFn() {
                serviceModal?.classList.add('hidden-section');
                document.body.classList.remove('modal-open');
                switchServiceTab('details');
                timeslotsAddForm?.classList.add('hidden-section');
                if (timeslotsStatus) timeslotsStatus.textContent = '';
            }

            toggleAddService?.addEventListener('click', () => openServiceModal('add'));
            closeServiceModal?.addEventListener('click', closeServiceModalFn);
            serviceFormCancel?.addEventListener('click', closeServiceModalFn);
            serviceModal?.querySelectorAll('[data-close-service-modal="true"]').forEach((el) => {
                el.addEventListener('click', closeServiceModalFn);
            });

            document.querySelectorAll('[data-open-service-edit="true"]').forEach((card) => {
                card.addEventListener('click', () => {
                    openServiceModal('edit', {
                        id: card.getAttribute('data-service-id'),
                        name: card.getAttribute('data-service-name'),
                        price: card.getAttribute('data-service-price'),
                        duration: card.getAttribute('data-service-duration'),
                        bestFor: card.getAttribute('data-service-best-for'),
                        description: card.getAttribute('data-service-description'),
                        image: card.getAttribute('data-service-image'),
                        imageUrl: card.getAttribute('data-service-image-url'),
                        prenatal: card.getAttribute('data-service-prenatal'),
                        active: card.getAttribute('data-service-active'),
                        slots: card.getAttribute('data-service-slots'),
                        bookings: card.getAttribute('data-service-bookings'),
                    });
                });
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && serviceModal && !serviceModal.classList.contains('hidden-section')) {
                    closeServiceModalFn();
                }
            });

            serviceToggleForm?.addEventListener('submit', (event) => {
                const isActive = serviceToggleLabel?.textContent === 'Make unavailable';
                const prompt = isActive
                    ? 'Make this service unavailable? It will be hidden from booking.'
                    : 'Make this service available again for booking?';
                if (!window.confirm(prompt)) {
                    event.preventDefault();
                }
            });

            document.querySelectorAll('[data-service-tab]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!(btn instanceof HTMLElement)) return;
                    switchServiceTab(btn.dataset.serviceTab ?? 'details');
                });
            });

            document.querySelectorAll('[data-slot-mode]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    if (!(btn instanceof HTMLElement)) return;
                    slotEditorMode = btn.dataset.slotMode === 'date' ? 'date' : 'weekly';
                    timeslotsAddForm?.classList.add('hidden-section');
                    updateTimeslotsToolbar();
                    loadTimeSlotsEditor().catch(() => {});
                });
            });

            timeslotsDate?.addEventListener('change', () => {
                if (slotEditorMode === 'date') {
                    loadTimeSlotsEditor().catch(() => {});
                }
            });

            timeslotsStoreClosed?.addEventListener('change', () => {
                renderTimeslotsGrid();
                updateTimeslotsAddControls();
            });

            timeslotsAddBtn?.addEventListener('click', () => {
                if (isTimeslotsStoreClosed()) return;
                timeslotsAddForm?.classList.toggle('hidden-section');
                if (timeslotsAddForm && !timeslotsAddForm.classList.contains('hidden-section')) {
                    timeslotsAddTime?.focus();
                }
            });

            timeslotsAddCancel?.addEventListener('click', () => {
                timeslotsAddForm?.classList.add('hidden-section');
            });

            timeslotsAddConfirm?.addEventListener('click', () => {
                addTimeSlotFromForm().catch(() => {});
            });

            timeslotsAddForm?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addTimeSlotFromForm().catch(() => {});
                }
            });

            timeslotsBackBtn?.addEventListener('click', () => switchServiceTab('details'));
            timeslotsSaveBtn?.addEventListener('click', () => {
                saveTimeSlotsEditor().catch(() => {});
            });

            document.getElementById('timeslots-select-all')?.addEventListener('click', () => {
                if (slotEditorMode === 'date' && timeslotsStoreClosed?.checked) return;
                slotEditorSlots = slotEditorSlots.map((slot) => ({ ...slot, enabled: true }));
                renderTimeslotsGrid();
            });

            document.getElementById('timeslots-clear-all')?.addEventListener('click', () => {
                if (slotEditorMode === 'date' && timeslotsStoreClosed?.checked) return;
                slotEditorSlots = slotEditorSlots.map((slot) => ({ ...slot, enabled: false }));
                renderTimeslotsGrid();
            });

            if (timeslotsDate && !timeslotsDate.value) {
                timeslotsDate.value = new Date().toISOString().slice(0, 10);
            }
            updateTimeslotsToolbar();

            bindPreviewListeners();

            @if ($errors->any())
                @if (old('_modal') === 'edit')
                    openServiceModal('edit', {
                        id: @json(old('_service_id')),
                        name: @json(old('name')),
                        price: @json(old('price_amount')),
                        duration: @json(old('duration_minutes')),
                        bestFor: @json(old('best_for')),
                        description: @json(old('description')),
                        image: @json(old('image')),
                        prenatal: @json(old('prenatal_only') ? '1' : '0'),
                    });
                @else
                    openServiceModal('add', null, { preserveFields: true });
                @endif
            @endif

            const statusToast = document.getElementById('status-toast');
            if (statusToast) {
                const closeBtn = statusToast.querySelector('.toast-close');
                const hideToast = () => statusToast.classList.add('hidden');
                closeBtn?.addEventListener('click', hideToast);
                window.setTimeout(hideToast, 3500);
            }
        })();
    </script>
</body>
</html>
