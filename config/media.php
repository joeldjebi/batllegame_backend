<?php

return [

    /*
    | Disk storing submissions and captations ("public" locally, an S3
    | compatible disk in production).
    */
    'disk' => env('MEDIA_DISK', 'public'),

    /*
    | Lifetime (minutes) of the signed links to files of a private S3 / Wasabi bucket.
    */
    'signed_url_minutes' => (int) env('MEDIA_URL_TTL', 360),

    /*
    | ffprobe binary used to check the real duration of uploaded media.
    | When it is not installed, the duration check is skipped (and logged).
    */
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),

];
