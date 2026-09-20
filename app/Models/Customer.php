<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use Archivable, HasFactory;

    protected $fillable = [
        'customer_id',
        'full_name',
        'birthday',
        'number',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'password' => 'hashed',
            'archived_at' => 'datetime',
        ];
    }

    public static function nextCustomerId(): string
    {
        $latest = static::query()->latest('id')->first();
        $nextNumber = ($latest?->id ?? 0) + 1;

        return 'CUS-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public function isWalkIn(): bool
    {
        if (User::isWalkInEmail($this->email)) {
            return true;
        }

        $linkedUser = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $this->email))])
            ->first();

        return $linkedUser instanceof User && $linkedUser->isWalkIn();
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeRegistered(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(email) NOT LIKE ?', ['%'.User::WALKIN_EMAIL_DOMAIN]);
    }
}

