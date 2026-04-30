<?php

return [
    'apple' => [
        'bundle_id' => env('APPLE_BUNDLE_ID'),
        'team_id' => env('APPLE_TEAM_ID'),
        'client_id' => env('APPLE_CLIENT_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
    ],

    'google' => [
        'android_client_ids' => array_filter(array_map('trim', explode(',', env('GOOGLE_ANDROID_CLIENT_IDS', '')))),
    ],
];
