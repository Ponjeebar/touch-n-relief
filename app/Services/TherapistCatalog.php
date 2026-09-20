<?php

namespace App\Services;

use App\Models\Therapist;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class TherapistCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forBooking(?Carbon $date = null): array
    {
        $this->ensureSeeded();

        if (! Schema::hasTable('therapists')) {
            return $this->defaultBookingRows($date);
        }

        $query = Therapist::query();
        if (Schema::hasColumn('therapists', 'is_active')) {
            $query->where('is_active', true);
        }

        $when = $date ?? now();

        $fromDb = $query
            ->orderBy(Schema::hasColumn('therapists', 'sort_order') ? 'sort_order' : 'therapist_code')
            ->orderBy('therapist_code')
            ->get()
            ->map(fn (Therapist $therapist): array => $therapist->toBookingArray($when))
            ->all();

        return $fromDb !== [] ? $fromDb : $this->defaultBookingRows($date);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultBookingRows(?Carbon $date = null): array
    {
        return array_map(function (array $row): array {
            $status = strtolower((string) ($row['status'] ?? 'available'));
            $bookable = $status === 'available';

            return [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'photo' => (string) ($row['photo'] ?? ''),
                'photo_url' => asset('images/landing/therapist/'.($row['photo'] ?? '')),
                'role' => (string) ($row['role'] ?? ''),
                'specialties' => array_values($row['specialties'] ?? []),
                'availability_status' => $status,
                'is_bookable' => $bookable,
                'unavailable_label' => $bookable ? null : ($status === 'busy' ? 'In session' : 'Off duty'),
            ];
        }, $this->defaultCatalog());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forLanding(bool $hidePrenatalRefs = false): array
    {
        $this->ensureSeeded();

        if (! Schema::hasTable('therapists')) {
            return array_map(
                fn (array $therapist): array => $this->filterLandingTherapist($therapist, $hidePrenatalRefs),
                $this->defaultCatalog(),
            );
        }

        $query = Therapist::query();
        if (Schema::hasColumn('therapists', 'is_active')) {
            $query->where('is_active', true);
        }

        $fromDb = $query
            ->orderBy(Schema::hasColumn('therapists', 'sort_order') ? 'sort_order' : 'therapist_code')
            ->orderBy('therapist_code')
            ->get()
            ->map(fn (Therapist $therapist): array => $therapist->toLandingArray($hidePrenatalRefs))
            ->all();

        if ($fromDb === []) {
            return array_map(
                fn (array $therapist): array => $this->filterLandingTherapist($therapist, $hidePrenatalRefs),
                $this->defaultCatalog(),
            );
        }

        return $fromDb;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forTracking(): array
    {
        $this->ensureSeeded();

        if (! Schema::hasTable('therapists')) {
            return $this->defaultTrackingRows();
        }

        $query = Therapist::query();

        return $query
            ->orderBy(Schema::hasColumn('therapists', 'sort_order') ? 'sort_order' : 'therapist_code')
            ->orderBy('therapist_code')
            ->get()
            ->map(fn (Therapist $therapist): array => $therapist->toTrackingArray())
            ->all();
    }

    public function ensureSeeded(): void
    {
        if (! Schema::hasTable('therapists')) {
            return;
        }

        if (Therapist::query()->exists()) {
            $this->backfillLandingProfiles();

            return;
        }

        foreach ($this->defaultCatalog() as $index => $row) {
            Therapist::query()->create($this->rowToModelAttributes($row, $index + 1));
        }
    }

    private function backfillLandingProfiles(): void
    {
        foreach ($this->defaultCatalog() as $index => $row) {
            $therapist = Therapist::query()->where('name', (string) $row['name'])->first();
            if ($therapist === null) {
                continue;
            }

            $updates = [];
            if (! Schema::hasColumn('therapists', 'role')) {
                return;
            }

            foreach ([
                'role' => $row['role'] ?? null,
                'bio' => $row['bio'] ?? null,
                'sessions_label' => $row['sessions'] ?? null,
                'accent_color' => $row['accent'] ?? null,
                'landing_photo' => $row['photo'] ?? null,
                'sort_order' => $index + 1,
            ] as $column => $value) {
                if ($value !== null && ($therapist->{$column} === null || $therapist->{$column} === '')) {
                    $updates[$column] = $value;
                }
            }

            if (($therapist->certifications ?? []) === [] && ! empty($row['certifications'])) {
                $updates['certifications'] = array_values($row['certifications']);
            }

            if ($updates !== []) {
                $therapist->update($updates);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowToModelAttributes(array $row, int $sortOrder): array
    {
        $name = (string) ($row['name'] ?? '');
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])->filter()->values();
        $initials = strtoupper(substr((string) ($parts[0] ?? $name), 0, 1).substr((string) ($parts->last() ?? ''), 0, 1));

        return [
            'therapist_code' => (string) ($row['id'] ?? $this->codeForSort($sortOrder)),
            'name' => $name,
            'role' => (string) ($row['role'] ?? ''),
            'bio' => (string) ($row['bio'] ?? ''),
            'contact_number' => $row['contact_number'] ?? null,
            'address' => $row['address'] ?? null,
            'email' => $row['email'] ?? null,
            'birthday' => $row['birthday'] ?? null,
            'avatar_initials' => $initials !== '' ? $initials : 'TT',
            'photo_url' => $row['photo_url'] ?? null,
            'landing_photo' => (string) ($row['photo'] ?? ''),
            'specializations' => array_values($row['specialties'] ?? $row['specializations'] ?? []),
            'certifications' => array_values($row['certifications'] ?? []),
            'sessions_label' => (string) ($row['sessions'] ?? '0'),
            'accent_color' => (string) ($row['accent'] ?? '#8fa89a'),
            'status' => (string) ($row['status'] ?? 'available'),
            'total_hours' => (int) ($row['total_hours'] ?? 0),
            'rating' => (float) ($row['rating'] ?? 0),
            'service_hours_pct' => (int) ($row['service_hours_pct'] ?? 0),
            'is_active' => true,
            'sort_order' => $sortOrder,
        ];
    }

    private function codeForSort(int $sortOrder): string
    {
        return 'T'.str_pad((string) $sortOrder, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $therapist
     * @return array<string, mixed>
     */
    private function filterLandingTherapist(array $therapist, bool $hidePrenatalRefs): array
    {
        if (! $hidePrenatalRefs) {
            return $therapist;
        }

        $therapist['specialties'] = array_values(array_filter(
            $therapist['specialties'] ?? [],
            static fn (string $specialty): bool => $specialty !== 'Prenatal Massage',
        ));
        $therapist['certifications'] = array_values(array_filter(
            $therapist['certifications'] ?? [],
            static fn (string $cert): bool => ! str_contains($cert, 'Prenatal'),
        ));

        return $therapist;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaultCatalog(): array
    {
        return [
            [
                'id' => 'T001',
                'name' => 'Maria Santos',
                'photo' => 'maria-santos.jpg',
                'role' => 'Senior Massage Therapist',
                'bio' => 'Maria brings over 8 years of clinical massage experience with a calm, attentive approach. She specializes in stress relief and muscle recovery for clients who need deep relaxation without harsh pressure.',
                'specialties' => ['Swedish Massage', 'Deep Tissue', 'Prenatal Massage'],
                'certifications' => ['Licensed Massage Therapist (LMT)', 'Prenatal Massage Certification', 'CPR & First Aid Certified'],
                'sessions' => '1,200+',
                'accent' => '#c4a882',
                'status' => 'available',
                'total_hours' => 218,
                'service_hours_pct' => 91,
            ],
            [
                'id' => 'T002',
                'name' => 'Juan dela Cruz',
                'photo' => 'juan-dela-cruz.jpg',
                'role' => 'Sports & Recovery Specialist',
                'bio' => 'Juan works with athletes and active clients to improve mobility, reduce soreness, and speed up recovery. His sessions combine targeted deep tissue work with sports-specific techniques.',
                'specialties' => ['Sports Massage', 'Deep Tissue', 'Thai Massage'],
                'certifications' => ['Sports Massage Therapy Certificate', 'Licensed Massage Therapist (LMT)', 'Injury Prevention & Recovery Training'],
                'sessions' => '980+',
                'accent' => '#8fa89a',
                'status' => 'busy',
                'total_hours' => 176,
                'service_hours_pct' => 73,
            ],
            [
                'id' => 'T003',
                'name' => 'Liza Reyes',
                'photo' => 'liza-reyes.jpg',
                'role' => 'Relaxation & Aromatherapy Expert',
                'bio' => 'Liza creates soothing, sensory-rich experiences using essential oils and gentle Swedish techniques. She is known for helping clients unwind mentally and physically in every session.',
                'specialties' => ['Aromatherapy', 'Swedish Massage', 'Hot Stone'],
                'certifications' => ['Aromatherapy Bodywork Certification', 'Licensed Massage Therapist (LMT)', 'Wellness & Stress Management Training'],
                'sessions' => '1,450+',
                'accent' => '#b89aab',
                'status' => 'available',
                'total_hours' => 231,
                'service_hours_pct' => 96,
            ],
            [
                'id' => 'T004',
                'name' => 'Carlos Mendoza',
                'photo' => 'carlos-mendoza.jpg',
                'role' => 'Therapeutic Bodywork Specialist',
                'bio' => 'Carlos focuses on therapeutic outcomes through structured bodywork, blending sports massage and traditional Thai stretching to restore movement and ease chronic tension.',
                'specialties' => ['Sports Massage', 'Thai Massage', 'Deep Tissue'],
                'certifications' => ['Thai Massage Practitioner Certificate', 'Licensed Massage Therapist (LMT)', 'Therapeutic Bodywork Diploma'],
                'sessions' => '860+',
                'accent' => '#9a8f7e',
                'status' => 'off-duty',
                'total_hours' => 129,
                'service_hours_pct' => 54,
            ],
            [
                'id' => 'T005',
                'name' => 'Angela Fernandez',
                'photo' => 'angela-fernandez.jpg',
                'role' => 'Hot Stone & Wellness Therapist',
                'bio' => 'Angela delivers warm, grounding treatments using heated stones and aromatherapy blends. Her sessions are ideal for clients seeking deep warmth, circulation support, and full-body calm.',
                'specialties' => ['Hot Stone', 'Aromatherapy', 'Swedish Massage'],
                'certifications' => ['Hot Stone Therapy Certification', 'Aromatherapy Specialist Certificate', 'Licensed Massage Therapist (LMT)'],
                'sessions' => '1,100+',
                'accent' => '#a8927c',
                'status' => 'available',
                'total_hours' => 203,
                'service_hours_pct' => 85,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultTrackingRows(): array
    {
        return array_map(function (array $row): array {
            return [
                'id' => (string) ($row['id'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'role' => (string) ($row['role'] ?? ''),
                'bio' => (string) ($row['bio'] ?? ''),
                'contact_number' => null,
                'address' => null,
                'email' => null,
                'birthday' => null,
                'avatar_initials' => strtoupper(substr((string) ($row['name'] ?? 'TT'), 0, 2)),
                'photo_url' => null,
                'landing_photo' => (string) ($row['photo'] ?? ''),
                'landing_photo_url' => asset('images/landing/therapist/'.($row['photo'] ?? '')),
                'specializations' => array_values($row['specialties'] ?? []),
                'certifications' => array_values($row['certifications'] ?? []),
                'sessions_label' => (string) ($row['sessions'] ?? ''),
                'accent_color' => (string) ($row['accent'] ?? '#8fa89a'),
                'status' => (string) ($row['status'] ?? 'available'),
                'total_hours' => (int) ($row['total_hours'] ?? 0),
                'rating' => (float) ($row['rating'] ?? 0),
                'service_hours_pct' => (int) ($row['service_hours_pct'] ?? 0),
                'is_active' => true,
                'sort_order' => 0,
            ];
        }, $this->defaultCatalog());
    }
}
