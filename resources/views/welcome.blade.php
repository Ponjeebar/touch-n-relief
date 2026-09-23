<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Buenos Touche Spa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    @auth
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="{{ asset('css/profile-app-modal.css') }}">
        @if (session('booking_confirmed'))
            <link rel="stylesheet" href="{{ asset('css/booking.css') }}?v={{ filemtime(public_path('css/booking.css')) }}">
            <link rel="stylesheet" href="{{ asset('css/payment-receipt.css') }}">
        @endif
        @if (auth()->user()->needsProfileOnboarding())
            <link rel="stylesheet" href="{{ asset('css/registration-onboarding.css') }}">
        @endif
    @endauth
        @include('partials.chatbot-assets')
    </head>
<body id="top">
    @include('partials.landing-nav', ['navMode' => 'full'])

    <section class="hero">
        <div class="hero-overlay"></div>
        <div class="container hero-content reveal">
            <p class="eyebrow reveal delay-1">WELCOME TO BUENOS TOUCHE SPA</p>
            <h1 class="reveal delay-2">Your Sanctuary of Relaxation and Rejuvenation</h1>
            <p class="hero-text reveal delay-3">
                At Buenos Touche, every treatment is crafted to ease stress, restore balance, and help you feel renewed.
            </p>
            <div class="hero-actions reveal delay-4">
                <a href="#services" class="btn btn-light">View Our Services</a>
            <a href="{{ route('booking.index') }}" class="btn btn-outline hero-book-btn">Book Now</a>
            </div>
        </div>
    </section>

    <section class="intro-card">
        <div class="container">
            <div class="card reveal">
                <h2>Experience the Buenos Touche Difference</h2>
                <p>From calming ambiance to expert therapists, Buenos Touche delivers a complete wellness experience focused on your comfort and care.</p>
            </div>
        </div>
    </section>

    <section class="features container" id="about">
        <div class="left reveal">
            <h2>Why Clients Choose Buenos Touche</h2>
            <p>We combine professional therapy techniques, personalized care, and a soothing environment to support your health and well-being.</p>
            @php
                $landing2Files = glob(public_path('images/landing/landing2/*.{jpg,jpeg,png,webp,gif}'), GLOB_BRACE) ?: [];
                $landing2Slides = collect($landing2Files)
                    ->map(fn ($p) => str_replace(public_path() . DIRECTORY_SEPARATOR, '', $p))
                    ->map(fn ($relative) => str_replace('\\', '/', $relative))
                    ->map(fn ($relative) => asset($relative))
                    ->values()
                    ->all();
            @endphp
            <div class="feature-carousel" data-feature-carousel>
                @forelse ($landing2Slides as $i => $src)
                    <img
                        src="{{ $src }}"
                        alt="Buenos Touche ambience {{ $i + 1 }}"
                        class="feature-slide {{ $i === 0 ? 'active' : '' }}"
                        loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                        decoding="async"
                    >
                @empty
                    <img src="{{ asset('images/login/background.jpg') }}" alt="Spa ambiance">
                @endforelse
            </div>
        </div>
        <div class="right">
            <article class="reveal delay-1">
                <h3>Relaxing Spa Environment</h3>
                <p>Buenos Touche is thoughtfully designed with calming scents, soft lighting, and quiet spaces that help you unwind instantly.</p>
            </article>
            <article class="reveal delay-2">
                <h3>Licensed Wellness Experts</h3>
                <p>Our trained therapists deliver safe, attentive, and effective treatments focused on your personal wellness goals.</p>
            </article>
            <article class="reveal delay-3">
                <h3>Personalized Therapy Plans</h3>
                <p>Every Buenos Touche session is customized to your needs, from deep relief sessions to gentle relaxation care.</p>
            </article>
        </div>
    </section>

    <section class="services" id="services">
        <div class="container">
            <h2 class="reveal">Buenos Touche Service Menu</h2>
            @if (!empty($isPregnantCustomer))
                <p class="section-sub reveal delay-1">Prenatal treatments selected for your safety — other services are not available while you are pregnant.</p>
            @else
                <p class="section-sub reveal delay-1">Browse all available Buenos Touche treatments with clear pricing.</p>
            @endif
            <div class="services-carousel reveal delay-2 {{ count($services) === 1 ? 'is-single' : '' }}" data-carousel>
                <button class="carousel-btn prev" type="button" aria-label="Previous services" data-prev>&#10094;</button>
                <div class="carousel-track-wrap">
                    <div class="carousel-track">
                        @foreach ($services as $service)
                            @php
                                $landingPath = 'images/landing/' . $service['image'];
                                $serviceImage = file_exists(public_path($landingPath))
                                    ? asset($landingPath)
                                    : asset('images/login/background.jpg');
                            @endphp
                            <article
                                class="service-card carousel-slide service-trigger"
                                tabindex="0"
                                role="button"
                                aria-label="View {{ $service['name'] }} details"
                                data-service-name="{{ $service['name'] }}"
                                data-service-price="{{ number_format($service['price_amount'], 2) }}"
                                data-service-duration="{{ $service['duration'] }}"
                                data-service-desc="{{ $service['desc'] }}"
                                data-service-best="{{ $service['best_for'] }}"
                                data-service-times="{{ implode(', ', $service['times']) }}"
                            >
                                <img src="{{ $serviceImage }}" alt="{{ $service['name'] }}">
                                <div class="service-meta">
                                    <h3>{{ $service['name'] }}</h3>
                                    <span class="price">PHP {{ number_format($service['price_amount'], 2) }}</span>
                                </div>
                                <p class="duration">{{ $service['duration'] }}</p>
                                <p>{{ $service['desc'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
                <button class="carousel-btn next" type="button" aria-label="Next services" data-next>&#10095;</button>
            </div>
            <div class="carousel-dots" data-dots></div>
        </div>
    </section>

    <section class="therapists" id="therapists">
        <div class="therapists-bg" aria-hidden="true"></div>
        <div class="container">
            <p class="section-eyebrow reveal">OUR WELLNESS TEAM</p>
            <h2 class="reveal delay-1">Meet Our Therapists</h2>
            <p class="section-sub reveal delay-2">
                Licensed professionals dedicated to personalized care — matched to your service and wellness goals.
            </p>
            @php
                $therapistCount = count($therapists);
                $gridCols = $therapistGridColumns ?? ['desktop' => min($therapistCount ?: 1, 5), 'tablet' => 3, 'mobile' => 2];
            @endphp
            <div
                class="therapists-grid"
                data-therapist-count="{{ $therapistCount }}"
                style="--therapist-cols: {{ $gridCols['desktop'] }}; --therapist-cols-tablet: {{ $gridCols['tablet'] }}; --therapist-cols-mobile: {{ $gridCols['mobile'] }};"
            >
                @foreach ($therapists as $i => $therapist)
                    @php
                        $photoUrl = $therapist['photo_url'] ?? asset('images/landing/therapist/' . ($therapist['photo'] ?? ''));
                    @endphp
                    <article
                        class="therapist-card therapist-trigger reveal delay-{{ min($i + 1, 5) }}"
                        tabindex="0"
                        role="button"
                        aria-label="View {{ $therapist['name'] }} profile"
                        style="--card-accent: {{ $therapist['accent'] }}"
                        data-therapist-name="{{ $therapist['name'] }}"
                        data-therapist-role="{{ $therapist['role'] }}"
                        data-therapist-photo="{{ $photoUrl }}"
                        data-therapist-bio="{{ $therapist['bio'] }}"
                        data-therapist-specialties="{{ implode('|', $therapist['specialties']) }}"
                        data-therapist-certifications="{{ implode('|', $therapist['certifications']) }}"
                        data-therapist-sessions="{{ $therapist['sessions'] }}"
                        data-therapist-accent="{{ $therapist['accent'] }}"
                    >
                        <div class="therapist-card-inner">
                            <div class="therapist-avatar-wrap">
                                <div class="therapist-avatar">
                                    <img
                                        src="{{ $photoUrl }}"
                                        alt="{{ $therapist['name'] }}"
                                        width="88"
                                        height="88"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                </div>
                            </div>
                            <div class="therapist-body">
                                <h3>{{ $therapist['name'] }}</h3>
                                <p class="therapist-role">{{ $therapist['role'] }}</p>
                                <div class="therapist-tags">
                                    @foreach ($therapist['specialties'] as $tag)
                                        <span class="therapist-tag">{{ $tag }}</span>
                                    @endforeach
                                </div>
                                <p class="therapist-meta">
                                    <span>{{ $therapist['sessions'] }} sessions</span>
                                </p>
                            </div>
                            <a href="{{ route('booking.index', ['therapist' => $therapist['name']]) }}" class="therapist-book" data-stop-card-click>Book with {{ explode(' ', $therapist['name'])[0] }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="therapists-footnote reveal delay-3">
                Meet our therapists below—explore their specialties and experience, then book directly with the professional who best fits your needs.
            </p>
        </div>
    </section>

    <section class="cta-band reveal">
        <div class="container cta-wrap">
            <div>
                <h3>Ready to relax with Buenos Touche?</h3>
                <p>Book your preferred therapy and let our wellness team take care of the rest.</p>
            </div>
            <a href="{{ route('booking.index') }}" class="btn btn-light">Book Your Session</a>
        </div>
    </section>

    <footer class="landing-footer" id="contact">
        <div class="container footer-grid">
            <div class="footer-brand">
                <div class="brand-wrap">
                    <img src="{{ asset('images/dashboard/logo.png') }}" alt="Buenos Touche logo" class="brand-logo">
                    <div class="brand">Buenos Touche</div>
                </div>
                <p>Professional care, peaceful ambiance, and wellness that lasts. Powered by the TouchNRelief booking system.</p>
            </div>

            <div>
                <h4>Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#therapists">Therapists</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="{{ route('login') }}">Login</a></li>
                </ul>
            </div>

            <div>
                <h4>Contact</h4>
                <ul class="footer-info">
                    <li>Phone: {{ $footer['contact_phone'] ?? '+63 912 345 6789' }}</li>
                    <li>Email: {{ $footer['contact_email'] ?? 'hello@touchnreliefspa.com' }}</li>
                    <li>Address: {{ $footer['contact_address'] ?? 'Wellness Ave, City Center' }}</li>
                </ul>
            </div>

            <div>
                <h4>Opening Hours</h4>
                <ul class="footer-info">
                    <li>{{ $footer['hours_weekday'] ?? 'Mon - Fri: 9:00 AM - 9:00 PM' }}</li>
                    <li>{{ $footer['hours_weekend'] ?? 'Sat - Sun: 10:00 AM - 10:00 PM' }}</li>
                    <li>{{ $footer['hours_holidays'] ?? 'Holidays: By Appointment' }}</li>
                </ul>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; {{ date('Y') }} Buenos Touche Spa. Powered by TouchNRelief.</p>
            <div class="footer-social">
                <a href="#" aria-label="Facebook">Facebook</a>
                <a href="#" aria-label="Instagram">Instagram</a>
                <a href="#" aria-label="TikTok">TikTok</a>
            </div>
        </div>
    </footer>

    <div class="therapist-modal" id="therapistModal" aria-hidden="true">
        <div class="therapist-modal-backdrop" data-close-therapist-modal></div>
        <div class="therapist-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="therapistModalTitle">
            <button type="button" class="therapist-modal-close" data-close-therapist-modal aria-label="Close">&times;</button>
            <div class="therapist-modal-header">
                <div class="therapist-modal-avatar" id="therapistModalAvatar">
                    <img src="" alt="" id="therapistModalPhoto" width="96" height="96">
                </div>
                <div class="therapist-modal-intro">
                    <p class="therapist-modal-label">Therapist Profile</p>
                    <h3 id="therapistModalTitle">Therapist Name</h3>
                    <p class="therapist-modal-role" id="therapistModalRole">Role</p>
                    <span class="therapist-modal-sessions" id="therapistModalSessions">0 sessions</span>
                </div>
            </div>
            <p class="therapist-modal-bio" id="therapistModalBio">Bio</p>
            <div class="therapist-modal-block">
                <p class="therapist-modal-block-title">Specialties</p>
                <div class="therapist-modal-tags" id="therapistModalSpecialties"></div>
            </div>
            <div class="therapist-modal-block">
                <p class="therapist-modal-block-title">Certifications</p>
                <ul class="therapist-modal-certs" id="therapistModalCertifications"></ul>
            </div>
            <a href="{{ route('booking.index') }}" id="therapistModalBook" class="btn btn-light therapist-modal-book">Book with Therapist</a>
        </div>
    </div>

    <div class="service-modal" id="serviceModal" aria-hidden="true">
        <div class="service-modal-backdrop" data-close-service-modal></div>
        <div class="service-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="serviceModalTitle">
            <button type="button" class="service-modal-close" data-close-service-modal aria-label="Close">&times;</button>
            <p class="service-modal-label">Service Details</p>
            <h3 id="serviceModalTitle">Service Name</h3>
            <div class="modal-pills">
                <span class="modal-pill service-modal-price" id="serviceModalPrice">PHP 0.00</span>
                <span class="modal-pill service-modal-duration" id="serviceModalDuration">60 min</span>
                <span class="modal-pill" id="serviceModalBest">Best for: Relaxation</span>
            </div>
            <p class="service-modal-desc" id="serviceModalDesc">Description</p>
            <div class="service-time-block">
                <label class="service-time-title" for="serviceModalDate">Date</label>
                <input type="date" id="serviceModalDate" class="service-modal-date" min="{{ $today }}" value="{{ $today }}">
                <p class="service-time-slots-label" id="serviceModalTimesLabel">Available Time Slots</p>
                <div class="time-chips" id="serviceModalTimes"></div>
                <p class="service-time-hint" id="serviceModalTimesHint" hidden>Select a date to see available time slots.</p>
            </div>
            <div class="service-modal-note">
                Includes consultation, therapist matching, and comfort-focused setup.
            </div>
            <div class="service-modal-footer">
            <a href="{{ route('booking.index') }}" id="serviceModalBook" class="btn btn-light">Book Now</a>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const header = document.querySelector('.landing-header');
            const items = document.querySelectorAll('.reveal');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });
            items.forEach((item) => observer.observe(item));

            const setHeaderState = () => {
                if (!header) return;
                header.classList.toggle('scrolled', window.scrollY > 12);
            };

            setHeaderState();
            window.addEventListener('scroll', setHeaderState, { passive: true });

            const carousel = document.querySelector('[data-carousel]');
            const track = carousel ? carousel.querySelector('.carousel-track') : null;
            const slides = track ? Array.from(track.children) : [];
            const dotsWrap = document.querySelector('[data-dots]');
            const prevBtn = carousel ? carousel.querySelector('[data-prev]') : null;
            const nextBtn = carousel ? carousel.querySelector('[data-next]') : null;
            const getVisible = () => (window.innerWidth <= 900 ? 1 : 3);
            let index = 0;
            let timer = null;

            const maxIndex = () => Math.max(slides.length - getVisible(), 0);
            const clampIndex = (value) => Math.max(0, Math.min(value, maxIndex()));

            const renderDots = () => {
                if (!dotsWrap) return;
                dotsWrap.innerHTML = '';
                const totalPages = maxIndex() + 1;
                for (let i = 0; i < totalPages; i += 1) {
                    const dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'carousel-dot' + (i === index ? ' active' : '');
                    dot.setAttribute('aria-label', 'Go to service page ' + (i + 1));
                    dot.addEventListener('click', () => {
                        index = i;
                        updateCarousel();
                        restartAuto();
                    });
                    dotsWrap.appendChild(dot);
                }
            };

            const updateCarousel = () => {
                if (!track || !slides.length) return;
                const slideWidth = slides[0].getBoundingClientRect().width;
                const gap = 16;
                index = clampIndex(index);
                track.style.transform = 'translateX(-' + ((slideWidth + gap) * index) + 'px)';
                const dots = dotsWrap ? Array.from(dotsWrap.children) : [];
                dots.forEach((dot, i) => dot.classList.toggle('active', i === index));
            };

            const restartAuto = () => {
                if (!slides.length || slides.length <= getVisible()) return;
                if (timer) clearInterval(timer);
                timer = setInterval(() => {
                    index = index >= maxIndex() ? 0 : index + 1;
                    updateCarousel();
                }, 3200);
            };

            if (carousel && slides.length === 1) {
                carousel.classList.add('is-single');
            }

            if (prevBtn && nextBtn && slides.length) {
                prevBtn.addEventListener('click', () => {
                    index = index <= 0 ? maxIndex() : index - 1;
                    updateCarousel();
                    restartAuto();
                });
                nextBtn.addEventListener('click', () => {
                    index = index >= maxIndex() ? 0 : index + 1;
                    updateCarousel();
                    restartAuto();
                });
                window.addEventListener('resize', () => {
                    index = clampIndex(index);
                    renderDots();
                    updateCarousel();
                });
                renderDots();
                updateCarousel();
                restartAuto();
            }

            const modal = document.getElementById('serviceModal');
            const serviceCards = document.querySelectorAll('.service-trigger');
            const closeServiceButtons = document.querySelectorAll('[data-close-service-modal]');
            const titleEl = document.getElementById('serviceModalTitle');
            const priceEl = document.getElementById('serviceModalPrice');
            const durationEl = document.getElementById('serviceModalDuration');
            const descEl = document.getElementById('serviceModalDesc');
            const bestEl = document.getElementById('serviceModalBest');
            const timesEl = document.getElementById('serviceModalTimes');
            const timesHint = document.getElementById('serviceModalTimesHint');
            const modalDateInput = document.getElementById('serviceModalDate');
            const bookEl = document.getElementById('serviceModalBook');
            const bookingBase = @json(route('booking.index'));
            const landingAvailabilityUrl = @json($landingAvailabilityUrl ?? '');
            let activeServiceName = '';
            let modalTimesPollTimer = null;
            let modalTimesLoading = false;
            let lastModalAvailabilityKey = '';

            const formatModalDateLabel = (iso) => {
                if (!iso) return 'Available Time Slots';
                try {
                    const parts = iso.split('-');
                    const d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                    return 'Available Time Slots — ' + d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
                } catch (e) {
                    return 'Available Time Slots';
                }
            };

            const paintModalTimes = (offered, fullyBooked, userConflicts, storeClosed) => {
                if (!timesEl) return;
                timesEl.innerHTML = '';

                if (storeClosed) {
                    if (timesHint) {
                        timesHint.hidden = false;
                        timesHint.textContent = 'The spa is closed on this date. Please choose another day.';
                    }
                    return;
                }

                if (!offered.length) {
                    if (timesHint) {
                        timesHint.hidden = false;
                        timesHint.textContent = 'No time slots are scheduled for this service on this date.';
                    }
                    return;
                }

                if (timesHint) timesHint.hidden = true;

                offered.forEach((time) => {
                    const chip = document.createElement('span');
                    const userConflict = userConflicts && userConflicts[time] ? userConflicts[time] : null;
                    const isFullyBooked = fullyBooked.indexOf(time) !== -1;

                    if (userConflict) {
                        chip.className = 'time-chip time-chip-user-conflict';
                        chip.title = 'This time overlaps with one of your existing appointments';
                    } else if (isFullyBooked) {
                        chip.className = 'time-chip time-chip-fully-booked';
                        chip.title = 'All therapists are booked at this time';
                    } else {
                        chip.className = 'time-chip';
                        chip.title = 'Available';
                    }

                    chip.textContent = time;
                    if (userConflict || isFullyBooked) {
                        chip.setAttribute('aria-disabled', 'true');
                    }
                    timesEl.appendChild(chip);
                });
            };

            const modalAvailabilitySnapshot = (data, dateKey, service) => JSON.stringify({
                dateKey,
                service,
                offered: data.offered_slots || [],
                fullyBooked: data.fully_booked_slots || [],
                userConflicts: data.user_conflicts || {},
                storeClosed: data.store_closed === true,
            });

            const renderModalTimes = (options = {}) => {
                const silent = !!options.silent;
                if (!timesEl) return;
                const dateKey = modalDateInput ? modalDateInput.value : '';
                const label = document.getElementById('serviceModalTimesLabel');
                if (label) label.textContent = formatModalDateLabel(dateKey);

                if (!dateKey || !activeServiceName) {
                    lastModalAvailabilityKey = '';
                    timesEl.innerHTML = '';
                    if (timesHint) {
                        timesHint.hidden = false;
                        timesHint.textContent = 'Select a date to see available time slots.';
                    }
                    return;
                }

                if (!landingAvailabilityUrl) {
                    if (timesHint) {
                        timesHint.hidden = false;
                        timesHint.textContent = 'Unable to load live availability.';
                    }
                    return;
                }

                if (modalTimesLoading) return;
                modalTimesLoading = true;

                if (!silent) {
                    lastModalAvailabilityKey = '';
                    if (timesHint) {
                        timesHint.hidden = false;
                        timesHint.textContent = 'Loading available time slots…';
                    }
                    timesEl.innerHTML = '';
                }

                const params = new URLSearchParams({
                    booking_date: dateKey,
                    service: activeServiceName,
                });

                fetch(landingAvailabilityUrl + '?' + params.toString(), {
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
                        const snapshotKey = modalAvailabilitySnapshot(data, dateKey, activeServiceName);
                        if (silent && snapshotKey === lastModalAvailabilityKey) {
                            return;
                        }
                        lastModalAvailabilityKey = snapshotKey;

                        const offered = Array.isArray(data.offered_slots) ? data.offered_slots : [];
                        const fullyBooked = Array.isArray(data.fully_booked_slots) ? data.fully_booked_slots : [];
                        const userConflicts = data.user_conflicts && typeof data.user_conflicts === 'object'
                            ? data.user_conflicts
                            : {};
                        paintModalTimes(offered, fullyBooked, userConflicts, data.store_closed === true);
                    })
                    .catch(function () {
                        if (!silent) {
                            timesEl.innerHTML = '';
                            if (timesHint) {
                                timesHint.hidden = false;
                                timesHint.textContent = 'Could not load time slots. Please refresh and try again.';
                            }
                        }
                    })
                    .finally(function () {
                        modalTimesLoading = false;
                    });
            };

            const startModalTimesPoll = () => {
                if (modalTimesPollTimer) clearInterval(modalTimesPollTimer);
                modalTimesPollTimer = setInterval(function () {
                    renderModalTimes({ silent: true });
                }, 15000);
            };

            const stopModalTimesPoll = () => {
                if (modalTimesPollTimer) {
                    clearInterval(modalTimesPollTimer);
                    modalTimesPollTimer = null;
                }
            };

            const openModal = (card) => {
                if (!modal || !card) return;
                const name = card.dataset.serviceName || 'Service';
                const price = card.dataset.servicePrice || '0.00';
                const duration = card.dataset.serviceDuration || '';
                const desc = card.dataset.serviceDesc || '';
                const best = card.dataset.serviceBest || 'Wellness';
                activeServiceName = name;
                titleEl.textContent = name;
                priceEl.textContent = 'PHP ' + price;
                durationEl.textContent = duration;
                descEl.textContent = desc;
                bestEl.textContent = 'Best for: ' + best;
                if (modalDateInput) {
                    modalDateInput.value = @json($today ?? now()->toDateString());
                }
                renderModalTimes();
                startModalTimesPoll();
                const bookParams = new URLSearchParams({ service: name });
                if (modalDateInput && modalDateInput.value) {
                    bookParams.set('date', modalDateInput.value);
                }
                bookEl.href = bookingBase + (bookingBase.includes('?') ? '&' : '?') + bookParams.toString();
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            };

            modalDateInput?.addEventListener('change', () => {
                renderModalTimes();
                const bookParams = new URLSearchParams({ service: activeServiceName });
                if (modalDateInput.value) {
                    bookParams.set('date', modalDateInput.value);
                }
                bookEl.href = bookingBase + (bookingBase.includes('?') ? '&' : '?') + bookParams.toString();
            });

            const closeModal = () => {
                if (!modal) return;
                stopModalTimesPoll();
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                if (!document.getElementById('therapistModal')?.classList.contains('open')) {
                    document.body.style.overflow = '';
                }
            };

            serviceCards.forEach((card) => {
                card.addEventListener('click', () => openModal(card));
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openModal(card);
                    }
                });
            });

            closeServiceButtons.forEach((btn) => btn.addEventListener('click', closeModal));

            const therapistModal = document.getElementById('therapistModal');
            const therapistCards = document.querySelectorAll('.therapist-trigger');
            const closeTherapistButtons = document.querySelectorAll('[data-close-therapist-modal]');
            const therapistTitleEl = document.getElementById('therapistModalTitle');
            const therapistRoleEl = document.getElementById('therapistModalRole');
            const therapistBioEl = document.getElementById('therapistModalBio');
            const therapistPhotoEl = document.getElementById('therapistModalPhoto');
            const therapistAvatarEl = document.getElementById('therapistModalAvatar');
            const therapistSessionsEl = document.getElementById('therapistModalSessions');
            const therapistSpecialtiesEl = document.getElementById('therapistModalSpecialties');
            const therapistCertsEl = document.getElementById('therapistModalCertifications');
            const therapistBookEl = document.getElementById('therapistModalBook');

            const splitPipe = (value) => (value || '').split('|').map((v) => v.trim()).filter(Boolean);

            const openTherapistModal = (card) => {
                if (!therapistModal || !card) return;
                const name = card.dataset.therapistName || 'Therapist';
                const role = card.dataset.therapistRole || '';
                const photo = card.dataset.therapistPhoto || '';
                const bio = card.dataset.therapistBio || '';
                const accent = card.dataset.therapistAccent || '#c4a882';
                const sessions = card.dataset.therapistSessions || '';
                const specialties = splitPipe(card.dataset.therapistSpecialties);
                const certifications = splitPipe(card.dataset.therapistCertifications);

                therapistTitleEl.textContent = name;
                therapistRoleEl.textContent = role;
                therapistBioEl.textContent = bio;
                therapistPhotoEl.src = photo;
                therapistPhotoEl.alt = name;
                therapistSessionsEl.textContent = sessions + ' sessions completed';
                therapistAvatarEl.style.setProperty('--modal-accent', accent);
                therapistSpecialtiesEl.innerHTML = '';
                specialties.forEach((item) => {
                    const tag = document.createElement('span');
                    tag.className = 'therapist-modal-tag';
                    tag.textContent = item;
                    therapistSpecialtiesEl.appendChild(tag);
                });
                therapistCertsEl.innerHTML = '';
                certifications.forEach((item) => {
                    const li = document.createElement('li');
                    li.textContent = item;
                    therapistCertsEl.appendChild(li);
                });
                therapistBookEl.href = bookingBase + '?therapist=' + encodeURIComponent(name);
                therapistBookEl.textContent = 'Book with ' + name.split(' ')[0];
                therapistModal.classList.add('open');
                therapistModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            };

            const closeTherapistModal = () => {
                if (!therapistModal) return;
                therapistModal.classList.remove('open');
                therapistModal.setAttribute('aria-hidden', 'true');
                if (!modal?.classList.contains('open')) {
                    document.body.style.overflow = '';
                }
            };

            therapistCards.forEach((card) => {
                card.addEventListener('click', (e) => {
                    if (e.target.closest('[data-stop-card-click]')) return;
                    openTherapistModal(card);
                });
                card.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        openTherapistModal(card);
                    }
                });
            });

            closeTherapistButtons.forEach((btn) => btn.addEventListener('click', closeTherapistModal));

            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                if (therapistModal?.classList.contains('open')) closeTherapistModal();
                if (modal?.classList.contains('open')) closeModal();
            });

            const featureCarousel = document.querySelector('[data-feature-carousel]');
            const featureSlides = featureCarousel ? Array.from(featureCarousel.querySelectorAll('.feature-slide')) : [];
            if (featureSlides.length > 1) {
                let featureIdx = 0;
                setInterval(() => {
                    featureSlides[featureIdx].classList.remove('active');
                    featureIdx = (featureIdx + 1) % featureSlides.length;
                    featureSlides[featureIdx].classList.add('active');
                }, 3500);
            }

            const userMenuWrap = document.querySelector('[data-user-menu]');
            if (userMenuWrap) {
                const btn = userMenuWrap.querySelector('.nav-user');
                const menu = userMenuWrap.querySelector('.nav-user-menu');
                const close = () => {
                    userMenuWrap.classList.remove('open');
                    btn?.setAttribute('aria-expanded', 'false');
                };
                const open = () => {
                    userMenuWrap.classList.add('open');
                    btn?.setAttribute('aria-expanded', 'true');
                };
                btn?.addEventListener('click', (e) => {
                    e.stopPropagation();
                    userMenuWrap.classList.contains('open') ? close() : open();
                });
                document.addEventListener('click', close);
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') close();
                });
            }
        })();
    </script>
    @auth
        @include('partials.registration-onboarding-modal')
        @include('partials.profile-transactions-modal')
        @include('partials.profile-transactions-script')
        @include('partials.logout-confirm-modal')
        @include('partials.payment-receipt-modal')
        @include('partials.payment-receipt-modal-script')
        @if (auth()->user()->isAdmin())
            @include('partials.profile-edit-modal')
            @include('partials.profile-edit-modal-script')
        @endif
    @endauth
    </body>
</html>
