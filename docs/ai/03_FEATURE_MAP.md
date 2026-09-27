# Feature Map

Use this map to find the likely owner of a feature. Search references before editing because shared partials and services can affect several pages.

| Feature | Main controllers | Domain/service owners | Main views/styles |
|---|---|---|---|
| Landing and catalogue | `BookingController`, `LandingSettingsController` | `SpaServiceCatalog`, `TherapistCatalog`, `SiteSettingsService` | `welcome.blade.php`, `landing.css` |
| Login and registration | `AuthController`, `SocialAuthController` | `AuthVerificationCodeService`, `ResendPasswordResetService`, `StrongPassword` | `auth/*`, `login.css`, `registration-onboarding.css` |
| Customer onboarding | `OnboardingController` | `User` wellness helpers | onboarding modal partial |
| Customer booking | `BookingController` | `BookingSlotService`, `TherapistAvailabilityService`, `SpaServiceCatalog` | `booking/create.blade.php`, `booking.css` |
| PayMongo and receipts | `PaymongoController` | `PaymongoService`, `PaymentMethodCatalog`, refund/session services | payment receipt partials, `payment-receipt.css` |
| Customer profile | `ProfileController` | `SpaSessionService` | `profile.blade.php`, profile and transaction partials |
| Staff appointments | `DashboardController`, `StaffAppointmentController` | booking/session/cancellation/reschedule/refund services | `appointments/*`, `appointments.css` |
| Therapists and schedules | `DashboardController` | `TherapistAvailabilityService`, `TherapistCatalog` | schedule partials, therapist tracking |
| Services and time slots | `DashboardController` | `SpaServiceCatalog`, `BookingSlotService` | `services/index.blade.php`, `services.css` |
| Notifications | customer/staff notification controllers | customer/staff notification services and feed services | notification partials and CSS |
| Sessions | `DashboardController` | `SpaSessionService` | ongoing/completed session pages |
| Customer records | `DashboardController` | `SpaSessionService`, `WalkInClientService` | `client-records/*` |
| Reporting | `DashboardController` | `ReportingPdfChartService` | `reporting/*`, `reporting.css` |
| Activity logs | `ActivityLogController` | `ActivityLogger`, `ActivityLogService`, `UserActivityService` | activity log page and CSS |
| Chatbot | `ChatbotController` | `ChatbotService` | chatbot partial/assets and `chatbot.css` |
| Media | `StorageMediaController` | configured filesystem disk | `/media/{path}` route |

## Shared Components to Check First

- `resources/views/partials/landing-nav.blade.php`
- `resources/views/partials/customer-mobile-nav.blade.php`
- `resources/views/partials/sidebar-nav.blade.php`
- `resources/views/partials/topbar-*.blade.php`
- `resources/views/partials/status-toast.blade.php`
- `resources/views/partials/customer-notifications.blade.php`
- `resources/views/partials/payment-receipt-modal*.blade.php`
- `resources/views/partials/profile-*.blade.php`
- `resources/views/partials/password-*.blade.php`
- `resources/views/partials/theme-head.blade.php`

## Change Impact Checklist

Before editing a feature, search for:

1. Route name and URL references.
2. Controller methods and request field names.
3. Service methods and model scopes.
4. Blade IDs/classes used by JavaScript.
5. Shared CSS selectors.
6. Tests naming the feature, status, route, or visible label.
