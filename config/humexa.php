<?php

return [
    // Leave: Carbon day numbers (0 = Sunday) that do not count as working days. Default Friday and Saturday.
    'leave' => [
        'weekend_days' => [5, 6],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Base Domain
    |--------------------------------------------------------------------------
    */

    'tenant_domain' => env('HUMEXA_TENANT_DOMAIN', 'humexa.test'),

    /*
    |--------------------------------------------------------------------------
    | Reserved Subdomains
    |--------------------------------------------------------------------------
    */

    'reserved_subdomains' => [
        'www',
        'admin',
        'platform',
        'api',
        'app',
        'mail',
        'support',
        'billing',
        'login',
    ],
];
