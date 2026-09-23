<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function findByPhone(string $phone): ?User
    {
        // Single-row lookup — no relations loaded, so no N+1 risk.
        return User::query()->where('phone', $phone)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();

        return $user->refresh();
    }

    public function markPhoneVerified(User $user): User
    {
        if ($user->phone_verified_at === null) {
            $user->phone_verified_at = now();
            $user->save();
        }

        return $user->refresh();
    }
}
