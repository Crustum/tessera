<?php
declare(strict_types=1);

/**
 * Crustum Tessera configuration.
 *
 * Host app should load this via Configure::load('tessera', 'default')
 * or copy to config/tessera.php and override.
 *
 * Align Tessera.usersTable with the host users table alias when present
 * (e.g. CakeDC/Users `Users.table`). CakeDC/Users is not a Tessera dependency.
 */
return [
    'Tessera' => [
        'path' => 'oauth',
        'guard' => 'users',
        'middleware' => [],
        'usersTable' => 'Users',
        'userIdType' => 'integer',
        'provider' => null,
        'loginUrl' => null,
        'loginRedirectQueryParam' => 'redirect',
        'debugOAuth' => filter_var(env('TESSERA_DEBUG_OAUTH', false), FILTER_VALIDATE_BOOLEAN),
        'private_key' => env('TESSERA_PRIVATE_KEY'),
        'public_key' => env('TESSERA_PUBLIC_KEY'),
        'connection' => env('TESSERA_CONNECTION'),
        'TokenAuthenticator' => [
            'className' => \Crustum\Tessera\Authenticator\TokenAuthenticator::class,
            'providerName' => 'users',
        ],
    ],
];
