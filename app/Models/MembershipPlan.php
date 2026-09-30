<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPlan extends Model
{
    protected $fillable = ['name', 'price_amount', 'validity_days', 'description', 'benefits', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'validity_days' => 'integer',
            'benefits' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
