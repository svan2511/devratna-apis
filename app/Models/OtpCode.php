<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One-time passcode record keyed by phone number.
 */
class OtpCode extends Model
{
    /** @use HasFactory<OtpCode> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'phone',
        'code_hash',
        'expires_at',
        'consumed_at',
        'attempts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * Only live (unconsumed + unexpired) codes.
     *
     * @param  Builder<OtpCode>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('consumed_at')->where('expires_at', '>', now());
    }

    public function isLive(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
