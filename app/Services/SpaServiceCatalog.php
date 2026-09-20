<?php

namespace App\Services;

use App\Models\SpaService;
use Illuminate\Support\Facades\Schema;

class SpaServiceCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $this->ensureSeeded();

        if (Schema::hasTable('spa_services')) {
            $fromDb = SpaService::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (SpaService $service): array => $service->toCatalogArray())
                ->all();

            if ($fromDb !== []) {
                return $fromDb;
            }
        }

        return $this->defaultCatalog();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByName(string $name): ?array
    {
        foreach ($this->all() as $service) {
            if (strcasecmp((string) ($service['name'] ?? ''), $name) === 0) {
                return $service;
            }
        }

        return null;
    }

    public function durationMinutesFor(string $name): int
    {
        $service = $this->findByName($name);
        if ($service === null) {
            return 60;
        }

        if (isset($service['duration_minutes'])) {
            return max((int) $service['duration_minutes'], 1);
        }

        $duration = (string) ($service['duration'] ?? '');
        if (preg_match('/(\d+)/', $duration, $matches) === 1) {
            return max((int) $matches[1], 1);
        }

        return 60;
    }

    public function ensureSeeded(): void
    {
        if (! Schema::hasTable('spa_services') || SpaService::query()->exists()) {
            return;
        }

        foreach ($this->defaultCatalog() as $service) {
            $duration = (string) ($service['duration'] ?? '');
            $minutes = 60;
            if (preg_match('/(\d+)/', $duration, $matches) === 1) {
                $minutes = (int) $matches[1];
            }

            SpaService::query()->create([
                'name' => (string) $service['name'],
                'price_amount' => (float) ($service['price_amount'] ?? 0),
                'duration_minutes' => $minutes,
                'best_for' => (string) ($service['best_for'] ?? ''),
                'description' => (string) ($service['desc'] ?? ''),
                'image' => (string) ($service['image'] ?? ''),
                'prenatal_only' => ! empty($service['prenatal_only']),
                'is_active' => true,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function defaultCatalog(): array
    {
        return [
            ['name' => 'Swedish Massage', 'price' => 'PHP 85.00', 'price_amount' => 85, 'duration' => '60 min', 'best_for' => 'Stress relief', 'desc' => 'Gentle full-body massage designed for relaxation and stress relief.', 'image' => 'swedish-massage.webp', 'times' => ['10:00 AM', '1:00 PM', '4:00 PM'], 'prenatal_only' => false],
            ['name' => 'Deep Tissue', 'price' => 'PHP 140.00', 'price_amount' => 140, 'duration' => '90 min', 'best_for' => 'Muscle tension', 'desc' => 'Focused pressure work to release chronic tension and muscle stiffness.', 'image' => 'deep-tissue-massage.jpg', 'times' => ['9:30 AM', '12:30 PM', '6:00 PM'], 'prenatal_only' => false],
            ['name' => 'Hot Stone', 'price' => 'PHP 145.00', 'price_amount' => 145, 'duration' => '90 min', 'best_for' => 'Deep relaxation', 'desc' => 'Heated stone therapy to improve circulation and deeply relax muscles.', 'image' => 'hot-stone.jpg', 'times' => ['11:00 AM', '2:30 PM', '7:00 PM'], 'prenatal_only' => false],
            ['name' => 'Aromatherapy', 'price' => 'PHP 95.00', 'price_amount' => 95, 'duration' => '60 min', 'best_for' => 'Mental calm', 'desc' => 'Relaxing massage paired with therapeutic essential oil blends.', 'image' => 'aromatheraphy.webp', 'times' => ['10:30 AM', '3:00 PM', '8:00 PM'], 'prenatal_only' => false],
            ['name' => 'Prenatal Massage', 'price' => 'PHP 110.00', 'price_amount' => 110, 'duration' => '60 min', 'best_for' => 'Prenatal comfort', 'desc' => 'Comfort-focused care tailored for expecting mothers.', 'image' => 'pre-natal.jpg', 'times' => ['9:00 AM', '1:30 PM', '5:30 PM'], 'prenatal_only' => true],
            ['name' => 'Thai Massage', 'price' => 'PHP 110.00', 'price_amount' => 110, 'duration' => '75 min', 'best_for' => 'Flexibility', 'desc' => 'Traditional assisted stretching and pressure-point treatment.', 'image' => 'thai-massage.webp', 'times' => ['11:30 AM', '4:30 PM', '7:30 PM'], 'prenatal_only' => false],
            ['name' => 'Sports Massage', 'price' => 'PHP 105.00', 'price_amount' => 105, 'duration' => '60 min', 'best_for' => 'Recovery', 'desc' => 'Performance and recovery massage for active lifestyles.', 'image' => 'sport-massage.webp', 'times' => ['8:30 AM', '12:00 PM', '6:30 PM'], 'prenatal_only' => false],
            ['name' => 'Foot Reflexology', 'price' => 'PHP 70.00', 'price_amount' => 70, 'duration' => '45 min', 'best_for' => 'Foot fatigue', 'desc' => 'Foot pressure therapy that supports whole-body relaxation.', 'image' => 'foot-reflexology.jpg', 'times' => ['10:00 AM', '2:00 PM', '5:00 PM'], 'prenatal_only' => false],
        ];
    }
}
