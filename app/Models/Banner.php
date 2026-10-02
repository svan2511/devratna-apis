<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Home offer banner (Breakfast Fest, Momos Fest, ...).
 */
class Banner extends Model
{
    /** @use HasFactory<Banner> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'pill_text',
        'target',
        'offer_id',
        'theme',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Admin panel me allowed theme keys — app inhe Brand colors pe map karti hai.
     *
     * @return list<string>
     */
    public static function themes(): array
    {
        return ['espresso', 'terracotta', 'gold', 'cream', 'forest'];
    }

    /**
     * Juda hua offer (countdown ke liye) — delete ho to link toot jaye, banner rahe.
     *
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @param  Builder<Banner>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<Banner>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
