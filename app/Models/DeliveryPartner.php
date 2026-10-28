<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeliveryPartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rider profile for a normal OTP user.
 */
class DeliveryPartner extends Model
{
    /** @use HasFactory<DeliveryPartnerFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_number',
        'is_active',
        'is_online',
        'last_lat',
        'last_lng',
        'last_location_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'last_location_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasActiveOrder(): bool
    {
        return $this->orders()
            ->where('status', 'paid')
            ->whereIn('fulfillment_status', ['ready', 'out_for_delivery'])
            ->exists();
    }
}
