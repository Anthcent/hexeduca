<?php

use App\Support\SecurityPolicy;

return [
    'trusted_proxies' => SecurityPolicy::trustedProxies(),
    'rate_limits' => [
        'login_per_minute' => SecurityPolicy::LOGIN_ATTEMPTS_PER_MINUTE,
        'registration_per_minute' => SecurityPolicy::REGISTRATION_ATTEMPTS_PER_MINUTE,
        'api_per_minute' => SecurityPolicy::API_REQUESTS_PER_MINUTE,
    ],

    'hsts' => [
        'max_age' => SecurityPolicy::HSTS_MAX_AGE_SECONDS,
        'include_subdomains' => true,
        'preload' => true,
    ],
];
