<?php

/*
 * Socket.IO realtime server (realtime/server.js). Laravel publishes updates to
 * `publish_url` with the shared `secret`; clients connect to `url` and join
 * private channels with a token signed by App\Realtime\RealtimeToken.
 */
return [
    'enabled' => (bool) env('REALTIME_ENABLED', filled(env('REALTIME_SECRET'))),

    // Public URL of the Socket.IO server, used by browsers and the mobile app.
    'url' => env('REALTIME_URL', 'http://localhost:6001'),

    // Internal URL Laravel publishes to (same host in development).
    'publish_url' => env('REALTIME_PUBLISH_URL', env('REALTIME_URL', 'http://localhost:6001')),

    'secret' => env('REALTIME_SECRET'),

    // Lifetime of a channel token, in minutes (pages refresh it on every load).
    'token_ttl' => (int) env('REALTIME_TOKEN_TTL', 240),

    // Seconds between two throttled updates of the same kind (votes, likes, scores).
    'throttle' => (int) env('REALTIME_THROTTLE', 2),
];
