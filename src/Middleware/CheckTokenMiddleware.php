<?php
declare(strict_types=1);

namespace Crustum\Tessera\Middleware;

use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Exception\MissingScopeException;

/**
 * Requires the access token to carry every configured scope.
 */
class CheckTokenMiddleware extends ValidateTokenMiddleware
{
    /**
     * Ensure the token has every required scope.
     *
     * @param \Crustum\Tessera\Contracts\ScopeAuthorizable $token Access token
     * @param list<string> $scopes Required scopes
     * @return void
     * @throws \Crustum\Tessera\Exception\MissingScopeException
     */
    protected function validate(ScopeAuthorizable $token, array $scopes): void
    {
        foreach ($scopes as $scope) {
            if ($token->cant($scope)) {
                throw new MissingScopeException($scope);
            }
        }
    }
}
