<?php
declare(strict_types=1);

namespace Crustum\Tessera\Middleware;

use Crustum\Tessera\AccessToken;
use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Exception\AuthenticationException;
use Crustum\Tessera\Exception\MissingScopeException;

/**
 * Requires the access token's client to also be the resource owner, and carry every configured scope.
 */
class EnsureClientIsResourceOwnerMiddleware extends ValidateTokenMiddleware
{
    /**
     * Ensure the client is the resource owner and has every required scope.
     *
     * @param \Crustum\Tessera\Contracts\ScopeAuthorizable $token Access token
     * @param list<string> $scopes Required scopes
     * @return void
     * @throws \Crustum\Tessera\Exception\AuthenticationException
     * @throws \Crustum\Tessera\Exception\MissingScopeException
     */
    protected function validate(ScopeAuthorizable $token, array $scopes): void
    {
        if (
            $token instanceof AccessToken
            && $token->oauth_user_id !== null
            && $token->oauth_user_id !== $token->oauth_client_id
        ) {
            throw new AuthenticationException();
        }

        foreach ($scopes as $scope) {
            if ($token->cant($scope)) {
                throw new MissingScopeException($scope);
            }
        }
    }
}
