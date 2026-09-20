<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\Archivable;
use App\Models\SpaBooking;
use App\Notifications\BrandedResetPassword;
use App\Support\WalkInSchema;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Archivable, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    public const ROLE_USER = 'user';

    public const WALKIN_EMAIL_DOMAIN = '@walkin.local';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_RECEPTIONIST = 'receptionist';

    public const SEX_MALE = 'male';

    public const SEX_FEMALE = 'female';

    /** @return list<string> */
    public static function sexOptions(): array
    {
        return [
            self::SEX_MALE,
            self::SEX_FEMALE,
        ];
    }

    public const THERAPIST_PREF_MALE = 'male';

    public const THERAPIST_PREF_FEMALE = 'female';

    public const THERAPIST_PREF_NO = 'no_preference';

    public const PRESSURE_LOW = 'low';

    public const PRESSURE_MEDIUM = 'medium';

    public const PRESSURE_HIGH = 'high';

    protected $fillable = [
        'name',
        'username',
        'email',
        'contact_number',
        'birthday',
        'sex',
        'therapist_gender_preference',
        'is_pregnant',
        'pressure_preference',
        'profile_completed_at',
        'profile_photo_path',
        'password',
        'role',
        'is_walk_in',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        $casts = [
            'email_verified_at' => 'datetime',
            'profile_completed_at' => 'datetime',
            'birthday' => 'date',
            'is_pregnant' => 'boolean',
            'password' => 'hashed',
            'archived_at' => 'datetime',
        ];

        if (WalkInSchema::hasWalkInColumn()) {
            $casts['is_walk_in'] = 'boolean';
        }

        return $casts;
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! WalkInSchema::hasWalkInColumn()) {
                unset($user->attributes['is_walk_in']);
            }
        });
    }

    /**
     * Snapshot row created when the account was registered.
     *
     * @return HasOne<Registration, User>
     */
    public function registration(): HasOne
    {
        return $this->hasOne(Registration::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new BrandedResetPassword($token));
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public static function isWalkInEmail(?string $email): bool
    {
        if ($email === null || trim($email) === '') {
            return false;
        }

        return str_ends_with(strtolower(trim($email)), self::WALKIN_EMAIL_DOMAIN);
    }

    public static function isWalkInUsername(?string $username): bool
    {
        if ($username === null || trim($username) === '') {
            return false;
        }

        return str_starts_with(strtolower(trim($username)), 'walkin_');
    }

    public function isWalkIn(): bool
    {
        if (WalkInSchema::hasWalkInColumn()
            && array_key_exists('is_walk_in', $this->attributes)
            && $this->is_walk_in === true) {
            return true;
        }

        return self::isWalkInEmail($this->email)
            || self::isWalkInUsername($this->username);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeWalkIn($query)
    {
        if (WalkInSchema::hasWalkInColumn()) {
            return $query->where(function ($inner): void {
                $inner->where('is_walk_in', true)
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.self::WALKIN_EMAIL_DOMAIN])
                    ->orWhereRaw('LOWER(username) LIKE ?', ['walkin\_%']);
            });
        }

        return $query->where(function ($inner): void {
            $inner->whereRaw('LOWER(email) LIKE ?', ['%'.self::WALKIN_EMAIL_DOMAIN])
                ->orWhereRaw('LOWER(username) LIKE ?', ['walkin\_%']);
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<User>  $query
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function scopeRegisteredClient($query)
    {
        if (WalkInSchema::hasWalkInColumn()) {
            return $query->where(function ($inner): void {
                $inner->where('is_walk_in', false)
                    ->orWhereNull('is_walk_in');
            })->whereRaw('LOWER(email) NOT LIKE ?', ['%'.self::WALKIN_EMAIL_DOMAIN])
                ->where(function ($inner): void {
                    $inner->whereNull('username')
                        ->orWhereRaw('LOWER(username) NOT LIKE ?', ['walkin\_%']);
                });
        }

        return $query->whereRaw('LOWER(email) NOT LIKE ?', ['%'.self::WALKIN_EMAIL_DOMAIN])
            ->where(function ($inner): void {
                $inner->whereNull('username')
                    ->orWhereRaw('LOWER(username) NOT LIKE ?', ['walkin\_%']);
            });
    }

    /**
     * @return HasMany<SpaBooking, User>
     */
    public function spaBookings(): HasMany
    {
        return $this->hasMany(SpaBooking::class);
    }

    public function isReceptionist(): bool
    {
        return $this->role === self::ROLE_RECEPTIONIST;
    }

    public function needsProfileOnboarding(): bool
    {
        return $this->isUser() && $this->profile_completed_at === null;
    }

    public function sexLabel(): ?string
    {
        return match ($this->sex) {
            self::SEX_MALE => 'Male',
            self::SEX_FEMALE => 'Female',
            default => null,
        };
    }

    public function therapistGenderPreferenceLabel(): ?string
    {
        return match ($this->therapist_gender_preference) {
            self::THERAPIST_PREF_MALE => 'Male therapist',
            self::THERAPIST_PREF_FEMALE => 'Female therapist',
            self::THERAPIST_PREF_NO => 'No preference',
            default => null,
        };
    }

    public function pressurePreferenceLabel(): ?string
    {
        return match ($this->pressure_preference) {
            self::PRESSURE_LOW => 'Low pressure',
            self::PRESSURE_MEDIUM => 'Medium pressure',
            self::PRESSURE_HIGH => 'High pressure',
            default => null,
        };
    }

    public function pregnancyLabel(): ?string
    {
        if ($this->sex !== self::SEX_FEMALE) {
            return null;
        }

        if ($this->is_pregnant === null) {
            return null;
        }

        return $this->is_pregnant ? 'Yes' : 'No';
    }

    public function isPregnant(): bool
    {
        return $this->sex === self::SEX_FEMALE && $this->is_pregnant === true;
    }

    public function isMale(): bool
    {
        return $this->sex === self::SEX_MALE;
    }

    public function canAccessPrenatalServices(): bool
    {
        return $this->isPregnant();
    }
}
