<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Controls which third-party origins may call the public API from the
    | browser. Only the paths listed here send CORS headers; everything else
    | (the web app, admin, auth) is unaffected. Add trusted partner sites to
    | the `CORS_ALLOWED_ORIGINS` env var (comma-separated) to grant access.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'https://rastriyaaawaj.com,https://www.rastriyaaawaj.com')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
