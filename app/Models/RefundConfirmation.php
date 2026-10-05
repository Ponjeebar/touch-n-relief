<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundConfirmation extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'confirmed_at' => 'datetime', 'evidence' => 'encrypted'];
    }
}
