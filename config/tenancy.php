<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    |
    | The root domain every tenant subdomain is stripped against to resolve
    | the tenant label (e.g. "school1.app.com" -> "school1"). This is a
    | placeholder value — swap it for the real production domain before
    | go-live and keep local dev pointed at the matching "*.app.test" hosts
    | documented in TENANCY.md / design.md.
    |
    */

    'base_domain' => env('TENANCY_BASE_DOMAIN', 'app.com'),

    /*
    |--------------------------------------------------------------------------
    | Landlord Hosts
    |--------------------------------------------------------------------------
    |
    | Hosts that are classified as "landlord" (no tenant bound, global scope
    | no-ops). Env-driven, comma-separated. Defaults to a single dedicated
    | landlord subdomain ("admin.<base_domain>") — the bare root domain is
    | intentionally NOT a landlord host by default.
    |
    */

    'landlord_hosts' => array_filter(explode(',', (string) env(
        'TENANCY_LANDLORD_HOSTS',
        'admin.'.env('TENANCY_BASE_DOMAIN', 'app.com')
    ))),

];
