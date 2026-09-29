<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Admin dashboard login — ADMIN_EMAIL / ADMIN_PASSWORD se.
     * Customer OTP flow ko touch nahi karta.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@devratna.in');
        $password = (string) env('ADMIN_PASSWORD', 'devratna123');
        $phone = (string) env('ADMIN_PHONE', '9000000001');

        $admin = User::query()->where('email', $email)->first();

        if ($admin === null) {
            User::create([
                'name' => 'Dev Ratna Admin',
                'email' => $email,
                'phone' => $phone,
                'phone_verified_at' => now(),
                'password' => Hash::make($password),
                'is_admin' => true,
            ]);
        } else {
            $admin->update([
                'name' => $admin->name ?? 'Dev Ratna Admin',
                'password' => Hash::make($password),
                'is_admin' => true,
            ]);
        }

        $this->command->info("Admin ready: {$email} (password from ADMIN_PASSWORD env)");
    }
}
