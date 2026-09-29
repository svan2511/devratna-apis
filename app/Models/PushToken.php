<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Expo push token (ExponentPushToken[...]) for order status pushes.
 */
class PushToken extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'token', 'platform'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
