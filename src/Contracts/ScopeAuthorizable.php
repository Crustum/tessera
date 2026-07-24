<?php
declare(strict_types=1);

namespace Crustum\Tessera\Contracts;

/**
 * Token that can be checked for OAuth scopes.
 */
interface ScopeAuthorizable
{
    /**
     * Determine if the token has a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function can(string $scope): bool;

    /**
     * Determine if the token is missing a given scope.
     *
     * @param string $scope Scope name
     * @return bool
     */
    public function cant(string $scope): bool;
}
