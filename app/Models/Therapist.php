<?php

namespace App\Models;

use App\Services\TherapistAvailabilityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Therapist extends Model
{
    protected $fillable = [
        'therapist_code',
        'name',
        'role',
        'bio',
        'contact_number',
        'address',
        'email',
        'birthday',
        'avatar_initials',
        'photo_url',
        'landing_photo',
        'specializations',
        'certifications',
        'sessions_label',
        'accent_color',
        'status',
        'working_days',
        'day_off_until',
        'work_on_off_day',
        'total_hours',
        'rating',
        'service_hours_pct',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'specializations' => 'array',
            'certifications' => 'array',
            'rating' => 'float',
            'birthday' => 'date',
            'day_off_until' => 'date',
            'working_days' => 'array',
            'work_on_off_day' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function resolvePhotoUrl(): ?string
    {
        $uploaded = trim((string) ($this->photo_url ?? ''));
        if ($uploaded !== '') {
            return $uploaded;
        }

        $landingPhoto = trim((string) ($this->landing_photo ?? ''));
        if ($landingPhoto !== '') {
            return asset('images/landing/therapist/'.$landingPhoto);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toLandingArray(bool $hidePrenatalRefs = false): array
    {
        $specialties = array_values($this->specializations ?? []);
        $certifications = array_values($this->certifications ?? []);

        if ($hidePrenatalRefs) {
            $specialties = array_values(array_filter(
                $specialties,
                static fn (string $specialty): bool => $specialty !== 'Prenatal Massage',
            ));
            $certifications = array_values(array_filter(
                $certifications,
                static fn (string $cert): bool => ! str_contains($cert, 'Prenatal'),
            ));
        }

        return [
            'id' => (string) $this->therapist_code,
            'name' => (string) $this->name,
            'photo' => (string) ($this->landing_photo ?? ''),
            'photo_url' => $this->resolvePhotoUrl(),
            'role' => (string) ($this->role ?? ''),
            'bio' => (string) ($this->bio ?? ''),
            'specialties' => $specialties,
            'certifications' => $certifications,
            'sessions' => (string) ($this->sessions_label ?? '0'),
            'accent' => (string) ($this->accent_color ?? '#8fa89a'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toBookingArray(?Carbon $date = null): array
    {
        $availability = app(TherapistAvailabilityService::class);
        $when = ($date ?? now())->copy();
        $status = $availability->effectiveStatusForDate($this, $when);
        $bookable = $status === 'available';

        return [
            'id' => (string) $this->therapist_code,
            'name' => (string) $this->name,
            'photo' => (string) ($this->landing_photo ?? ''),
            'photo_url' => $this->resolvePhotoUrl(),
            'role' => (string) ($this->role ?? ''),
            'specialties' => array_values($this->specializations ?? []),
            'availability_status' => $status,
            'is_bookable' => $bookable,
            'unavailable_label' => $bookable ? null : $availability->unavailableLabel($this, $when),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toTrackingArray(): array
    {
        $availability = app(TherapistAvailabilityService::class);
        $storedStatus = (string) $this->status;

        return [
            'id' => (string) $this->therapist_code,
            'name' => (string) $this->name,
            'role' => (string) ($this->role ?? ''),
            'bio' => (string) ($this->bio ?? ''),
            'contact_number' => $this->contact_number !== null ? (string) $this->contact_number : null,
            'address' => $this->address !== null ? (string) $this->address : null,
            'email' => $this->email !== null ? (string) $this->email : null,
            'birthday' => $this->birthday?->format('Y-m-d'),
            'avatar_initials' => (string) ($this->avatar_initials ?: ''),
            'photo_url' => $this->photo_url,
            'landing_photo' => (string) ($this->landing_photo ?? ''),
            'landing_photo_url' => $this->resolvePhotoUrl(),
            'specializations' => array_values($this->specializations ?? []),
            'certifications' => array_values($this->certifications ?? []),
            'sessions_label' => (string) ($this->sessions_label ?? ''),
            'accent_color' => (string) ($this->accent_color ?? '#8fa89a'),
            'stored_status' => $storedStatus,
            'status' => $availability->effectiveStatus($this),
            'working_days' => array_values($this->working_days ?? []),
            'day_off_until' => $this->day_off_until?->format('Y-m-d'),
            'work_on_off_day' => (bool) $this->work_on_off_day,
            'total_hours' => (int) $this->total_hours,
            'rating' => (float) $this->rating,
            'service_hours_pct' => (int) $this->service_hours_pct,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
