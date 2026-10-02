<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\MenuRepositoryInterface;
use App\Repositories\Contracts\OtpRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\MenuRepository;
use App\Repositories\Eloquent\OtpRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Service-Repository bindings. Switching databases
        // (SQLite/MySQL/Postgres) needs no change here — Eloquent handles it.
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(OtpRepositoryInterface::class, OtpRepository::class);
        $this->app->bind(MenuRepositoryInterface::class, MenuRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Named throttle limiters — key me kaam ka naam hota hai, isliye
        // menu-polling kabhi OTP/admin-login ka quota nahi kha sakta.
        // (Plain `throttle:10,1` me route ka naam key me nahi hota — sab share ho jata hai.)
        // OTP limit phone+IP pe — ek user ke retry dusre ko block nahi karenge.
        RateLimiter::for('otp', function (Request $request): Limit {
            $phone = strtolower(trim((string) $request->input('phone', '')));

            return Limit::perMinute(10)->by('otp:'.$phone.'|'.$request->ip());
        });
        RateLimiter::for('otp-verify', function (Request $request): Limit {
            $phone = strtolower(trim((string) $request->input('phone', '')));

            return Limit::perMinute(15)->by('otp-verify:'.$phone.'|'.$request->ip());
        });
        // Public catalog (menu/banners/offers/shop-status/webhook) — polling ke liye generous.
        RateLimiter::for('catalog', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });
        // Admin login — email+IP pe, taaki ek bande ke galat attempts
        // dusre admin ko lock na karein. Brute-force guard barkarar.
        RateLimiter::for('admin-login', function (Request $request): Limit {
            $email = strtolower(trim((string) $request->input('email', '')));

            return Limit::perMinute(10)->by('admin-login:'.$email.'|'.$request->ip());
        });
    }
}
