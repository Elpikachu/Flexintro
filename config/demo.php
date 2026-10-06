<?php

return [

    // The object storage disk. Stays `s3` on Upsun; set DEMO_DISK=local to
    // browse the same pages on a laptop without a bucket.
    'disk' => env('DEMO_DISK', 's3'),

    // Prefix under which the demo files live on that disk.
    'files_prefix' => 'demo',

    // Wall clock time each import chunk spends burning CPU before it writes
    // its rows, in milliseconds. A chunk costs roughly this plus the upsert,
    // so the import takes about
    //
    //     total_chunks x (import_cpu_ms + ~50 ms) / instances
    //
    // 400 chunks at 1600 ms lands near 11 minutes on one instance, and near
    // 4 minutes on three. Re-time it on the real project and adjust this one
    // number; nothing else needs to change.
    'import_cpu_ms' => (int) env('IMPORT_CPU_MS', 1600),

    'import' => [
        'rows' => (int) env('IMPORT_ROWS', 200000),
        'chunk' => (int) env('IMPORT_CHUNK', 500),
    ],

    // Window used for the "per minute" figures and the worker strip.
    'window_seconds' => 60,

    'environment' => [
        'name' => env('PLATFORM_ENVIRONMENT') ?: env('PLATFORM_BRANCH') ?: 'local',
    ],

    'basic_auth' => [
        'user' => env('DEMO_USER'),
        'password' => env('DEMO_PASSWORD'),
    ],

    // Shown in the "Where these files live" panel.
    'object_storage' => [
        'host' => env('OBJECT_STORAGE_HOST'),
        'port' => env('OBJECT_STORAGE_PORT'),
        'bucket' => env('AWS_BUCKET'),
    ],
];
