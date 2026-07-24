<?php
declare(strict_types=1);

namespace Crustum\Tessera\Controller;

use Cake\Controller\Controller;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Crustum\Tessera\ApiTokenCookieFactory;
use Crustum\Tessera\Contracts\OAuthenticatable;
use Crustum\Tessera\Controller\Trait\ResolvesOAuthenticatableTrait;
use Crustum\Tessera\Exception\AuthenticationException;

/**
 * Refreshes the transient API token cookie used by first-party SPAs.
 */
class TransientTokenController extends Controller
{
    use ResolvesOAuthenticatableTrait;

    /**
     * Issue a fresh API token cookie for the current identity.
     *
     * @param \Crustum\Tessera\ApiTokenCookieFactory $cookieFactory Cookie factory
     * @return \Cake\Http\Response
     * @throws \Crustum\Tessera\Exception\AuthenticationException
     */
    public function refresh(ApiTokenCookieFactory $cookieFactory): Response
    {
        $identity = $this->currentIdentity();
        if (!$identity instanceof OAuthenticatable) {
            throw new AuthenticationException();
        }

        $userId = $identity->getIdentifier();
        if (!is_string($userId) && !is_int($userId)) {
            throw new AuthenticationException();
        }

        $csrfToken = ApiTokenCookieFactory::csrfClaimFromRequest($this->getRequest());
        if ($csrfToken === null) {
            throw new AuthenticationException();
        }

        $symfonyCookie = $cookieFactory->make($userId, $csrfToken);
        $cookie = Cookie::create(
            $symfonyCookie->getName(),
            (string)$symfonyCookie->getValue(),
            [
                'expires' => $symfonyCookie->getExpiresTime(),
                'path' => $symfonyCookie->getPath(),
                'domain' => $symfonyCookie->getDomain() ?? '',
                'secure' => $symfonyCookie->isSecure(),
                'httponly' => $symfonyCookie->isHttpOnly(),
                'samesite' => $symfonyCookie->getSameSite(),
            ],
        );

        return $this->getResponse()
            ->withStringBody('Refreshed.')
            ->withCookie($cookie);
    }
}
