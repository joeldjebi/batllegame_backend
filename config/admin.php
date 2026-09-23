<?php

return [

    /*
    | URL prefix of the platform administration area (login included).
    | Use a hard-to-guess value in production, e.g. ADMIN_PATH=sa-7f3k9q2m.
    */
    'path' => env('ADMIN_PATH', 'admin'),

    /*
    | Minutes of inactivity after which the admin session is closed.
    */
    'idle_timeout' => (int) env('ADMIN_IDLE_TIMEOUT', 30),

    /*
    | Login attempts allowed per minute (per IP and phone number).
    */
    'login_attempts_per_minute' => (int) env('ADMIN_LOGIN_ATTEMPTS', 5),

];
