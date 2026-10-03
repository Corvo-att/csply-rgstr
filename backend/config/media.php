<?php

/*
|--------------------------------------------------------------------------
| Media / upload settings
|--------------------------------------------------------------------------
|
| Everything the upload pipeline needs lives here so it can be tuned from
| .env without touching code. Remember that PHP itself has limits too
| (upload_max_filesize / post_max_size in php.ini, client_max_body_size in
| nginx) - the form builder shows a warning when a field limit is higher
| than what the server will actually accept.
|
*/

return [

    // Laravel filesystem disk uploads are written to (see config/filesystems.php).
    // "public" needs `php artisan storage:link` once per environment.
    'disk' => env('MEDIA_DISK', 'public'),
    'directory' => 'uploads',

    /*
     * Allow-list of MIME types => the file extension we store them under.
     * The extension is ALWAYS derived from here (never from the client's
     * filename), so a ".php" or ".html" upload can never be saved.
     */
    'mime_extensions' => [
        // images
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        // video
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-matroska' => 'mkv',
        // audio (show music tracks)
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        // documents
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    ],

    // What each field type may accept (subset of mime_extensions keys).
    'image_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    'video_mimes' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'],

    // Used when a manager leaves "max size" blank. A blank limit must never
    // mean "unlimited" on a public registration form.
    'default_max_kb' => [
        'image' => 10 * 1024,        // 10 MB
        'video' => 500 * 1024,       // 500 MB
        'file' => 25 * 1024,        // 25 MB
    ],

    'image' => [
        'max_dimension' => 2400,
        'quality' => 82,
    ],

    'video' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),

        // true  = refuse video uploads when ffmpeg/ffprobe are missing (use in production,
        //         so unchecked videos can never reach the show).
        // false = accept them unchecked and log a warning (fine for local development).
        'require_ffmpeg' => (bool) env('MEDIA_REQUIRE_FFMPEG', false),

        // Encoding used when a video field normalises uploads for the show.
        'crf' => (int) env('VIDEO_CRF', 23),
        'preset' => env('VIDEO_PRESET', 'veryfast'),
        'timeout' => (int) env('VIDEO_TIMEOUT', 3600),
    ],
];
