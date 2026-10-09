<?php
declare(strict_types=1);

namespace Crustum\Tessera\Middleware;

use Authentication\IdentityInterface;
use Cake\Http\Response as CakeResponse;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Tessera;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Attaches a fresh transient API token cookie to GET responses for authenticated identities.
 */
class CreateFreshApiTokenMiddleware implements MiddlewareInterface
{
    /**
     * @param \Crustum\Tessera\ApiTokenCookieFactory $cookieFactory Factory used to build the cookie
     */
    public function __construct(
        protected ApiTokenCookieFactory $cookieFactory,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->shouldReceiveFreshToken($request, $response)) {
            return $response;
        }

        $identity = $this->oauthUserFromIdentity($request->getAttribute('identity'));
        if (!$identity instanceof OAuthenticatable) {
            return $response;
        }

        $userId = $identity->getIdentifier();
        if (!is_string($userId) && !is_int($userId)) {
            return $response;
        }

        $csrfToken = ApiTokenCookieFactory::csrfClaimFromRequest($request);
        if ($csrfToken === null) {
            return $response;
        }

        $cookie = $this->cookieFactory->make($userId, $csrfToken);

        return $response->withAddedHeader('Set-Cookie', (string)$cookie);
    }

    /**
     * Whether the response should carry a freshly issued API token cookie.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @param \Psr\Http\Message\ResponseInterface $response Response produced by the rest of the queue
     * @return bool
     */
    protected function shouldReceiveFreshToken(ServerRequestInterface $request, ResponseInterface $response): bool
    {
        return strtoupper($request->getMethod()) === 'GET'
            && $this->oauthUserFromIdentity($request->getAttribute('identity')) instanceof OAuthenticatable
            && !$this->isRedirect($response)
            && !$this->alreadyContainsToken($response);
    }

    /**
     * Whether the response is a redirect that must not carry a fresh token.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response produced by the rest of the queue
     * @return bool
     */
    protected function isRedirect(ResponseInterface $response): bool
    {
        $status = $response->getStatusCode();

        return $status >= 300 && $status < 400;
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
     * Whether the response already carries a Tessera API token cookie.
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response to inspect
     * @return bool
     */
    protected function alreadyContainsToken(ResponseInterface $response): bool
    {
        if ($response instanceof CakeResponse && $response->getCookie(Tessera::cookie()) !== null) {
            return true;
        }

        $prefix = Tessera::cookie() . '=';
        foreach ($response->getHeader('Set-Cookie') as $cookieHeader) {
            if (str_starts_with($cookieHeader, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
