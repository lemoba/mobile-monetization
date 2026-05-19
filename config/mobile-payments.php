<?php

return [
    'apple' => [
        'bundle_id' => env('APPLE_BUNDLE_ID'),
        'issuer_id' => env('APPLE_ISSUER_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
        'promotional_offer_key_id' => env('APPLE_PROMOTIONAL_OFFER_KEY_ID', env('APPLE_KEY_ID')),
        'promotional_offer_private_key' => env('APPLE_PROMOTIONAL_OFFER_PRIVATE_KEY'),
        'promotional_offer_private_key_path' => env('APPLE_PROMOTIONAL_OFFER_PRIVATE_KEY_PATH'),
        'environment' => env('APPLE_IAP_ENVIRONMENT', 'production'),
    ],

    'google' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME'),
        'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON'),
        'service_account_json_path' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON_PATH'),
    ],

    'products' => [
        'coins' => array_filter(array_map('trim', explode(',', env('MOBILE_COIN_PRODUCT_IDS', '')))),
        'subscriptions' => [
            'week' => env('MOBILE_VIP_WEEK_PRODUCT_ID'),
            'month' => env('MOBILE_VIP_MONTH_PRODUCT_ID'),
            'year' => env('MOBILE_VIP_YEAR_PRODUCT_ID'),
        ],
    ],
];
