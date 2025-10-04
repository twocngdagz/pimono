<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        // 'balance' intentionally not mass assignable via create/fill to avoid accidental overwrites
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
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Accessor: ensure balance is always returned as string with scale=2.
     */
    public function getBalanceAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = (string) $value;
        if (! str_contains($value, '.')) {
            $value .= '.00';
        } else {
            [$i, $f] = explode('.', $value, 2);
            $f = str_pad($f, 2, '0');
            $value = $i.'.'.substr($f, 0, 2);
        }

        return $value;
    }

    /**
     * Mutator: normalize input to scale=2 string; reject invalid formats.
     */
    public function setBalanceAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['balance'] = null;

            return;
        }
        $value = trim((string) $value);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            throw new \InvalidArgumentException('Invalid balance format.');
        }
        if (! str_contains($value, '.')) {
            $value .= '.00';
        } else {
            [$i, $f] = explode('.', $value, 2);
            $f = str_pad($f, 2, '0');
            $value = $i.'.'.substr($f, 0, 2);
        }
        $this->attributes['balance'] = $value;
    }
}
