<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMedication extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'sort_order',
    ];

    /**
     * @return BelongsTo<User, UserMedication>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
