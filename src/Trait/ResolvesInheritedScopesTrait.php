<?php
declare(strict_types=1);

namespace Crustum\Tessera\Trait;

use Crustum\Tessera\Tessera;

/**
 * Resolves colon-delimited inherited OAuth scopes.
 */
trait ResolvesInheritedScopesTrait
{
    /**
     * Determine if a scope exists or is inherited in the given array.
     *
     * @param string $scope Scope to find
     * @param list<string> $haystack Available scopes
     * @return bool
     */
    protected function scopeExistsIn(string $scope, array $haystack): bool
    {
        $scopes = Tessera::$withInheritedScopes
            ? $this->resolveInheritedScopes($scope)
            : [$scope];

        foreach ($scopes as $candidate) {
            if (in_array($candidate, $haystack, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve all possible inherited scopes for a scope string.
     *
     * @param string $scope Scope name (e.g. `user:read:email`)
     * @return list<string>
     */
    protected function resolveInheritedScopes(string $scope): array
    {
        $parts = explode(':', $scope);
        $partsCount = count($parts);
        $scopes = [];

        for ($i = 1; $i <= $partsCount; $i++) {
            $scopes[] = implode(':', array_slice($parts, 0, $i));
        }

        return $scopes;
    }
}
