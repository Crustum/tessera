<?php
declare(strict_types=1);

namespace Crustum\Tessera\Support;

use Authentication\Identifier\IdentifierFactory;

/**
 * Compatibility shim for cakephp/authentication ^3.0 | ^4.0.
 *
 * v3 expects a collection-style identifier config:
 * `['Authentication.Password' => [...options]]`
 * backed by `IdentifierCollection`.
 *
 * v4 removed `IdentifierCollection` and resolves a single identifier via
 * `IdentifierFactory`:
 * `['className' => 'Authentication.Password', ...options]`
 * or a plain `'Authentication.Password'` string.
 *
 * Branching on the array shape at runtime keeps host `Application code
 * working on both majors without version checks scattered everywhere.
 */
class AuthenticationCompat
{
    /**
     * Whether the installed authentication plugin uses the v4 API.
     *
     * @return bool
     */
    public static function isV4(): bool
    {
        return class_exists(IdentifierFactory::class);
    }

    /**
     * Build an `identifier` config for `AuthenticationService::loadAuthenticator()`.
     *
     * @param string $name Identifier name, e.g. `Authentication.Password`
     * @param array<string, mixed> $options Identifier options (`fields`, `resolver`, ...)
     * @return array<string, mixed>|string v4 single-identifier config, v3 collection config
     */
    public static function identifier(string $name = 'Authentication.Password', array $options = []): array|string
    {
        if (static::isV4()) {
            if ($options === []) {
                return $name;
            }

            return ['className' => $name] + $options;
        }

        return [$name => $options];
    }

    /**
     * Build the default Tessera password identifier config.
     *
     * @param array<string, mixed>|null $options Identifier options override
     * @return array<string, mixed>|string
     */
    public static function passwordIdentifier(?array $options = null): array|string
    {
        $options ??= [
            'fields' => [
                'username' => 'email',
                'password' => 'password',
            ],
            'resolver' => [
                'className' => 'Authentication.Orm',
                'userModel' => 'Users',
            ],
        ];

        return static::identifier('Authentication.Password', $options);
    }
}
