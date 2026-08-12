<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],

    // Only the dashboard(s) that legitimately need to call this API from a browser —
    // this covers the admin routes too, since '*' let any origin hit /admin/login.
    // Comma-separated for multiple environments, e.g. "https://app.example.com,https://staging.example.com".
    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:4200')))
    )),

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
