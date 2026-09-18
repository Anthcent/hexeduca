<?php

namespace App\Support;

final class SecurityPolicy
{
    public const API_REQUESTS_PER_MINUTE = 60;

    public const HSTS_MAX_AGE_SECONDS = 31536000;

    public const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    /** @return list<string> */
    public static function trustedProxies(): array
    {
        return array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', ''))
        )));
    }
}
