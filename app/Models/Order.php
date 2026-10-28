<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Prepaid customer order (Razorpay).
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'delivery_partner_id',
        'items',
        'delivery_address',
        'subtotal',
        'discount',
        'offer_id',
        'offer_name',
        'total',
        'status',
        'fulfillment_status',
        'kitchen_note',
        'failure_reason',
        'paid_at',
        'ready_at',
        'rider_assigned_at',
        'rider_accepted_at',
        'picked_up_at',
        'arrived_at',
        'delivered_at',
        'razorpay_order_id',
        'razorpay_payment_id',
        'customer_lat',
        'customer_lng',
        'distance_m',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'distance_m' => 'integer',
            'paid_at' => 'datetime',
            'ready_at' => 'datetime',
            'rider_assigned_at' => 'datetime',
            'rider_accepted_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'arrived_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<DeliveryPartner, $this>
     */
    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    /**
     * @return HasMany<OrderLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(OrderLocation::class);
    }

    /**
     * Latest rider position for live tracking.
     *
     * @return HasOne<OrderLocation, $this>
     */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(OrderLocation::class)->latestOfMany('recorded_at');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
