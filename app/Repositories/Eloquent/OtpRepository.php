<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\OtpCode;
use App\Repositories\Contracts\OtpRepositoryInterface;
use Illuminate\Support\Collection;

class OtpRepository implements OtpRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): OtpCode
    {
        return OtpCode::query()->create($attributes);
    }

    public function latestLiveForPhone(string $phone): ?OtpCode
    {
        return OtpCode::query()
            ->where('phone', $phone)
            ->live()
            ->latest('id')
            ->first();
    }

    public function incrementAttempts(OtpCode $otp): OtpCode
    {
        $otp->increment('attempts');

        return $otp->refresh();
    }

    public function consume(OtpCode $otp): OtpCode
    {
        $otp->consumed_at = now();
        $otp->save();

        return $otp->refresh();
    }

    public function invalidateLiveForPhone(string $phone): void
    {
        // Expire previous live codes when a new OTP is requested (single bulk query).
        OtpCode::query()
            ->where('phone', $phone)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    public function countRecentForPhone(string $phone, int $minutes): int
    {
        return OtpCode::query()
            ->where('phone', $phone)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * @return Collection<int, OtpCode>
     */
    public function recentForPhone(string $phone, int $limit = 5): Collection
    {
        return OtpCode::query()
            ->where('phone', $phone)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
