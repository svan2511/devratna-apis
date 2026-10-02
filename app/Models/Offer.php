<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time-bound offer — discount and/or free item, auto-applied at billing.
 */
class Offer extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'target_type',
        'target_value',
        'discount_type',
        'discount_value',
        'discount_scope',
        'free_item_id',
        'min_order',
        'min_qty',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'min_order' => 'integer',
            'min_qty' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function freeItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'free_item_id');
    }

    /**
     * @return list<string>
     */
    public static function targetTypes(): array
    {
        return ['all', 'category', 'item'];
    }

    /**
     * @return list<string>
     */
    public static function discountTypes(): array
    {
        return ['none', 'percent', 'flat'];
    }

    /**
     * @param  Builder<Offer>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Abhi chal rahe offers — active + window ke andar.
     *
     * @param  Builder<Offer>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $now = now();
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}
