<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreClosure extends Model
{
    protected $fillable = [
        'closure_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'closure_date' => 'date',
        ];
    }
}
