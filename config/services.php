<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dev Ratna Diner — shop geofence + Razorpay
    |--------------------------------------------------------------------------
    |
    | SHOP_LAT / SHOP_LNG = exact shop location (Google Maps pin).
    | Orders are accepted only within SHOP_RADIUS_M metres of the shop
    | and only when the food bill is at least MIN_ORDER_AMOUNT rupees.
    | DELIVERY_CHARGE rupees flat delivery is added on every order.
    | DELIVERY_MODE = fixed (flat charge) ya distance (slab-wise).
    | Distance mode: FREE_UPTO_M tak BASE, uske baad har 500m pe PER_500M extra.
    |
    */

    'shop' => [
        'lat' => env('SHOP_LAT', 30.2710150),
        'lng' => env('SHOP_LNG', 77.9926317),
        'radius_m' => env('SHOP_RADIUS_M', 1000),
        'min_order' => env('MIN_ORDER_AMOUNT', 500),
        'delivery_charge' => env('DELIVERY_CHARGE', 40),
        'delivery_mode' => env('DELIVERY_MODE', 'fixed'),
        'delivery_base' => env('DELIVERY_BASE', 40),
        'delivery_free_m' => env('DELIVERY_FREE_M', 1000),
        'delivery_per_500m' => env('DELIVERY_PER_500M', 4),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],

    // Expo push (order status notifications) — optional, rate limits badhata hai.
    // Free token: expo.dev → Account → Access Tokens.
    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
