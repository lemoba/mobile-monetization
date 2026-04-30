<?php

return [
    'cache' => [
        'store' => env('MOBILE_MONETIZATION_CACHE_STORE', 'redis'),
        'key_prefix' => env('MOBILE_MONETIZATION_CACHE_PREFIX', 'mobile_monetization'),
        'jwks_ttl' => (int) env('MOBILE_MONETIZATION_JWKS_TTL', 3600),
        'oauth_token_ttl' => (int) env('MOBILE_MONETIZATION_OAUTH_TOKEN_TTL', 3300),
    ],
];
