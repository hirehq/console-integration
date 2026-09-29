<?php

return [
    'product' => env('CONSOLE_PRODUCT'),
    'environment' => env('CONSOLE_ENVIRONMENT'),
    'connection' => env('CONSOLE_DATABASE_CONNECTION'),
    'routes_enabled' => env('CONSOLE_INTEGRATION_ROUTES_ENABLED', false),
    'route_prefix' => 'hire-hq-console',
    // Use a distinct, random secret of at least 32 bytes per product and environment.
    'signing_keys' => ['primary' => env('CONSOLE_SIGNING_SECRET')],
    'signature_tolerance_seconds' => 300,
    // Console must refresh snapshots before this lease expires. Never extend a lease locally.
    'maximum_lease_seconds' => 300,
];
