<?php
declare(strict_types=1);

namespace Crustum\Tessera\Middleware;

use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Exception\MissingScopeException;

/**
 * Requires the access token to carry at least one of the configured scopes.
 */
class CheckTokenForAnyScopeMiddleware extends ValidateTokenMiddleware
{
    /**
     * Ensure the token has at least one of the required scopes.
     *
     * @param \Crustum\Tessera\Contracts\ScopeAuthorizable $token Access token
     * @param list<string> $scopes Acceptable scopes
     * @return void
     * @throws \Crustum\Tessera\Exception\MissingScopeException
     */
    protected function validate(ScopeAuthorizable $token, array $scopes): void
    {
        foreach ($scopes as $scope) {
            if ($token->can($scope)) {
                return;
            }
        }

        throw new MissingScopeException($scopes);
    }
}
