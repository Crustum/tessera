<?php
declare(strict_types=1);

namespace Crustum\Tessera\Middleware;

use Authentication\IdentityInterface;
use Crustum\Tessera\AccessToken;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Contracts\ScopeAuthorizable;
use Crustum\Tessera\Exception\AuthenticationException;
use Crustum\Tessera\Tessera;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Resolves and validates the OAuth access token for the current request.
 */
abstract class ValidateTokenMiddleware implements MiddlewareInterface
{
    /**
     * @param \League\OAuth2\Server\ResourceServer $server Resource server used to validate bearer tokens
     * @param list<string> $scopes Scopes required by the concrete middleware
     */
    public function __construct(
        protected ResourceServer $server,
        protected array $scopes = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->validateToken($request);

        $this->validate($token, $this->scopes);

        return $handler->handle($request);
    }

    /**
     * Resolve the request's access token from the identity or the bearer token.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @return \Crustum\Tessera\Contracts\ScopeAuthorizable
     * @throws \Crustum\Tessera\Exception\AuthenticationException
     */
    protected function validateToken(ServerRequestInterface $request): ScopeAuthorizable
    {
        $user = $this->oauthUserFromIdentity($request->getAttribute('identity'));
        if ($user instanceof OAuthenticatable && $user->currentAccessToken() instanceof ScopeAuthorizable) {
            return $user->currentAccessToken();
        }

        if (Tessera::$actingAsAllowed && Tessera::$actingAsClientToken instanceof ScopeAuthorizable) {
            return Tessera::$actingAsClientToken;
        }

        try {
            $validatedRequest = $this->server->validateAuthenticatedRequest($request);
        } catch (OAuthServerException) {
            throw new AuthenticationException();
        }

        return AccessToken::fromPsrRequest($validatedRequest);
    }

    /**
     * Unwrap Cake Authentication identity to an OAuthenticatable user when present.
     *
     * @param mixed $identity Request identity attribute
     * @return \Crustum\Tessera\Contracts\OAuthenticatable|null
     */
    protected function oauthUserFromIdentity(mixed $identity): ?OAuthenticatable
    {
        if ($identity instanceof OAuthenticatable) {
            return $identity;
        }

        if ($identity instanceof IdentityInterface) {
            $data = $identity->getOriginalData();
            if ($data instanceof OAuthenticatable) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Validate the resolved access token against the middleware's requirements.
     *
     * @param \Crustum\Tessera\Contracts\ScopeAuthorizable $token Resolved access token
     * @param list<string> $scopes Scopes configured for the middleware
     * @return void
     */
    abstract protected function validate(ScopeAuthorizable $token, array $scopes): void;
}
