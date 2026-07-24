<?php
declare(strict_types=1);

namespace Crustum\Tessera;

use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Trait\ResolvesInheritedScopesTrait;

/**
 * Transient first-party JWT-style token that authorizes all scopes.
 */
class TransientToken implements ScopeAuthorizable
{
    use ResolvesInheritedScopesTrait;

    /**
     * Determine if the token has a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function can(string $scope): bool
    {
        return true;
    }

    /**
     * Determine if the token is missing a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function cant(string $scope): bool
    {
        return false;
    }

    /**
     * Determine if the token is a transient JWT token.
     *
     * @return bool
     */
    public function transient(): bool
    {
        return true;
    }
}
