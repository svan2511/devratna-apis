<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// App band/webhook miss hone par bhi pending Razorpay order paid ho jaye.
Schedule::command('orders:reconcile')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
