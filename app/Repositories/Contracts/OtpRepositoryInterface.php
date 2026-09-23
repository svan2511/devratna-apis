<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\OtpCode;
use Illuminate\Support\Collection;

interface OtpRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): OtpCode;

    public function latestLiveForPhone(string $phone): ?OtpCode;

    public function incrementAttempts(OtpCode $otp): OtpCode;

    public function consume(OtpCode $otp): OtpCode;

    public function invalidateLiveForPhone(string $phone): void;

    public function countRecentForPhone(string $phone, int $minutes): int;

    /**
     * @return Collection<int, OtpCode>
     */
    public function recentForPhone(string $phone, int $limit = 5): Collection;
}
