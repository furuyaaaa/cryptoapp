<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Require active subscription
    |--------------------------------------------------------------------------
    |
    | When false, main app routes are available without Stripe (local dev).
    |
    */

    'required' => env('SUBSCRIPTION_REQUIRED', true),

    /*
    |--------------------------------------------------------------------------
    | Stripe Price ID
    |--------------------------------------------------------------------------
    |
    | Recurring price ID from Stripe Dashboard (Products → Price).
    |
    */

    'price_id' => env('STRIPE_PRICE_ID'),

];
