<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaService extends Model
{
    protected $fillable = [
        'name',
        'price_amount',
        'duration_minutes',
        'best_for',
        'description',
        'image',
        'prenatal_only',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'duration_minutes' => 'integer',
            'prenatal_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toCatalogArray(): array
    {
        $minutes = (int) $this->duration_minutes;

        return [
            'name' => (string) $this->name,
            'price' => 'PHP '.number_format((float) $this->price_amount, 2),
            'price_amount' => (float) $this->price_amount,
            'duration' => $minutes.' min',
            'best_for' => (string) $this->best_for,
            'desc' => (string) $this->description,
            'image' => (string) ($this->image ?? ''),
            'times' => [],
            'prenatal_only' => (bool) $this->prenatal_only,
        ];
    }
}
