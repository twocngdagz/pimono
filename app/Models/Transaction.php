<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\\Models\\Transaction
 *
 * @property int $id
 * @property string $uuid
 * @property int $sender_id
 * @property int $receiver_id
 * @property string $amount Decimal(20,2) stored as string
 * @property string $commission_fee Decimal(20,2) stored as string
 * @property string $status success|failed
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Transaction extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'sender_id',
        'receiver_id',
        'amount',
        'commission_fee',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Keep as string for precision; casting to decimal can lose scale on some drivers
            'amount' => 'string',
            'commission_fee' => 'string',
        ];
    }
}
