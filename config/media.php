<?php

return [

    /*
    | Disk storing submissions and captations ("public" locally, an S3
    | compatible disk in production).
    */
    'disk' => env('MEDIA_DISK', 'public'),

    /*
    | ffprobe binary used to check the real duration of uploaded media.
    | When it is not installed, the duration check is skipped (and logged).
    */
    'ffprobe' => env('FFPROBE_PATH', 'ffprobe'),

];
