<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receptionist extends Model
{
    use Archivable, HasFactory;

    protected $fillable = [
        'receptionist_id',
        'full_name',
        'username',
        'email',
        'address',
        'phone_number',
        'birthday',
        'shift',
        'profile_picture',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'archived_at' => 'datetime',
        ];
    }
}
