<?php

return [
    'fcm' => [
        'android' => [
            'project_id' => env('FCM_ANDROID_PROJECT_ID'),
            'service_account_json' => env('FCM_ANDROID_SERVICE_ACCOUNT_JSON'),
            'service_account_json_path' => env('FCM_ANDROID_SERVICE_ACCOUNT_JSON_PATH'),
        ],

        'ios' => [
            'project_id' => env('FCM_IOS_PROJECT_ID'),
            'service_account_json' => env('FCM_IOS_SERVICE_ACCOUNT_JSON'),
            'service_account_json_path' => env('FCM_IOS_SERVICE_ACCOUNT_JSON_PATH'),
        ],

        'default_platform' => env('FCM_DEFAULT_PLATFORM', 'android'),
        'timeout' => (int) env('FCM_TIMEOUT', 15),
    ],
];
