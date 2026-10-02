<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prepaid customer order (Razorpay).
 */
class Order extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
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

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
