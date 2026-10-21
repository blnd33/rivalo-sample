<?php

return [
    /*
    | Public support contact shown in the app and policy pages. Must be a monitored inbox.
    */
    'support_email' => env('RIVALO_SUPPORT_EMAIL'),

    /*
    | Push notifications provider (Milestone 6). "log" writes notifications to the log only.
    | Planned: "fcm" (Firebase Cloud Messaging, which also delivers to iOS via APNs).
    */
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'fcm_project_id' => env('FCM_PROJECT_ID'),
        // Path to the service-account JSON on the server. Never commit it, never ship it in the app.
        'fcm_credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],

    /*
    | Competition (Milestone 5). DRAFT values until the owner confirms the published rules (D1, D3).
    | dispute_window_hours: how long after approval a participant may dispute a result.
    */
    'competition' => [
        'dispute_window_hours' => (int) env('RIVALO_DISPUTE_WINDOW_HOURS', 48),
    ],

    /*
    | Store (Milestone 8). One currency for every product and order. Payment is cash on pickup or
    | delivery; no card payments until a provider is verified.
    */
    'store' => [
        'currency' => env('RIVALO_STORE_CURRENCY', 'IQD'),
    ],

    /*
    | Security (Milestone 7). App sessions (one token per device) that are not used for this many
    | days are signed out by the daily `rivalo:prune-idle-sessions`.
    */
    'security' => [
        'idle_session_days' => (int) env('RIVALO_IDLE_SESSION_DAYS', 180),
    ],

    /*
    | Bootstrap super admin, created by `php artisan rivalo:make-admin`. No default password exists.
    */
];
