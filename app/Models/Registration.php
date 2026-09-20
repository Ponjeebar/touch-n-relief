<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'contact_number',
    ];

    /**
     * @return BelongsTo<User, Registration>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
