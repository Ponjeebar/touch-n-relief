<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    @include('partials.theme-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Edit landing page footer content">
    <title>Landing Page Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/landing-settings.css') }}">
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
                @include('partials.sidebar-nav', ['active' => 'landing-settings'])
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
                        <h1>Landing Page</h1>
                        <div class="subtitle">Edit footer contact details and opening hours shown on the public homepage</div>
                    </div>
                    <div class="right">
                        @include('partials.topbar-notifications')
                        @include('partials.topbar-settings')
                        @include('partials.topbar-profile')
                    </div>
                </div>

                @include('partials.status-toast')

                <section class="ls-wrap" aria-label="Landing page footer settings">
                    <div class="ls-layout">
                        <div class="ls-main">
                            <div class="ls-tip">
                                <span class="ls-tip-icon" aria-hidden="true"><i class="bi bi-globe2"></i></span>
                                <div class="ls-tip-copy">
                                    <strong>Public homepage footer</strong>
                                    <p>Changes here update the contact column and opening hours on your live landing page. Use the preview panel to check how visitors will see them.</p>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('landing-settings.update') }}" class="ls-form" id="landingSettingsForm">
                                @csrf
                                @method('PUT')

                                <article class="ls-section">
                                    <header class="ls-section-head">
                                        <span class="ls-section-icon contact" aria-hidden="true"><i class="bi bi-telephone-outbound"></i></span>
                                        <div>
                                            <h2 class="ls-section-title">Contact</h2>
                                            <p class="ls-section-desc">Displayed in the footer contact column on the landing page.</p>
                                        </div>
                                    </header>
                                    <div class="ls-fields">
                                        <div class="ls-field">
                                            <label for="contact_phone">Phone</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-telephone" aria-hidden="true"></i>
                                                <input id="contact_phone" name="contact_phone" type="text" value="{{ old('contact_phone', $footer['contact_phone']) }}" required maxlength="80" data-preview="preview-phone" autocomplete="tel">
                                            </div>
                                            @error('contact_phone')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="ls-field">
                                            <label for="contact_email">Email</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                                <input id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $footer['contact_email']) }}" required maxlength="120" data-preview="preview-email" autocomplete="email">
                                            </div>
                                            @error('contact_email')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="ls-field">
                                            <label for="contact_address">Address</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                                <input id="contact_address" name="contact_address" type="text" value="{{ old('contact_address', $footer['contact_address']) }}" required maxlength="200" data-preview="preview-address" autocomplete="street-address">
                                            </div>
                                            @error('contact_address')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </article>

                                <article class="ls-section">
                                    <header class="ls-section-head">
                                        <span class="ls-section-icon hours" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
                                        <div>
                                            <h2 class="ls-section-title">Opening Hours</h2>
                                            <p class="ls-section-desc">Each line appears as a separate item under Opening Hours in the footer.</p>
                                        </div>
                                    </header>
                                    <div class="ls-fields">
                                        <div class="ls-field">
                                            <label for="hours_weekday">Weekdays</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-calendar-week" aria-hidden="true"></i>
                                                <input id="hours_weekday" name="hours_weekday" type="text" value="{{ old('hours_weekday', $footer['hours_weekday']) }}" required maxlength="120" data-preview="preview-hours-weekday" placeholder="Mon - Fri: 9:00 AM - 9:00 PM">
                                            </div>
                                            @error('hours_weekday')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="ls-field">
                                            <label for="hours_weekend">Weekends</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-calendar2-event" aria-hidden="true"></i>
                                                <input id="hours_weekend" name="hours_weekend" type="text" value="{{ old('hours_weekend', $footer['hours_weekend']) }}" required maxlength="120" data-preview="preview-hours-weekend" placeholder="Sat - Sun: 10:00 AM - 10:00 PM">
                                            </div>
                                            @error('hours_weekend')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="ls-field">
                                            <label for="hours_holidays">Holidays</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-calendar-x" aria-hidden="true"></i>
                                                <input id="hours_holidays" name="hours_holidays" type="text" value="{{ old('hours_holidays', $footer['hours_holidays']) }}" required maxlength="120" data-preview="preview-hours-holidays" placeholder="Holidays: By Appointment">
                                            </div>
                                            @error('hours_holidays')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </article>

                                <article class="ls-section">
                                    <header class="ls-section-head">
                                        <span class="ls-section-icon social" aria-hidden="true"><i class="bi bi-share"></i></span>
                                        <div>
                                            <h2 class="ls-section-title">Social Media Links</h2>
                                            <p class="ls-section-desc">Set where the Facebook and Instagram links in the public footer open. Leave a field blank to hide that link.</p>
                                        </div>
                                    </header>
                                    <div class="ls-fields">
                                        <div class="ls-field">
                                            <label for="facebook_url">Facebook page URL</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-facebook" aria-hidden="true"></i>
                                                <input id="facebook_url" name="facebook_url" type="url" value="{{ old('facebook_url', $footer['facebook_url']) }}" maxlength="2048" placeholder="https://www.facebook.com/your-page" autocomplete="url" data-social-preview="preview-facebook">
                                            </div>
                                            @error('facebook_url')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="ls-field">
                                            <label for="instagram_url">Instagram profile URL</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-instagram" aria-hidden="true"></i>
                                                <input id="instagram_url" name="instagram_url" type="url" value="{{ old('instagram_url', $footer['instagram_url']) }}" maxlength="2048" placeholder="https://www.instagram.com/your-profile" autocomplete="url" data-social-preview="preview-instagram">
                                            </div>
                                            @error('instagram_url')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </article>

                                <article class="ls-section">
                                    <header class="ls-section-head">
                                        <span class="ls-section-icon hours" aria-hidden="true"><i class="bi bi-calendar-x"></i></span>
                                        <div>
                                            <h2 class="ls-section-title">Booking Cancellation Policy</h2>
                                            <p class="ls-section-desc">Choose how many hours before an appointment customers must cancel. The cancel button becomes unavailable inside this period.</p>
                                        </div>
                                    </header>
                                    <div class="ls-fields">
                                        <div class="ls-field">
                                            <label for="cancellation_cutoff_hours">Cancellation cutoff (hours)</label>
                                            <div class="ls-input-wrap">
                                                <i class="bi bi-clock" aria-hidden="true"></i>
                                                <input id="cancellation_cutoff_hours" name="cancellation_cutoff_hours" type="number" value="{{ old('cancellation_cutoff_hours', $cancellationCutoffHours) }}" required min="0" max="8760" step="1" inputmode="numeric">
                                            </div>
                                            <p class="ls-field-help">Example: 24 disables cancellation during the final 24 hours. Enter 0 to allow cancellation until the appointment begins.</p>
                                            @error('cancellation_cutoff_hours')<span class="field-error">{{ $message }}</span>@enderror
                                        </div>
                                    </div>
                                </article>

                                <div class="ls-actions">
                                    <button type="submit" class="ls-btn ls-btn-primary">
                                        <i class="bi bi-check2-circle" aria-hidden="true"></i>
                                        Save changes
                                    </button>
                                    <a href="{{ route('landing') }}" class="ls-btn ls-btn-secondary" target="_blank" rel="noopener">
                                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                        Preview landing page
                                    </a>
                                </div>
                            </form>
                        </div>

                        <aside class="ls-preview" aria-label="Footer preview">
                            <div class="ls-preview-card">
                                <div class="ls-preview-head">
                                    <h3>Live preview</h3>
                                    <span class="ls-preview-badge"><span class="dot" aria-hidden="true"></span> Updates as you type</span>
                                </div>
                                <div class="ls-footer-mock">
                                    <div class="ls-footer-mock-brand">
                                        <strong>TOUCHnRELIEF</strong>
                                        <p>Wellness and massage services tailored to your comfort and care.</p>
                                    </div>
                                    <div class="ls-footer-mock-grid">
                                        <div class="ls-footer-mock-block">
                                            <h4>Contact</h4>
                                            <ul>
                                                <li><i class="bi bi-telephone" aria-hidden="true"></i><span id="preview-phone">{{ old('contact_phone', $footer['contact_phone']) }}</span></li>
                                                <li><i class="bi bi-envelope" aria-hidden="true"></i><span id="preview-email">{{ old('contact_email', $footer['contact_email']) }}</span></li>
                                                <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span id="preview-address">{{ old('contact_address', $footer['contact_address']) }}</span></li>
                                            </ul>
                                        </div>
                                        <div class="ls-footer-mock-block">
                                            <h4>Opening Hours</h4>
                                            <ul>
                                                <li><i class="bi bi-clock" aria-hidden="true"></i><span id="preview-hours-weekday">{{ old('hours_weekday', $footer['hours_weekday']) }}</span></li>
                                                <li><i class="bi bi-clock" aria-hidden="true"></i><span id="preview-hours-weekend">{{ old('hours_weekend', $footer['hours_weekend']) }}</span></li>
                                                <li><i class="bi bi-clock" aria-hidden="true"></i><span id="preview-hours-holidays">{{ old('hours_holidays', $footer['hours_holidays']) }}</span></li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="ls-footer-mock-social" aria-label="Social link preview">
                                        <a id="preview-facebook" href="{{ old('facebook_url', $footer['facebook_url']) ?: '#' }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook" aria-hidden="true"></i> Facebook</a>
                                        <a id="preview-instagram" href="{{ old('instagram_url', $footer['instagram_url']) ?: '#' }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-instagram" aria-hidden="true"></i> Instagram</a>
                                    </div>
                                    <div class="ls-footer-mock-bottom">© {{ date('Y') }} TOUCHnRELIEF. All rights reserved.</div>
                                </div>
                                <p class="ls-preview-note">This is a simplified preview of the footer section. Open the full landing page to see the complete layout.</p>
                            </div>
                        </aside>
                    </div>
                </section>
            </main>
        </div>
    </div>

    @include('partials.logout-confirm-modal')
    <script>
        document.querySelectorAll('[data-preview]').forEach((input) => {
            const previewId = input.getAttribute('data-preview');
            const previewEl = previewId ? document.getElementById(previewId) : null;
            if (!previewEl) return;

            const sync = () => {
                previewEl.textContent = input.value.trim() || '—';
            };

            input.addEventListener('input', sync);
            sync();
        });

        document.querySelectorAll('[data-social-preview]').forEach((input) => {
            const preview = document.getElementById(input.dataset.socialPreview);
            if (!preview) return;

            const sync = () => {
                const url = input.value.trim();
                preview.hidden = url === '';
                preview.href = url || '#';
            };

            input.addEventListener('input', sync);
            sync();
        });
    </script>
</body>
</html>
