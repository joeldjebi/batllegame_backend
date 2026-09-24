<?php

return [
    // Temporary password of the judge accounts created by organizers, while no SMS service
    // delivers the access codes. Empty = a random password (sent by SMS). Ignored in production.
    'judge_default_password' => env('JUDGE_DEFAULT_PASSWORD'),
];
