<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public static function log(
        string $action,
        string $description,
        ?array $properties = null,
        ?Model $subject = null,
        ?User $user = null,
        ?Request $request = null,
    ): void {
        $user = $user ?? Auth::user();

        if (! $user instanceof User) {
            return;
        }

        if (! $user->isAdmin() && ! $user->isReceptionist()) {
            return;
        }

        $request = $request ?? request();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'user_name' => $user->name ?: $user->username ?: $user->email,
            'action' => $action,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr((string) $request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Log actions performed by customers (bookings, cancellations, reschedules).
     *
     * @param  array<string, mixed>|null  $properties
     */
    public static function logCustomerAction(
        string $action,
        string $description,
        User $user,
        ?array $properties = null,
        ?Model $subject = null,
        ?Request $request = null,
    ): void {
        $request = $request ?? request();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'user_role' => User::ROLE_USER,
            'user_name' => trim((string) ($user->name ?: $user->email)) ?: 'Customer',
            'action' => $action,
            'subject_type' => $subject !== null ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr((string) $request->userAgent(), 0, 500) : null,
            'created_at' => now(),
        ]);
    }

    public static function pageLabel(?string $routeName): string
    {
        return match ($routeName) {
            'dashboard' => 'Admin dashboard',
            'receptionist.dashboard' => 'Receptionist dashboard',
            'users.index' => 'Users management',
            'appointments.index' => 'Appointments',
            'services.index' => 'Services',
            'ongoing-sessions.index' => 'Ongoing sessions',
            'completed-sessions.index' => 'Completed sessions',
            'therapist-tracking.index' => 'Therapist monitoring',
            'client-records.index' => 'Client records',
            'client-records.show' => 'Client record details',
            'reporting.index' => 'Reporting',
            'landing-settings.edit' => 'Landing page settings',
            'profile.edit' => 'Profile',
            'activity-logs.index' => 'Activity log',
            default => $routeName ? str_replace(['.', '-'], ' ', ucfirst($routeName)) : 'Page',
        };
    }

    public static function actionForRoute(?string $routeName): string
    {
        return match ($routeName) {
            'appointments.index' => 'appointments.view',
            'services.index' => 'services.view',
            'client-records.index' => 'client_records.view',
            'client-records.show' => 'client_record.view',
            'ongoing-sessions.index' => 'ongoing_sessions.view',
            'completed-sessions.index' => 'completed_sessions.view',
            'therapist-tracking.index' => 'therapist_tracking.view',
            'reporting.index' => 'reporting.view',
            'users.index' => 'receptionist.view',
            'dashboard' => 'dashboard.view',
            'receptionist.dashboard' => 'receptionist_dashboard.view',
            'profile.edit' => 'profile.view',
            default => 'page.view',
        };
    }
}
