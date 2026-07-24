<?php
declare(strict_types=1);

/**
 * CakeDC Auth public-route fragment for Tessera OAuth endpoints.
 *
 * `bypassAuth` here means: skip CakeDC "logged-in user required" for the HTTP
 * route so anonymous clients can hit `/oauth/token` and guests can start authorize.
 * It does NOT mean endpoints are unauthenticated OAuth:
 * - AccessToken still validates client credentials / grant body
 * - Authorization / Approve / Deny still require session user + `auth_token`
 * - TransientToken::refresh still requires an authenticated identity
 * - Device flows still bind device codes / user codes
 *
 * Merge into host `config/permissions.php`:
 *
 * ```php
 * use Cake\Core\Plugin;
 *
 * $permissions = array_merge(
 *     $permissions,
 *     require Plugin::path('Crustum/Tessera') . 'config' . DS . 'permissions.php',
 * );
 * ```
 *
 * For `CakeDC/Auth.preloadPermissions.public`, strip `bypassAuth` or map rules
 * without that key — the host preload loop adds it.
 *
 * @return list<array<string, mixed>>
 */
return [
    [
        'prefix' => false,
        'plugin' => 'Crustum/Tessera',
        'controller' => 'AccessToken',
        'action' => ['issueToken'],
        'bypassAuth' => true,
    ],
    [
        'prefix' => false,
        'plugin' => 'Crustum/Tessera',
        'controller' => 'TransientToken',
        'action' => ['refresh'],
        'bypassAuth' => true,
    ],
    [
        'prefix' => false,
        'plugin' => 'Crustum/Tessera',
        'controller' => 'Authorization',
        'action' => ['authorize'],
        'bypassAuth' => true,
    ],
    [
        'prefix' => false,
        'plugin' => 'Crustum/Tessera',
        'controller' => ['ApproveAuthorization', 'DenyAuthorization'],
        'action' => '*',
        'bypassAuth' => true,
    ],
    [
        'prefix' => false,
        'plugin' => 'Crustum/Tessera',
        'controller' => [
            'DeviceUserCode',
            'DeviceCode',
            'DeviceAuthorization',
            'ApproveDeviceAuthorization',
            'DenyDeviceAuthorization',
        ],
        'action' => '*',
        'bypassAuth' => true,
    ],
];
