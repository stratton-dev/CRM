<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (FCM) — for Android / iOS mobile apps
    |--------------------------------------------------------------------------
    | Set FCM_SERVER_KEY in your .env to enable FCM push notifications.
    | Get it from Firebase Console → Project Settings → Cloud Messaging.
    */
    'fcm_server_key' => env('FCM_SERVER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | VAPID keys — for Web Push (browser / PWA)
    |--------------------------------------------------------------------------
    | Generate with: php artisan push:generate-vapid
    | Or online:    https://vapidkeys.com/
    */
    'vapid_public_key'  => env('VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
];
