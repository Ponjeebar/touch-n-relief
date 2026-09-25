<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CustomerNotificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingSettingsController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymongoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StaffAppointmentController;
use App\Http\Controllers\StorageMediaController;
use Illuminate\Support\Facades\Route;

Route::get('/media/{path}', [StorageMediaController::class, 'show'])
    ->where('path', '.+')
    ->name('storage.media');

Route::get('/', [BookingController::class, 'landing'])->name('landing');
Route::view('/privacy-policy', 'legal.privacy')->name('privacy-policy');
Route::view('/terms-and-conditions', 'legal.terms')->name('terms-and-conditions');
Route::get('/landing/availability', [BookingController::class, 'landingAvailability'])->name('landing.availability');
Route::post('/chatbot/message', [ChatbotController::class, 'reply'])->middleware('throttle:30,1')->name('chatbot.reply');

Route::post('/webhooks/paymongo', [PaymongoController::class, 'webhook'])->name('paymongo.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10')->name('register');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,10')->name('password.email');
    Route::get('/verify-code/{verification}', [AuthController::class, 'showVerification'])->name('verification.show');
    Route::post('/verify-code/{verification}', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1')->name('verification.verify');
    Route::post('/verify-code/{verification}/resend', [AuthController::class, 'resendCode'])->middleware('throttle:3,10')->name('verification.resend');
});

// A reset link must remain usable when it opens in a browser with an existing session.
// The password broker still validates the email and one-time token before any change.
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

Route::middleware(['auth', 'staff.activity'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/booking', [BookingController::class, 'create'])->name('booking.index');
    Route::get('/booking/availability', [BookingController::class, 'availability'])->name('booking.availability');
    Route::get('/booking/therapist-availability', [BookingController::class, 'therapistAvailability'])->name('booking.therapist-availability');
    Route::post('/booking', [BookingController::class, 'store'])->name('booking.store');
    Route::get('/booking/paymongo/success/{spaBooking}', [PaymongoController::class, 'success'])->name('paymongo.success');
    Route::get('/booking/paymongo/cancel/{spaBooking}', [PaymongoController::class, 'cancel'])->name('paymongo.cancel');
    Route::post('/bookings/{spaBooking}/cancel', [BookingController::class, 'cancel'])->name('booking.cancel');
    Route::post('/bookings/{spaBooking}/continue-payment', [PaymongoController::class, 'customerRetry'])->middleware('throttle:10,1')->name('booking.payment.retry');
    Route::patch('/bookings/{spaBooking}/reschedule', [BookingController::class, 'reschedule'])->name('booking.reschedule');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::get('/customer-notifications/poll', [CustomerNotificationController::class, 'poll'])->name('customer-notifications.poll');
    Route::post('/customer-notifications/mark-all-read', [CustomerNotificationController::class, 'markAllRead'])->name('customer-notifications.mark-all-read');
    Route::post('/customer-notifications/{customerNotification}/read', [CustomerNotificationController::class, 'markRead'])->name('customer-notifications.read');

    Route::middleware('staff')->group(function () {
        Route::get('/receptionist-dashboard', [DashboardController::class, 'receptionistDashboard'])->name('receptionist.dashboard');

        Route::get('/appointments', [DashboardController::class, 'appointments'])->name('appointments.index');
        Route::get('/appointments/export', [DashboardController::class, 'appointmentsExport'])->name('appointments.export');
        Route::get('/appointments/availability', [StaffAppointmentController::class, 'availability'])->name('appointments.availability');
        Route::get('/appointments/clients/search', [StaffAppointmentController::class, 'searchClients'])->name('appointments.clients.search');
        Route::post('/appointments', [StaffAppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/paymongo/success/{spaBooking}', [PaymongoController::class, 'staffSuccess'])->name('appointments.paymongo.success');
        Route::get('/appointments/paymongo/cancel/{spaBooking}', [PaymongoController::class, 'staffCancel'])->name('appointments.paymongo.cancel');
        Route::get('/appointments/paymongo/retry/{spaBooking}', [PaymongoController::class, 'staffRetry'])->name('appointments.paymongo.retry');
        Route::patch('/appointments/{spaBooking}/reschedule', [StaffAppointmentController::class, 'reschedule'])->name('appointments.reschedule');
        Route::get('/appointments/{spaBooking}/reschedule/availability', [StaffAppointmentController::class, 'rescheduleAvailability'])->name('appointments.reschedule.availability');
        Route::patch('/appointments/{spaBooking}/cancel', [StaffAppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::patch('/appointments/{spaBooking}/no-show', [StaffAppointmentController::class, 'markNoShow'])->name('appointments.no-show');
        Route::patch('/appointments/{spaBooking}/refund', [StaffAppointmentController::class, 'completeRefund'])->name('appointments.refund.complete');
        Route::patch('/appointments/{spaBooking}/collect-balance', [DashboardController::class, 'collectBalance'])->name('appointments.collect-balance');
        Route::patch('/appointments/{spaBooking}/start', [DashboardController::class, 'startSession'])->name('appointments.start');
        Route::get('/services', [DashboardController::class, 'services'])->name('services.index');
        Route::post('/services', [DashboardController::class, 'storeService'])->name('services.store');
        Route::put('/services/{spaService}', [DashboardController::class, 'updateService'])->name('services.update');
        Route::get('/services/{spaService}/timeslots', [DashboardController::class, 'serviceTimeSlotsData'])->name('services.timeslots');
        Route::post('/services/{spaService}/timeslots', [DashboardController::class, 'storeServiceTimeSlot'])->name('services.timeslots.store');
        Route::put('/services/{spaService}/timeslots', [DashboardController::class, 'updateServiceTimeSlots'])->name('services.timeslots.update');
        Route::post('/services/{spaService}/toggle-availability', [DashboardController::class, 'toggleServiceAvailability'])->name('services.toggle-availability');
        Route::get('/client-records', [DashboardController::class, 'clientRecords'])->name('client-records.index');
        Route::get('/client-records/{customer}', [DashboardController::class, 'showClientRecord'])->name('client-records.show');

        Route::get('/ongoing-sessions', [DashboardController::class, 'ongoingSessions'])->name('ongoing-sessions.index');
        Route::get('/ongoing-sessions/poll', [DashboardController::class, 'ongoingSessionsPoll'])->name('ongoing-sessions.poll');
        Route::get('/staff-feed/poll', [DashboardController::class, 'staffFeedPoll'])->name('staff-feed.poll');
        Route::patch('/ongoing-sessions/{spaBooking}/complete', [DashboardController::class, 'completeSession'])->name('ongoing-sessions.complete');
        Route::get('/completed-sessions', [DashboardController::class, 'completedSessions'])->name('completed-sessions.index');
        Route::get('/therapist-tracking', [DashboardController::class, 'therapistTracking'])->name('therapist-tracking.index');
        Route::get('/activity-log/click', [ActivityLogController::class, 'click'])->name('activity-logs.click');
    });
});

Route::middleware(['auth', 'admin', 'staff.activity'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reporting', [DashboardController::class, 'reporting'])->name('reporting.index');
    Route::get('/reporting/data', [DashboardController::class, 'reportingData'])->name('reporting.data');
    Route::get('/reporting/export', [DashboardController::class, 'reportingExport'])->name('reporting.export');
    Route::get('/reporting/pdf', [DashboardController::class, 'reportingPdf'])->name('reporting.pdf');
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-log/poll', [ActivityLogController::class, 'poll'])->name('activity-logs.poll');
    Route::get('/users', [DashboardController::class, 'users'])->name('users.index');
    Route::post('/therapists', [DashboardController::class, 'storeTherapist'])->name('therapists.store');
    Route::put('/therapists/{therapistCode}', [DashboardController::class, 'updateTherapist'])->name('therapists.update');
    Route::put('/therapists/{therapistCode}/availability', [DashboardController::class, 'updateTherapistAvailability'])->name('therapists.availability.update');
    Route::put('/therapist-schedule/settings', [DashboardController::class, 'updateTherapistScheduleSettings'])->name('therapist-schedule.settings.update');
    Route::delete('/therapists/{therapistCode}', [DashboardController::class, 'destroyTherapist'])->name('therapists.destroy');
    Route::get('/reporting/backup', [DashboardController::class, 'reportingBackup'])->name('reporting.backup');
    Route::post('/dashboard/customers', [DashboardController::class, 'storeCustomer'])->name('dashboard.customers.store');
    Route::put('/dashboard/customers/{customer}', [DashboardController::class, 'updateCustomer'])->name('dashboard.customers.update');
    Route::delete('/dashboard/customers/{customer}', [DashboardController::class, 'destroyCustomer'])->name('dashboard.customers.destroy');
    Route::post('/dashboard/receptionists', [DashboardController::class, 'storeReceptionist'])->name('dashboard.receptionists.store');
    Route::put('/dashboard/receptionists/{receptionist}', [DashboardController::class, 'updateReceptionist'])->name('dashboard.receptionists.update');
    Route::delete('/dashboard/receptionists/{receptionist}', [DashboardController::class, 'destroyReceptionist'])->name('dashboard.receptionists.destroy');
    Route::get('/landing-settings', [LandingSettingsController::class, 'edit'])->name('landing-settings.edit');
    Route::put('/landing-settings', [LandingSettingsController::class, 'update'])->name('landing-settings.update');
});
