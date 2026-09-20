<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_role',
        'user_name',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, ActivityLog>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roleLabel(): string
    {
        return match ($this->user_role) {
            User::ROLE_ADMIN => 'Admin',
            User::ROLE_RECEPTIONIST => 'Receptionist',
            User::ROLE_USER => 'Customer',
            default => ucfirst($this->user_role),
        };
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'login' => 'Signed in',
            'logout' => 'Signed out',
            'page.view' => 'Viewed page',
            'appointments.view' => 'Viewed appointments',
            'services.view' => 'Viewed services',
            'service.created' => 'Added service',
            'service.updated' => 'Updated service',
            'service.timeslots_updated' => 'Updated service time slots',
            'service.unavailable' => 'Made service unavailable',
            'service.available' => 'Made service available',
            'client_records.view' => 'Viewed client records',
            'client_record.view' => 'Viewed client record details',
            'ongoing_sessions.view' => 'Viewed ongoing sessions',
            'completed_sessions.view' => 'Viewed completed sessions',
            'session.completed' => 'Completed a session',
            'session.started' => 'Started a session',
            'therapist_tracking.view' => 'Viewed therapist tracking',
            'reporting.view' => 'Viewed reporting',
            'receptionist.view' => 'Viewed receptionist page',
            'dashboard.view' => 'Viewed admin dashboard',
            'receptionist_dashboard.view' => 'Viewed receptionist dashboard',
            'profile.view' => 'Viewed profile',
            'ui.click' => 'Clicked panel element',
            'schedule.cancelled' => 'Cancelled a schedule',
            'schedule.rescheduled' => 'Rescheduled an appointment',
            'booking.created' => 'Booked an appointment',
            'customer.created' => 'Added customer',
            'customer.updated' => 'Updated customer',
            'customer.deleted' => 'Deleted customer',
            'receptionist.created' => 'Added receptionist',
            'receptionist.updated' => 'Updated receptionist',
            'receptionist.deleted' => 'Deleted receptionist',
            'profile.updated' => 'Updated profile',
            'report.exported' => 'Exported report',
            'report.backup' => 'Downloaded backup',
            default => str_replace(['.', '_'], ' ', ucfirst($this->action)),
        };
    }
}
