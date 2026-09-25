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

    /*
    | Streaming optimization of uploaded videos with ffmpeg (ProcessSubmission):
    | compatible H.264 files are remuxed without re-encoding (lossless, playback
    | starts at once), other codecs/containers are re-encoded in high quality
    | (H.264 CRF 20, long side capped at 1920 px), and a poster image is extracted.
    | Without ffmpeg the original file is kept as is.
    */
    'ffmpeg' => env('FFMPEG_PATH', 'ffmpeg'),

    'optimize' => (bool) env('MEDIA_OPTIMIZE', true),

    // Longest side of a re-encoded video, and width of the poster image.
    'max_video_size' => (int) env('MEDIA_MAX_VIDEO_SIZE', 1920),
    'poster_width' => (int) env('MEDIA_POSTER_WIDTH', 720),

];
